<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\EmployeeResignation;
use App\Models\EmployeeTermination;
use App\Models\ExitClearance;
use App\Services\HrNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The NOC tracker: HR watches which departments still owe clearance for a
 * leaver, and each department signs off its own row. The exit is only closed
 * once every clearance is in.
 */
class ExitClearanceController extends Controller
{
    /** HR's tracker across all exits; everyone else sees only their own steps. */
    public function index(Request $request)
    {
        $user       = auth()->user();
        $isOverseer = $user && $user->hasRole(['admin', 'Admin', 'hr', 'HR']);

        if ($isOverseer) {
            // HR / admin: full board — every exit, every step.
            $rows = ExitClearance::with(['user.department', 'clearedBy'])
                ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
                ->orderByRaw("FIELD(status,'pending','blocked','cleared')")
                ->orderByDesc('created_at')
                ->get();

            $groups       = $rows->groupBy(fn($r) => $r->exit_type . ':' . $r->exit_id);
            $pendingCount = ExitClearance::pending()->count();
            $myHistory    = collect();
            $progressMap  = [];
        } else {
            // Department signatory / reporting manager: load everything, then
            // keep ONLY the rows they can actually sign — not all five steps for
            // every exit they appear in. This is the security fix: IT should not
            // see HR's or Finance's clearance rows.
            $allRows = ExitClearance::with(['user.department', 'clearedBy'])
                ->orderByRaw("FIELD(status,'pending','blocked','cleared')")
                ->orderByDesc('created_at')
                ->get();

            $myRows = $allRows->filter(fn($r) => $r->isSignableBy($user));

            if ($myRows->isEmpty()) {
                abort(403, 'You have no clearances to action.');
            }

            // Split into pending/blocked (needs action) vs already cleared (history).
            $myPending = $myRows->filter(fn($r) => $r->status !== 'cleared');
            $myHistory = $myRows->filter(fn($r) => $r->status === 'cleared')
                ->sortByDesc('cleared_at')
                ->values();

            $groups       = $myPending->groupBy(fn($r) => $r->exit_type . ':' . $r->exit_id);
            $pendingCount = $myPending->count();

            // Overall progress per exit (all departments, not just this user's
            // step) so a signatory can see "2 of 4 cleared" for the full exit.
            $progressMap = [];
            foreach ($groups as $key => $_) {
                [$exitType, $exitId] = explode(':', $key, 2);
                $progressMap[$key] = ExitClearance::progress($exitType, (int) $exitId);
            }
        }

        return view('tenant.employee-lifecycle.exit-clearances', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'groups'      => $groups,
            'pendingCount' => $pendingCount,
            'myHistory'   => $myHistory,
            'isOverseer'  => $isOverseer,
            'progressMap' => $progressMap,
        ]);
    }

    /**
     * Full NOC detail for one exit — all department steps, who signed what, when.
     *
     * HR/admin see everything. A department signatory may view any exit they have
     * a step in (they should be able to see the overall progress, not just their
     * own row). Anyone else gets a 403.
     */
    public function show(Request $request, string $type, int $exitId)
    {
        abort_unless(in_array($type, ['termination', 'resignation'], true), 404);

        $user       = auth()->user();
        $isOverseer = $user && $user->hasRole(['admin', 'Admin', 'hr', 'HR']);

        $rows = ExitClearance::with(['clearedBy'])
            ->forExit($type, $exitId)
            ->orderByRaw("FIELD(step_key,'it','hr','accounts','finance','reporting_manager')")
            ->get();

        if ($rows->isEmpty()) {
            abort(404, 'No clearance record found for this exit.');
        }

        // Non-overseers must have at least one step they can sign (or have signed).
        if (!$isOverseer) {
            $canView = $rows->contains(fn($r) => $r->isSignableBy($user));
            abort_unless($canView, 403, 'You are not a signatory for this exit.');
        }

        // Load the underlying exit record for context (dates, reason, type label).
        $exitRecord = $type === 'termination'
            ? EmployeeTermination::with('user.department')->find($exitId)
            : EmployeeResignation::with('user.department')->find($exitId);

        // Leaver from either the exit record or the first clearance row.
        $leaver = optional($exitRecord)->user ?? optional($rows->first())->user;

        $progress = ExitClearance::progress($type, $exitId);

        return view('tenant.employee-lifecycle.exit-clearance-detail', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'type'        => $type,
            'exitId'      => $exitId,
            'exitRecord'  => $exitRecord,
            'leaver'      => $leaver,
            'rows'        => $rows,
            'progress'    => $progress,
            'isOverseer'  => $isOverseer,
            'currentUser' => $user,
        ]);
    }

    /**
     * Sign off (or block) one department's clearance.
     *
     * When the last one lands, the underlying resignation/termination is
     * marked approved — that is the only thing gated on full clearance; the
     * exit itself took effect when HR logged it.
     */
    public function update(Request $request, $id)
    {
        $clearance = ExitClearance::with('user')->findOrFail($id);
        $this->authoriseStep($clearance);

        $data = $request->validate([
            'status'  => 'required|in:cleared,blocked,pending',
            'remarks' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $clearance->status  = $data['status'];
            $clearance->remarks = $data['remarks'] ?? null;

            if ($data['status'] === 'cleared') {
                $clearance->cleared_by_id = auth()->id();
                $clearance->cleared_at    = now();
            } else {
                $clearance->cleared_by_id = null;
                $clearance->cleared_at    = null;
            }
            $clearance->save();

            $allDone = ExitClearance::allCleared($clearance->exit_type, $clearance->exit_id);
            if ($allDone) {
                $this->closeExit($clearance->exit_type, $clearance->exit_id);
            }

            DB::commit();

            // Keep everyone updated on where the clearance stands.
            if ($clearance->user) {
                HrNotificationService::notifyExit(
                    $clearance->user, $clearance->exit_type, $clearance->exit_id
                );
            }

            $p = ExitClearance::progress($clearance->exit_type, $clearance->exit_id);

            return response()->json([
                'success'  => true,
                'message'  => $allDone
                    ? 'All clearances received — the exit is now closed.'
                    : 'Clearance updated. ' . $p['label'] . '.',
                'progress' => $p,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Exit clearance {$id} update failed: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Could not update. Please try again.'], 500);
        }
    }

    /** Mark the underlying exit record approved once every NOC is in. */
    private function closeExit(string $type, int $id): void
    {
        $model = $type === 'termination'
            ? EmployeeTermination::find($id)
            : EmployeeResignation::find($id);

        if ($model && $model->status !== 'approved') {
            $model->status = 'approved';
            $model->save();
        }
    }

    /**
     * Who may sign a given step: HR and admin can sign anything (they own the
     * process), a department signatory signs their department's step, and a
     * leaver's reporting manager signs the reporting-manager step whatever
     * role they hold. The rule itself lives on the model so the tracker, this
     * endpoint and My Tasks cannot drift apart.
     */
    private function authoriseStep(ExitClearance $clearance): void
    {
        if (!$clearance->isSignableBy(auth()->user())) {
            abort(403, 'This clearance is for someone else to sign.');
        }
    }
}
