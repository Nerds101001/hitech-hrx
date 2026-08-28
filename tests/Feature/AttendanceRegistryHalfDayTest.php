<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveBalance;
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

        $data = $this->callRegistry(['month' => 6, 'year' => 2026, 'userId' => $employee->id]);
        $row = collect($data['data'])->firstWhere('code', $employee->code);

        $this->assertNotNull($row);
        $this->assertEquals(1, $row['npl'], 'an unpaid leave day must be counted as NPL');
        $this->assertEquals(0, $row['leaves'], 'an unpaid leave day must not also inflate the paid-leave count');
        $this->assertEquals($row['presents'] + $row['offs'] + $row['leaves'], $row['payable'], 'payable days must equal presents + offs + leaves even with an NPL day present');
    }
}
