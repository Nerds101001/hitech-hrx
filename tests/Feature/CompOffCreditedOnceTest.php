<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveHistoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A worked Sunday earns one comp-off. The Attendance hook stamped the row and
 * checked the stamp - but CCare's register rewrites admin_reason on every save,
 * so the stamp vanished and the next save credited the day again (MEER SINGH,
 * 27 Sep 2026: two credits two minutes apart; 7 days across 6 people).
 *
 * And the leave statement never listed absences CCare covered from the balance
 * (SHRI KANT: statement 2, Total Used 4).
 */
class CompOffCreditedOnceTest extends TestCase
{
    use DatabaseTransactions;

    private function person(): array
    {
        Mail::fake();
        $user = User::create(['first_name' => 'QA', 'last_name' => 'CompOff', 'email' => 'qaco' . uniqid() . '@example.test',
            'code' => 'QACO' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active']);
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();
        LeaveBalance::create(['user_id' => $user->id, 'leave_type_id' => $alp->id, 'balance' => 2, 'used' => 0]);

        return [$user, $alp];
    }

    public function test_a_worked_sunday_is_credited_once_even_after_ccare_rewrites_the_row(): void
    {
        [$user, $alp] = $this->person();
        $row = Attendance::create(['user_id' => $user->id, 'check_in_time' => '2026-06-14 09:30:00', 'check_out_time' => '2026-06-14 18:30:00',
            'status' => 'present', 'admin_reason' => 'Marked by CCare']);   // 14 Jun 2026 is a Sunday
        $balance = fn () => (float) LeaveBalance::where('user_id', $user->id)->where('leave_type_id', $alp->id)->value('balance');
        $this->assertSame(3.0, $balance(), 'credited once');

        // CCare saves the day again: admin_reason is overwritten, the stamp is gone.
        $row->fresh()->update(['admin_reason' => 'Marked by CCare', 'notes' => 'seminar']);
        $row->fresh()->update(['admin_reason' => 'Marked by CCare', 'notes' => 'seminar, visit']);

        $this->assertSame(3.0, $balance(), 'still one credit for one Sunday');
        $this->assertStringContainsString('Sunday Working Credit', (string) $row->fresh()->admin_reason, 'stamp put back');
        $this->assertSame(1, Attendance::offDayCreditStanding($user->id, $alp->id, '2026-06-14'));

        // Deleting the day still takes the one credit back, stamp or not.
        $row->fresh()->updateQuietly(['admin_reason' => 'Marked by CCare']);
        $row->fresh()->delete();
        $this->assertSame(2.0, $balance());
    }

    public function test_the_statement_counts_a_leave_across_a_sunday_in_working_days(): void
    {
        [$user, $alp] = $this->person();

        \App\Models\LeaveRequest::withoutEvents(fn () => \App\Models\LeaveRequest::create(['user_id' => $user->id, 'leave_type_id' => $alp->id,
            'from_date' => '2026-08-14', 'to_date' => '2026-08-17', 'status' => 'approved', 'user_notes' => 'qa']));   // Fri 14 - Mon 17 Aug, Sunday 16th

        $line = LeaveHistoryService::getUnifiedHistory($user)->firstWhere('type', 'Request');
        $working = \App\Services\LeavePolicyService::calculateWorkingDays($user, $alp->id, '2026-08-14', '2026-08-17');

        $this->assertLessThan(4.0, $working);
        $this->assertSame($working, (float) $line->paid_days);
        $this->assertSame(-$working, (float) $line->amount);
    }

    public function test_the_statement_lists_an_absence_ccare_covered_from_the_balance(): void
    {
        [$user] = $this->person();
        Attendance::withoutEvents(fn () => Attendance::create(['user_id' => $user->id, 'check_in_time' => '2026-09-04 00:00:00',
            'status' => 'paid_leave', 'admin_reason' => 'Auto-adjusted against Earned Leave (Sunday Working) | Marked by CCare']));

        $line = LeaveHistoryService::getUnifiedHistory($user)->firstWhere('type', 'Deduction');

        $this->assertNotNull($line);
        $this->assertSame(-1.0, $line->amount);
        $this->assertStringContainsString('04 Sep 2026', $line->notes);
    }
}
