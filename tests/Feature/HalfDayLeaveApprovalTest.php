<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * LeaveRequest::processApproval()'s half-day branch only ever set an
 * attendance label — unlike its short-leave and full-day siblings, it never
 * touched the leave balance or corrected is_lwp. Every approved half-day
 * leave (113 on prod at the time this was found) silently deducted nothing
 * and kept is_lwp frozen at whatever it was at submission time, even for a
 * genuinely paid leave type. Reported live: an employee's half-day leave
 * showed as plain unpaid "Half-Day" on Attendance instead of "Present +
 * Paid Half Day Leave", because the attendance page's paid-check reads this
 * same is_lwp flag.
 */
class HalfDayLeaveApprovalTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'HalfDay',
            'email'      => 'qahalfday' . uniqid() . '@example.test',
            'code'       => 'QAHD' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
    }

    /** THE CORE BUG: an approved paid half-day leave must actually deduct 0.5 and clear is_lwp. */
    public function test_approving_a_paid_half_day_leave_deducts_half_a_day_and_marks_it_paid(): void
    {
        $user = $this->makeUser();
        $alp  = LeaveType::where('code', 'ALP')->firstOrFail();
        $hdl  = LeaveType::where('code', 'HDL')->firstOrFail();

        LeaveBalance::create([
            'user_id' => $user->id, 'leave_type_id' => $alp->id,
            'balance' => 2.0, 'used' => 0.0,
        ]);

        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $hdl->id,
            'from_date' => now()->toDateString(), 'to_date' => now()->toDateString(),
            'is_half_day' => true, 'half_day_session' => 'first_half',
            'status' => 'pending', 'is_lwp' => true, // starts wrong, same as the live bug
            'user_notes' => 'half-day regression test',
        ]);

        $leave->update(['status' => 'approved']);

        $balance = LeaveBalance::where('user_id', $user->id)->where('leave_type_id', $alp->id)->first();
        $this->assertEquals(0.5, (float) $balance->used, 'the half day was never deducted from the pooled ALP balance');
        $this->assertFalse($leave->fresh()->is_lwp, 'is_lwp stayed frozen at its submission-time value instead of being corrected on approval');
    }

    /** The discriminator: genuinely insufficient balance still correctly falls through to LWP. */
    public function test_approving_a_half_day_leave_with_no_balance_left_marks_it_unpaid(): void
    {
        $user = $this->makeUser();
        $alp  = LeaveType::where('code', 'ALP')->firstOrFail();
        $hdl  = LeaveType::where('code', 'HDL')->firstOrFail();

        LeaveBalance::create([
            'user_id' => $user->id, 'leave_type_id' => $alp->id,
            'balance' => 0.0, 'used' => 0.0,
        ]);

        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $hdl->id,
            'from_date' => now()->toDateString(), 'to_date' => now()->toDateString(),
            'is_half_day' => true, 'half_day_session' => 'first_half',
            'status' => 'pending', 'user_notes' => 'half-day insufficient-balance test',
        ]);

        $leave->update(['status' => 'approved']);

        $balance = LeaveBalance::where('user_id', $user->id)->where('leave_type_id', $alp->id)->first();
        $this->assertEquals(0.0, (float) $balance->used, 'nothing should have been deducted when the balance could not cover it');
        $this->assertTrue($leave->fresh()->is_lwp);
        $this->assertEquals(0.5, (float) $leave->fresh()->lwp_days);
    }

    /** A half-day WFH request must never draw from a balance pool at all - same exemption as full-day WFH. */
    public function test_a_half_day_wfh_request_does_not_touch_any_balance(): void
    {
        $user = $this->makeUser();
        $alp  = LeaveType::where('code', 'ALP')->firstOrFail();
        $wfh  = LeaveType::where('code', 'WFH')->first();
        if (!$wfh) {
            $this->markTestSkipped('No WFH leave type on this database.');
        }

        LeaveBalance::create([
            'user_id' => $user->id, 'leave_type_id' => $alp->id,
            'balance' => 2.0, 'used' => 0.0,
        ]);

        $leave = LeaveRequest::create([
            'user_id' => $user->id, 'leave_type_id' => $wfh->id,
            'from_date' => now()->toDateString(), 'to_date' => now()->toDateString(),
            'is_half_day' => true, 'half_day_session' => 'first_half',
            'status' => 'pending', 'user_notes' => 'half-day WFH test',
        ]);

        $leave->update(['status' => 'approved']);

        $balance = LeaveBalance::where('user_id', $user->id)->where('leave_type_id', $alp->id)->first();
        $this->assertEquals(0.0, (float) $balance->used, 'WFH must never draw from a balance pool');
    }
}
