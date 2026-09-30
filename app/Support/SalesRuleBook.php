<?php

namespace App\Support;

use App\Models\KpiTemplate;

/**
 * The Sales Assessment Rule Book as KPI templates: 14 parameters and the
 * attrition penalty, scored yearly (financial year, April to March).
 *
 * Each tier list is a band table of [minimum, points] pairs: the highest band
 * whose minimum the figure reaches gives the points. Tiers are taken straight
 * from the rule book's sheet, so the points a person earns here are the points
 * the sheet would give.
 *
 * Two things are worth knowing when reading the tables:
 *  - Count and percentage figures are scored against a target of 100 so the band
 *    minimums can be written in the figure's own units (8 demos, 97 %).
 *  - "Lower is better" rows (cost of selling, attrition, dominant-product share)
 *    use a decreasing trend, which turns the figure into target ÷ figure × 100;
 *    their minimums are that converted number, worked out from the sheet's limits
 *    (a cost of selling of 4 % is 25 when the target is 1). Minimums such as
 *    30.0001 stand for a strict "more than 30".
 *
 * definitions() is the sheet as written. install() leaves out anything in
 * RETIRED, so re-running it can never bring a replaced KPI back.
 */
class SalesRuleBook
{
    /**
     * Rows of the sheet that are no longer installed, with where they went.
     *
     * Cost of Selling became a monthly KPI typed in through Bulk KPI Entry for
     * everyone in Sales and New Biz (2026-09-28). Installing the yearly
     * automatic one next to it would count cost of selling twice.
     */
    public const RETIRED = [
        'Cost of Selling (COS%)' => 'Replaced by the monthly "Cost of Selling" KPI filled in Bulk KPI Entry',
        // No demos or product trials are logged in HRX (sales_visits is empty), so it
        // scored everyone 0. Taken out of the rule book on 2026-09-29.
        'Trial Activity / Demos' => 'Removed: demos are not recorded in HRX',
    ];

    public const KRA_TITLE = 'Sales Assessment';

    /** title, basis (shown on the scorecard), source, trend, weight, target, tiers [[min, points], ...] */
    public static function definitions(): array
    {
        // One point for each, up to eight.
        $upTo8 = array_map(fn ($n) => [$n, $n], range(0, 8));

        return [
            ['Target Achievement (Bingo %)', 'Sale ÷ Bingo target, %',                         'auto:bingo_achievement',        'increase', 25, 100, [[0, 0], [70, 4], [80, 8], [90, 12], [97, 15], [110, 20], [125, 25]]],
            ['Pipeline Strength',            'Pipeline potential ÷ Bingo target, %',            'auto:pipeline_vs_target',       'increase', 10, 100, [[0, 0], [60, 3], [70, 5], [80, 8], [90, 10]]],
            ['YoY Revenue Growth',           'Growth on the same period last year, %',          'auto:yoy_growth',               'increase', 25, 100, [[0, 0], [15, 10], [25, 20], [35, 30]]],
            ['Trial Activity / Demos',       'Product-trial visits completed',                  'auto:trial_demo_visits',        'increase', 5,  100, [[0, 0], [8, 2], [12, 3], [20, 5], [30.0001, 8]]],
            ['New Customer Wins (%)',        'Revenue from customers new this year, %',         'auto:new_customer_revenue_pct', 'increase', 13, 100, [[0, 0], [15, 5], [20, 8], [25, 13], [30.0001, 15]]],
            ['90-Day Goals',                 'Goals on track or in regular order, %',           'auto:ninety_day_goals',         'increase', 5,  100, [[0, 0], [40, 3], [50, 5], [70, 8]]],
            // Counted from Mind Speak, not typed in: one Sales Pitch submitted in the
            // month earns the marks (2026-09-29; was a reviewer's score out of 20).
            ['Sales Pitch Score',            'Sales Pitch submitted in Mind Speak',             'auto:sales_pitch_submitted',    'increase', 2,  100, [[0, 0], [1, 2]]],
            ['Learn@HiTech',                 'Programs presented',                              'auto:learn_hitech_sessions',    'increase', 2,  100, [[0, 0], [1, 2], [2, 5]]],
            ['Seminars / Presentations',     'Seminars conducted',                              'auto:seminars_conducted',       'increase', 2,  100, [[0, 0], [8, 2], [12, 5]]],
            ['Competitor Insights',          '1 point per insight, up to 8',                    'auto:mindspeak_insights',       'increase', 3,  100, $upTo8],
            ['New Product Ideas',            '1 point per idea, up to 8',                       'auto:mindspeak_ideas',          'increase', 3,  100, $upTo8],
            ['Cost of Selling (COS%)',       'Travel + expenses ÷ revenue, %. Lower is better', 'auto:cost_of_selling',          'decrease', 3,  1,   [[0, -5], [10, 0], [16.6666, 1], [25, 3], [50.0001, 5]]],
            ['Cross-Sell / Basket Mix',      'Dominant product share, %. Lower is better',      'approval',                      'decrease', 2,  100, [[0, 0], [111.1111, 3], [133.3333, 5]]],
            // Counted, not typed in (2026-09-30): the month's sales ÷ the book's monthly business potential.
            ['Market Share %',               "Sales ÷ the book's monthly business potential, %", 'auto:market_share',             'increase', 10, 100, [[0, 0], [20, 5], [30, 8], [40, 10], [60, 15]]],
            ['Attrition Penalty',            "Last year's customers with no order yet, %. Lower is better", 'auto:customer_attrition', 'decrease', 0, 1, [[0, -5], [10.0001, -3], [20.0001, 0]]],
        ];
    }

    /** Band table in the shape the scoring engine reads. */
    public static function bands(array $tiers): array
    {
        return array_map(fn ($t) => ['min' => (float) $t[0], 'marks' => (float) $t[1]], $tiers);
    }

    /**
     * Create the KRA heading and any of its KPIs the department does not have
     * yet, restricted to the given designations. Safe to re-run: a KPI that
     * already exists is returned as it is, never changed. The one exception is
     * a RETIRED KPI, which is switched off.
     *
     * @return array<string, KpiTemplate>  KPI title => template
     */
    public static function install(int $departmentId, array $designationIds, ?int $createdById = null): array
    {
        $base = ['department_id' => $departmentId, 'frequency' => 'yearly', 'check_month' => 3, 'check_day' => 31,
                 'late_policy' => 'none', 'late_score' => 0, 'grace_days' => 0, 'is_active' => true];

        // Create only what is missing. Once a KPI exists it belongs to whoever runs
        // the scorecards: frequency, on/off, bands and targets are changed on the
        // site (11 of these were moved to monthly and Cross-Sell switched off after
        // the first install), and a re-run used to put all of that back.
        $kra = KpiTemplate::firstOrCreate(
            ['department_id' => $departmentId, 'title' => self::KRA_TITLE, 'category' => 'KRA'],
            $base + ['max_marks' => 0, 'scoring_type' => 'target', 'data_source' => 'approval', 'created_by_id' => $createdById]
        );

        $out = [];
        foreach (self::definitions() as [$title, $basis, $source, $trend, $weight, $target, $tiers]) {
            if (isset(self::RETIRED[$title])) {
                continue;
            }
            $out[$title] = KpiTemplate::firstOrCreate(
                ['department_id' => $departmentId, 'title' => $title, 'category' => 'KPI'],
                $base + [
                    'description'        => $basis,
                    'parent_template_id' => $kra->id,
                    'designation_ids'    => array_values(array_map('intval', $designationIds)),
                    'scoring_type'       => 'banded',
                    'bands'              => self::bands($tiers),
                    'trend'              => $trend,
                    'max_marks'          => $weight,
                    'target_value'       => $target,
                    'result_cap_percent' => null,
                    'data_source'        => $source,
                    'approver_id'        => null,
                    'created_by_id'      => $createdById,
                ]
            );
        }

        self::retire($departmentId);

        return $out;
    }

    /**
     * Switch off retired KPIs an earlier install left in the department, and
     * their assignments. Nothing is deleted: scores already recorded stay.
     */
    private static function retire(int $departmentId): void
    {
        $old = KpiTemplate::where('department_id', $departmentId)
            ->where('category', 'KPI')
            ->whereIn('title', array_keys(self::RETIRED))
            ->get();

        foreach ($old as $t) {
            $t->forceFill(['is_active' => false])->save();
            \App\Models\KpiAssignment::where('kpi_template_id', $t->id)->update(['is_active' => false]);
        }
    }
}
