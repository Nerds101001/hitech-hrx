<?php

namespace Tests\Unit;

use App\Models\KpiAssignment;
use App\Services\KpiPeriodService;
use App\Support\SalesRuleBook;
use Tests\TestCase;

/**
 * The rule book's tiers, checked at and either side of every boundary the sheet
 * states, and the whole sheet reproduced: the annual report this was written
 * from scores 63 points, and so must these templates given the same figures.
 */
class SalesRuleBookTest extends TestCase
{
    private function assignment(string $title): KpiAssignment
    {
        foreach (SalesRuleBook::definitions() as [$t, $basis, $source, $trend, $weight, $target, $tiers]) {
            if ($t === $title) {
                return new KpiAssignment([
                    'title' => $t, 'scoring_type' => 'banded', 'bands' => SalesRuleBook::bands($tiers),
                    'trend' => $trend, 'max_marks' => $weight, 'target_value' => $target, 'result_cap_percent' => null,
                ]);
            }
        }
        $this->fail("no such KPI: $title");
    }

    private function points(string $title, float $figure): float
    {
        return KpiPeriodService::targetScore($this->assignment($title), $figure);
    }

    public static function tiers(): array
    {
        // [KPI, figure, points the sheet gives]
        return [
            // Bingo %: >=97 = 15 | 90-96 = 12 | 80-89 = 8 | 70-79 = 4 | <70 = 0; bonus >=110 = 20, >=125 = 25
            ['Target Achievement (Bingo %)', 69.99, 0], ['Target Achievement (Bingo %)', 70, 4], ['Target Achievement (Bingo %)', 79.9, 4],
            ['Target Achievement (Bingo %)', 80, 8], ['Target Achievement (Bingo %)', 86, 8], ['Target Achievement (Bingo %)', 90, 12],
            ['Target Achievement (Bingo %)', 96.9, 12], ['Target Achievement (Bingo %)', 97, 15], ['Target Achievement (Bingo %)', 109.9, 15],
            ['Target Achievement (Bingo %)', 110, 20], ['Target Achievement (Bingo %)', 125, 25], ['Target Achievement (Bingo %)', 180, 25],
            // Pipeline: >=90 = 10 | >=80 = 8 | >=70 = 5 | >=60 = 3 | <60 = 0
            ['Pipeline Strength', 59.9, 0], ['Pipeline Strength', 60, 3], ['Pipeline Strength', 70.8, 5], ['Pipeline Strength', 80, 8], ['Pipeline Strength', 90, 10],
            // YoY: >=35 = 30 | 25-35 = 20 | 15-20 = 10 | <15 = 0
            ['YoY Revenue Growth', 14.9, 0], ['YoY Revenue Growth', 15, 10], ['YoY Revenue Growth', 25, 20], ['YoY Revenue Growth', 34.9, 20], ['YoY Revenue Growth', 35, 30],
            // Demos: >30 = 8 | 20-30 = 5 | 12-20 = 3 | 8-12 = 2 | <8 = 0
            ['Trial Activity / Demos', 7, 0], ['Trial Activity / Demos', 8, 2], ['Trial Activity / Demos', 12, 3], ['Trial Activity / Demos', 20, 5],
            ['Trial Activity / Demos', 30, 5], ['Trial Activity / Demos', 31, 8], ['Trial Activity / Demos', 34, 8],
            // New customer revenue %: >30 = 15 | 25-30 = 13 | 20-25 = 8 | 15-20 = 5 | <15 = 0
            ['New Customer Wins (%)', 14.9, 0], ['New Customer Wins (%)', 15, 5], ['New Customer Wins (%)', 20, 8], ['New Customer Wins (%)', 25, 13],
            ['New Customer Wins (%)', 30, 13], ['New Customer Wins (%)', 30.1, 15], ['New Customer Wins (%)', 55.1, 15],
            // 90-day goals: >=70 = 8 | 50-70 = 5 | 40-50 = 3 | <40 = 0
            ['90-Day Goals', 39, 0], ['90-Day Goals', 40, 3], ['90-Day Goals', 50, 5], ['90-Day Goals', 70, 8],
            // Sales pitch (2026-09-29: pitches submitted in Mind Speak, not a reviewer's score): 1+ = 2 | none = 0
            ['Sales Pitch Score', 0, 0], ['Sales Pitch Score', 1, 2], ['Sales Pitch Score', 5, 2],
            // Learn@HiTech: 2+ = 5 | 1 = 2 | none = 0
            ['Learn@HiTech', 0, 0], ['Learn@HiTech', 1, 2], ['Learn@HiTech', 2, 5], ['Learn@HiTech', 6, 5],
            // Seminars: >=12 = 5 | 8-11 = 2 | <8 = 0
            ['Seminars / Presentations', 7, 0], ['Seminars / Presentations', 8, 2], ['Seminars / Presentations', 11, 2], ['Seminars / Presentations', 12, 5],
            // Insights / ideas: 1 point each, max 8
            ['Competitor Insights', 0, 0], ['Competitor Insights', 7, 7], ['Competitor Insights', 8, 8], ['Competitor Insights', 15, 8],
            ['New Product Ideas', 3, 3], ['New Product Ideas', 12, 8],
            // Cost of selling %, lower is better: <2 = 5 | 2-4 = 3 | 4-6 = 1 | 6-10 = 0 | >10 = -5
            ['Cost of Selling (COS%)', 0, 5], ['Cost of Selling (COS%)', 1.5, 5], ['Cost of Selling (COS%)', 1.99, 5], ['Cost of Selling (COS%)', 2, 3],
            ['Cost of Selling (COS%)', 4, 3], ['Cost of Selling (COS%)', 4.01, 1], ['Cost of Selling (COS%)', 6, 1], ['Cost of Selling (COS%)', 6.01, 0],
            ['Cost of Selling (COS%)', 10, 0], ['Cost of Selling (COS%)', 10.01, -5], ['Cost of Selling (COS%)', 40, -5],
            // Dominant product share %, lower is better: <=75 = 5 | 75-90 = 3 | >90 = 0
            ['Cross-Sell / Basket Mix', 60, 5], ['Cross-Sell / Basket Mix', 75, 5], ['Cross-Sell / Basket Mix', 75.5, 3], ['Cross-Sell / Basket Mix', 90, 3], ['Cross-Sell / Basket Mix', 90.5, 0],
            // Market share %: >=60 = 15 | 40-60 = 10 | 30-40 = 8 | 20-30 = 5 | <20 = 0
            ['Market Share %', 19.9, 0], ['Market Share %', 20, 5], ['Market Share %', 30, 8], ['Market Share %', 40.4, 10], ['Market Share %', 60, 15],
            // Attrition, lower is better: >10 % lost = -5 | 5-10 % = -3 | <5 % = 0
            ['Attrition Penalty', 0, 0], ['Attrition Penalty', 4.9, 0], ['Attrition Penalty', 5, -3], ['Attrition Penalty', 9.9, -3], ['Attrition Penalty', 10, -5], ['Attrition Penalty', 21.4, -5],
        ];
    }

    /**
     * The tiers above are the sheet as written. Since 2026-09-30 no KPI scores
     * above its own maximum (user decision): a tier that paid past the weight -
     * YoY 30 on 25, New Customer Wins 15 on 13, Market Share 15 on 10 - is capped
     * at the weight. A 0-weight KPI (the attrition penalty) keeps its tiers.
     *
     * @dataProvider tiers
     */
    public function test_each_tier_boundary_gives_the_points_the_sheet_gives_capped_at_the_kpis_maximum(string $kpi, float $figure, float $expected): void
    {
        $weight = (float) collect(SalesRuleBook::definitions())->firstWhere(0, $kpi)[4];
        $capped = $weight > 0 ? min($expected, $weight) : $expected;
        $this->assertSame($capped, $this->points($kpi, $figure), "{$kpi} at {$figure}");
    }

    public function test_the_sheets_report_scores_48_points_once_capped_at_each_maximum(): void
    {
        // The figures printed in the annual report the rule book was written from.
        $figures = [
            'Target Achievement (Bingo %)' => 86.0,   // 8
            'Pipeline Strength'            => 70.8,   // 5
            'YoY Revenue Growth'           => 9.3,    // 0
            'Trial Activity / Demos'       => 34,     // 8
            'New Customer Wins (%)'        => 55.1,   // 15
            '90-Day Goals'                 => 45,     // 3
            'Sales Pitch Score'            => 0,      // 0
            'Learn@HiTech'                 => 0,      // 0
            'Seminars / Presentations'     => 5,      // 0
            'Competitor Insights'          => 7,      // 7
            'New Product Ideas'            => 7,      // 7
            'Cost of Selling (COS%)'       => 1.5,    // 5
            'Cross-Sell / Basket Mix'      => 100,    // 0 (no data)
            'Market Share %'               => 40.4,   // 10
            'Attrition Penalty'            => 21.4,   // -5
        ];

        $total = 0.0;
        foreach ($figures as $kpi => $figure) {
            $total += $this->points($kpi, $figure);
        }

        // The sheet printed 63. Capped at each KPI's maximum it is 48: Demos 8 -> 5,
        // New Customer Wins 15 -> 13, Competitor Insights 7 -> 3, New Product Ideas 7 -> 3,
        // Cost of Selling 5 -> 3.
        $this->assertSame(48.0, $total);
    }

    public function test_the_definitions_are_complete_and_self_consistent(): void
    {
        $defs = SalesRuleBook::definitions();

        $this->assertCount(15, $defs, '14 parameters and the attrition penalty');
        $this->assertSame(count($defs), count(array_unique(array_column($defs, 0))), 'titles are unique');
        foreach ($defs as [$title, $basis, $source, $trend, $weight, $target, $tiers]) {
            $this->assertTrue($source === 'approval' || str_starts_with($source, 'auto:'), $title);
            $this->assertContains($trend, ['increase', 'decrease'], $title);
            $this->assertGreaterThan(0, $target, "$title needs a target above zero");
            $mins = array_column($tiers, 0);
            $sorted = $mins;
            sort($sorted);
            $this->assertSame($sorted, $mins, "$title bands are listed in ascending order");
            $this->assertLessThanOrEqual(70, mb_strlen($basis), "$title basis must fit on one scorecard line");
        }
        // Every automatic source named here exists in the engine.
        foreach ($defs as $d) {
            if (str_starts_with($d[2], 'auto:')) {
                $this->assertArrayHasKey($d[2], \App\Services\KpiAutoSourceService::SOURCES, $d[2]);
            }
        }
    }
}
