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
    /** HR's tracker across all exits; everyone else sees only their own steps. */
    public function index(Request $request)
    {
        $user = auth()->user();

        $rows = ExitClearance::with(['user.department', 'clearedBy'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByRaw("FIELD(status,'pending','blocked','cleared')")
            ->orderByDesc('created_at')
            ->get();

        // HR and admin own the process and see every exit. Anyone else — a
        // department signatory or a leaver's reporting manager — sees only the
        // exits they actually have a step in; an exit is confidential to the
        // people handling it.
        $isOverseer = $user && $user->hasRole(['admin', 'Admin', 'hr', 'HR']);
        if (!$isOverseer) {
            $mineKeys = $rows->filter(fn($r) => $r->isSignableBy($user))
                ->map(fn($r) => $r->exit_type . ':' . $r->exit_id)
                ->unique()->all();

            // Nothing to sign and no oversight role — nothing to show.
            if (empty($mineKeys)) {
                abort(403, 'You have no clearances to action.');
            }

            $rows = $rows->filter(
                fn($r) => in_array($r->exit_type . ':' . $r->exit_id, $mineKeys, true)
            );
        }

        $rows = $rows->groupBy(fn($r) => $r->exit_type . ':' . $r->exit_id);

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
