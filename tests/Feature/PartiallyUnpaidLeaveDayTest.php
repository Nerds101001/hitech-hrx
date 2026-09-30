<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceDayStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A leave longer than the remaining balance is split: the leading days come
 * off the balance, the rest are leave without pay. `is_lwp` and `lwp_days`
 * describe the REQUEST, not a day of it, so reading is_lwp per-day painted
 * every day of such a request unpaid -- including the ones that had been paid
 * for and deducted.
 *
 * Reported live: a two-day leave taken with one day of balance left showed
 * both days purple on the calendar. A sweep found 51 days across 43 requests
 * in the same state, all of them already carrying the correct per-day status
 * on their own attendance row.
 *
 * June 10-11 2026 (Wed/Thu) is used because neither coincides with a holiday
 * in this database, matching AttendanceRegistryHalfDayTest's reasoning.
 */
class PartiallyUnpaidLeaveDayTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'QA',
            'last_name' => 'PartialLwp',
            'email' => 'qalwp' . uniqid() . '@example.test',
            'code' => 'QALW' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'),
            'status' => 'active',
        ]);
    }

    private function paidLeaveType(): LeaveType
    {
        return LeaveType::where('code', 'ALP')->firstOrFail();
    }

    /**
     * The request is created directly with the per-day attendance rows the
     * approval hook would have written, so this exercises the resolver rather
     * than re-testing approval.
     */
    private function makeSplitLeave(User $user): LeaveRequest
    {
        $leave = LeaveRequest::withoutEvents(fn () => LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type_id' => $this->paidLeaveType()->id,
            'from_date' => '2026-06-10',
            'to_date' => '2026-06-11',
            'status' => 'approved',
            'is_lwp' => true,      // whole-request flag: SOME day was unpaid
            'lwp_days' => 1,       // exactly one of the two
            'user_notes' => 'partial lwp fixture',
        ]));

        // Day one was covered by the balance, day two was not.
        Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-10 00:00:00',
            'status' => 'leave',
            'leave_request_id' => $leave->id,
        ]);
        Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-11 00:00:00',
            'status' => 'unpaid_leave',
            'leave_request_id' => $leave->id,
        ]);

        return $leave->fresh('leaveType');
    }

    private function resolveDay(User $user, LeaveRequest $leave, string $date): array
    {
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', $date)
            ->first();

        return AttendanceDayStatus::resolve($user, \Carbon\Carbon::parse($date), $attendance, $leave, null);
    }

    public function test_the_paid_day_of_a_partially_unpaid_leave_is_not_shown_as_unpaid(): void
    {
        $user = $this->makeUser();
        $leave = $this->makeSplitLeave($user);

        $day = $this->resolveDay($user, $leave, '2026-06-10');

        $this->assertSame('paid_leave', $day['code'], 'the day the balance paid for must resolve as paid leave');
        $this->assertTrue($day['isPaid'], 'isPaid must be true for the day that was deducted');
    }

    public function test_the_unpaid_day_of_the_same_leave_is_still_unpaid(): void
    {
        $user = $this->makeUser();
        $leave = $this->makeSplitLeave($user);

        $day = $this->resolveDay($user, $leave, '2026-06-11');

        $this->assertSame('unpaid_leave', $day['code'], 'the day with no balance behind it must stay unpaid');
        $this->assertFalse($day['isPaid']);
    }

    public function test_a_wholly_unpaid_leave_is_unpaid_on_every_day(): void
    {
        $user = $this->makeUser();

        $leave = LeaveRequest::withoutEvents(fn () => LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type_id' => $this->paidLeaveType()->id,
            'from_date' => '2026-06-10',
            'to_date' => '2026-06-11',
            'status' => 'approved',
            'is_lwp' => true,
            'lwp_days' => 2,
            'user_notes' => 'fully unpaid fixture',
        ]));

        foreach (['2026-06-10', '2026-06-11'] as $date) {
            Attendance::create([
                'user_id' => $user->id,
                'check_in_time' => $date . ' 00:00:00',
                'status' => 'unpaid_leave',
                'leave_request_id' => $leave->id,
            ]);
        }

        foreach (['2026-06-10', '2026-06-11'] as $date) {
            $day = $this->resolveDay($user, $leave->fresh('leaveType'), $date);
            $this->assertSame('unpaid_leave', $day['code'], "$date must be unpaid");
        }
    }

    public function test_a_fully_paid_leave_is_unaffected(): void
    {
        $user = $this->makeUser();

        $leave = LeaveRequest::withoutEvents(fn () => LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type_id' => $this->paidLeaveType()->id,
            'from_date' => '2026-06-10',
            'to_date' => '2026-06-10',
            'status' => 'approved',
            'is_lwp' => false,
            'lwp_days' => 0,
            'user_notes' => 'fully paid fixture',
        ]));

        Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => '2026-06-10 00:00:00',
            'status' => 'leave',
            'leave_request_id' => $leave->id,
        ]);

        $day = $this->resolveDay($user, $leave->fresh('leaveType'), '2026-06-10');

        $this->assertSame('paid_leave', $day['code']);
        $this->assertTrue($day['isPaid']);
    }

    /**
     * With no attendance row to consult, the resolver falls back to the order
     * the approval hook pays days in: leading working days first.
     */
    public function test_without_an_attendance_row_the_leading_days_are_the_paid_ones(): void
    {
        $user = $this->makeUser();

        $leave = LeaveRequest::withoutEvents(fn () => LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type_id' => $this->paidLeaveType()->id,
            'from_date' => '2026-06-10',
            'to_date' => '2026-06-11',
            'status' => 'approved',
            'is_lwp' => true,
            'lwp_days' => 1,
            'user_notes' => 'no attendance fixture',
        ]))->fresh('leaveType');

        $first = AttendanceDayStatus::resolve($user, \Carbon\Carbon::parse('2026-06-10'), null, $leave, null);
        $second = AttendanceDayStatus::resolve($user, \Carbon\Carbon::parse('2026-06-11'), null, $leave, null);

        $this->assertSame('paid_leave', $first['code'], 'the first working day is the one the balance paid for');
        $this->assertSame('unpaid_leave', $second['code'], 'the day past the balance is unpaid');
    }
}
