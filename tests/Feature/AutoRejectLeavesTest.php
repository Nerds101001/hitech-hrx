<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * leave:auto-reject-expired
 *
 * This command filtered on `is_adjustment`, which is not a column on
 * leave_requests and never has been — it is a presentation flag on the
 * in-memory rows LeaveHistoryService builds. The query threw "Unknown column"
 * on every scheduled run, so no leave was ever auto-rejected in production.
 */
class AutoRejectLeavesTest extends TestCase
{
    use DatabaseTransactions;

    /** The regression itself: the command must run against the real schema. */
    public function test_the_command_runs_without_a_database_error(): void
    {
        Mail::fake();

        $this->assertFalse(
            Schema::hasColumn('leave_requests', 'is_adjustment'),
            'is_adjustment is not a column — if this ever becomes one, revisit the command'
        );

        $this->artisan('leave:auto-reject-expired')->assertExitCode(0);
    }

    public function test_it_rejects_a_leave_pending_beyond_48_hours(): void
    {
        Mail::fake();

        $leave = $this->makeLeave(now()->subHours(72));

        $this->artisan('leave:auto-reject-expired')->assertExitCode(0);

        $leave->refresh();
        $this->assertSame('rejected', $leave->status instanceof \UnitEnum ? $leave->status->value : $leave->status);
        $this->assertNotNull($leave->rejected_at);
        $this->assertStringContainsString('Auto-rejected', (string) $leave->approval_notes);
    }

    public function test_it_leaves_a_recent_request_alone(): void
    {
        Mail::fake();

        $leave = $this->makeLeave(now()->subHours(2));

        $this->artisan('leave:auto-reject-expired')->assertExitCode(0);

        $leave->refresh();
        $this->assertSame('pending', $leave->status instanceof \UnitEnum ? $leave->status->value : $leave->status);
    }

    /** Past-dated leave stays pending: the employee has very likely taken it. */
    public function test_it_leaves_a_past_dated_request_pending(): void
    {
        Mail::fake();

        $leave = $this->makeLeave(now()->subDays(10), now()->subDays(3));

        $this->artisan('leave:auto-reject-expired')->assertExitCode(0);

        $leave->refresh();
        $this->assertSame('pending', $leave->status instanceof \UnitEnum ? $leave->status->value : $leave->status);
    }

    /** Leave starting today has begun — also a human decision. */
    public function test_it_leaves_a_request_starting_today_pending(): void
    {
        Mail::fake();

        $leave = $this->makeLeave(now()->subDays(5), now());

        $this->artisan('leave:auto-reject-expired')->assertExitCode(0);

        $leave->refresh();
        $this->assertSame('pending', $leave->status instanceof \UnitEnum ? $leave->status->value : $leave->status);
    }

    private function makeLeave(\Carbon\Carbon $createdAt, ?\Carbon\Carbon $leaveDate = null): LeaveRequest
    {
        $user = User::where('status', \App\Enums\UserAccountStatus::ACTIVE)->firstOrFail();
        $type = LeaveType::firstOrFail();

        $leave = LeaveRequest::create([
            'user_id'       => $user->id,
            'leave_type_id' => $type->id,
            'from_date'     => ($leaveDate ?? now()->addDays(5))->toDateString(),
            'to_date'       => ($leaveDate ?? now()->addDays(5))->toDateString(),
            'status'        => 'pending',
            'user_notes'    => 'auto-reject regression test',
            'tenant_id'     => $user->tenant_id,
        ]);

        // created_at is not fillable; set it directly so the age is what we want.
        $leave->created_at = $createdAt;
        $leave->saveQuietly();

        return $leave;
    }
}
