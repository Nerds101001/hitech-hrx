<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeavePolicyProfile;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AttendanceController::registryAjax() - the Monthly Registry grid's P/A/OFF/L
 * summary columns. A half-day worked plus a paid half-day leave used to count
 * as a full Present (+1) on top of also crediting 0.5 to leaves - one day
 * contributing 1.5 of "value" instead of 1.0. Unpaid leave was lumped into the
 * same 'leaves' bucket as paid leave, with no way to see paid vs unpaid
 * separately. Reported live, alongside the same badge looking identical to an
 * ordinary full day present (fixed separately, this file covers the numbers).
 *
 * June 10 2026 (Wednesday) is used because it does not coincide with any
 * holiday in this database (checked against the full imported holiday list).
 */
class AttendanceRegistryHalfDayTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(?string $role = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Registry',
            'email'      => 'qareg' . uniqid() . '@example.test',
            'code'       => 'QARG' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    /** Every other day in the month gets a real Present punch, so the one day under test is the only source of Absent/NPL/Leave noise in the row totals. */
    private function fillMonthPresent(User $employee, int $year, int $month, string $exceptDate): void
    {
        $days = \Carbon\Carbon::create($year, $month, 1)->daysInMonth;
        for ($d = 1; $d <= $days; $d++) {
            $date = \Carbon\Carbon::create($year, $month, $d)->toDateString();
            if ($date === $exceptDate) continue;
            Attendance::create([
                'user_id' => $employee->id,
                'check_in_time' => $date . ' 09:00:00',
                'check_out_time' => $date . ' 18:00:00',
                'status' => 'Present',
            ]);
        }
    }

    private function callRegistry(array $params): array
    {
        $admin = $this->makeUser('admin');
        Auth::login($admin);
        $response = app(\App\Http\Controllers\tenant\AttendanceController::class)
            ->registryAjax(Request::create('/attendance/registryAjax', 'GET', $params));
        return json_decode($response->getContent(), true);
    }

    public function test_a_worked_half_day_with_paid_leave_counts_half_present_and_half_leave(): void
    {
        $employee = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        $hdl = LeaveType::where('code', 'HDL')->firstOrFail();

        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => $alp->id, 'balance' => 2.0, 'used' => 0]);

        $leave = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $hdl->id,
            'from_date' => '2026-06-10', 'to_date' => '2026-06-10',
            'is_half_day' => true, 'half_day_session' => 'first_half',
            'status' => 'pending', 'user_notes' => 'registry half-day test',
        ]);
        $leave->update(['status' => 'approved']); // triggers processApproval, sets is_lwp correctly

        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-10 14:00:00',
            'check_out_time' => '2026-06-10 18:00:00', // 4h - clears the 240-minute half-day bar
            'status' => 'Present',
            'leave_request_id' => $leave->id,
        ]);

        $data = $this->callRegistry(['month' => 6, 'year' => 2026, 'userId' => $employee->id]);
        $row = collect($data['data'])->firstWhere('code', $employee->code);

        $this->assertNotNull($row);
        $this->assertEquals(0.5, $row['presents'], 'a worked-and-paid-leave half day must count as half a present day, not a whole one');
        $this->assertEquals(0.5, $row['leaves'], 'the paid half-day must still show up as 0.5 of leave used');
        $this->assertEquals(0, $row['npl'], 'a paid leave must never be counted as non-paid');
        $this->assertEquals($row['presents'] + $row['offs'] + $row['leaves'], $row['payable'], 'payable days must equal presents + offs + leaves');
    }

    public function test_an_unpaid_leave_day_counts_toward_npl_not_leaves(): void
    {
        $employee = $this->makeUser();
        $lwpType = LeaveType::where('code', 'ALP')->firstOrFail();

        // updateQuietly() to approved, deliberately bypassing the
        // processApproval model hook (which would auto-create an Attendance
        // row for the day and route it through a different, already-tested
        // code path). This is exactly the shape registryAjax's own
        // no-attendance-record branch exists for: a leave that is approved
        // with no Attendance row of its own to look at that day.
        $leave = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $lwpType->id,
            'from_date' => '2026-06-11', 'to_date' => '2026-06-11',
            'status' => 'pending', 'user_notes' => 'registry unpaid-leave test',
        ]);
        $leave->updateQuietly(['status' => 'approved', 'is_lwp' => true, 'lwp_days' => 1]);
        $this->fillMonthPresent($employee, 2026, 6, '2026-06-11');

        $data = $this->callRegistry(['month' => 6, 'year' => 2026, 'userId' => $employee->id]);
        $row = collect($data['data'])->firstWhere('code', $employee->code);

        $this->assertNotNull($row);
        $this->assertEquals(1, $row['npl'], 'an unpaid leave day must be counted as NPL');
        $this->assertEquals(0, $row['leaves'], 'an unpaid leave day must not also inflate the paid-leave count');
        $this->assertEquals(0, $row['absents'], 'NPL is its own bucket - an NPL day must not also be counted as a plain Absent day');
        $this->assertEquals($row['presents'] + $row['offs'] + $row['leaves'], $row['payable'], 'payable days must equal presents + offs + leaves even with an NPL day present');
    }

    /**
     * A full-day approved paid leave, WITH an Attendance row (status='paid_leave')
     * for the day - used to add +1 to both presents and leaves for the same
     * single day, inflating both the present count and Payable by one
     * "phantom" day. Reported live: presents+absents+offs+npl+leaves summed to
     * more days than exist in the month.
     */
    public function test_a_full_day_paid_leave_with_attendance_record_counts_as_leave_not_present(): void
    {
        $employee = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => $alp->id, 'balance' => 2.0, 'used' => 0]);

        $leave = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-06-12', 'to_date' => '2026-06-12',
            'status' => 'pending', 'user_notes' => 'registry full-day paid leave test',
        ]);
        $leave->update(['status' => 'approved']); // triggers processApproval - deducts the ALP balance, creates the Attendance row itself

        $data = $this->callRegistry(['month' => 6, 'year' => 2026, 'userId' => $employee->id]);
        $row = collect($data['data'])->firstWhere('code', $employee->code);

        $this->assertNotNull($row);
        $this->assertEquals(1, $row['leaves'], 'a full paid-leave day must be counted as one leave day');
        $this->assertEquals(0, $row['presents'], 'a full paid-leave day is not a Present day - the employee did not work it');
        $this->assertEquals($row['presents'] + $row['offs'] + $row['leaves'], $row['payable'], 'payable days must equal presents + offs + leaves, not double-count the same paid-leave day');
    }

    /**
     * Same bug, no-attendance-record branch (registryAjax falls back to a
     * plain LeaveRequest lookup when there's no Attendance row at all for
     * the day) - mirrors the found-record fix above.
     */
    public function test_a_full_day_unpaid_leave_with_no_attendance_record_counts_as_npl_not_absent(): void
    {
        $employee = $this->makeUser();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();

        $leave = LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-06-13', 'to_date' => '2026-06-13',
            'status' => 'pending', 'user_notes' => 'registry no-attendance unpaid leave test',
        ]);
        $leave->updateQuietly(['status' => 'approved', 'is_lwp' => true, 'lwp_days' => 1]);
        $this->fillMonthPresent($employee, 2026, 6, '2026-06-13');

        $data = $this->callRegistry(['month' => 6, 'year' => 2026, 'userId' => $employee->id]);
        $row = collect($data['data'])->firstWhere('code', $employee->code);

        $this->assertNotNull($row);
        $this->assertEquals(1, $row['npl'], 'an unpaid full-day leave with no punch must be counted as NPL');
        $this->assertEquals(0, $row['absents'], 'it must not also be double-counted as a plain Absent day');
    }

    /**
     * A designated off-Saturday (per the employee's own saturday_off_config)
     * already earns the identical comp-off credit as a worked Sunday - see
     * Attendance.php's own crediting hook - but the registry had no matching
     * status branch for it, so it rendered as an ordinary Present, visually
     * identical to any other weekday, unlike a worked Sunday/holiday which
     * already get the green SP/HP badge. 2026-06-13 is the 2nd Saturday of
     * June 2026.
     */
    public function test_a_worked_designated_off_saturday_shows_as_sp_not_plain_present(): void
    {
        $profile = LeavePolicyProfile::create([
            'name' => 'QA Even Saturday Off', 'saturday_off_config' => ['2', '4'], 'status' => 'active',
        ]);
        $employee = $this->makeUser();
        $employee->update(['leave_policy_profile_id' => $profile->id]);

        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-06-13 09:00:00',
            'check_out_time' => '2026-06-13 18:00:00',
            'status' => 'Present',
        ]);

        $data = $this->callRegistry(['month' => 6, 'year' => 2026, 'userId' => $employee->id]);
        $row = collect($data['data'])->firstWhere('code', $employee->code);

        $this->assertEquals('SP', $row['day_13']['status'], 'a worked designated off-Saturday must render as SP, matching a worked Sunday');
        $this->assertStringContainsString('bg-success', $row['day_13']['class']);
    }
}
