<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\KpiCheck;
use App\Models\User;
use App\Models\WorkControlRoutine;
use App\Services\KpiPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class KpiController extends Controller
{
    /** Sales-department users are excluded from DWM-based KPI/KRA. */
    private function salesDepartmentIds(): array
    {
        return Department::where('name', 'like', '%Sales%')->pluck('id')->all();
    }

    /**
     * HR / Manager screen: pick a (non-sales) employee, view their DWM
     * routines and existing KPI/KRA assignments, add/edit/deactivate.
     */
    public function manage(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr', 'manager'])) {
            return redirect()->route('tenant.dashboard')->with('error', 'Unauthorized.');
        }

        $salesIds = $this->salesDepartmentIds();

        // Non-sales, non-terminated employees only
        $employeesQuery = User::notTerminated()->where(function ($q) use ($salesIds) {
            $q->whereNotIn('department_id', $salesIds)->orWhereNull('department_id');
        });

        // Managers see only their reports
        if ($user->hasRole('manager') && !$user->hasRole(['admin', 'hr'])) {
            $reportIds = User::where('reporting_to_id', $user->id)->pluck('id')->all();
            $employeesQuery = User::notTerminated()->whereIn('id', $reportIds)->whereNotIn('department_id', $salesIds);
        }

        $employees = $employeesQuery->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'code', 'department_id']);

        $selectedId = $request->input('user_id');
        $routines = collect();
        $assignments = collect();
        $selected = null;

        if ($selectedId) {
            $selected = User::find($selectedId);
            if ($selected) {
                $routines = WorkControlRoutine::where('user_id', $selectedId)->get();
                $assignments = KpiAssignment::with('routine')
                    ->where('user_id', $selectedId)
                    ->orderBy('category')
                    ->get();
            }
        }

        return view('tenant.kpi.manage', compact('employees', 'selected', 'routines', 'assignments'));
    }

    /** Create a KPI/KRA assignment (optionally from a DWM routine). */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr', 'manager'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'work_control_routine_id' => 'nullable|exists:work_control_routines,id',
            'title' => 'required|string|max:255',
            'category' => 'required|in:KPI,KRA',
            'frequency' => 'required|in:daily,weekly,fortnightly,monthly,quarterly,yearly',
            'scoring_type' => 'required|in:timeline,target',
            'max_marks' => 'required|numeric|min:0',
            'late_score' => 'nullable|numeric|min:0',
            'target_value' => 'nullable|numeric|min:0',
            'check_day' => 'nullable|integer|min:1|max:31',
            'check_month' => 'nullable|integer|min:1|max:12',
        ]);

        if ($data['scoring_type'] === 'target' && empty($data['target_value'])) {
            return response()->json(['status' => 'error', 'message' => 'Target value is required for target-based scoring.'], 422);
        }

        $target = User::find($data['user_id']);
        $data['tenant_id'] = $target->tenant_id;
        $data['late_score'] = $data['late_score'] ?? 0;
        $data['is_active'] = true;
        $data['created_by_id'] = $user->id;

        KpiAssignment::create($data);

        return response()->json(['status' => 'success', 'message' => 'KPI/KRA added successfully.']);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr', 'manager'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $assignment = KpiAssignment::findOrFail($id);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:KPI,KRA',
            'frequency' => 'required|in:daily,weekly,fortnightly,monthly,quarterly,yearly',
            'scoring_type' => 'required|in:timeline,target',
            'max_marks' => 'required|numeric|min:0',
            'late_score' => 'nullable|numeric|min:0',
            'target_value' => 'nullable|numeric|min:0',
            'check_day' => 'nullable|integer|min:1|max:31',
            'check_month' => 'nullable|integer|min:1|max:12',
            'is_active' => 'nullable|boolean',
        ]);
        $data['late_score'] = $data['late_score'] ?? 0;
        $assignment->update($data);

        return response()->json(['status' => 'success', 'message' => 'KPI/KRA updated.']);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr', 'manager'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }
        KpiAssignment::where('id', $id)->update(['is_active' => false]);
        return response()->json(['status' => 'success', 'message' => 'KPI/KRA deactivated.']);
    }

    /**
     * Assign a DWM routine as a KRA/KPI straight from the employee page.
     * Frequency (timeline) is taken from the routine; the picked check date
     * sets when within each period it is evaluated. category "none" removes it.
     */
    public function assignFromRoutine(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr', 'manager'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'work_control_routine_id' => 'required|exists:work_control_routines,id',
            'category' => 'required|in:KRA,KPI,none',
            'check_date' => 'nullable|date',
        ]);

        $routine = WorkControlRoutine::findOrFail($data['work_control_routine_id']);

        if ($data['category'] === 'none') {
            KpiAssignment::where('work_control_routine_id', $routine->id)
                ->where('user_id', $data['user_id'])->update(['is_active' => false]);
            return response()->json(['status' => 'success', 'message' => 'Removed from KPI/KRA.']);
        }

        // Derive check_day / check_month from the chosen date, per frequency.
        $checkDay = null; $checkMonth = null;
        if (!empty($data['check_date'])) {
            $d = \Carbon\Carbon::parse($data['check_date']);
            switch ($routine->frequency) {
                case 'weekly':
                case 'fortnightly': $checkDay = $d->dayOfWeekIso; break;        // 1-7
                case 'monthly':     $checkDay = $d->day; break;                 // 1-31
                case 'quarterly':   $checkDay = $d->day; $checkMonth = (($d->month - 1) % 3) + 1; break;
                case 'yearly':      $checkDay = $d->day; $checkMonth = $d->month; break;
            }
        }

        $target = User::find($data['user_id']);
        KpiAssignment::updateOrCreate(
            ['work_control_routine_id' => $routine->id, 'user_id' => $data['user_id']],
            [
                'tenant_id'    => $target->tenant_id,
                'title'        => $routine->title,
                'category'     => $data['category'],
                'frequency'    => $routine->frequency,
                'scoring_type' => 'timeline',
                'max_marks'    => 5,
                'late_score'   => 0,
                'check_day'    => $checkDay,
                'check_month'  => $checkMonth,
                'is_active'    => true,
                'created_by_id'=> $user->id,
            ]
        );

        return response()->json(['status' => 'success', 'message' => strtoupper($data['category']) . ' assigned (checked ' . $routine->frequency . ').']);
    }

    /** HR/Manager records an achieved value for a target KPI's current period. */
    public function evaluate(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr', 'manager'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'achieved_value' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);
        $assignment = KpiAssignment::findOrFail($id);
        $check = KpiPeriodService::recordAchievement($assignment, (float)$data['achieved_value'], $user->id, $data['remarks'] ?? null);

        return response()->json(['status' => 'success', 'message' => 'Achievement recorded.', 'score' => $check->score]);
    }

    /** Employee's own KPI/KRA dashboard. */
    public function my(Request $request)
    {
        $user = Auth::user();
        $assignments = KpiPeriodService::syncUser($user->id);

        // Score summary for the current fiscal year
        $history = KpiCheck::with('assignment')
            ->where('user_id', $user->id)
            ->orderByDesc('period_start')
            ->limit(200)
            ->get();

        $earned = $history->whereNotNull('score')->sum('score');
        $possible = $assignments->sum('max_marks');

        return view('tenant.kpi.my', compact('assignments', 'history', 'earned', 'possible'));
    }

    /** Employee marks a timeline task done for the current period. */
    public function complete(Request $request, $id)
    {
        $user = Auth::user();
        $assignment = KpiAssignment::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        if ($assignment->scoring_type !== 'timeline') {
            return response()->json(['status' => 'error', 'message' => 'This KPI is target-based; achievement is recorded by HR/Manager.'], 422);
        }
        $check = KpiPeriodService::markCompleted($assignment, $request->input('remarks'));

        return response()->json([
            'status' => 'success',
            'message' => $check->status === 'completed_on_time' ? 'Marked complete (on time).' : 'Marked complete (late).',
            'check_status' => $check->status,
            'score' => $check->score,
        ]);
    }
}
