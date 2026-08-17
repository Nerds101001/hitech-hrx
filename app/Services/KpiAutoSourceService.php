<?php

namespace App\Services;

use App\Models\KpiAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Computes the "achieved" figure for KPIs whose data already lives in HRX, so
 * nobody types in a number the system can work out itself.
 *
 * A KPI opts in through kpi_assignments.data_source = 'auto:<key>'. Anything
 * else (manual / approval) is ignored here and stays a human entry.
 *
 * Every source returns a figure on the SAME scale as the KPI's target_value,
 * because KpiPeriodService scores achieved ÷ target. Percentage-style KPIs are
 * therefore expected to carry target_value = 100.
 */
class KpiAutoSourceService
{
    public const SOURCES = [
        'auto:revenue'             => 'Revenue booked in the period (₹ lakh)',
        'auto:target_achievement'  => 'Revenue as a % of the salesperson target',
        'auto:customer_health'     => '% of the book that ordered at least once',
        'auto:total_pipeline'      => 'Revenue as a % of pipeline potential',
        'auto:customers_added'     => 'Customers whose first ever order falls in the period',
        'auto:customers_retained'  => '% of customers first seen 6+ months ago still ordering',
    ];

    /** Null = this KPI is not auto-sourced, or the figure cannot be derived. */
    public static function compute(KpiAssignment $a, Carbon $start, Carbon $end): ?float
    {
        $source = (string) $a->data_source;
        if (!str_starts_with($source, 'auto:')) {
            return null;
        }

        return match ($source) {
            'auto:revenue'            => self::revenue($a->user_id, $start, $end),
            'auto:target_achievement' => self::targetAchievement($a->user_id, $start, $end),
            'auto:customer_health'    => self::customerHealth($a->user_id, $start, $end),
            'auto:total_pipeline'     => self::totalPipeline($a->user_id, $start, $end),
            'auto:customers_added'    => self::customersAdded($a->user_id, $start, $end),
            'auto:customers_retained' => self::customersRetained($a->user_id, $start, $end),
            default                   => null,
        };
    }

    /**
     * The customers a person is measured on: theirs as salesperson, as New Biz
     * rep, or as CCare rep. A New Biz executive owns rows through new_biz_id
     * rather than salesperson_id, so all three have to count.
     */
    private static function book(int $userId)
    {
        return DB::table('sales_pipelines')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($userId) {
                $q->where('salesperson_id', $userId)
                  ->orWhere('new_biz_id', $userId)
                  ->orWhere('ccare_id', $userId);
            });
    }

    /** Month keys (YYYY-MM) the period covers. */
    private static function monthKeys(Carbon $start, Carbon $end): array
    {
        $keys = [];
        $c = $start->copy()->startOfMonth();
        while ($c->lte($end)) {
            $keys[] = $c->format('Y-m');
            $c->addMonth();
        }
        return $keys;
    }

    /** Revenue in ₹ lakh — sale_amount is stored in lakh. */
    public static function revenue(int $userId, Carbon $start, Carbon $end): float
    {
        return (float) DB::table('sales_pipeline_months as m')
            ->whereIn('m.sales_pipeline_id', self::book($userId)->select('id'))
            ->whereIn('m.month_year', self::monthKeys($start, $end))
            ->sum('m.sale_amount');
    }

    /** Revenue ÷ target, as a percentage. Pair with target_value = 100. */
    public static function targetAchievement(int $userId, Carbon $start, Carbon $end): ?float
    {
        $target = (float) DB::table('salesperson_targets')
            ->where('salesperson_id', $userId)
            ->whereIn('month_year', self::monthKeys($start, $end))
            ->sum(DB::raw('COALESCE(kingo_target, 0)'));

        if ($target <= 0) {
            return null;   // no target set: scoring them against zero would be meaningless
        }

        return round(self::revenue($userId, $start, $end) / $target * 100, 2);
    }

    /**
     * Share of the book that actually ordered, as a percentage — the user's
     * "total number of orders / total number of customers, existing only".
     */
    public static function customerHealth(int $userId, Carbon $start, Carbon $end): ?float
    {
        $ids = self::book($userId)->pluck('id');
        if ($ids->isEmpty()) {
            return null;
        }

        $ordered = DB::table('sales_pipeline_months')
            ->whereIn('sales_pipeline_id', $ids)
            ->whereIn('month_year', self::monthKeys($start, $end))
            ->where('sale_amount', '>', 0)
            ->distinct()->count('sales_pipeline_id');

        return round($ordered / $ids->count() * 100, 2);
    }

    /** Revenue against the book's total potential, as a percentage. */
    public static function totalPipeline(int $userId, Carbon $start, Carbon $end): ?float
    {
        $potential = (float) self::book($userId)->sum('total_business_potential');
        if ($potential <= 0) {
            return null;
        }

        return round(self::revenue($userId, $start, $end) / $potential * 100, 2);
    }

    /** Customers whose FIRST ever recorded sale falls inside the period. */
    public static function customersAdded(int $userId, Carbon $start, Carbon $end): float
    {
        $keys = self::monthKeys($start, $end);

        $firstSale = DB::table('sales_pipeline_months')
            ->whereIn('sales_pipeline_id', self::book($userId)->select('id'))
            ->where('sale_amount', '>', 0)
            ->selectRaw('sales_pipeline_id, MIN(month_year) first_month')
            ->groupBy('sales_pipeline_id');

        return (float) DB::query()->fromSub($firstSale, 'f')
            ->whereIn('f.first_month', $keys)
            ->count();
    }

    /**
     * Of the customers first seen 6+ months before the period, the share still
     * ordering in it — retention, as a percentage.
     */
    public static function customersRetained(int $userId, Carbon $start, Carbon $end): ?float
    {
        $cutoff = $start->copy()->subMonths(6)->format('Y-m');

        $firstSale = DB::table('sales_pipeline_months')
            ->whereIn('sales_pipeline_id', self::book($userId)->select('id'))
            ->where('sale_amount', '>', 0)
            ->selectRaw('sales_pipeline_id, MIN(month_year) first_month')
            ->groupBy('sales_pipeline_id');

        $eligible = DB::query()->fromSub($firstSale, 'f')
            ->where('f.first_month', '<=', $cutoff)
            ->pluck('f.sales_pipeline_id');

        if ($eligible->isEmpty()) {
            return null;
        }

        $still = DB::table('sales_pipeline_months')
            ->whereIn('sales_pipeline_id', $eligible)
            ->whereIn('month_year', self::monthKeys($start, $end))
            ->where('sale_amount', '>', 0)
            ->distinct()->count('sales_pipeline_id');

        return round($still / $eligible->count() * 100, 2);
    }
}
