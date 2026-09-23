<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Approving a leave must clear an absence on the same day.
 *
 * The approval hook keeps an attendance row's existing status whenever the row
 * has a check-in other than 00:00, so that somebody who actually worked is not
 * relabelled as being on leave. But an admin marking someone absent also
 * writes clock times (placeholder shift hours, working_hours 0), so an
 * 'absent' mark survived the approval: the employee was unpaid for the day AND
 * still carrying the absence, which costs 1.5 days under the current rule.
 *
 * Found live on MEER SINGH, 18 Sep 2026 -- marked absent by CCare on the day,
 * then granted a back-dated leave three days later that never displaced it.
 *
 * June 10 2026 (Wednesday) is used because it is not a holiday in this
 * database, matching the other attendance tests.
 */
class ApprovedLeaveOverridesAbsenceTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA',
            'last_name' => 'AbsentLeave',
            'email' => 'qaabs' . uniqid() . '@example.test',
            'code' => 'QAAB' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'),
            'status' => 'active',
        ]);
    }

    private function alp(): LeaveType
    {
        return LeaveType::where('code', 'ALP')->firstOrFail();
    }

    /** The row an admin creates when marking somebody absent: clock times, no worked hours. */
    private function adminMarkedAbsence(User $user, string $date): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => $date . ' 09:30:00',
            'check_out_time' => $date . ' 18:30:00',
            'working_hours' => 0,
            'status' => 'absent',
            'admin_reason' => 'Marked by CCare',
            'notes' => 'leave',
        ]);
    }

    private function approveLeaveFor(User $user, string $date): LeaveRequest
    {
        Mail::fake();

        $leave = LeaveRequest::withoutEvents(fn () => LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type_id' => $this->alp()->id,
            'from_date' => $date,
            'to_date' => $date,
            'status' => 'pending',
            'user_notes' => 'urgent work',
        ]));

        // Approving fires the hook that reconciles balance and attendance.
        $leave->status = \App\Enums\LeaveRequestStatus::APPROVED;
        $leave->save();

        return $leave->fresh();
    }

    public function test_approving_a_leave_clears_an_admin_marked_absence(): void
    {
        $user = $this->makeUser();
        $absence = $this->adminMarkedAbsence($user, '2026-06-10');

        $this->assertSame('absent', $absence->status, 'precondition: the day starts as an absence');

        $this->approveLeaveFor($user, '2026-06-10');

        $absence->refresh();

        $this->assertNotSame(
            'absent',
            strtolower($absence->status),
            'an approved leave must displace the absence, or the day is unpaid AND penalised'
        );
        $this->assertContains(
            strtolower($absence->status),
            ['leave', 'unpaid_leave'],
            'the day should now read as leave (paid or unpaid depending on balance)'
        );
    }

    public function test_the_absence_becomes_unpaid_leave_when_there_is_no_balance(): void
    {
        $user = $this->makeUser();
        $absence = $this->adminMarkedAbsence($user, '2026-06-10');

        // No balance record at all: nothing to draw from.
        $this->approveLeaveFor($user, '2026-06-10');

        $absence->refresh();

        $this->assertSame('unpaid_leave', strtolower($absence->status));
    }

    public function test_the_absence_becomes_paid_leave_when_balance_covers_it(): void
    {
        $user = $this->makeUser();
        $absence = $this->adminMarkedAbsence($user, '2026-06-10');

        LeaveBalance::create([
            'user_id' => $user->id,
            'leave_type_id' => $this->alp()->id,
            'balance' => 5,
            'used' => 0,
        ]);

        $this->approveLeaveFor($user, '2026-06-10');

        $absence->refresh();

        $this->assertSame('leave', strtolower($absence->status), 'with balance available the day is paid leave');
    }

    /** The original protection must survive: a genuine worked day keeps its status. */
    public function test_a_real_present_punch_is_not_relabelled_by_an_approved_leave(): void
    {
        $user = $this->makeUser();

        $worked = Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-10 09:30:00',
            'check_out_time' => '2026-06-10 18:30:00',
            'working_hours' => 9,
            'status' => 'Present',
        ]);

        $this->approveLeaveFor($user, '2026-06-10');

        $worked->refresh();

        $this->assertSame('present', strtolower($worked->status), 'a day actually worked must keep its Present status');
    }
}
