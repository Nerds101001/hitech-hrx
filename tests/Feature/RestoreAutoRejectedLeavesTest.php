<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\RestorePastDatedAutoRejectedLeavesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Restores only the past-dated auto-rejections — nothing else. */
class RestoreAutoRejectedLeavesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_restores_a_past_dated_auto_rejection(): void
    {
        Mail::fake();
        $leave = $this->rejected(now()->subDays(5), 'Auto-rejected: No action taken by manager within 48 hours.');

        (new RestorePastDatedAutoRejectedLeavesSeeder)->run();

        $leave->refresh();
        $this->assertSame('pending', $this->statusOf($leave));
        $this->assertNull($leave->rejected_at);
        $this->assertNull($leave->rejected_by_id);
        $this->assertStringContainsString('Auto-rejection reversed', (string) $leave->approval_notes);
    }

    /** A future-dated auto-rejection was correct and must stay rejected. */
    public function test_it_leaves_a_future_dated_auto_rejection_alone(): void
    {
        Mail::fake();
        $leave = $this->rejected(now()->addDays(5), 'Auto-rejected: No action taken by manager within 48 hours.');

        (new RestorePastDatedAutoRejectedLeavesSeeder)->run();

        $leave->refresh();
        $this->assertSame('rejected', $this->statusOf($leave));
    }

    /** A manager's own rejection must never be undone. */
    public function test_it_never_touches_a_manual_rejection(): void
    {
        Mail::fake();
        $leave = $this->rejected(now()->subDays(5), 'Not enough cover that week.');

        (new RestorePastDatedAutoRejectedLeavesSeeder)->run();

        $leave->refresh();
        $this->assertSame('rejected', $this->statusOf($leave));
        $this->assertSame('Not enough cover that week.', $leave->approval_notes);
    }

    public function test_running_it_twice_changes_nothing_further(): void
    {
        Mail::fake();
        $leave = $this->rejected(now()->subDays(5), 'Auto-rejected: No action taken by manager within 48 hours.');

        (new RestorePastDatedAutoRejectedLeavesSeeder)->run();
        $first = $leave->fresh()->updated_at;

        (new RestorePastDatedAutoRejectedLeavesSeeder)->run();

        $this->assertEquals($first, $leave->fresh()->updated_at);
    }

    private function statusOf(LeaveRequest $l): string
    {
        return $l->status instanceof \UnitEnum ? $l->status->value : $l->status;
    }

    private function rejected(\Carbon\Carbon $leaveDate, string $notes): LeaveRequest
    {
        $user = User::where('status', \App\Enums\UserAccountStatus::ACTIVE)->firstOrFail();

        return LeaveRequest::create([
            'user_id'        => $user->id,
            'leave_type_id'  => LeaveType::firstOrFail()->id,
            'from_date'      => $leaveDate->toDateString(),
            'to_date'        => $leaveDate->toDateString(),
            'status'         => 'rejected',
            'rejected_at'    => now(),
            'approval_notes' => $notes,
            'user_notes'     => 'restore seeder test',
            'tenant_id'      => $user->tenant_id,
        ]);
    }
}
