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

        // Kingo/Bingo — the same five attribution routes as book(), matching
        // the real Kingo/Bingo scorecard exactly
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

        // Sales Assessment rule book. Each returns the raw figure the rule book's tiers are
        // written against (a % or a count), to be scored with bands.
        'auto:pipeline_vs_target'         => 'Pipeline potential as a % of the Bingo target',
        'auto:yoy_growth'                 => 'Revenue growth % against the same period a year earlier',
        'auto:new_customer_revenue_pct'   => '% of revenue from customers whose first ever order falls in the period',
        'auto:customer_attrition'         => '% of last year\'s ordering customers with no order yet this year',
        'auto:cost_of_selling'            => 'Travel + expense cost as a % of revenue',
        'auto:mindspeak_insights'         => 'Competitor Insights submissions accepted in Mind Speak',
        'auto:mindspeak_ideas'            => 'New Product Ideas accepted in Mind Speak',
        'auto:ninety_day_goals'           => '% of 90-Day Goals on track or in regular order',

        // Attendance, read through AttendanceDayStatus so a KPI score can never
        // disagree with what the attendance registry itself shows for the day.
        'auto:attendance_percent'         => 'Days attended as a % of the working days in the period',
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
            'auto:trial_demo_visits'         => self::visitTypeCount($a->user_id, $start, $end, 'product_trial'),

            'auto:sales_pitches'      => self::mindSpeakCount($a->user_id, $start, $end, 'Sales Pitch'),
            'auto:seminars_conducted' => self::mindSpeakCount($a->user_id, $start, $end, 'Seminar'),

            'auto:learn_hitech_sessions' => self::learnHitechCount($a->user_id, $start, $end),

            'auto:pipeline_vs_target'       => self::pipelineVsTarget($a->user_id),
            'auto:yoy_growth'               => self::yoyGrowth($a->user_id, $start, $end),
            'auto:new_customer_revenue_pct' => self::newCustomerRevenuePct($a->user_id, $start, $end),
            'auto:customer_attrition'       => self::customerAttrition($a->user_id, $start, $end),
            'auto:cost_of_selling'          => self::costOfSelling($a->user_id, $start, $end),
            'auto:mindspeak_insights'       => self::mindSpeakAccepted($a->user_id, $start, $end, 'Competitor Insights'),
            'auto:mindspeak_ideas'          => self::mindSpeakAccepted($a->user_id, $start, $end, 'Idea'),
            'auto:ninety_day_goals'         => self::ninetyDayGoals($a->user_id, $start, $end),

            'auto:attendance_percent'       => self::attendancePercent($a->user_id, $start, $end),

            default => null,
        };
    }

    /**
     * The customers a person is measured on.
     *
     * There are FIVE ways somebody is attached to a customer, and this used to
     * check only three -- salesperson_id, new_biz_id, ccare_id -- silently
     * dropping new_biz_2_id and every row tagged through
     * sales_pipeline_extra_reps. The Sales Report and the Kingo/Bingo
     * scorecard both count all five, so a KPI built on the short list reported
     * less revenue than the screens the same person is looking at.
     *
     * Measured on September 2026: ten of fifty KPI holders were short, by
     * ₹24.78 lakh in total. DIPALI SHARMA's Kingo Achievement read 23.32%
     * against the 22.99 lakh her own Sales Report showed (35.37%), and PRAPTI
     * KAPOOR scored 2.38% on a month where she had in fact made 102% of her
     * target -- nearly all of her revenue arrived through extra-rep tagging.
     *
     * Booked business is booked business whoever tagged it, which is the rule
     * the Kingo/Bingo actuals already follow.
     */
    private static function book(int $userId)
    {
        return DB::table('sales_pipelines')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($userId) {
                $q->where('salesperson_id', $userId)
                  ->orWhere('new_biz_id', $userId)
                  ->orWhere('new_biz_2_id', $userId)
                  ->orWhere('ccare_id', $userId)
                  ->orWhereExists(function ($e) use ($userId) {
                      $e->selectRaw('1')
                        ->from('sales_pipeline_extra_reps as er')
                        ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')
                        ->where('er.user_id', $userId);
                  });
            });
    }

    /**
     * Days attended as a percentage of the working days in the period.
     *
     * Resolved one day at a time through AttendanceDayStatus rather than by
     * counting attendance rows, so this figure can never disagree with what
     * the registry shows: the same rules for miss-punches, Sunday/holiday
     * working, part-day holidays and off-Saturdays apply here too.
     *
     * What counts:
     *   full credit  present, late, WFH, short-leave-present, SP, HP
     *   half credit  a worked half day
     *   no credit    absent, miss-punch, unpaid leave
     *
     * APPROVED PAID LEAVE COUNTS AS ATTENDED. Leave that HR has granted and
     * already charged to the person's balance is authorised absence, and
     * scoring it as a miss would dock marks for taking earned leave -- an
     * incentive to come in sick rather than an attendance measure. Unpaid
     * leave gets no credit, because there was no entitlement behind it.
     *
     * Returns a 0-100 figure, so the KPI carries target_value = 100.
     */
    private static function attendancePercent(int $userId, Carbon $start, Carbon $end): ?float
    {
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return null;
        }

        // Never score a period that has not finished happening: counting the
        // rest of the month as absence would show everyone failing mid-month.
        $last = $end->copy()->min(Carbon::today());
        if ($last->lt($start)) {
            return null;
        }

        $rows = \App\Models\Attendance::where('user_id', $userId)
            ->whereBetween('check_in_time', [$start->copy()->startOfDay(), $last->copy()->endOfDay()])
            ->get()
            ->keyBy(fn ($a) => $a->check_in_time->toDateString());

        $leaves = \App\Models\LeaveRequest::with('leaveType')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $last->toDateString())
            ->whereDate('to_date', '>=', $start->toDateString())
            ->get();

        $working = 0.0;
        $attended = 0.0;

        for ($d = $start->copy(); $d->lte($last); $d->addDay()) {
            $holiday = \App\Models\Holiday::where('status', 1)->whereDate('date', $d->toDateString())->first();

            // Sundays, weekly offs and full holidays are not working days, so
            // they belong in neither side of the fraction.
            if (!LeavePolicyService::isWorkingDay($user, $d) && !($holiday && $holiday->isFullDay())) {
                if (!$rows->has($d->toDateString())) {
                    continue;
                }
            }

            $attendance = $rows->get($d->toDateString());
            $leave = $leaves->first(fn ($l) => $l->from_date->lte($d) && $l->to_date->gte($d));

            $result = AttendanceDayStatus::resolve($user, $d->copy(), $attendance, $leave, $holiday);
            $code = $result['code'];

            // Off days only enter the count when they were actually worked,
            // where they can add credit but must not add to the denominator.
            if (in_array($code, ['weekly_off', 'holiday', 'scheduled', 'today', 'missing'], true)) {
                continue;
            }
            if (in_array($code, ['sp', 'hp'], true)) {
                $attended += 1.0;
                continue;
            }

            $working += 1.0;

            if (in_array($code, ['half-day', 'half-day-paid'], true)) {
                $attended += 0.5;
            } elseif (in_array($code, AttendanceDayStatus::PRESENT_LIKE_CODES, true)) {
                $attended += 1.0;
            } elseif ($code === 'paid_leave') {
                $attended += 1.0;   // authorised, and already paid for from the balance
            }
            // absent / miss-punch / unpaid_leave earn nothing.
        }

        if ($working <= 0) {
            return null;
        }

        // A person with no attendance row and no leave for the whole period
        // scores zero, the same as somebody who was marked absent every day.
        // Asked for deliberately: the business treats an unrecorded month as an
        // unattended one, and the four accounts it currently applies to are
        // regional logins and a new joiner who should be on the registry. The
        // KPI cannot distinguish missing paperwork from absence, and this is
        // the side of that it errs on.
        return round(min(100, $attended / $working * 100), 2);
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

    /**
     * The pipeline someone is carrying, in ₹ lakh.
     *
     * Not the same figure as totalPipeline() above, which is revenue expressed
     * as a PERCENTAGE of this. The scorecard card wants the value itself.
     */
    public static function totalPipelineValue(int $userId): float
    {
        return round((float) self::book($userId)->sum('total_business_potential'), 2);
    }

    /** Public door to the attendance figure, for the scorecard's headline card. */
    public static function attendancePercentFor(int $userId, Carbon $start, Carbon $end): ?float
    {
        return self::attendancePercent($userId, $start, $end);
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

        // Same five attribution routes as book(). Counting only three made this
        // disagree with the Sales Report the salesperson reads their own number
        // off -- see book()'s note for the measured effect.
        $actual = (float) DB::table('sales_pipeline_months as m')
            ->join('sales_pipelines as sp', 'sp.id', '=', 'm.sales_pipeline_id')
            ->where(function ($q) use ($userId) {
                $q->where('sp.salesperson_id', $userId)
                  ->orWhere('sp.new_biz_id', $userId)
                  ->orWhere('sp.new_biz_2_id', $userId)
                  ->orWhere('sp.ccare_id', $userId)
                  ->orWhereExists(function ($e) use ($userId) {
                      $e->selectRaw('1')
                        ->from('sales_pipeline_extra_reps as er')
                        ->whereColumn('er.sales_pipeline_id', 'sp.id')
                        ->where('er.user_id', $userId);
                  });
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
    /** Last day the figures can cover: the period's end, or today if it has not ended. */
    private static function upTo(Carbon $end): Carbon
    {
        return $end->copy()->min(Carbon::today());
    }

    /**
     * Total business potential of the book against the annual Bingo target, as a
     * percentage (the rule book's "Pipeline ÷ Target"). Potential is not dated, so
     * this reads the book as it stands; the target is the current fiscal year's.
     */
    public static function pipelineVsTarget(int $userId): ?float
    {
        $target = (float) DB::table('salesperson_targets')
            ->where('salesperson_id', $userId)
            ->whereIn('month_year', self::currentFiscalKeys())
            ->sum('bingo_target');
        if ($target <= 0) {
            return null;
        }

        return round((float) self::book($userId)->sum('total_business_potential') / $target * 100, 2);
    }

    /** The current fiscal year's month keys (Apr to Mar), for the undated figure above. */
    private static function currentFiscalKeys(): array
    {
        $today = Carbon::today();
        $start = $today->month >= 4 ? Carbon::create($today->year, 4, 1) : Carbon::create($today->year - 1, 4, 1);

        return self::monthKeys($start, $start->copy()->addMonths(11));
    }

    /**
     * Revenue growth against the SAME stretch a year earlier. A part-year is
     * compared with the same part of last year, never with all twelve months.
     * Null when last year has no revenue: growth from nothing is not a number.
     */
    public static function yoyGrowth(int $userId, Carbon $start, Carbon $end): ?float
    {
        $to   = self::upTo($end);
        $now  = self::revenue($userId, $start, $to);
        $then = self::revenue($userId, $start->copy()->subYear(), $to->copy()->subYear());
        if ($then <= 0) {
            return null;
        }

        return round(($now - $then) / $then * 100, 2);
    }

    /**
     * Share of the period's revenue that came from customers whose first ever
     * order falls inside the period. Only as good as the order history loaded:
     * with a short history nearly everyone looks new.
     */
    public static function newCustomerRevenuePct(int $userId, Carbon $start, Carbon $end): ?float
    {
        $to    = self::upTo($end);
        $keys  = self::monthKeys($start, $to);
        $total = self::revenue($userId, $start, $to);
        if ($total <= 0) {
            return null;
        }

        // Without the year before on record, every customer looks new and everybody
        // would be handed full marks. No history, no figure.
        if (self::revenue($userId, $start->copy()->subYear(), $start->copy()->subDay()) <= 0) {
            return null;
        }

        $firstSale = DB::table('sales_pipeline_months')
            ->whereIn('sales_pipeline_id', self::book($userId)->select('id'))
            ->where('sale_amount', '>', 0)
            ->selectRaw('sales_pipeline_id, MIN(month_year) first_month')
            ->groupBy('sales_pipeline_id');
        $newIds = DB::query()->fromSub($firstSale, 'f')->whereIn('f.first_month', $keys)->pluck('f.sales_pipeline_id');
        if ($newIds->isEmpty()) {
            return 0.0;
        }

        $fromNew = (float) DB::table('sales_pipeline_months')
            ->whereIn('sales_pipeline_id', $newIds)
            ->whereIn('month_year', $keys)
            ->sum('sale_amount');

        return round($fromNew / $total * 100, 2);
    }

    /**
     * Of the customers who ordered at all in last year's financial year, the share
     * with no order yet this year. Null when last year had no customers to lose.
     */
    public static function customerAttrition(int $userId, Carbon $start, Carbon $end): ?float
    {
        $lastYear = self::monthKeys($start->copy()->subYear(), $end->copy()->subYear());
        $base = DB::table('sales_pipeline_months')
            ->whereIn('sales_pipeline_id', self::book($userId)->select('id'))
            ->whereIn('month_year', $lastYear)
            ->where('sale_amount', '>', 0)
            ->distinct()->pluck('sales_pipeline_id');
        if ($base->isEmpty()) {
            return null;
        }

        $kept = DB::table('sales_pipeline_months')
            ->whereIn('sales_pipeline_id', $base)
            ->whereIn('month_year', self::monthKeys($start, self::upTo($end)))
            ->where('sale_amount', '>', 0)
            ->distinct()->count('sales_pipeline_id');

        return round(($base->count() - $kept) / $base->count() * 100, 2);
    }

    /**
     * Verified/paid travel claims plus approved expenses, as a % of revenue.
     * Revenue is stored in lakh and money in rupees, hence the 100,000.
     *
     * The rule book gives anyone under six months' service 3 points whatever they
     * spent; 3 is what this returns for them so the bands award exactly that.
     * Null when there is no revenue, or no claim of any kind, to measure against.
     */
    public static function costOfSelling(int $userId, Carbon $start, Carbon $end): ?float
    {
        $joined = DB::table('users')->where('id', $userId)->value('date_of_joining');
        if ($joined && Carbon::parse($joined)->diffInMonths(Carbon::today()) < 6) {
            return 3.0;
        }

        $to = self::upTo($end);
        $revenueRupees = self::revenue($userId, $start, $to) * 100000;
        if ($revenueRupees <= 0) {
            return null;
        }

        $window = [$start->copy()->startOfDay(), $to->copy()->endOfDay()];
        $travel = (float) DB::table('travel_claims')->where('user_id', $userId)
            ->whereIn('status', ['verified', 'paid'])->whereBetween('created_at', $window)->sum('total_amount');
        $other = DB::getSchemaBuilder()->hasTable('expense_requests')
            ? (float) DB::table('expense_requests')->where('user_id', $userId)
                ->whereIn('status', ['approved', 'processed'])->whereBetween('created_at', $window)->sum('approved_amount')
            : 0.0;

        // Nothing claimed at all is far more likely to be claims that were never
        // entered than a genuinely free year, so it is not scored as a perfect one.
        if ($travel + $other <= 0) {
            return null;
        }

        return round(($travel + $other) / $revenueRupees * 100, 2);
    }

    /** Mind Speak entries of a category that a reviewer has approved or acknowledged. */
    public static function mindSpeakAccepted(int $userId, Carbon $start, Carbon $end, string $category): float
    {
        return (float) DB::table('mind_speaks')
            ->where('user_id', $userId)
            ->where('category', $category)
            ->whereIn('status', ['approved', 'acknowledged'])
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->count();
    }

    /**
     * Share of the person's 90-Day Goals (in an appraisal cycle overlapping the
     * period) that reached week 12 as "Regular Order" or "On Track". Null with no goals.
     */
    public static function ninetyDayGoals(int $userId, Carbon $start, Carbon $end): ?float
    {
        $good = ['Regular Order', 'On Track'];
        $statuses = collect();

        // The tracker: every goal in a 13-week round that overlaps the period, judged by
        // the latest status the person has reported for it.
        $roundIds = DB::table('ninety_day_rounds')->where('user_id', $userId)
            ->where('starts_on', '<=', $end->toDateString())->where('ends_on', '>=', $start->toDateString())->pluck('id');
        if ($roundIds->isNotEmpty()) {
            foreach (DB::table('ninety_day_goals')->where('user_id', $userId)->whereNull('deleted_at')->whereIn('round_id', $roundIds)->pluck('id') as $goalId) {
                $statuses->push(DB::table('ninety_day_goal_checkins')->where('goal_id', $goalId)->whereNotNull('status')->orderByDesc('week_no')->value('status'));
            }
        }

        // Older goals filed against an appraisal cycle and judged by week 12.
        $cycles = DB::table('appraisal_cycles')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->pluck('id');
        if ($cycles->isNotEmpty()) {
            $statuses = $statuses->concat(
                DB::table('ninety_day_goals')->where('user_id', $userId)->whereNull('deleted_at')->whereNull('round_id')
                    ->whereIn('appraisal_cycle_id', $cycles)->pluck('w12')
            );
        }

        if ($statuses->isEmpty()) {
            return null;
        }

        return round($statuses->filter(fn ($w) => in_array($w, $good, true))->count() / $statuses->count() * 100, 2);
    }
}
