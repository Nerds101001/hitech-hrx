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

        // Kingo/Bingo — salesperson_id ONLY (not the broader "book" the sources
        // above use), matching the real Kingo/Bingo scorecard exactly
        // (SalesPipelineController@index's $targetAchieversByMonth) so a KPI
        // score never disagrees with what the scorecard itself shows.
        'auto:bingo_achievement'       => 'Sale ÷ Bingo target, as a % (matches the Kingo/Bingo scorecard)',
        'auto:kingo_achievement'       => 'Sale ÷ Kingo target, as a %',
        'auto:super_kingo_achievement' => 'Sale ÷ Super Kingo target, as a %',

        // sales_visits boolean flags — a straight count of completed visits
        // flagged for that activity in the period.
        'auto:rate_increase_visits'      => 'Completed visits flagged Rate Increase',
        'auto:upsell_visits'             => 'Completed visits flagged Upsell',
        'auto:nif_recovery_visits'       => 'Completed visits flagged NIF Recovery',
        'auto:competitor_insight_visits' => 'Completed visits flagged Competitor Insight',
        'auto:product_idea_visits'       => 'Completed visits flagged Product Idea',
        'auto:new_customer_visits'       => 'Completed visits flagged New Customer',
        'auto:trial_demo_visits'         => 'Completed visits of type Product Trial',

        // Mind Speak — only APPROVED submissions count, so an unreviewed or
        // rejected entry can't inflate a score before a reviewer has seen it.
        'auto:sales_pitches'      => 'Approved Sales Pitch submissions (Mind Speak)',
        'auto:seminars_conducted' => 'Approved Seminar submissions (Mind Speak)',

        // Digital Library — videos presented under the "Learn @ Hi Tech"
        // shelf, credited to whoever is tagged as presenter_id.
        'auto:learn_hitech_sessions' => 'Learn @ Hi Tech videos presented (Digital Library)',
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

            'auto:bingo_achievement'       => self::scorecardAchievement($a->user_id, $start, $end, 'bingo_target'),
            'auto:kingo_achievement'       => self::scorecardAchievement($a->user_id, $start, $end, 'kingo_target'),
            'auto:super_kingo_achievement' => self::scorecardAchievement($a->user_id, $start, $end, 'super_kingo_target'),

            'auto:rate_increase_visits'      => self::visitFlagCount($a->user_id, $start, $end, 'rate_increase'),
            'auto:upsell_visits'             => self::visitFlagCount($a->user_id, $start, $end, 'is_upsell'),
            'auto:nif_recovery_visits'       => self::visitFlagCount($a->user_id, $start, $end, 'is_nif'),
            'auto:competitor_insight_visits' => self::visitFlagCount($a->user_id, $start, $end, 'competitor_insight'),
            'auto:product_idea_visits'       => self::visitFlagCount($a->user_id, $start, $end, 'product_idea'),
            'auto:new_customer_visits'       => self::visitFlagCount($a->user_id, $start, $end, 'is_new_customer'),
            'auto:trial_demo_visits'         => self::visitTypeCount($a->user_id, $start, $end, 'Product Trial'),

            'auto:sales_pitches'      => self::mindSpeakCount($a->user_id, $start, $end, 'Sales Pitch'),
            'auto:seminars_conducted' => self::mindSpeakCount($a->user_id, $start, $end, 'Seminar'),

            'auto:learn_hitech_sessions' => self::learnHitechCount($a->user_id, $start, $end),

            default => null,
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

    /**
     * Sale ÷ target for one Kingo/Bingo target column, as a %.
     *
     * Actual sales now use self::book()'s full reach (salesperson_id OR
     * new_biz_id OR ccare_id), not salesperson_id alone. That used to be
     * deliberate, to match SalesPipelineController@index's
     * $targetAchieversByMonth (the real Kingo/Bingo scorecard popup)
     * exactly — but that popup's own achievement query is ITSELF
     * salesperson_id-only, and New Biz's whole "Kingo Achievement" KPI
     * (their single largest, 30 of ~120 marks) depends on this method:
     * salesperson_targets carries a real monthly target for New Biz staff
     * too, but their sales are recorded under new_biz_id, not
     * salesperson_id, so the narrower join scored every one of them a
     * permanent, unconditional 0% regardless of real performance (confirmed
     * live 2026-08-25 — 183 real deals, 12 months of real targets, 0.0
     * every time). Broadening this doesn't create a case where the KPI and
     * the popup disagree on two different non-zero numbers for the same
     * person: the popup doesn't measure CCare/New Biz achievement at all
     * today, so this only moves them from "wrong zero" to "real number".
     *
     * Null when no target is set for the period — scoring against a zero
     * target would read as "0% achieved" when really nothing was asked of
     * them.
     */
    public static function scorecardAchievement(int $userId, Carbon $start, Carbon $end, string $targetColumn): ?float
    {
        $target = (float) DB::table('salesperson_targets')
            ->where('salesperson_id', $userId)
            ->whereIn('month_year', self::monthKeys($start, $end))
            ->sum($targetColumn);

        if ($target <= 0) {
            return null;
        }

        $actual = (float) DB::table('sales_pipeline_months as m')
            ->join('sales_pipelines as sp', 'sp.id', '=', 'm.sales_pipeline_id')
            ->where(function ($q) use ($userId) {
                $q->where('sp.salesperson_id', $userId)
                  ->orWhere('sp.new_biz_id', $userId)
                  ->orWhere('sp.ccare_id', $userId);
            })
            ->where('sp.is_locked', true)
            ->whereNull('sp.deleted_at')
            ->whereIn('m.month_year', self::monthKeys($start, $end))
            ->sum('m.sale_amount');

        return round($actual / $target * 100, 2);
    }

    /** How many of this user's COMPLETED visits in the period have $flagColumn set. */
    public static function visitFlagCount(int $userId, Carbon $start, Carbon $end, string $flagColumn): float
    {
        return (float) DB::table('sales_visits')
            ->where('salesperson_id', $userId)
            ->where('status', 'completed')
            ->where($flagColumn, true)
            ->whereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()])
            ->count();
    }

    /** How many of this user's COMPLETED visits in the period are of $visitType. */
    public static function visitTypeCount(int $userId, Carbon $start, Carbon $end, string $visitType): float
    {
        return (float) DB::table('sales_visits')
            ->where('salesperson_id', $userId)
            ->where('status', 'completed')
            ->where('visit_type', $visitType)
            ->whereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()])
            ->count();
    }

    /**
     * Approved Mind Speak submissions of $category in the period. Only
     * 'approved' counts — pending or rejected entries can't inflate a score
     * before a reviewer has actually signed off on them.
     */
    public static function mindSpeakCount(int $userId, Carbon $start, Carbon $end, string $category): float
    {
        return (float) DB::table('mind_speaks')
            ->where('user_id', $userId)
            ->where('category', $category)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
            ->count();
    }

    /**
     * Digital Library videos under the "Learn @ Hi Tech" shelf, credited to
     * whoever presented them (presenter_id) — see
     * MigrateLearnToVideoCommand::LEARN_CATEGORY for the exact sub_category
     * string this must match.
     */
    public static function learnHitechCount(int $userId, Carbon $start, Carbon $end): float
    {
        return (float) DB::table('library_files')
            ->where('presenter_id', $userId)
            ->where('sub_category', 'Learn @ Hi Tech')
            ->whereBetween('session_date', [$start->toDateString(), $end->toDateString()])
            ->count();
    }
}
