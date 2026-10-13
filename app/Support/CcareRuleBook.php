<?php

namespace App\Support;

use App\Models\KpiTemplate;

/**
 * The CCare Team Scoring Rulebook (April 2026, 150 points) as monthly KPI
 * templates, laid out like the New Biz ones.
 *
 * Each tier list is a band table of [minimum, points] pairs: the highest band
 * whose minimum the figure reaches gives the points. Count and percentage
 * figures are scored against a target of 100 so the minimums can be written in
 * the figure's own units (6 rate increases, 60 %).
 *
 * What HRX works out by itself, and what a person types in:
 *  - Monthly Sale: automatic, the person's sale over their Kingo target.
 *  - Transfer Order Rate: automatic, from the Sales Report's New Biz -> CCare move.
 *  - Call Coverage, Rate Increases, NIF Recovery, Upgrades, Cost of Selling:
 *    typed in Bulk KPI Entry. HRX holds no dated record of them (the per-customer
 *    Upgrade / Rate Increase flags on the Sales Report carry no date, and no
 *    visits or call records are logged for CCare), so there is nothing to count.
 *
 * The rate / NIF / upgrade rows follow the sheet's formula, min(int(avg x k), 20),
 * which is what its examples use. The sheet's band table disagrees with it in a
 * few places (1.5 a month: 3 by formula, 2 in the table); the formula wins.
 *
 * install() creates only what is missing, so re-running it never undoes what was
 * changed on the site afterwards.
 */
class CcareRuleBook
{
    public const KRA_TITLE = 'CCare Scoring';

    /** Customer Care and Customer Care + New Biz. */
    public const DEPARTMENT_IDS = [10, 34];

    /** title, basis (shown on the scorecard), source, trend, weight, target, tiers [[min, points], ...] */
    public static function definitions(): array
    {
        // 2 points for each, up to the 20 cap (the sheet's min(int(avg x 2), 20)).
        $twoEach = array_map(fn ($n) => [$n, min(2 * $n, 20)], range(0, 10));

        return [
            ['Monthly Sale',        'Sale ÷ Kingo target, %',                                    'auto:kingo_achievement',   'increase', 35, 100, [[0, 0], [70, 10], [80, 20], [90, 30], [100, 35]]],
            ['Transfer Order Rate', 'Customers moved from New Biz that ordered after the move, %', 'auto:transfer_order_rate', 'increase', 40, 100, [[0, 0], [36.7, 10], [43.3, 20], [50, 30], [60, 40]]],
            ['Call Coverage',       'Calls made ÷ Kingo Bingo active clients, %',                'approval',                 'increase', 10, 100, [[0, 0], [0.01, 5], [95, 10]]],
            ['Rate Increases Secured', 'Rate increases secured in the month. 2 points each, up to 20', 'approval',          'increase', 20, 100, $twoEach],
            ['NIF Recovery',        'Non-invoiced clients recovered in the month. 2 points each, up to 20', 'approval',      'increase', 20, 100, $twoEach],
            ['Upgrades Secured',    'Upgrades secured in the month. 10 points each, up to 20',   'approval',                 'increase', 20, 100, [[0, 0], [1, 10], [2, 20]]],
            // Lower is better, so the figure is read as target ÷ figure x 100 (target 1):
            // under 0.5 % is above 200, 0.75 % is 133.33.
            ['Cost of Selling (COS %)', 'Monthly salary cost ÷ monthly sale, %. Lower is better', 'approval',               'decrease', 5,  1,   [[0, 0], [133.3334, 2], [200.0001, 5]]],
        ];
    }

    /** Band table in the shape the scoring engine reads. */
    public static function bands(array $tiers): array
    {
        return array_map(fn ($t) => ['min' => (float) $t[0], 'marks' => (float) $t[1]], $tiers);
    }

    /**
     * Create the KRA heading and any of its KPIs the department does not have yet.
     *
     * @return array<string, KpiTemplate>  KPI title => template
     */
    public static function install(int $departmentId, ?int $createdById = null): array
    {
        $base = ['department_id' => $departmentId, 'frequency' => 'monthly', 'late_policy' => 'none',
                 'late_score' => 0, 'grace_days' => 0, 'is_active' => true];

        $kra = KpiTemplate::firstOrCreate(
            ['department_id' => $departmentId, 'title' => self::KRA_TITLE, 'category' => 'KRA'],
            $base + ['max_marks' => 0, 'scoring_type' => 'target', 'data_source' => 'approval', 'created_by_id' => $createdById]
        );

        $out = [];
        foreach (self::definitions() as [$title, $basis, $source, $trend, $weight, $target, $tiers]) {
            $out[$title] = KpiTemplate::firstOrCreate(
                ['department_id' => $departmentId, 'title' => $title, 'category' => 'KPI'],
                $base + [
                    'description'        => $basis,
                    'parent_template_id' => $kra->id,
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

        return $out;
    }
}
