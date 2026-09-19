<?php

namespace Tests\Unit;

use App\Models\KpiAssignment;
use App\Services\KpiPeriodService;
use Tests\TestCase;

/**
 * Lower-is-better KPIs, a zero target, and a result ceiling — the scoring the
 * Production scorecard needs. Every case is taken from that sheet, so the last
 * test reproduces its own total. Pure functions, no database.
 */
class KpiTrendScoringTest extends TestCase
{
    private function kpi(array $attributes): KpiAssignment
    {
        $a = new KpiAssignment();
        foreach ($attributes as $k => $v) {
            $a->{$k} = $v;
        }
        return $a;
    }

    public function test_an_assignment_with_no_trend_still_scores_as_increase(): void
    {
        // Every KPI that predates the trend column must score exactly as before.
        $kpi = $this->kpi(['scoring_type' => 'target', 'max_marks' => 15, 'target_value' => 700]);

        $this->assertSame(12.99, KpiPeriodService::targetScore($kpi, 606));
        $this->assertSame(15.0, KpiPeriodService::targetScore($kpi, 900), 'still capped at its marks');
    }

    public function test_decrease_scores_target_over_achieved(): void
    {
        // No. of Mandays: target 6100, actual 6100.
        $kpi = $this->kpi(['scoring_type' => 'target', 'trend' => 'decrease', 'max_marks' => 5, 'target_value' => 6100]);

        $this->assertSame(5.0, KpiPeriodService::targetScore($kpi, 6100));
        $this->assertSame(2.5, KpiPeriodService::targetScore($kpi, 12200), 'twice the mandays halves the score');
    }

    public function test_decrease_is_capped_at_its_marks_without_a_ceiling(): void
    {
        // Waste Produced: target 7.0, actual 6.5 is 107.7% — but with no
        // ceiling set a KPI never pays more than its marks.
        $kpi = $this->kpi(['scoring_type' => 'target', 'trend' => 'decrease', 'max_marks' => 5, 'target_value' => 7]);

        $this->assertSame(5.0, KpiPeriodService::targetScore($kpi, 6.5));
    }

    public function test_a_ceiling_lets_over_achievement_score_above_the_marks(): void
    {
        $waste = $this->kpi(['scoring_type' => 'target', 'trend' => 'decrease', 'max_marks' => 5,
            'target_value' => 7, 'result_cap_percent' => 200]);
        $audit = $this->kpi(['scoring_type' => 'target', 'max_marks' => 5,
            'target_value' => 70, 'result_cap_percent' => 200]);

        $this->assertSame(5.38, KpiPeriodService::targetScore($waste, 6.5));   // 107.7%
        $this->assertSame(5.36, KpiPeriodService::targetScore($audit, 75));    // 107.1%
    }

    public function test_the_ceiling_is_a_hard_limit(): void
    {
        $kpi = $this->kpi(['scoring_type' => 'target', 'max_marks' => 10, 'target_value' => 100, 'result_cap_percent' => 150]);

        $this->assertSame(15.0, KpiPeriodService::targetScore($kpi, 900), '900% counts as 150%');
    }

    public function test_a_zero_target_on_a_decrease_kpi_is_none_allowed(): void
    {
        // Quality Complaints: target 0. No complaints = full marks, any = nothing.
        $kpi = $this->kpi(['scoring_type' => 'target', 'trend' => 'decrease', 'max_marks' => 5, 'target_value' => 0]);

        $this->assertSame(5.0, KpiPeriodService::targetScore($kpi, 0));
        $this->assertSame(0.0, KpiPeriodService::targetScore($kpi, 1));
        $this->assertSame(0.0, KpiPeriodService::targetScore($kpi, 16));
    }

    public function test_zero_actual_against_a_real_target_is_the_best_possible_result(): void
    {
        $capped   = $this->kpi(['scoring_type' => 'target', 'trend' => 'decrease', 'max_marks' => 5, 'target_value' => 7]);
        $ceiling  = $this->kpi(['scoring_type' => 'target', 'trend' => 'decrease', 'max_marks' => 5, 'target_value' => 7, 'result_cap_percent' => 200]);

        $this->assertSame(5.0,  KpiPeriodService::targetScore($capped, 0));
        $this->assertSame(10.0, KpiPeriodService::targetScore($ceiling, 0));
    }

    public function test_an_increase_kpi_with_no_target_still_scores_zero(): void
    {
        $kpi = $this->kpi(['scoring_type' => 'target', 'max_marks' => 10, 'target_value' => 0]);

        $this->assertSame(0.0, KpiPeriodService::targetScore($kpi, 500));
    }

    public function test_manpower_reduction_bands_by_percent_of_target(): void
    {
        // 0% = 0 pts, 1-50% = 5 pts, 51-100% = 10 pts. Target: reduce by 5.
        $kpi = $this->kpi(['scoring_type' => 'banded', 'max_marks' => 10, 'target_value' => 5,
            'bands' => [['min' => 0, 'marks' => 0], ['min' => 1, 'marks' => 5], ['min' => 51, 'marks' => 10]]]);

        $this->assertSame(0.0,  KpiPeriodService::targetScore($kpi, 0));
        $this->assertSame(5.0,  KpiPeriodService::targetScore($kpi, 1));   // 20%
        $this->assertSame(5.0,  KpiPeriodService::targetScore($kpi, 2.5)); // 50%
        $this->assertSame(10.0, KpiPeriodService::targetScore($kpi, 3));   // 60%
        $this->assertSame(10.0, KpiPeriodService::targetScore($kpi, 5));   // 100%
    }

    /**
     * The whole Production sheet, one month. Its printed figures are reproduced
     * to the paisa except Productivity, whose sheet cell shows 86.6% (a copy of
     * the Production row) though 99 ÷ 115 is 86.1% — the engine scores the real
     * arithmetic, so that row is 8.61 and the total 78.42, not 8.66 and 78.46.
     */
    public function test_the_production_scorecard_reproduces_the_sheet(): void
    {
        $up   = ['scoring_type' => 'target', 'result_cap_percent' => 200];
        $down = ['scoring_type' => 'target', 'result_cap_percent' => 200, 'trend' => 'decrease'];

        $rows = [
            // name, kpi, actual, expected score
            ['Production',         $this->kpi($up + ['max_marks' => 15, 'target_value' => 700]),   606,   12.99],
            ['No. of Mandays',     $this->kpi($down + ['max_marks' => 5, 'target_value' => 6100]), 6100,  5.00],
            ['Productivity',       $this->kpi($up + ['max_marks' => 10, 'target_value' => 115]),   99,    8.61],
            ['Overall Efficiency', $this->kpi($up + ['max_marks' => 15, 'target_value' => 57]),    55,    14.47],
            ['Waste Produced',     $this->kpi($down + ['max_marks' => 5, 'target_value' => 7]),    6.5,   5.38],
            ['Waste Consumed',     $this->kpi($up + ['max_marks' => 10, 'target_value' => 14]),    13,    9.29],
            ['OTIF',               $this->kpi($up + ['max_marks' => 15, 'target_value' => 95]),    40,    6.32],
            ['Quality Complaints', $this->kpi($down + ['max_marks' => 5, 'target_value' => 0]),    16,    0.0],
            ['Manpower Reduction', $this->kpi(['scoring_type' => 'banded', 'max_marks' => 10, 'target_value' => 5,
                'bands' => [['min' => 0, 'marks' => 0], ['min' => 1, 'marks' => 5], ['min' => 51, 'marks' => 10]]]), 5, 10.0],
            ['Audit Score',        $this->kpi($up + ['max_marks' => 5, 'target_value' => 70]),     75,    5.36],
            ['Cost Saving',        $this->kpi($up + ['max_marks' => 5, 'target_value' => 50000]),  10000, 1.0],
        ];

        $total = 0.0;
        $weight = 0.0;
        foreach ($rows as [$name, $kpi, $actual, $expected]) {
            $score = KpiPeriodService::targetScore($kpi, (float) $actual);
            $this->assertEqualsWithDelta($expected, $score, 0.001, $name);
            $total += $score;
            $weight += (float) $kpi->max_marks;
        }

        $this->assertSame(100.0, $weight, 'the weightages add to 100');
        $this->assertEqualsWithDelta(78.42, $total, 0.001);
    }
}
