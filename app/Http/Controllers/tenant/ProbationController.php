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
        
        // Ensure the logged-in user is the manager
        if (Auth::id() !== $employee->reporting_to_id && !Auth::user()->hasRole('hr')) {
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
