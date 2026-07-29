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

/**
 * The NOC tracker: HR watches which departments still owe clearance for a
 * leaver, and each department signs off its own row. The exit is only closed
 * once every clearance is in.
 */
class ExitClearanceController extends Controller
{
    /** HR's tracker across all exits. */
    public function index(Request $request)
    {
        $this->authoriseView();

        $rows = ExitClearance::with(['user.department', 'clearedBy'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByRaw("FIELD(status,'pending','blocked','cleared')")
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn($r) => $r->exit_type . ':' . $r->exit_id);

        return view('tenant.employee-lifecycle.exit-clearances', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'groups'      => $rows,
            'pendingCount' => ExitClearance::pending()->count(),
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

    private function authoriseView(): void
    {
        $u = auth()->user();
        if (!$u || !$u->hasRole(['admin', 'Admin', 'hr', 'HR', 'accounts', 'manager'])) {
            abort(403);
        }
    }

    /**
     * Who may sign a given step: HR and admin can sign anything (they own the
     * process), otherwise the user must sit in that step's department.
     */
    private function authoriseStep(ExitClearance $clearance): void
    {
        $u = auth()->user();
        if (!$u) {
            abort(403);
        }
        if ($u->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
            return;
        }

        $step = config('hr_notifications.exit_clearances.' . $clearance->step_key);

        // The reporting-manager step is signed by that leaver's own manager,
        // resolved from the employee record rather than by department.
        if ($step && ($step['resolve'] ?? null) === 'reporting_manager') {
            if ($clearance->user && (int) $clearance->user->reporting_to_id === (int) $u->id) {
                return;
            }
            abort(403, 'Only this employee\'s reporting manager can sign this clearance.');
        }

        if ($step && !empty($step['department_id']) && (int) $u->department_id === (int) $step['department_id']) {
            return;
        }

        abort(403, 'This clearance belongs to another department.');
    }
}
