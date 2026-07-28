<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\ProbationEvaluation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProbationController extends Controller
{
    /**
     * Show the evaluation form to the manager.
     */
    public function showEvaluationForm($id)
    {
        $employee = User::findOrFail($id);
        
        // Ensure the logged-in user is the manager (directly, or as the acting
        // delegate of a vacant manager account), or has Admin/HR roles
        if (!in_array($employee->reporting_to_id, Auth::user()->managerIdentityIds()) && !Auth::user()->hasRole(['hr', 'admin', 'Admin', 'HR'])) {
             return redirect()->route('tenant.dashboard')->with('error', 'Unauthorized access to evaluation form.');
        }

        return view('tenant.employees.probation.evaluate', [
            'employee' => $employee,
            'manager' => Auth::user(),
        ]);
    }

    /**
     * Store the manager's evaluation.
     */
    public function storeEvaluation(Request $request, $id)
    {
        $employee = User::findOrFail($id);
        
        $request->validate([
            'job_knowledge' => 'required|string',
            'quality_of_work' => 'required|string',
            'attendance_punctuality' => 'required|string',
            'initiative_reliability' => 'required|string',
            'overall_performance' => 'required|string',
            'recommendation' => 'required|in:confirm,extend,terminate',
            'extension_months' => 'nullable|integer|required_if:recommendation,extend',
            'areas_for_improvement' => 'nullable|string',
            'manager_remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $evaluation = ProbationEvaluation::updateOrCreate(
                ['user_id' => $employee->id, 'hr_status' => 'pending'],
                [
                    'manager_id' => Auth::id(),
                    'tenant_id' => Auth::user()->tenant_id,
                    'job_knowledge' => $request->job_knowledge,
                    'quality_of_work' => $request->quality_of_work,
                    'attendance_punctuality' => $request->attendance_punctuality,
                    'initiative_reliability' => $request->initiative_reliability,
                    'overall_performance' => $request->overall_performance,
                    'recommendation' => $request->recommendation,
                    'extension_months' => $request->extension_months,
                    'areas_for_improvement' => $request->areas_for_improvement,
                    'manager_remarks' => $request->manager_remarks,
                    'submitted_at' => now(),
                ]
            );

            // Optional: Notify HR
            // $hrUsers = User::role('hr')->get();
            // Notification::send($hrUsers, new ProbationSubmitted($evaluation));

            DB::commit();

            return view('tenant.employees.probation.success', ['employee' => $employee]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Probation Evaluation Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to submit evaluation. Please try again.');
        }
    }

    /**
     * Probation control dashboard.
     *
     * The route and the blade both already existed, but this method never did,
     * so /probation/dashboard returned a 500. It renders stat tiles plus a
     * server-side DataTable fed by dashboardAjax() below.
     */
    public function dashboard()
    {
        return view('tenant.employees.probation.dashboard', [
            'statCounts'  => $this->probationStatCounts(),
            'departments' => \App\Models\Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Headline counts for the probation dashboard tiles.
     *
     * "on_probation" is genuinely in-flight (end date still ahead), while
     * "overdue" is the pile that matters operationally: the end date has
     * passed and nobody confirmed, extended or terminated the employee.
     */
    private function probationStatCounts(): array
    {
        $base = fn() => User::where('status', \App\Enums\UserAccountStatus::ACTIVE)
            ->whereNotNull('probation_end_date');

        return [
            'on_probation' => (clone $base())->whereNull('probation_confirmed_at')
                ->whereDate('probation_end_date', '>=', now()->toDateString())->count(),
            'overdue'      => (clone $base())->whereNull('probation_confirmed_at')
                ->whereDate('probation_end_date', '<', now()->toDateString())->count(),
            'confirmed'    => (clone $base())->whereNotNull('probation_confirmed_at')->count(),
            'extended'     => (clone $base())->where('is_probation_extended', true)->count(),
            'terminated'   => ProbationEvaluation::where('hr_decision', 'terminate')->count(),
        ];
    }

    /**
     * Server-side DataTable feed for the probation dashboard.
     */
    public function dashboardAjax(Request $request)
    {
        $query = User::query()
            ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
            ->whereNotNull('probation_end_date')
            ->with(['department:id,name'])
            // Latest evaluation per user drives the decision column.
            ->with(['probationEvaluations' => fn($q) => $q->latest('submitted_at')->limit(1)]);

        // Status filter mirrors the tiles.
        switch ($request->input('statusFilter')) {
            case 'on_probation':
                $query->whereNull('probation_confirmed_at')
                      ->whereDate('probation_end_date', '>=', now()->toDateString());
                break;
            case 'overdue':
                $query->whereNull('probation_confirmed_at')
                      ->whereDate('probation_end_date', '<', now()->toDateString());
                break;
            case 'confirmed':
                $query->whereNotNull('probation_confirmed_at');
                break;
            case 'extended':
                $query->where('is_probation_extended', true);
                break;
        }

        if ($request->filled('departmentFilter')) {
            $query->where('department_id', $request->input('departmentFilter'));
        }

        if ($search = $request->input('search.value')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        return \Yajra\DataTables\Facades\DataTables::eloquent($query)
            ->addColumn('name', fn($u) => $u->getFullName())
            ->addColumn('code', fn($u) => $u->code ?? '—')
            ->addColumn('profile_picture', fn($u) => $u->getProfilePicture())
            ->addColumn('department', fn($u) => optional($u->department)->name ?? '—')
            ->addColumn('doj', fn($u) => $u->date_of_joining
                ? \Carbon\Carbon::parse($u->date_of_joining)->format('d M Y') : '—')
            ->addColumn('period', fn($u) => $u->probation_period_months)
            ->addColumn('end_date', fn($u) => $u->probation_end_date
                ? \Carbon\Carbon::parse($u->probation_end_date)->format('d M Y') : null)
            ->addColumn('status_display', fn($u) => $u->probation_status_display)
            ->addColumn('decision_label', function ($u) {
                $eval = $u->probationEvaluations->first();
                if ($u->probation_confirmed_at) return 'Confirmed';
                if (!$eval)                      return 'No Decision Yet';
                if ($eval->hr_status !== 'approved') return 'Awaiting HR Decision';
                return match ($eval->hr_decision) {
                    'confirm'   => 'Confirmed',
                    'extend'    => 'Extended',
                    'terminate' => 'Terminated',
                    default     => 'Awaiting HR Decision',
                };
            })
            ->addColumn('evaluation_id', fn($u) => optional($u->probationEvaluations->first())->id)
            ->addColumn('decided_by', function ($u) {
                $eval = $u->probationEvaluations->first();
                return $eval && $eval->reviewed_by_id
                    ? optional(User::find($eval->reviewed_by_id))->getFullName() : null;
            })
            ->addColumn('decided_at', function ($u) {
                $eval = $u->probationEvaluations->first();
                return $eval && $eval->reviewed_at
                    ? \Carbon\Carbon::parse($eval->reviewed_at)->format('d M Y') : null;
            })
            ->rawColumns(['status_display'])
            ->make(true);
    }

    /**
     * List evaluations for HR.
     */
    public function index()
    {
        $evaluations = ProbationEvaluation::with(['user', 'manager'])
            ->latest('submitted_at')
            ->get();

        return view('tenant.employees.probation.index', compact('evaluations'));
    }

    /**
     * Show preview for HR to review.
     */
    public function review($id)
    {
        $evaluation = ProbationEvaluation::with(['user', 'manager'])->findOrFail($id);
        return view('tenant.employees.probation.hr_review', compact('evaluation'));
    }

    /**
     * Finalize probation status by HR.
     */
    public function finalize(Request $request, $id)
    {
        $evaluation = ProbationEvaluation::findOrFail($id);
        $user = $evaluation->user;

        $request->validate([
            'hr_decision' => 'required|in:confirm,extend,terminate',
            'hr_remarks' => 'nullable|string',
            'effective_date' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            $evaluation->update([
                'hr_status' => 'approved',
                'hr_decision' => $request->hr_decision,
                'hr_remarks' => $request->hr_remarks,
                'reviewed_at' => now(),
                'reviewed_by_id' => Auth::id(),
            ]);

            // Update user record based on decision
            if ($request->hr_decision === 'confirm') {
                $user->update([
                    'probation_confirmed_at' => now(),
                    // Optionally update status or other fields
                ]);
            } elseif ($request->hr_decision === 'extend') {
                $months = $evaluation->extension_months ?? 3;
                $currentEnd = \Carbon\Carbon::parse($user->probation_end_date);
                $user->update([
                    'probation_end_date' => $currentEnd->addMonths($months)->toDateString(),
                ]);
            } elseif ($request->hr_decision === 'terminate') {
                $user->update([
                    'status' => 'inactive',
                    'relieved_at' => $request->effective_date,
                ]);
            }

            DB::commit();
            return redirect()->route('probation.index')->with('success', 'Probation status finalized successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Probation Finalization Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to finalize probation.');
        }
    }
}
