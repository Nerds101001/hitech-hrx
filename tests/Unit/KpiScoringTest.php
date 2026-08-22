<?php

namespace Tests\Unit;

use App\Models\KpiAssignment;
use App\Services\KpiPeriodService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Scoring is where a KPI system earns or loses trust: an appraisal number that
 * is wrong by one mark is a conversation with an employee that cannot be taken
 * back. These are pure-function tests — no database — so they run in
 * milliseconds and can be exhaustive about boundaries.
 *
 * Three of these are regression tests for defects found during development and
 * are marked as such; they must never be deleted for being "obvious".
 */
class KpiScoringTest extends TestCase
{
    /** Build an unsaved assignment: scoring never touches the database. */
    private function kpi(array $attributes): KpiAssignment
    {
        $a = new KpiAssignment();
        foreach ($attributes as $k => $v) {
            $a->{$k} = $v;
        }
        return $a;
    }

    // ── target: proportional ─────────────────────────────────────────────

    public function test_target_scoring_is_proportional_to_achievement(): void
    {
        $kpi = $this->kpi(['scoring_type' => 'target', 'max_marks' => 10, 'target_value' => 100]);

        $this->assertSame(0.0,  KpiPeriodService::targetScore($kpi, 0));
        $this->assertSame(5.0,  KpiPeriodService::targetScore($kpi, 50));
        $this->assertSame(8.9,  KpiPeriodService::targetScore($kpi, 89));
        $this->assertSame(10.0, KpiPeriodService::targetScore($kpi, 100));
    }

    public function test_target_scoring_is_capped_at_max_marks_when_over_achieved(): void
    {
        $kpi = $this->kpi(['scoring_type' => 'target', 'max_marks' => 10, 'target_value' => 100]);

        // 300% of target must not hand out 30 marks and skew the scorecard.
        $this->assertSame(10.0, KpiPeriodService::targetScore($kpi, 300));
    }

    public function test_a_target_of_zero_scores_nothing_rather_than_dividing_by_zero(): void
    {
        $kpi = $this->kpi(['scoring_type' => 'target', 'max_marks' => 10, 'target_value' => 0]);

        $this->assertSame(0.0, KpiPeriodService::targetScore($kpi, 500));
    }

    // ── threshold: all or nothing ────────────────────────────────────────

    /**
     * The whole point of threshold scoring. Under the old linear-only engine
     * 89.9% scored 8.99 marks; the business rule is that it scores nothing.
     */
    public function test_threshold_pays_nothing_below_the_gate_and_full_marks_at_it(): void
    {
        $kpi = $this->kpi([
            'scoring_type' => 'threshold', 'max_marks' => 10,
            'target_value' => 100, 'threshold_percent' => 90,
        ]);

        $this->assertSame(0.0,  KpiPeriodService::targetScore($kpi, 89.99), 'just below the gate must score zero');
        $this->assertSame(10.0, KpiPeriodService::targetScore($kpi, 90),    'exactly on the gate must pay in full');
        $this->assertSame(10.0, KpiPeriodService::targetScore($kpi, 100));
        $this->assertSame(10.0, KpiPeriodService::targetScore($kpi, 250),   'over-achievement still pays max, never more');
    }

    /** Customer Health: "must be more than 60%", worth 20 marks. */
    public function test_customer_health_gate_at_sixty_percent(): void
    {
        $kpi = $this->kpi([
            'scoring_type' => 'threshold', 'max_marks' => 20,
            'target_value' => 100, 'threshold_percent' => 60,
        ]);

        $this->assertSame(0.0,  KpiPeriodService::targetScore($kpi, 59.99));
        $this->assertSame(20.0, KpiPeriodService::targetScore($kpi, 60));
    }

    /**
     * REGRESSION. A threshold KPI saved without a threshold_percent must not
     * score the entire department zero for a configuration slip — it falls
     * back to proportional scoring.
     */
    public function test_threshold_without_a_configured_gate_falls_back_to_proportional(): void
    {
        $kpi = $this->kpi([
            'scoring_type' => 'threshold', 'max_marks' => 10,
            'target_value' => 100, 'threshold_percent' => null,
        ]);

        $this->assertSame(5.0, KpiPeriodService::targetScore($kpi, 50));
    }

    // ── banded: tiered ───────────────────────────────────────────────────

    public function test_banded_scoring_awards_the_highest_band_that_is_met(): void
    {
        $kpi = $this->kpi([
            'scoring_type' => 'banded', 'max_marks' => 10, 'target_value' => 100,
            'bands' => [['min' => 90, 'marks' => 10], ['min' => 75, 'marks' => 7], ['min' => 60, 'marks' => 4]],
        ]);

        $this->assertSame(0.0,  KpiPeriodService::targetScore($kpi, 59), 'below every band');
        $this->assertSame(4.0,  KpiPeriodService::targetScore($kpi, 60), 'lower edge of the first band');
        $this->assertSame(4.0,  KpiPeriodService::targetScore($kpi, 74));
        $this->assertSame(7.0,  KpiPeriodService::targetScore($kpi, 75));
        $this->assertSame(10.0, KpiPeriodService::targetScore($kpi, 90));
        $this->assertSame(10.0, KpiPeriodService::targetScore($kpi, 130), 'top band is the ceiling');
    }

    /** Bands are authored by hand in the UI and arrive in any order. */
    public function test_banded_scoring_does_not_depend_on_the_order_bands_are_stored_in(): void
    {
        $ascending = $this->kpi([
            'scoring_type' => 'banded', 'max_marks' => 10, 'target_value' => 100,
            'bands' => [['min' => 60, 'marks' => 4], ['min' => 75, 'marks' => 7], ['min' => 90, 'marks' => 10]],
        ]);
        $shuffled = $this->kpi([
            'scoring_type' => 'banded', 'max_marks' => 10, 'target_value' => 100,
            'bands' => [['min' => 75, 'marks' => 7], ['min' => 90, 'marks' => 10], ['min' => 60, 'marks' => 4]],
        ]);

        foreach ([59, 60, 75, 90, 120] as $achieved) {
            $this->assertSame(
                KpiPeriodService::targetScore($ascending, $achieved),
                KpiPeriodService::targetScore($shuffled, $achieved),
                "band order changed the score at {$achieved}"
            );
        }
    }

    /**
     * REGRESSION. bands is a JSON column; when it arrives as a raw string
     * rather than an array the score must still be correct, not silently zero.
     */
    public function test_banded_scoring_accepts_bands_stored_as_a_json_string(): void
    {
        $kpi = $this->kpi([
            'scoring_type' => 'banded', 'max_marks' => 10, 'target_value' => 100,
            'bands' => json_encode([['min' => 90, 'marks' => 10], ['min' => 50, 'marks' => 3]]),
        ]);

        $this->assertSame(3.0,  KpiPeriodService::targetScore($kpi, 60));
        $this->assertSame(10.0, KpiPeriodService::targetScore($kpi, 95));
    }

    /**
     * REGRESSION. A banded KPI with no bands configured must not zero an
     * entire department; it degrades to proportional scoring.
     */
    public function test_banded_without_bands_falls_back_to_proportional(): void
    {
        $kpi = $this->kpi([
            'scoring_type' => 'banded', 'max_marks' => 10,
            'target_value' => 100, 'bands' => null,
        ]);

        $this->assertSame(5.0, KpiPeriodService::targetScore($kpi, 50));
    }

    // ── late policies ────────────────────────────────────────────────────

    public function test_late_policy_none_does_not_penalise(): void
    {
        $kpi = $this->kpi(['max_marks' => 10, 'late_policy' => 'none']);

        $this->assertSame(10.0, KpiPeriodService::lateScore(
            $kpi, Carbon::parse('2026-08-15'), Carbon::parse('2026-08-10')
        ));
    }

    public function test_late_policy_fixed_awards_the_configured_consolation_marks(): void
    {
        $kpi = $this->kpi(['max_marks' => 10, 'late_policy' => 'fixed', 'late_score' => 2]);

        $this->assertSame(2.0, KpiPeriodService::lateScore(
            $kpi, Carbon::parse('2026-08-15'), Carbon::parse('2026-08-10')
        ));
    }

    public function test_late_policy_percent_awards_a_share_of_max_marks(): void
    {
        $kpi = $this->kpi(['max_marks' => 10, 'late_policy' => 'percent', 'late_percent' => 50]);

        $this->assertSame(5.0, KpiPeriodService::lateScore(
            $kpi, Carbon::parse('2026-08-15'), Carbon::parse('2026-08-10')
        ));
    }

    public function test_per_day_policy_charges_only_days_beyond_the_grace_window(): void
    {
        $kpi = $this->kpi([
            'max_marks' => 10, 'late_policy' => 'per_day',
            'grace_days' => 2, 'per_day_penalty' => 1,
        ]);
        $due = Carbon::parse('2026-08-10');

        // 1 day late, inside a 2-day grace window: nothing is charged.
        $this->assertSame(10.0, KpiPeriodService::lateScore($kpi, Carbon::parse('2026-08-11'), $due));
        // 5 days late, 2 forgiven, 3 chargeable at 1 mark each.
        $this->assertSame(7.0,  KpiPeriodService::lateScore($kpi, Carbon::parse('2026-08-15'), $due));
    }

    public function test_per_day_penalty_never_produces_a_negative_score(): void
    {
        $kpi = $this->kpi([
            'max_marks' => 10, 'late_policy' => 'per_day',
            'grace_days' => 0, 'per_day_penalty' => 3,
        ]);

        // 30 days late would be -80 marks if the floor were missing.
        $this->assertSame(0.0, KpiPeriodService::lateScore(
            $kpi, Carbon::parse('2026-09-09'), Carbon::parse('2026-08-10')
        ));
    }

    /** An assignment created before late policies existed must still score. */
    public function test_missing_late_policy_defaults_to_fixed_behaviour(): void
    {
        $kpi = $this->kpi(['max_marks' => 10, 'late_policy' => null, 'late_score' => 3]);

        $this->assertSame(3.0, KpiPeriodService::lateScore(
            $kpi, Carbon::parse('2026-08-15'), Carbon::parse('2026-08-10')
        ));
    }
}
