<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeavePolicyProfile;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceDayStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * AttendanceDayStatus::resolve() is the single source of truth consolidating
 * what used to be four independently-drifted copies of the same decision
 * tree (admin Monthly Registry, admin Attendance Logs, employee My
 * Attendance calendar, employee My Attendance list). Every branch of the
 * original four is exercised here directly against the resolver, isolated
 * from any controller/view wiring.
 *
 * Weekday reference dates avoid Sundays/holidays already in this database.
 * 2026-06-15 is a Monday, 2026-06-13 is a Saturday (2nd Saturday of June).
 */
class AttendanceDayStatusTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Resolver',
            'email'      => 'qaresolver' . uniqid() . '@example.test',
            'code'       => 'QARS' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    private function attendance(User $user, string $date, ?string $inTime, ?string $outTime, ?LeaveRequest $leave = null): Attendance
    {
        $a = Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => $inTime ? "$date $inTime" : "$date 00:00:00",
            'check_out_time' => $outTime ? "$date $outTime" : null,
            'status' => 'Present',
            'leave_request_id' => $leave?->id,
        ]);
        return $a->fresh(['leaveRequest.leaveType']);
    }

    private function partDayHoliday(string $date, float $hoursOff = 1.5): Holiday
    {
        return Holiday::create([
            'name' => 'QA Concession', 'code' => 'QAC' . random_int(100000, 999999), 'date' => $date,
            'status' => 'active', 'holiday_type' => 'hours', 'hours_off' => $hoursOff,
        ]);
    }

    private function fullHoliday(string $date): Holiday
    {
        return Holiday::create([
            'name' => 'QA Full Holiday', 'code' => 'QAF' . random_int(100000, 999999), 'date' => $date,
            'status' => 'active', 'holiday_type' => 'full',
        ]);
    }

    private function leave(User $user, string $date, string $typeCode, array $overrides = []): LeaveRequest
    {
        $type = LeaveType::where('code', $typeCode)->firstOrFail();
        $lr = LeaveRequest::create(array_merge([
            'user_id' => $user->id, 'leave_type_id' => $type->id,
            'from_date' => $date, 'to_date' => $date,
            'status' => 'pending', 'user_notes' => 'resolver test',
        ], $overrides));
        $lr->updateQuietly(array_merge(['status' => 'approved'], $overrides['quiet'] ?? []));
        return $lr->fresh('leaveType');
    }

    // --- With an attendance record ------------------------------------

    public function test_worked_sunday_above_credit_bar_is_sp(): void
    {
        $user = $this->makeUser();
        $date = Carbon::parse('2026-06-14'); // Sunday
        $att = $this->attendance($user, '2026-06-14', '09:00:00', '14:00:00'); // 5h

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('sp', $r['code']);
    }

    public function test_worked_sunday_below_credit_bar_is_weekly_off(): void
    {
        $user = $this->makeUser();
        $date = Carbon::parse('2026-06-14');
        $att = $this->attendance($user, '2026-06-14', '09:00:00', '10:00:00'); // 1h

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('weekly_off', $r['code']);
    }

    public function test_worked_designated_off_saturday_is_sp(): void
    {
        $profile = LeavePolicyProfile::create(['name' => 'QA Sat Off ' . uniqid(), 'saturday_off_config' => ['2', '4'], 'status' => 'active']);
        $user = $this->makeUser();
        $user->update(['leave_policy_profile_id' => $profile->id]);
        $date = Carbon::parse('2026-06-13'); // 2nd Saturday
        $att = $this->attendance($user, '2026-06-13', '09:00:00', '14:00:00');

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('sp', $r['code']);
    }

    public function test_worked_full_holiday_above_credit_bar_is_hp(): void
    {
        $user = $this->makeUser();
        $holiday = $this->fullHoliday('2026-06-15');
        $date = Carbon::parse('2026-06-15');
        $att = $this->attendance($user, '2026-06-15', '09:00:00', '14:00:00');

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, $holiday);
        $this->assertEquals('hp', $r['code']);
    }

    public function test_unworked_full_holiday_with_attendance_row_is_holiday(): void
    {
        $user = $this->makeUser();
        $holiday = $this->fullHoliday('2026-06-15');
        $date = Carbon::parse('2026-06-15');
        $att = $this->attendance($user, '2026-06-15', null, null); // dummy row, no real punch

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, $holiday);
        $this->assertEquals('holiday', $r['code']);
    }

    public function test_part_day_holiday_worked_enough_to_clear_the_reduced_bar_is_present(): void
    {
        $user = $this->makeUser();
        $holiday = $this->partDayHoliday('2026-06-15', 1.5); // reqMins = 465-90 = 375
        $date = Carbon::parse('2026-06-15');
        $att = $this->attendance($user, '2026-06-15', '09:00:00', '15:30:00'); // 390 min

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, $holiday);
        $this->assertEquals('present', $r['code']);
        $this->assertEquals(375, $r['reqMins']);
    }

    public function test_part_day_holiday_with_no_punch_is_absent_not_holiday(): void
    {
        $user = $this->makeUser();
        $holiday = $this->partDayHoliday('2026-06-15');
        $date = Carbon::parse('2026-06-15');
        $att = $this->attendance($user, '2026-06-15', null, null);

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, $holiday);
        $this->assertEquals('absent', $r['code']);
    }

    public function test_wfh_leave_is_work_from_home_regardless_of_minutes(): void
    {
        $user = $this->makeUser();
        $leave = $this->leave($user, '2026-06-15', 'WFH');
        $date = Carbon::parse('2026-06-15');
        $att = $this->attendance($user, '2026-06-15', null, null, $leave);

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('work_from_home', $r['code']);
    }

    public function test_short_leave_worked_350_or_more_is_short_leave_present(): void
    {
        $user = $this->makeUser();
        $shl = LeaveType::where('code', 'SHL')->first() ?? LeaveType::where('is_short_leave', true)->firstOrFail();
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $shl->id,
            'from_date' => '2026-06-15', 'to_date' => '2026-06-15',
            'is_short_leave' => true, 'status' => 'pending', 'user_notes' => 'resolver short leave test',
        ]);
        $leave->updateQuietly(['status' => 'approved']);
        $date = Carbon::parse('2026-06-15');
        $att = $this->attendance($user, '2026-06-15', '09:00:00', '15:00:00', $leave->fresh('leaveType')); // 360 min

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('short-leave-present', $r['code']);
    }

    public function test_half_day_leave_paid_worked_enough_is_half_day_paid(): void
    {
        $user = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        \App\Models\LeaveBalance::create(['user_id' => $user->id, 'leave_type_id' => $alp->id, 'balance' => 2, 'used' => 0]);
        $hdl = LeaveType::where('code', 'HDL')->firstOrFail();
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $hdl->id,
            'from_date' => '2026-06-15', 'to_date' => '2026-06-15',
            'is_half_day' => true, 'half_day_session' => 'first_half',
            'status' => 'pending', 'user_notes' => 'resolver half day test',
        ]);
        $leave->update(['status' => 'approved']);
        $date = Carbon::parse('2026-06-15');
        $att = $this->attendance($user, '2026-06-15', '14:00:00', '18:00:00', $leave->fresh('leaveType')); // 240 min

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('half-day-paid', $r['code']);
        $this->assertTrue($r['isPaid']);
    }

    /**
     * An unpaid half-day whose worked half CCare recorded afterwards: the row
     * turns from 'unpaid_leave' into 'half-day', which used to read as paid.
     */
    private function unpaidHalfDayWorked(string $date): array
    {
        $user = $this->makeUser();
        $hdl = LeaveType::where('code', 'HDL')->firstOrFail();
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $hdl->id, 'from_date' => $date, 'to_date' => $date,
            'is_half_day' => true, 'half_day_session' => 'second_half', 'status' => 'pending', 'user_notes' => 'resolver unpaid half',
        ]);
        $leave->updateQuietly(['status' => 'approved', 'is_lwp' => true, 'lwp_days' => 0.5]);
        $att = $this->attendance($user, $date, '09:30:00', '14:00:00', $leave->fresh('leaveType'));
        $att->updateQuietly(['status' => 'half-day']);

        return [$user, $att->fresh(['leaveRequest.leaveType'])];
    }

    public function test_from_september_2026_an_unpaid_half_day_stays_unpaid_after_the_worked_half_is_recorded(): void
    {
        [$user, $att] = $this->unpaidHalfDayWorked('2026-09-29');

        $r = AttendanceDayStatus::resolve($user, Carbon::parse('2026-09-29'), $att, null, null);

        $this->assertEquals('half-day', $r['code'], 'half worked, half unpaid - not a full paid day');
        $this->assertFalse($r['isPaid']);
    }

    public function test_july_and_august_2026_keep_the_old_reading(): void
    {
        [$user, $att] = $this->unpaidHalfDayWorked('2026-08-24');

        $r = AttendanceDayStatus::resolve($user, Carbon::parse('2026-08-24'), $att, null, null);

        $this->assertEquals('half-day-paid', $r['code'], 'already paid out; left as it was');
    }

    /** A half-day leave approved with a dummy row (no punches) and nothing recorded for the other half. */
    private function halfDayNothingWorked(string $date, bool $unpaid = false, bool $withRow = true): array
    {
        $user = $this->makeUser();
        $hdl = LeaveType::where('code', 'HDL')->firstOrFail();
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $hdl->id, 'from_date' => $date, 'to_date' => $date,
            'is_half_day' => true, 'half_day_session' => 'first_half', 'status' => 'pending', 'user_notes' => 'resolver unworked half',
        ]);
        $leave->updateQuietly(['status' => 'approved'] + ($unpaid ? ['is_lwp' => true, 'lwp_days' => 0.5] : []));
        $leave = $leave->fresh('leaveType');
        $att = null;
        if ($withRow) {
            $att = $this->attendance($user, $date, null, null, $leave);
            $att->updateQuietly(['status' => 'half-Day']);
            $att = $att->fresh(['leaveRequest.leaveType']);
        }

        return AttendanceDayStatus::resolve($user, Carbon::parse($date), $att, $withRow ? null : $leave, null);
    }

    public function test_from_september_2026_a_paid_half_day_with_the_other_half_not_worked_pays_only_half(): void
    {
        $this->assertEquals('half-day', $this->halfDayNothingWorked('2026-09-26')['code']);
        $this->assertEquals('half-day', $this->halfDayNothingWorked('2026-09-26', withRow: false)['code'], 'no attendance row at all');
    }

    public function test_from_september_2026_an_unpaid_half_day_with_the_other_half_not_worked_pays_nothing(): void
    {
        $this->assertEquals('unpaid_leave', $this->halfDayNothingWorked('2026-09-26', unpaid: true)['code']);
    }

    public function test_the_unworked_half_rule_leaves_july_august_and_days_not_yet_over_alone(): void
    {
        $this->assertEquals('paid_leave', $this->halfDayNothingWorked('2026-08-24')['code'], 'already paid out');
        Carbon::setTestNow('2026-09-30 11:00:00');   // a Wednesday, so neither day below is a weekly off
        $this->assertEquals('paid_leave', $this->halfDayNothingWorked('2026-10-07')['code'], 'not worked yet');
        $this->assertEquals('paid_leave', $this->halfDayNothingWorked('2026-09-30')['code'], 'today: the worked half may still be recorded');
        Carbon::setTestNow();
    }

    public function test_punches_stored_the_other_way_round_still_count_as_worked(): void
    {
        $user = $this->makeUser();
        // The biometric report listed the evening punch as check-in (Esha Das, 24 Sep 2026).
        $swapped = $this->attendance($user, '2026-06-15', '17:57:00', '09:56:00');
        $this->assertEquals('present', AttendanceDayStatus::resolve($user, Carbon::parse('2026-06-15'), $swapped, null, null)['code']);

        // A night shift that ends after midnight, stored on the one date.
        $night = $this->attendance($user, '2026-06-16', '19:41:00', '05:51:00');
        $this->assertEquals('present', AttendanceDayStatus::resolve($user, Carbon::parse('2026-06-16'), $night, null, null)['code']);

        $this->assertSame(481, AttendanceDayStatus::workedMinutes(Carbon::parse('2026-06-15 17:57'), Carbon::parse('2026-06-15 09:56')));
    }

    public function test_an_absence_ccare_covered_from_the_balance_reads_as_paid(): void
    {
        $user = $this->makeUser();
        $att = Attendance::withoutEvents(fn () => Attendance::create(['user_id' => $user->id, 'check_in_time' => '2026-09-04 00:00:00',
            'status' => 'paid_leave', 'admin_reason' => 'Auto-adjusted against Earned Leave (Sunday Working) | Marked by CCare']));

        $r = AttendanceDayStatus::resolve($user, Carbon::parse('2026-09-04'), $att->fresh(), null, null);

        $this->assertEquals('paid_leave', $r['code']);
        $this->assertTrue($r['isPaid'], 'the calendar must not call a paid day "Unpaid Leave"');
    }

    public function test_full_day_paid_leave_with_a_dummy_attendance_row_is_paid_leave(): void
    {
        $user = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        \App\Models\LeaveBalance::create(['user_id' => $user->id, 'leave_type_id' => $alp->id, 'balance' => 2, 'used' => 0]);
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-06-15', 'to_date' => '2026-06-15',
            'status' => 'pending', 'user_notes' => 'resolver full day paid leave test',
        ]);
        $leave->update(['status' => 'approved']); // processApproval creates the Attendance row itself
        $date = Carbon::parse('2026-06-15');
        $att = Attendance::where('user_id', $user->id)->whereDate('check_in_time', '2026-06-15')->firstOrFail()
            ->load('leaveRequest.leaveType');

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('paid_leave', $r['code']);
    }

    public function test_ordinary_day_worked_full_is_present(): void
    {
        $user = $this->makeUser();
        $date = Carbon::parse('2026-06-15');
        $att = $this->attendance($user, '2026-06-15', '09:00:00', '18:00:00'); // 540 min

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('present', $r['code']);
    }

    public function test_ordinary_day_with_no_checkout_on_a_past_day_is_miss_punch(): void
    {
        $user = $this->makeUser();
        $date = Carbon::parse('2020-06-15'); // definitely in the past
        $att = $this->attendance($user, '2020-06-15', '09:00:00', null);

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('miss-punch', $r['code']);
    }

    public function test_manual_admin_adjustment_passes_through_the_stored_status(): void
    {
        $user = $this->makeUser();
        $date = Carbon::parse('2026-06-15');
        $att = Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-15 09:00:00',
            'check_out_time' => '2026-06-15 10:00:00', // would otherwise be 'absent'
            'status' => 'Late',
            'admin_reason' => 'HR corrected punch',
        ])->fresh(['leaveRequest.leaveType']);

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('late', $r['code']);
    }

    public function test_admin_adjustment_does_not_override_sunday_credit_precedence(): void
    {
        $user = $this->makeUser();
        $date = Carbon::parse('2026-06-14'); // Sunday
        $att = Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-14 09:00:00',
            'check_out_time' => '2026-06-14 14:00:00', // 5h, clears the 240-min SP bar
            'status' => 'Present',
            'admin_reason' => 'HR corrected punch',
        ])->fresh(['leaveRequest.leaveType']);

        $r = AttendanceDayStatus::resolve($user, $date, $att, null, null);
        $this->assertEquals('sp', $r['code'], 'Sunday/holiday credit precedence must win even over a manual admin adjustment');
    }

    // --- No attendance record at all ------------------------------------

    public function test_no_attendance_short_leave_is_absent(): void
    {
        $user = $this->makeUser();
        $shl = LeaveType::where('is_short_leave', true)->firstOrFail();
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $shl->id,
            'from_date' => '2026-06-15', 'to_date' => '2026-06-15',
            'is_short_leave' => true, 'status' => 'pending', 'user_notes' => 'resolver no-attendance short leave test',
        ]);
        $leave->updateQuietly(['status' => 'approved']);
        $date = Carbon::parse('2026-06-15');

        $r = AttendanceDayStatus::resolve($user, $date, null, $leave->fresh('leaveType'), null);
        $this->assertEquals('absent', $r['code']);
    }

    public function test_no_attendance_full_day_unpaid_leave_is_unpaid_leave(): void
    {
        $user = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-06-15', 'to_date' => '2026-06-15',
            'status' => 'pending', 'user_notes' => 'resolver no-attendance unpaid leave test',
        ]);
        $leave->updateQuietly(['status' => 'approved', 'is_lwp' => true, 'lwp_days' => 1]);
        $date = Carbon::parse('2026-06-15');

        $r = AttendanceDayStatus::resolve($user, $date, null, $leave->fresh('leaveType'), null);
        $this->assertEquals('unpaid_leave', $r['code']);
    }

    /**
     * Reversed 2026-10-24 (owner's call, re: Sachin Sharma EMP-004's Oct 2-5
     * request where Oct 2 is Gandhi Jayanti): a full-day holiday now wins over
     * a coinciding leave day with no attendance row, not the other way round.
     * Nobody was ever going to work a declared holiday regardless of the leave
     * applied over it, and nothing is deducted from balance for it either way
     * (calculateWorkingDays() already skips holidays) - so "Holiday" is the
     * correct label, not "Paid Leave". The Sunday/weekly-off precedence below
     * is untouched; this applies to a genuine full holiday only.
     */
    public function test_no_attendance_full_holiday_takes_priority_over_a_coinciding_leave(): void
    {
        $user = $this->makeUser();
        $holiday = $this->fullHoliday('2026-06-15');
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        \App\Models\LeaveBalance::create(['user_id' => $user->id, 'leave_type_id' => $alp->id, 'balance' => 2, 'used' => 0]);
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-06-15', 'to_date' => '2026-06-15',
            'status' => 'pending', 'user_notes' => 'resolver leave-vs-holiday precedence test',
        ]);
        $leave->updateQuietly(['status' => 'approved']); // quiet: no auto-created Attendance row, so this exercises the true no-attendance branch
        $date = Carbon::parse('2026-06-15');

        $r = AttendanceDayStatus::resolve($user, $date, null, $leave->fresh('leaveType'), $holiday);
        $this->assertEquals('holiday', $r['code'], 'a declared holiday wins over a coinciding leave day with no attendance row');
    }

    public function test_no_attendance_no_leave_full_holiday_is_holiday(): void
    {
        $user = $this->makeUser();
        $holiday = $this->fullHoliday('2026-06-15');
        $date = Carbon::parse('2026-06-15');

        $r = AttendanceDayStatus::resolve($user, $date, null, null, $holiday);
        $this->assertEquals('holiday', $r['code']);
    }

    public function test_no_attendance_no_leave_sunday_is_weekly_off(): void
    {
        $user = $this->makeUser();
        $date = Carbon::parse('2026-06-14');

        $r = AttendanceDayStatus::resolve($user, $date, null, null, null);
        $this->assertEquals('weekly_off', $r['code']);
    }

    public function test_no_attendance_no_leave_future_working_day_is_scheduled(): void
    {
        $user = $this->makeUser();
        $date = Carbon::now()->addDays(10)->startOfDay();
        if ($date->isSunday()) { $date->addDay(); }

        $r = AttendanceDayStatus::resolve($user, $date, null, null, null);
        $this->assertEquals('scheduled', $r['code']);
    }

    public function test_no_attendance_no_leave_past_working_day_is_absent(): void
    {
        $user = $this->makeUser();
        $date = Carbon::parse('2020-06-15'); // Monday, definitely past
        $this->assertFalse($date->isSunday());

        $r = AttendanceDayStatus::resolve($user, $date, null, null, null);
        $this->assertEquals('absent', $r['code']);
    }

    public function test_no_attendance_no_leave_today_is_today(): void
    {
        $user = $this->makeUser();
        $date = Carbon::now()->startOfDay();
        if ($date->isSunday()) {
            $this->markTestSkipped('today happens to be a Sunday in this run');
        }

        $r = AttendanceDayStatus::resolve($user, $date, null, null, null);
        $this->assertEquals('today', $r['code']);
    }

    // --- Batch holiday map helper ---------------------------------------

    public function test_holiday_maps_pick_the_most_generous_applicable_part_day_concession(): void
    {
        $user = $this->makeUser();
        Holiday::create(['name' => 'Small concession', 'code' => 'QAS' . random_int(1, 999999), 'date' => '2026-06-15', 'status' => 'active', 'holiday_type' => 'hours', 'hours_off' => 1]);
        $bigger = Holiday::create(['name' => 'Bigger concession', 'code' => 'QAB' . random_int(1, 999999), 'date' => '2026-06-15', 'status' => 'active', 'holiday_type' => 'hours', 'hours_off' => 2]);

        $maps = AttendanceDayStatus::holidayMapsForMonth(6, 2026);
        $picked = AttendanceDayStatus::holidayForUserOnDate($maps, $user, $user->site_id, '2026-06-15');

        $this->assertNotNull($picked);
        $this->assertEquals($bigger->id, $picked->id);
    }

    public function test_holiday_maps_prefer_a_full_day_holiday_over_any_part_day_concession(): void
    {
        $user = $this->makeUser();
        Holiday::create(['name' => 'Concession', 'code' => 'QAC' . random_int(1, 999999), 'date' => '2026-06-16', 'status' => 'active', 'holiday_type' => 'hours', 'hours_off' => 2]);
        $full = Holiday::create(['name' => 'Full', 'code' => 'QAFU' . random_int(1, 999999), 'date' => '2026-06-16', 'status' => 'active', 'holiday_type' => 'full']);

        $maps = AttendanceDayStatus::holidayMapsForMonth(6, 2026);
        $picked = AttendanceDayStatus::holidayForUserOnDate($maps, $user, $user->site_id, '2026-06-16');

        $this->assertNotNull($picked);
        $this->assertEquals($full->id, $picked->id);
        $this->assertTrue($picked->isFullDay());
    }

    /**
     * A department-scoped holiday must reach only that department, matching
     * Holiday::scopeForUser() - the batched lookup used to group only by
     * site, silently ignoring department_id, while the single-row lookup
     * (holidayForUserOnDateSingle, used by indexAjax) already respected it -
     * a department holiday would have applied everywhere except the admin
     * Attendance Logs page.
     */
    public function test_holiday_maps_respect_department_scoping(): void
    {
        $dept = \App\Models\Department::create(['name' => 'QA Dept ' . uniqid(), 'code' => 'QAD' . random_int(1, 999999), 'status' => 'active']);
        $inDept = $this->makeUser();
        $inDept->update(['department_id' => $dept->id]);
        $outsideDept = $this->makeUser();

        Holiday::create([
            'name' => 'QA Dept Holiday', 'code' => 'QADH' . random_int(1, 999999), 'date' => '2026-06-15',
            'status' => 'active', 'holiday_type' => 'full', 'department_id' => $dept->id,
        ]);

        $maps = AttendanceDayStatus::holidayMapsForMonth(6, 2026);

        $this->assertNotNull(AttendanceDayStatus::holidayForUserOnDate($maps, $inDept, $inDept->site_id, '2026-06-15'), 'a user in the target department must see the holiday');
        $this->assertNull(AttendanceDayStatus::holidayForUserOnDate($maps, $outsideDept, $outsideDept->site_id, '2026-06-15'), 'a user outside the target department must not see the holiday');
    }

    // --- Single-row (non-batched) holiday lookup, used by indexAjax -----

    public function test_single_lookup_finds_a_full_day_holiday(): void
    {
        $user = $this->makeUser();
        $full = $this->fullHoliday('2026-06-15');

        $picked = AttendanceDayStatus::holidayForUserOnDateSingle($user, '2026-06-15');

        $this->assertNotNull($picked);
        $this->assertEquals($full->id, $picked->id);
    }

    public function test_single_lookup_finds_the_most_generous_part_day_concession(): void
    {
        $user = $this->makeUser();
        Holiday::create(['name' => 'Small', 'code' => 'QASM' . random_int(1, 999999), 'date' => '2026-06-15', 'status' => 'active', 'holiday_type' => 'hours', 'hours_off' => 1]);
        $bigger = Holiday::create(['name' => 'Bigger', 'code' => 'QABG' . random_int(1, 999999), 'date' => '2026-06-15', 'status' => 'active', 'holiday_type' => 'hours', 'hours_off' => 2]);

        $picked = AttendanceDayStatus::holidayForUserOnDateSingle($user, '2026-06-15');

        $this->assertNotNull($picked);
        $this->assertEquals($bigger->id, $picked->id);
    }

    public function test_single_lookup_returns_null_when_no_holiday_applies(): void
    {
        $user = $this->makeUser();

        $picked = AttendanceDayStatus::holidayForUserOnDateSingle($user, '2026-06-15');

        $this->assertNull($picked);
    }

    public function test_single_lookup_ignores_an_inactive_holiday(): void
    {
        $user = $this->makeUser();
        Holiday::create(['name' => 'Cancelled', 'code' => 'QACX' . random_int(1, 999999), 'date' => '2026-06-15', 'status' => 'inactive', 'holiday_type' => 'full']);

        $picked = AttendanceDayStatus::holidayForUserOnDateSingle($user, '2026-06-15');

        $this->assertNull($picked);
    }
}
