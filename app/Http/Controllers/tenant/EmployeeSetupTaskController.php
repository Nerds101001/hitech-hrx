<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSetupTask;
use App\Models\User;
use App\Services\HrNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The attendance-setup queue accounts works through: record each new joiner's
 * employee ID and (unless exempt) biometric ID so payroll and attendance can
 * begin. Drives the login popup and the dashboard task card.
 */
class EmployeeSetupTaskController extends Controller
{
    /** Only accounts (and admin/HR, who oversee them) may action these. */
    private function authorise(): void
    {
        $u = auth()->user();
        if (!$u || !$u->hasRole(['accounts', 'admin', 'Admin', 'hr', 'HR'])) {
            abort(403);
        }
    }

    /**
     * Outstanding tasks for the popup. Called on every page load by an
     * accounts user until the queue is clear.
     */
    public function pending()
    {
        $this->authorise();

        $tasks = EmployeeSetupTask::pending()
            ->with(['user.department', 'user.designation'])
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'user_id'      => $t->user_id,
                'name'         => optional($t->user)->getFullName(),
                'photo'        => optional($t->user)->getProfilePicture(),
                'designation'  => optional(optional($t->user)->designation)->name,
                'department'   => optional(optional($t->user)->department)->name,
                'joined'       => optional($t->user)->date_of_joining
                    ? \Carbon\Carbon::parse($t->user->date_of_joining)->format('d M Y') : null,
                'needs_emp_id'    => (bool) $t->needs_emp_id,
                'needs_biometric' => (bool) $t->needs_biometric,
                'current_emp_id'  => optional($t->user)->code,
                'requirement'     => $t->requirement_label,
            ]);

        return response()->json(['count' => $tasks->count(), 'tasks' => $tasks]);
    }

    /**
     * Record the details and close the task.
     *
     * Employee ID is always required. Biometric is required only when this
     * employee's task asks for it — field departments are exempt.
     */
    public function complete(Request $request, $id)
    {
        $this->authorise();

        $task = EmployeeSetupTask::with('user')->findOrFail($id);

        if ($task->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'This setup is already complete.'], 422);
        }

        $rules = [];
        if ($task->needs_emp_id) {
            // Employee codes must stay unique — payroll keys off them.
            $rules['emp_id'] = 'required|string|max:50|unique:users,code,' . $task->user_id;
        }
        if ($task->needs_biometric) {
            $rules['biometric_id'] = 'required|string|max:50';
        }
        $rules['notes'] = 'nullable|string|max:500';

        $data = $request->validate($rules, [
            'emp_id.required'       => 'Employee ID is required.',
            'emp_id.unique'         => 'That Employee ID is already assigned to someone else.',
            'biometric_id.required' => 'Biometric ID is required for this employee.',
        ]);

        DB::beginTransaction();
        try {
            $user = $task->user;

            if ($task->needs_emp_id && !empty($data['emp_id'])) {
                $user->code = trim($data['emp_id']);
                $task->emp_id_value = trim($data['emp_id']);
            }
            if ($task->needs_biometric && !empty($data['biometric_id'])) {
                $user->biometric_id = trim($data['biometric_id']);
                $task->biometric_id_value = trim($data['biometric_id']);
            }
            $user->save();

            $task->status          = 'completed';
            $task->completed_by_id = auth()->id();
            $task->completed_at    = now();
            $task->notes           = $data['notes'] ?? null;
            $task->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attendance setup completed for ' . $user->getFullName() . '.',
                'remaining' => EmployeeSetupTask::pending()->count(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Setup task {$id} failed: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Could not save. Please try again.'], 500);
        }
    }

    /** Full queue for the dashboard card / list view. */
    public function index()
    {
        $this->authorise();

        return view('tenant.employees.setup-tasks', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'tasks' => EmployeeSetupTask::with(['user.department', 'user.designation', 'completedBy'])
                ->orderByRaw("FIELD(status,'pending','completed')")
                ->orderByDesc('created_at')
                ->paginate(30),
            'pendingCount' => EmployeeSetupTask::pending()->count(),
        ]);
    }
}
