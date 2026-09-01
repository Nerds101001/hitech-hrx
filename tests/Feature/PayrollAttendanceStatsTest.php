<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeavePolicyProfile;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use Tests\TestCase;

/**
 * PayrollService::calculateAttendanceStats() - the function deciding worked
 * vs. absent days for pay - used to reimplement day-status classification on
 * its own with a flat 465-minute cutoff, completely independent of
 * AttendanceDayStatus (the resolver every attendance-facing page already
 * shares). That independence produced two confirmed real bugs, found by
 * replaying real 2026 attendance data through the actual method:
 *
 *  - A partially-worked designated off-Saturday (or Sunday, or holiday) -
 *    which only needs 240 minutes to earn full SP/HP credit on every
 *    attendance page - was silently docked half a day's pay under the flat
 *    465-minute check. Confirmed against 11 real rows across 8 real
 *    employees in Jan-Aug 2026.
 *  - A real check-in with no check-out and no admin correction (a "miss
 *    punch") was paid as a full present day - though every attendance page
 *    already treats it as absent until HR corrects the punch. Confirmed
 *    against 65 real rows across ~20 real employees in the same period.
 *
 * Both are fixed by routing classification through AttendanceDayStatus and
 * applying this service's own pay-consequence rules on top of its code,
 * instead of re-deriving the classification. This file exercises that
 * mapping directly via the private method (Reflection - it has no public
 * seam and payroll's own consequence rules, distinct from the day-status
 * question, belong tested here rather than duplicated into the resolver's
 * own suite).
 *
 * 2026-06-15 (Monday) / 2026-06-13 (2nd Saturday) avoid Sundays/holidays
 * already in this database, matching the convention used elsewhere this
 * session.
 */
class PayrollAttendanceStatsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Payroll',
            'email'      => 'qapayroll' . uniqid() . '@example.test',
            'code'       => 'QAPR' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    private function stats(User $user, string $startDate, string $endDate): array
    {
        $svc = new PayrollService();
        $ref = new ReflectionMethod($svc, 'calculateAttendanceStats');
        $ref->setAccessible(true);
        return $ref->invoke($svc, $user, Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay());
    }

    // --- The two confirmed bugs -----------------------------------------

    public function test_a_partially_worked_off_saturday_earns_full_credit_not_a_half_day_docking(): void
    {
        $profile = LeavePolicyProfile::create(['name' => 'QA Sat Off ' . uniqid(), 'saturday_off_config' => ['2', '4'], 'status' => 'active']);
        $user = $this->makeUser();
        $user->update(['leave_policy_profile_id' => $profile->id]);

        Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-13 09:00:00',
            'check_out_time' => '2026-06-13 13:00:00', // 240 min: clears the SP bar, well under the old flat 465
            'status' => 'Present',
        ]);

        $stats = $this->stats($user, '2026-06-13', '2026-06-13');

        $this->assertEquals(1.0, $stats['worked_days'], 'a credited SP day must count as a full worked day');
        $this->assertEquals(0.0, $stats['absents'], 'a credited SP day must never dock any pay');
    }

    public function test_an_uncorrected_miss_punch_counts_as_absent_not_present(): void
    {
        $user = $this->makeUser();
        Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2020-06-15 09:00:00', // definitely in the past
            'check_out_time' => null,
            'status' => 'Present',
        ]);

        $stats = $this->stats($user, '2020-06-15', '2020-06-15');

        $this->assertEquals(0.0, $stats['worked_days'], 'an uncorrected miss-punch must not be paid as a worked day');
        $this->assertEquals(1.5, $stats['absents'], 'no leave on file - the unexplained-absence penalty applies, same as a plain Absent');
    }

    public function test_a_genuine_unexplained_absence_still_gets_the_1_5_day_penalty(): void
    {
        $user = $this->makeUser();
        Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-15 09:00:00',
            'check_out_time' => '2026-06-15 09:10:00', // 10 min - nowhere near any credit bar, no leave applied
            'status' => 'Present',
        ]);

        $stats = $this->stats($user, '2026-06-15', '2026-06-15');

        $this->assertEquals(1.5, $stats['absents'], 'no leave at all on an ordinary working day is still the unexplained-absence rate');
    }

    // --- Regression guards: everything that was already correct ---------

    public function test_an_ordinary_full_day_present_is_unaffected(): void
    {
        $user = $this->makeUser();
        Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-15 09:00:00',
            'check_out_time' => '2026-06-15 18:00:00',
            'status' => 'Present',
        ]);

        $stats = $this->stats($user, '2026-06-15', '2026-06-15');

        $this->assertEquals(1.0, $stats['worked_days']);
        $this->assertEquals(0.0, $stats['absents']);
    }

    public function test_an_ordinary_half_day_is_unaffected(): void
    {
        $user = $this->makeUser();
        Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-15 09:00:00',
            'check_out_time' => '2026-06-15 13:00:00', // 240 min, ordinary day (no leave, not a day off)
            'status' => 'Present',
        ]);

        $stats = $this->stats($user, '2026-06-15', '2026-06-15');

        $this->assertEquals(0.5, $stats['worked_days']);
        $this->assertEquals(0.5, $stats['absents']);
    }

    public function test_wfh_still_pays_exactly_half_a_day_regardless_of_balance(): void
    {
        $user = $this->makeUser();
        $wfh = LeaveType::where('code', 'WFH')->firstOrFail();
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $wfh->id,
            // WFH is only ever handled as a half-day leave by processApproval()
            // (a full-day WFH request falls through to the generic 'leave'
            // status instead of 'work_from_home').
            'is_half_day' => true, 'half_day_session' => 'first_half',
            'from_date' => '2026-06-15', 'to_date' => '2026-06-15',
            'status' => 'pending', 'user_notes' => 'payroll wfh test',
        ]);
        $leave->update(['status' => 'approved']); // real approval - creates its own Attendance row with status=work_from_home

        $stats = $this->stats($user, '2026-06-15', '2026-06-15');

        $this->assertEquals(0.5, $stats['worked_days']);
        $this->assertEquals(0.5, $stats['absents']);
    }

    public function test_a_full_day_paid_leave_with_attendance_record_pays_in_full(): void
    {
        $user = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create(['user_id' => $user->id, 'leave_type_id' => $alp->id, 'balance' => 2.0, 'used' => 0]);
        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-06-15', 'to_date' => '2026-06-15',
            'status' => 'pending', 'user_notes' => 'payroll full paid leave test',
        ]);
        $leave->update(['status' => 'approved']); // creates the Attendance row itself

        $stats = $this->stats($user, '2026-06-15', '2026-06-15');

        $this->assertEquals(1.0, $stats['worked_days']);
        $this->assertEquals(0.0, $stats['absents']);
    }

    public function test_a_half_day_paid_leave_that_was_worked_enough_pays_in_full(): void
    {
        $user = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create(['user_id' => $user->id, 'leave_type_id' => $alp->id, 'balance' => 2.0, 'used' => 0]);
        $hdl = LeaveType::where('code', 'HDL')->firstOrFail();

        // The real punch must exist BEFORE approval: processApproval() finds
        // any existing Attendance row for the date and attaches the leave to
        // it, but creates a fresh (dummy) row of its own if none exists yet -
        // approving first would leave two rows for the same day.
        Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-15 14:00:00',
            'check_out_time' => '2026-06-15 18:00:00', // 240 min, clears the half-day credit bar
            'status' => 'Present',
        ]);

        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $hdl->id,
            'from_date' => '2026-06-15', 'to_date' => '2026-06-15',
            'is_half_day' => true, 'half_day_session' => 'first_half',
            'status' => 'pending', 'user_notes' => 'payroll half-day-paid test',
        ]);
        $leave->update(['status' => 'approved']);

        $stats = $this->stats($user, '2026-06-15', '2026-06-15');

        $this->assertEquals(1.0, $stats['worked_days'], 'half worked, half covered by a paid leave the balance covers - a full paid day');
        $this->assertEquals(0.0, $stats['absents']);
    }

    public function test_a_worked_sunday_is_never_penalized_even_if_marked_absent_by_mistake(): void
    {
        // A dummy Attendance row dated on a Sunday with a raw 'Absent' status
        // (e.g. from a stray import) must never cost a day's pay - Sundays
        // are not part of expectedWorkingDays at all.
        $user = $this->makeUser();
        Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-14 09:00:00', // Sunday
            'check_out_time' => '2026-06-14 14:00:00', // 300 min, clears the SP bar
            'status' => 'Absent',
        ]);

        $stats = $this->stats($user, '2026-06-14', '2026-06-14');

        $this->assertEquals(0.0, $stats['absents'], 'Sunday/holiday credit precedence must win even over a stray raw Absent status');
        $this->assertEquals(1.0, $stats['worked_days']);
    }

    public function test_holidays_and_weekends_are_still_excluded_from_expected_working_days(): void
    {
        $user = $this->makeUser();
        Holiday::create([
            'name' => 'QA Full Holiday', 'code' => 'QAFH' . random_int(100000, 999999), 'date' => '2026-06-17',
            'status' => 'active', 'holiday_type' => 'full',
        ]);

        // A full week: Mon(15) .. Sun(21), with a full holiday on Wed(17).
        $stats = $this->stats($user, '2026-06-15', '2026-06-21');

        // Working days = 7 - 1 holiday - 1 Sunday = 5.
        $this->assertEquals(5, $stats['expected_days']);
        $this->assertEquals(1, $stats['holidays']);
        $this->assertEquals(1, $stats['weekends']);
    }
}
