<?php

namespace Tests\Unit;

use App\Models\KpiAssignment;
use App\Services\KpiPeriodService;
use App\Support\CcareRuleBook;
use Tests\TestCase;

/** The CCare rulebook's bands, checked at and either side of every boundary the sheet states. */
class CcareRuleBookTest extends TestCase
{
    private function points(string $title, float $figure): float
    {
        foreach (CcareRuleBook::definitions() as [$t, $basis, $source, $trend, $weight, $target, $tiers]) {
            if ($t === $title) {
                return KpiPeriodService::targetScore(new KpiAssignment([
                    'title' => $t, 'scoring_type' => 'banded', 'bands' => CcareRuleBook::bands($tiers),
                    'trend' => $trend, 'max_marks' => $weight, 'target_value' => $target, 'result_cap_percent' => null,
                ]), $figure);
            }
        }
        $this->fail("no such KPI: $title");
    }

    public static function tiers(): array
    {
        return [
            // Monthly Sale: >=100 = 35 | 90-99 = 30 | 80-89 = 20 | 70-79 = 10 | <70 = 0
            ['Monthly Sale', 69.9, 0], ['Monthly Sale', 70, 10], ['Monthly Sale', 80, 20], ['Monthly Sale', 94.7, 30], ['Monthly Sale', 100, 35], ['Monthly Sale', 130, 35],
            // Transfer rate: >=60 = 40 | 50-59.9 = 30 | 43.3-49.9 = 20 | 36.7-43.2 = 10 | <36.7 = 0
            ['Transfer Order Rate', 32, 0], ['Transfer Order Rate', 36.7, 10], ['Transfer Order Rate', 43.2, 10], ['Transfer Order Rate', 43.3, 20],
            ['Transfer Order Rate', 49.9, 20], ['Transfer Order Rate', 50, 30], ['Transfer Order Rate', 59.9, 30], ['Transfer Order Rate', 60, 40], ['Transfer Order Rate', 100, 40],
            // Call coverage: >=95 = 10 | any calls = 5 | none = 0
            ['Call Coverage', 0, 0], ['Call Coverage', 76.9, 5], ['Call Coverage', 94.9, 5], ['Call Coverage', 95, 10], ['Call Coverage', 97.4, 10],
            // Rate increases / NIF: min(int(n x 2), 20)
            ['Rate Increases Secured', 0, 0], ['Rate Increases Secured', 1, 2], ['Rate Increases Secured', 3, 6], ['Rate Increases Secured', 5, 10], ['Rate Increases Secured', 10, 20], ['Rate Increases Secured', 14, 20],
            ['NIF Recovery', 0, 0], ['NIF Recovery', 1, 2], ['NIF Recovery', 4, 8], ['NIF Recovery', 9, 18], ['NIF Recovery', 10, 20], ['NIF Recovery', 12, 20],
            // Upgrades: min(int(n x 10), 20)
            ['Upgrades Secured', 0, 0], ['Upgrades Secured', 1, 10], ['Upgrades Secured', 2, 20], ['Upgrades Secured', 3, 20],
            // Cost of selling: <0.5 = 5 | 0.5-0.74 = 2 | >=0.75 = 0
            ['Cost of Selling (COS %)', 0.2, 5], ['Cost of Selling (COS %)', 0.49, 5], ['Cost of Selling (COS %)', 0.5, 2], ['Cost of Selling (COS %)', 0.62, 2],
            ['Cost of Selling (COS %)', 0.74, 2], ['Cost of Selling (COS %)', 0.75, 0], ['Cost of Selling (COS %)', 0.8, 0],
        ];
    }

    /** @dataProvider tiers */
    public function test_tier(string $title, float $figure, float $expected): void
    {
        $this->assertEquals($expected, $this->points($title, $figure), "$title at $figure");
    }

    public function test_the_weights_add_up_to_the_sheets_150(): void
    {
        $this->assertSame(150, array_sum(array_column(CcareRuleBook::definitions(), 4)));
    }
}
