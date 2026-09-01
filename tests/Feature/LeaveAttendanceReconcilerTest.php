<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveAttendanceReconciler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * LeaveAttendanceReconciler::run() refunds a day back to whichever balance
 * the leave actually drew from - it used to always refund the shared ALP
 * pool, even for Short Leave, which keeps its own separate balance and is
 * never pooled into ALP (see LeaveRequest::processApproval()'s own pooling
 * rule, which this mirrors). A Short Leave conflict then cancelled the
 * request correctly but credited a day ALP never lost, while leaving the
 * Short Leave balance permanently drained. Reported live: an employee whose
 * approved 2-hour short leave turned out to be a fully-attended day had
 * their Short Leave balance stuck at 0 even after the "conflict" was found.
 */
class LeaveAttendanceReconcilerTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Reconciler',
            'email'      => 'qarecon' . uniqid() . '@example.test',
            'code'       => 'QARC' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    public function test_a_fully_attended_short_leave_day_refunds_short_leave_not_alp(): void
    {
        $employee = $this->makeUser();
        $shl = LeaveType::where('code', 'SHL')->firstOrFail();
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();

        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => $shl->id, 'balance' => 1.0, 'used' => 0]);
        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => $alp->id, 'balance' => 2.0, 'used' => 0]);

        // is_short_leave/duration_hours/start_time/end_time aren't mass-assignable
        // (missing from LeaveRequest::$fillable) - set directly, matching how the
        // real short-leave submission path (Api\LeaveController::store()) does it.
        $leave = new LeaveRequest([
            'user_id' => $employee->id, 'leave_type_id' => $shl->id,
            'from_date' => '2026-08-10', 'to_date' => '2026-08-10',
            'status' => 'pending', 'user_notes' => 'reconciler test',
        ]);
        $leave->is_short_leave = true;
        $leave->duration_hours = 2.0;
        $leave->start_time = '16:00';
        $leave->end_time = '18:00';
        $leave->save();
        $leave->update(['status' => 'approved']); // deducts the SHL balance, same as Priyanka's real leave#725

        $shlBalance = LeaveBalance::where('user_id', $employee->id)->where('leave_type_id', $shl->id)->first();
        $this->assertEquals(1.0, (float) $shlBalance->used, 'sanity check: approval must have deducted the Short Leave balance');

        // The employee actually worked the whole day - the claimed short
        // leave window was never taken. No leave_request_id, so it reads as
        // a real, independent punch rather than the leave's own placeholder.
        Attendance::create([
            'user_id' => $employee->id,
            'check_in_time' => '2026-08-10 09:00:00',
            'check_out_time' => '2026-08-10 18:00:00',
            'status' => 'Present',
        ]);

        $result = LeaveAttendanceReconciler::run(false, $employee->id);

        $this->assertEquals(1, $result['conflicts'], 'the fully-attended short leave day must be detected as a conflict');
        $this->assertEquals(1, $result['cancelled'], 'the request must be cancelled once its only covered day turns out attended');

        $shlBalance->refresh();
        $alpBalance = LeaveBalance::where('user_id', $employee->id)->where('leave_type_id', $alp->id)->first();

        $this->assertEquals(0.0, (float) $shlBalance->used, 'the refund must land back on the Short Leave balance it actually came from');
        $this->assertEquals(0.0, (float) $alpBalance->used, 'ALP was never touched by this leave, so it must not receive a phantom credit either');

        $leave->refresh();
        $status = $leave->status instanceof \UnitEnum ? $leave->status->value : $leave->status;
        $this->assertEquals('cancelled', $status);
    }
}
