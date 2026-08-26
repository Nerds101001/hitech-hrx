<?php

namespace Database\Seeders;

use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Puts back the leave requests that leave:auto-reject-expired rejected even
 * though their date had already passed.
 *
 * The command used to reject any request pending beyond 48 hours regardless of
 * when the leave itself fell. For a date already gone, the employee had very
 * likely taken the day, so the rejection marked them absent for a day nobody
 * had actually reviewed — and payroll follows the rejection. The command now
 * only touches leave that has not started yet; this restores the ones it
 * caught before that rule existed.
 *
 * Selected by condition rather than by id: auto-rejected, still rejected, and
 * dated on or before today. Under the new rule no such row can be created
 * again, so the set is exactly the mistakes. Future-dated auto-rejections are
 * deliberately left alone — those were correct.
 *
 * Idempotent: re-running finds nothing once the rows are back to pending.
 */
class RestorePastDatedAutoRejectedLeavesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = LeaveRequest::with(['user', 'leaveType'])
            ->where('status', 'rejected')
            ->where('approval_notes', 'like', 'Auto-rejected%')
            ->whereDate('from_date', '<=', Carbon::today())
            ->get();

        if ($rows->isEmpty()) {
            $this->command?->info('Nothing to restore — no past-dated auto-rejections found.');
            return;
        }

        $this->command?->info("Restoring {$rows->count()} past-dated auto-rejected request(s) to pending:");

        foreach ($rows as $leave) {
            $leave->status          = 'pending';
            $leave->rejected_at     = null;
            $leave->rejected_by_id  = null;
            // Left visible on purpose: the manager needs to know why this is
            // back in their queue rather than finding it silently reappear.
            $leave->approval_notes  = 'Auto-rejection reversed: the leave date had already passed, so this needs a manual decision.';
            $leave->save();   // not saveQuietly — the audit trail is wanted here

            $name = trim(($leave->user->first_name ?? '?') . ' ' . ($leave->user->last_name ?? ''));
            $this->command?->line(sprintf(
                '  #%s  %-22s  %-6s  %s to %s',
                $leave->id, $name, $leave->leaveType->code ?? '?', $leave->from_date, $leave->to_date
            ));
            Log::info("LEAVE_AUTOREJECT_REVERSED: request {$leave->id} ({$name}) restored to pending");
        }

        $this->command?->info('Done. These are pending again and await a manager decision.');
    }
}
