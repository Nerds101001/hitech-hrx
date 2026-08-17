<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\KpiCheck;
use App\Models\User;
use App\Models\KpiTemplate;
use App\Models\WorkControlRoutine;
use App\Services\KpiPeriodService;
use App\Services\KpiTemplateService;
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

    // ── Department templates ──────────────────────────────────────────────
    // A KPI/KRA is defined once for a department and stamped onto everyone in
    // it, instead of an admin repeating the same row per employee. Unlike
    // manage(), this covers EVERY department including sales — the exclusion
    // there is about DWM routines, not about who can hold a scorecard.

    private function assertCanManageTemplates(): ?string
    {
        return Auth::user()->hasRole(['admin', 'hr']) ? null : 'Unauthorized.';
    }

    public function templates(Request $request)
    {
        if ($msg = $this->assertCanManageTemplates()) {
            return redirect()->route('tenant.dashboard')->with('error', $msg);
        }

        $departmentId = $request->input('department_id');

        $templates = KpiTemplate::with(['department', 'parent'])
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('department_id')->orderBy('parent_template_id')->orderBy('category', 'desc')->orderBy('id')
            ->get();

        // How many employees each template currently covers, for the UI count.
        $assignmentCounts = KpiAssignment::whereNotNull('kpi_template_id')
            ->selectRaw('kpi_template_id, COUNT(*) c, SUM(is_active) active')
            ->groupBy('kpi_template_id')->get()->keyBy('kpi_template_id');

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $kras = KpiTemplate::where('category', 'KRA')->orderBy('title')->get(['id', 'title', 'department_id']);
        $approvers = User::notTerminated()->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return view('tenant.kpi.templates', compact(
            'templates', 'departments', 'kras', 'approvers', 'departmentId', 'assignmentCounts'
        ));
    }

    private function templateRules(): array
    {
        return [
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string|max:255',
            'category'           => 'required|in:KPI,KRA',
            'parent_template_id' => 'nullable|exists:kpi_templates,id',
            'department_id'      => 'nullable|exists:departments,id',
            'frequency'          => 'required|in:daily,weekly,fortnightly,monthly,quarterly,yearly',
            'scoring_type'       => 'required|in:timeline,target,threshold,banded',
            'max_marks'          => 'required|numeric|min:0',
            'target_value'       => 'nullable|numeric',
            'threshold_percent'  => 'nullable|numeric|min:0|max:1000',
            'bands'              => 'nullable|array',
            'bands.*.min'        => 'required_with:bands|numeric|min:0',
            'bands.*.marks'      => 'required_with:bands|numeric|min:0',
            'late_policy'        => 'required|in:none,fixed,percent,per_day',
            'late_score'         => 'nullable|numeric|min:0',
            'late_percent'       => 'nullable|numeric|min:0|max:100',
            'grace_days'         => 'nullable|integer|min:0|max:60',
            'per_day_penalty'    => 'nullable|numeric|min:0',
            'data_source'        => 'nullable|string|max:40',
            'approver_id'        => 'nullable|exists:users,id',
            'check_day'          => 'nullable|integer|min:1|max:31',
            'check_month'        => 'nullable|integer|min:1|max:12',
        ];
    }

    /** Reject combinations the scoring engine cannot act on. */
    private function templateConsistencyError(array $d): ?string
    {
        $needsTarget = in_array($d['scoring_type'], ['target', 'threshold', 'banded'], true);
        if ($needsTarget && $d['category'] === 'KPI' && ($d['target_value'] ?? null) === null) {
            return 'A target value is required for target, threshold and banded scoring.';
        }
        if ($d['scoring_type'] === 'threshold' && ($d['threshold_percent'] ?? null) === null) {
            return 'Threshold scoring needs the % of target that earns full marks.';
        }
        if ($d['scoring_type'] === 'banded' && empty($d['bands'])) {
            return 'Banded scoring needs at least one band.';
        }
        if ($d['late_policy'] === 'percent' && ($d['late_percent'] ?? null) === null) {
            return 'A late percentage is required for the percent late policy.';
        }
        if ($d['late_policy'] === 'per_day' && ($d['per_day_penalty'] ?? null) === null) {
            return 'A per-day penalty is required for the per-day late policy.';
        }
        return null;
    }

    public function storeTemplate(Request $request)
    {
        if ($msg = $this->assertCanManageTemplates()) {
            return response()->json(['status' => 'error', 'message' => $msg], 403);
        }
        $data = $request->validate($this->templateRules());
        if ($err = $this->templateConsistencyError($data)) {
            return response()->json(['status' => 'error', 'message' => $err], 422);
        }

        $data['tenant_id'] = Auth::user()->tenant_id;
        $data['created_by_id'] = Auth::id();
        $data['is_active'] = true;
        // A KRA is a heading: it holds no marks and no schedule of its own.
        if ($data['category'] === 'KRA') {
            $data['max_marks'] = 0;
            $data['parent_template_id'] = null;
        }

        $t = KpiTemplate::create($data);

        return response()->json([
            'status' => 'success',
            'message' => $data['category'] . ' created. Use Apply to give it to the department.',
            'id' => $t->id,
        ]);
    }

    public function updateTemplate(Request $request, $id)
    {
        if ($msg = $this->assertCanManageTemplates()) {
            return response()->json(['status' => 'error', 'message' => $msg], 403);
        }
        $t = KpiTemplate::findOrFail($id);
        $data = $request->validate($this->templateRules());
        if ($err = $this->templateConsistencyError($data)) {
            return response()->json(['status' => 'error', 'message' => $err], 422);
        }
        if ($data['category'] === 'KRA') {
            $data['max_marks'] = 0;
            $data['parent_template_id'] = null;
        }
        // A template cannot be its own parent.
        if (($data['parent_template_id'] ?? null) == $t->id) {
            $data['parent_template_id'] = null;
        }

        $t->update($data);

        // Existing assignments follow the edit, so a weight change does not
        // leave half the department scored on the old number.
        $res = KpiTemplateService::apply($t, true);

        return response()->json([
            'status' => 'success',
            'message' => "Saved. {$res['updated']} existing assignment(s) refreshed, {$res['created']} added.",
        ]);
    }

    public function destroyTemplate($id)
    {
        if ($msg = $this->assertCanManageTemplates()) {
            return response()->json(['status' => 'error', 'message' => $msg], 403);
        }
        $t = KpiTemplate::findOrFail($id);

        // Assignments are deactivated, never deleted: their kpi_checks hold
        // scores that people have already been measured on.
        $deactivated = KpiAssignment::where('kpi_template_id', $t->id)->update(['is_active' => false]);
        $t->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => "Template retired. {$deactivated} assignment(s) deactivated; past scores kept.",
        ]);
    }

    /** Stamp the template onto every employee in its department. */
    public function applyTemplate(Request $request, $id)
    {
        if ($msg = $this->assertCanManageTemplates()) {
            return response()->json(['status' => 'error', 'message' => $msg], 403);
        }
        $t = KpiTemplate::findOrFail($id);
        if ($t->category === 'KRA') {
            return response()->json([
                'status' => 'error',
                'message' => 'A KRA is a heading — apply the KPIs underneath it instead.',
            ], 422);
        }

        $res = KpiTemplateService::apply($t, true);
        $pruned = KpiTemplateService::prune($t);

        return response()->json([
            'status' => 'success',
            'message' => "Applied: {$res['created']} added, {$res['updated']} refreshed"
                . ($pruned ? ", {$pruned} deactivated (left the department)." : '.'),
        ]);
    }
}
