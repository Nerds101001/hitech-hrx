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
 * CCare's register auto-adjusts an absence against the leave balance. When the
 * employee's own leave for that day was approved afterwards, approval took the
 * day from the balance again - SHRI KANT lost 2 days this way (5 Aug, 1 Sep
 * 2026), three others 1 day each.
 *
 * 10 June 2026 (Wednesday) is not a holiday in this database.
 */
class LeaveNotDeductedTwiceTest extends TestCase
{
    use DatabaseTransactions;

    private const DAY = '2026-06-10';

    private function setUpPerson(): array
    {
        Mail::fake();
        $user = User::create(['first_name' => 'QA', 'last_name' => 'Twice', 'email' => 'qatw' . uniqid() . '@example.test',
            'code' => 'QATW' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active']);
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        // Balance 3; CCare has already taken 1 for the absence it auto-adjusted.
        $balance = LeaveBalance::create(['user_id' => $user->id, 'leave_type_id' => $alp->id, 'balance' => 3, 'used' => 1]);

        return [$user, $alp, $balance];
    }

    private function approve(User $user, LeaveType $alp, string $day): LeaveRequest
    {
        $leave = LeaveRequest::withoutEvents(fn () => LeaveRequest::create(['user_id' => $user->id, 'leave_type_id' => $alp->id,
            'from_date' => $day, 'to_date' => $day, 'status' => 'pending', 'user_notes' => 'urgent work']));
        $leave->status = \App\Enums\LeaveRequestStatus::APPROVED;
        $leave->save();

        return $leave->fresh();
    }

    public function test_a_day_the_register_already_took_from_the_balance_is_not_taken_again(): void
    {
        [$user, $alp, $balance] = $this->setUpPerson();
        Attendance::withoutEvents(fn () => Attendance::create(['user_id' => $user->id, 'check_in_time' => self::DAY . ' 00:00:00',
            'status' => 'paid_leave', 'admin_reason' => 'Auto-adjusted against Earned Leave (Sunday Working) | Marked by CCare']));

        $leave = $this->approve($user, $alp, self::DAY);

        $this->assertSame(1.0, (float) $balance->fresh()->used, 'taken once, by the register');
        $this->assertFalse((bool) $leave->is_lwp, 'the day is paid');
        $row = Attendance::where('user_id', $user->id)->whereDate('check_in_time', self::DAY)->first();
        $this->assertSame($leave->id, $row->leave_request_id);
        $this->assertSame('leave', strtolower($row->status));

        // Cancelling gives the day back - undoing the register's deduction too.
        $leave->status = \App\Enums\LeaveRequestStatus::CANCELLED;
        $leave->save();
        $this->assertSame(0.0, (float) $balance->fresh()->used);
    }

    public function test_an_ordinary_day_is_still_taken_from_the_balance(): void
    {
        [$user, $alp, $balance] = $this->setUpPerson();

        $this->approve($user, $alp, self::DAY);

        $this->assertSame(2.0, (float) $balance->fresh()->used);
    }
}
