<?php

namespace Tests\Feature;

use App\Models\KpiAssignment;
use App\Models\KpiCheck;
use App\Models\KpiEntry;
use App\Models\User;
use App\Services\KpiEntryService;
use App\Services\KpiPeriodService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A KPI figure does not always arrive inside the month it describes.
 *
 * The sales report stays open to the 5th and gets reopened for weeks; a data
 * owner fills last month's call hours this month; a correction lands later
 * still. Every write path used to resolve its check from TODAY, so a figure
 * reported for August was filed against September -- scoring the month nobody
 * had touched and leaving August marked "missed" with the number sitting
 * unused in kpi_entries. One salesperson's August scorecard read 20.4% when
 * the source tables said 29.1%.
 *
 * These tests pin the period a figure lands in, which is the part a person's
 * appraisal depends on.
 */
class KpiBackdatedPeriodTest extends TestCase
{
    use DatabaseTransactions;

    private User $rep;
    private Carbon $lastMonth;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $department = \App\Models\Department::create([
            'name' => 'ZZ QA Dept ' . uniqid(),
            'code' => 'ZZQ' . random_int(1000, 9999),
        ]);

        $this->rep = User::create([
            'first_name' => 'QA', 'last_name' => 'Backdate',
            'email'    => 'qa' . uniqid() . '@example.test',
            'code'     => 'QA' . random_int(100000, 999999),
            'password' => \Illuminate\Support\Facades\Hash::make('secret-not-used'),
            'status'   => 'active',
            'department_id' => $department->id,
        ]);
        $this->lastMonth = Carbon::today()->startOfMonth()->subMonthNoOverflow();
    }

    private function assignment(array $overrides = []): KpiAssignment
    {
        // $overrides first: array union keeps the LEFT operand's value.
        return KpiAssignment::create($overrides + [
            'user_id' => $this->rep->id,
            'title' => 'QA Calls ' . uniqid(),
            'category' => 'KPI',
            'frequency' => 'monthly',
            'scoring_type' => 'target',
            'target_value' => 10,
            'max_marks' => 10,
            'data_source' => 'manual',
            'is_active' => true,
        ]);
    }

    /** The bug itself: last month's figure must land on last month's check. */
    public function test_a_figure_filed_for_last_month_scores_last_month(): void
    {
        $a = $this->assignment();

        KpiEntryService::fillForTeam($a, 8.0, $this->rep->id, null, $this->lastMonth->copy());

        $entry = KpiEntry::where('kpi_assignment_id', $a->id)->sole();
        $this->assertSame($this->lastMonth->toDateString(), Carbon::parse($entry->period_start)->toDateString());

        $last = KpiCheck::where('kpi_assignment_id', $a->id)
            ->whereDate('period_start', $this->lastMonth->toDateString())->first();
        $this->assertNotNull($last, 'last month has no check row');
        $this->assertEquals(8.0, (float) $last->achieved_value);
        $this->assertSame((int) $last->id, (int) $entry->kpi_check_id,
            'the entry is attached to a different month\'s check');
        $this->assertEquals(8.0, (float) $last->score, 'score should be 8 of 10 marks');

        // This month must be untouched -- the old code wrote a 0 here.
        $thisMonth = KpiCheck::where('kpi_assignment_id', $a->id)
            ->whereDate('period_start', Carbon::today()->startOfMonth()->toDateString())->first();
        $this->assertTrue($thisMonth === null || $thisMonth->achieved_value === null,
            'filling last month must not write a figure into this month');
    }

    /** A number that arrives after the month closed reopens the check. */
    public function test_a_late_figure_revives_a_missed_check(): void
    {
        $a = $this->assignment();

        // Let the engine close last month as missed, exactly as the nightly run does.
        KpiPeriodService::sync($a, $this->lastMonth->copy());
        KpiPeriodService::sync($a);
        $missed = KpiCheck::where('kpi_assignment_id', $a->id)
            ->whereDate('period_start', $this->lastMonth->toDateString())->first();
        $this->assertSame('missed', $missed->status, 'setup: last month should start out missed');

        KpiEntryService::fillForTeam($a, 6.0, $this->rep->id, null, $this->lastMonth->copy());

        $missed->refresh();
        $this->assertSame('completed_late', $missed->status);
        $this->assertEquals(6.0, (float) $missed->achieved_value);
        $this->assertEquals(6.0, (float) $missed->score);
    }

    /** sync() must not close the very period it was asked to open. */
    public function test_syncing_a_past_period_does_not_mark_it_missed(): void
    {
        $a = $this->assignment();

        $check = KpiPeriodService::sync($a, $this->lastMonth->copy());

        $this->assertSame('pending', $check->status,
            'the period just resolved was closed as missed before the caller could write to it');
    }

    /** Clearing a figure must recalculate the month it belonged to. */
    public function test_clearing_a_backdated_figure_clears_that_month(): void
    {
        $a = $this->assignment();
        KpiEntryService::fillForTeam($a, 8.0, $this->rep->id, null, $this->lastMonth->copy());

        KpiEntryService::fillForTeam($a, null, $this->rep->id, null, $this->lastMonth->copy());

        $this->assertSame(0, KpiEntry::where('kpi_assignment_id', $a->id)->count());
        $last = KpiCheck::where('kpi_assignment_id', $a->id)
            ->whereDate('period_start', $this->lastMonth->toDateString())->first();
        $this->assertEquals(0.0, (float) $last->achieved_value);
        $this->assertEquals(0.0, (float) $last->score);
    }

    /** The command can re-score a month that has already closed. */
    public function test_auto_score_can_rewrite_a_closed_month(): void
    {
        $a = $this->assignment([
            'data_source' => 'auto:attendance_percent',
            'target_value' => 100,
            'max_marks' => 10,
        ]);
        // Assigned before the month it is being scored for, as a real one would be.
        $a->forceFill(['created_at' => $this->lastMonth->copy()->subMonthNoOverflow()])->save();

        // Something for the source to measure: one day present in that month.
        \Illuminate\Support\Facades\DB::table('attendances')->insert([
            'user_id' => $this->rep->id,
            'status' => 'present',
            'check_in_time' => $this->lastMonth->copy()->addDays(14)->setTime(9, 30),
            'check_out_time' => $this->lastMonth->copy()->addDays(14)->setTime(18, 30),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // A stale figure, as if the month had been scored once and left.
        $check = KpiPeriodService::sync($a, $this->lastMonth->copy());
        $check->update(['achieved_value' => 1, 'score' => 0.1, 'status' => 'missed']);

        $this->artisan('kpi:auto-score', [
            '--user' => $this->rep->id,
            '--as-of' => $this->lastMonth->format('Y-m'),
        ])->assertSuccessful();

        $check->refresh();
        $this->assertNotEquals(1.0, (float) $check->achieved_value,
            'the closed month was not re-scored');
        // An auto figure has no human deadline: re-reading it later is not lateness.
        $this->assertSame('completed_on_time', $check->status,
            're-scoring a closed month stamped it late');
    }

    /**
     * A re-score must not invent months. The scorecard shows any period that
     * has a check row, so scoring a period that closed before the KPI was
     * assigned puts a KPI on a sheet nobody was working to -- and its marks
     * into that month's denominator. A first run of --months=6 created 1,228
     * such rows for 50 people before this guard existed.
     */
    public function test_a_period_that_closed_before_the_kpi_existed_is_skipped(): void
    {
        $a = $this->assignment([
            'data_source' => 'auto:attendance_percent',
            'target_value' => 100,
        ]);
        $a->forceFill(['created_at' => Carbon::today()->startOfMonth()])->save();

        // Something for the source to measure this month -- with no attendance
        // rows at all it returns null (the person is not tracked) and no check
        // would be written for any period, which would pass this test blindly.
        \Illuminate\Support\Facades\DB::table('attendances')->insert([
            'user_id' => $this->rep->id,
            'status' => 'present',
            'check_in_time' => Carbon::today()->startOfMonth()->setTime(9, 30),
            'check_out_time' => Carbon::today()->startOfMonth()->setTime(18, 30),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('kpi:auto-score', [
            '--user' => $this->rep->id,
            '--months' => 6,
        ])->assertSuccessful();

        $earliest = KpiCheck::where('kpi_assignment_id', $a->id)->min('period_start');
        $this->assertSame(Carbon::today()->startOfMonth()->toDateString(), $earliest,
            'a period that ended before the KPI was assigned was scored anyway');
    }

    /**
     * A monthly April and a fiscal year both START on 1 April. Keying a check
     * on period_start alone let an April pass adopt the year-to-date row and
     * overwrite its running total with one month's figure -- it did that to 33
     * live rows, moving 258 marks across 24 people before this was found.
     */
    public function test_a_monthly_period_does_not_adopt_a_fiscal_year_check(): void
    {
        $a = $this->assignment(['frequency' => 'monthly']);
        $fyStart = Carbon::create(Carbon::today()->month >= 4 ? Carbon::today()->year : Carbon::today()->year - 1, 4, 1);

        // A year-to-date row, as the rule book left behind when these KPIs
        // were switched from yearly to monthly.
        $yearly = KpiCheck::create([
            'kpi_assignment_id' => $a->id,
            'user_id' => $this->rep->id,
            'period_start' => $fyStart->toDateString(),
            'period_end' => $fyStart->copy()->addYear()->subDay()->toDateString(),
            'due_date' => $fyStart->copy()->addYear()->subDay()->toDateString(),
            'status' => 'completed_on_time',
            'achieved_value' => 40.72,
            'score' => 8,
        ]);

        // kpi_checks is unique on (assignment, period_start), so April cannot
        // get its own row -- the write has to be refused instead.
        KpiPeriodService::recordAchievement($a, 112.09, null, null, $fyStart->copy()->addDays(10));

        $yearly->refresh();
        $this->assertEquals(40.72, (float) $yearly->achieved_value,
            'one month\'s figure overwrote the year-to-date total');
        $this->assertEquals(8.0, (float) $yearly->score, 'the year-to-date score was rewritten');
    }

    /**
     * No attendance record at all scores zero, not "unmeasured".
     *
     * The KPI cannot tell missing paperwork from a month of absence, and this
     * is the side it was asked to err on: an unrecorded month counts as an
     * unattended one. Pinned in a test because it is a policy choice rather
     * than an obvious one -- the rest of this service returns null when it has
     * nothing to measure.
     */
    public function test_no_attendance_record_at_all_scores_zero(): void
    {
        $a = $this->assignment([
            'data_source' => 'auto:attendance_percent',
            'target_value' => 100,
            'max_marks' => 10,
        ]);
        // Last month, which is over: "this month" has no finished working day on the 1st (today
        // itself is never scored), so the test failed on the first of every month.
        $from = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $this->assertSame(
            0.0,
            \App\Services\KpiAutoSourceService::compute($a, $from, $to),
            'a month with no attendance record must score zero'
        );

        \Illuminate\Support\Facades\DB::table('attendances')->insert([
            'user_id' => $this->rep->id, 'status' => 'present',
            'check_in_time' => $from->copy()->setTime(9, 30),
            'check_out_time' => $from->copy()->setTime(18, 30),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertGreaterThan(
            0.0,
            \App\Services\KpiAutoSourceService::compute($a, $from, $to),
            'a day attended must lift the figure off zero'
        );
    }

    /** --dry changes nothing. */
    public function test_dry_run_writes_nothing(): void
    {
        $a = $this->assignment([
            'data_source' => 'auto:attendance_percent',
            'target_value' => 100,
        ]);
        $before = KpiCheck::where('kpi_assignment_id', $a->id)->count();

        $this->artisan('kpi:auto-score', [
            '--user' => $this->rep->id,
            '--as-of' => $this->lastMonth->format('Y-m'),
            '--months' => 3,
            '--dry' => true,
        ])->assertSuccessful();

        $this->assertSame($before, KpiCheck::where('kpi_assignment_id', $a->id)->count());
    }
}
