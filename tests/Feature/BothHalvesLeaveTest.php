<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\CcAttendanceController;
use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceDayStatus;
use App\Services\LeavePolicyService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Someone who has the first half of a day off may later need the second half
 * too. The overlap rule refused any second request on that date, and the rest
 * of the system assumed one leave per day: the second approval overwrote the
 * first's attendance label, cancelling one half deleted the row the other half
 * still needed, and CCare could mark the "other half" as worked.
 */
class BothHalvesLeaveTest extends TestCase
{
    use DatabaseTransactions;

    private const DAY = '2026-09-23'; // a Wednesday

    private function person(float $alpBalance): User
    {
        Mail::fake();
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Halves ' . uniqid(), 'email' => 'qahalves' . uniqid() . '@example.test',
            'code' => 'QH' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        LeaveBalance::create(['user_id' => $u->id, 'leave_type_id' => LeaveType::where('code', 'ALP')->firstOrFail()->id, 'balance' => $alpBalance, 'used' => 0]);

        return $u;
    }

    private function half(User $u, string $session, string $status = 'approved'): LeaveRequest
    {
        $l = LeaveRequest::create([
            'user_id' => $u->id, 'leave_type_id' => LeaveType::where('code', 'HDL')->firstOrFail()->id,
            'from_date' => self::DAY, 'to_date' => self::DAY, 'is_half_day' => true, 'half_day_session' => $session,
            'status' => 'pending', 'user_notes' => 'both halves test',
        ]);
        if ($status === 'approved') {
            $l->update(['status' => 'approved']);
        }

        return $l->fresh();
    }

    private function row(User $u): Attendance
    {
        $rows = Attendance::where('user_id', $u->id)->whereDate('check_in_time', self::DAY)->get();
        $this->assertCount(1, $rows, 'one attendance row for the day');

        return $rows->first();
    }

    private function dayCode(User $u): string
    {
        return AttendanceDayStatus::resolve($u, Carbon::parse(self::DAY), $this->row($u), null, null)['code'];
    }

    private function payroll(User $u): array
    {
        $svc = new PayrollService();
        $m = new ReflectionMethod($svc, 'calculateAttendanceStats');
        $m->setAccessible(true);

        return $m->invoke($svc, $u, Carbon::parse(self::DAY)->startOfDay(), Carbon::parse(self::DAY)->endOfDay());
    }

    private function check(User $u, string $session): ?string
    {
        return LeavePolicyService::validate($u, LeaveType::where('code', 'HDL')->firstOrFail()->id, self::DAY, self::DAY, null, true, null, null, $session);
    }

    public function test_the_other_half_may_be_applied_for_but_not_the_same_half_or_over_a_full_day(): void
    {
        $u = $this->person(5);
        $this->half($u, 'first_half');

        $this->assertNull($this->check($u, 'second_half'), 'second half next to an approved first half');
        $this->assertStringContainsString('overlaps', (string) $this->check($u, 'first_half'), 'the same half again is still refused');

        $full = $this->person(5);
        LeaveRequest::create(['user_id' => $full->id, 'leave_type_id' => LeaveType::where('code', 'ALP')->firstOrFail()->id,
            'from_date' => self::DAY, 'to_date' => self::DAY, 'status' => 'pending', 'user_notes' => 'full day']);
        $this->assertStringContainsString('overlaps', (string) $this->check($full, 'second_half'), 'a half inside a full-day leave is still refused');
    }

    public function test_both_halves_paid_make_one_full_paid_day(): void
    {
        $u = $this->person(5);
        $this->half($u, 'first_half');
        $this->half($u, 'second_half');

        $this->assertSame('leave', $this->row($u)->status);
        $this->assertSame('paid_leave', $this->dayCode($u));
        $this->assertEquals(1.0, (float) LeaveBalance::where('user_id', $u->id)->where('leave_type_id', LeaveType::where('code', 'ALP')->first()->id)->value('used'));
        $p = $this->payroll($u);
        $this->assertEquals(1.0, $p['worked_days']);
        $this->assertEquals(0.0, $p['absents']);
    }

    public function test_one_paid_one_unpaid_half_is_half_paid(): void
    {
        $u = $this->person(0.5); // enough for one half only
        $this->half($u, 'first_half');
        $this->half($u, 'second_half');

        $this->assertSame('half-Day', $this->row($u)->status);
        $this->assertSame('half-day', $this->dayCode($u));
        $p = $this->payroll($u);
        $this->assertEquals(0.5, $p['worked_days']);
        $this->assertEquals(0.5, $p['absents']);
    }

    public function test_both_halves_unpaid_is_an_unpaid_day(): void
    {
        $u = $this->person(0);
        $this->half($u, 'second_half');
        $this->half($u, 'first_half');

        $this->assertSame('unpaid_leave', $this->row($u)->status);
        $this->assertSame('unpaid_leave', $this->dayCode($u));
        $p = $this->payroll($u);
        $this->assertEquals(0.0, $p['worked_days']);
        $this->assertEquals(1.0, $p['absents']);
    }

    public function test_cancelling_one_half_keeps_the_other_half(): void
    {
        $u = $this->person(5);
        $first = $this->half($u, 'first_half');
        $second = $this->half($u, 'second_half');

        $second->update(['status' => 'cancelled']);

        $row = $this->row($u);
        $this->assertSame($first->id, $row->leave_request_id, 'the row now belongs to the surviving half');
        $this->assertSame('half-Day', $row->status);
        $this->assertEquals(0.5, (float) LeaveBalance::where('user_id', $u->id)->where('leave_type_id', LeaveType::where('code', 'ALP')->first()->id)->value('used'), 'the cancelled half is refunded');
    }

    public function test_ccare_sees_both_halves_as_a_full_day_off(): void
    {
        $u = $this->person(5);
        $first = $this->half($u, 'first_half');
        $this->assertSame('half', CcAttendanceController::leaveKind($first), 'one half alone leaves the other half to be worked');

        $this->half($u, 'second_half', 'pending');
        $this->assertSame('full', CcAttendanceController::leaveKind($first->fresh()), 'with the other half claimed too, nothing is left to mark');
    }
}
