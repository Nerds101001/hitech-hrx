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
use App\Services\KpiEntryService;
use App\Services\KpiScorecardService;
use App\Services\KpiTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class KpiController extends Controller
{
    /**
     * Sales-floor users are excluded from DWM-based KPI/KRA -- they're
     * scored through the department-template auto-sourced KPIs instead
     * (templates(), which covers every department including these).
     *
     * Matches the same substring set already used elsewhere for "is this a
     * sales-floor department" (SalesScope::isRep(), config/hr_notifications.
     * php's biometric_exempt_departments) rather than a plain '%Sales%'
     * LIKE, which missed "New Biz" outright -- confirmed live: a New Biz
     * employee showed up in this screen's picker. Nobody had actually
     * assigned them a DWM/KRA through it yet, but the gap was real.
     */
    private function salesDepartmentIds(): array
    {
        $patterns = ['sales', 'new biz', 'new business', 'customer care'];

        return Department::all(['id', 'name'])
            ->filter(function ($d) use ($patterns) {
                $name = strtolower($d->name);
                foreach ($patterns as $p) {
                    if (str_contains($name, $p)) {
                        return true;
                    }
                }
                return false;
            })
            ->pluck('id')->all();
    }

    /**
     * HR / Manager screen: pick a (non-sales) employee, view their DWM
     * routines and existing KPI/KRA assignments, add/edit/deactivate.
     */
    public function manage(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr', 'manager']) && !$user->can('kpi.manage')) {
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
                $assignments = KpiAssignment::with(['routine', 'template'])
                    ->where('user_id', $selectedId)
                    ->orderBy('category')
                    ->get();
            }
        }

        return view('tenant.kpi.manage', compact('employees', 'selected', 'routines', 'assignments'));
    }

    /**
     * The same limit manage() puts on the employee list: a manager (who is not
     * also admin/HR) works only on their direct reports. The write actions used
     * to check the role alone, so a manager could add, change, retire or score
     * any employee's KPIs by posting another id.
     */
    private function mayManageKpisOf($user, $targetUserId): bool
    {
        if (!$user->hasRole('manager') || $user->hasRole(['admin', 'hr'])) {
            return true;
        }

        return User::where('id', $targetUserId)->where('reporting_to_id', $user->id)->exists();
    }

    /** Create a KPI/KRA assignment (optionally from a DWM routine). */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr', 'manager']) && !$user->can('kpi.manage')) {
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

        if (!$this->mayManageKpisOf($user, $data['user_id'])) {
            return response()->json(['status' => 'error', 'message' => 'You can only manage KPIs of your own team.'], 403);
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
        if (!$user->hasRole(['admin', 'hr', 'manager']) && !$user->can('kpi.manage')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $assignment = KpiAssignment::findOrFail($id);
        if (!$this->mayManageKpisOf($user, $assignment->user_id)) {
            return response()->json(['status' => 'error', 'message' => 'You can only manage KPIs of your own team.'], 403);
        }
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
        if (!$user->hasRole(['admin', 'hr', 'manager']) && !$user->can('kpi.manage')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }
        $assignment = KpiAssignment::findOrFail($id);
        if (!$this->mayManageKpisOf($user, $assignment->user_id)) {
            return response()->json(['status' => 'error', 'message' => 'You can only manage KPIs of your own team.'], 403);
        }
        $assignment->update(['is_active' => false]);
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
        if (!$user->hasRole(['admin', 'hr', 'manager']) && !$user->can('kpi.manage')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'work_control_routine_id' => 'required|exists:work_control_routines,id',
            'category' => 'required|in:KRA,KPI,none',
            'check_date' => 'nullable|date',
        ]);

        if (!$this->mayManageKpisOf($user, $data['user_id'])) {
            return response()->json(['status' => 'error', 'message' => 'You can only manage KPIs of your own team.'], 403);
        }

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
        if (!$user->hasRole(['admin', 'hr', 'manager']) && !$user->can('kpi.manage')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'achieved_value' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);
        $assignment = KpiAssignment::findOrFail($id);
        if (!$this->mayManageKpisOf($user, $assignment->user_id)) {
            return response()->json(['status' => 'error', 'message' => 'You can only manage KPIs of your own team.'], 403);
        }
        $check = KpiPeriodService::recordAchievement($assignment, (float)$data['achieved_value'], $user->id, $data['remarks'] ?? null);

        return response()->json(['status' => 'success', 'message' => 'Achievement recorded.', 'score' => $check->score]);
    }

    /**
     * The read-only KPI/KRA matrix data for one user - one row per KPI, one
     * column-pair per fiscal-year-to-date month. Shared by the employee's own
     * "My Scorecard" (my(), which syncs the CURRENT user and adds the
     * editable weekly-reporting section on top) and the admin's read-only
     * report for an arbitrary employee (SalesAssessmentController::show()),
     * so the two pages can never drift apart on how a cell is computed.
     *
     * $sync controls whether this call is allowed to create/update this
     * user's current-period KpiCheck rows (KpiPeriodService::syncUser()) -
     * true for the user's own live dashboard, false for an admin browsing
     * many employees' listing rows where N syncs per page load would be
     * needless write traffic for numbers nobody is about to act on.
     */
    public static function matrixData($user, bool $sync = true): array
    {
        $assignments = $sync ? KpiPeriodService::syncUser($user->id) : KpiAssignment::where('user_id', $user->id)->get();
        // Shown-not-scored figures (Total Leads Allocated) are cards, not rows.
        $assignments = $assignments->reject(fn ($a) => $a->data_source === KpiAssignment::INFO)->values();

        // Fiscal year to date (Apr -> current month), newest first — same
        // window convention the Sales Report month columns use.
        $fyStart = now()->month >= 4
            ? now()->copy()->startOfMonth()->month(4)
            : now()->copy()->subYear()->startOfMonth()->month(4);
        $months = [];
        for ($cursor = $fyStart->copy(); $cursor->lte(now()); $cursor->addMonth()) {
            $months[] = ['key' => $cursor->format('Y-m'), 'label' => $cursor->format('M Y')];
        }
        $months = array_reverse($months);

        $history = KpiCheck::with('assignment')
            ->where('user_id', $user->id)
            ->where('period_start', '>=', $fyStart->toDateString())
            ->orderByDesc('period_start')
            ->get()
            ->reject(fn ($h) => optional($h->assignment)->data_source === KpiAssignment::INFO)
            ->values();

        // Every check this FY, keyed by [assignment_id][Y-m of period_start] —
        // a KPI's row can then look up any month's cell by simple array access.
        $grid = [];
        foreach ($history as $h) {
            $grid[$h->kpi_assignment_id][\Carbon\Carbon::parse($h->period_start)->format('Y-m')] = $h;
        }

        $earned = $history->whereNotNull('score')->sum('score');
        $possible = $assignments->sum('max_marks');

        $pendingCount = $completedCount = 0;
        foreach ($assignments as $a) {
            $status = optional($a->currentCheck)->status;
            if (in_array($status, ['completed_on_time', 'completed_late'], true)) {
                $completedCount++;
            } elseif ($status === 'pending') {
                $pendingCount++;
            }
        }

        // What the target on each row actually means for this person.
        // A percentage-based auto KPI stores 100, which reads as a flat "100"
        // but means "100% of your own figure" — so show the real number behind
        // it. A manual KPI shows its own value, flagged when it has been set
        // for this person rather than inherited from the department.
        $mk = now()->format('Y-m');
        $myKingo = (float) \Illuminate\Support\Facades\DB::table('salesperson_targets')
            ->where('salesperson_id', $user->id)->where('month_year', $mk)
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(kingo_target, 0)'));
        $targetNote = [];
        foreach ($assignments as $a) {
            $src = (string) $a->data_source;
            if ($src === 'auto:target_achievement') {
                $targetNote[$a->id] = $myKingo > 0
                    ? '= ' . rtrim(rtrim(number_format($myKingo, 2), '0'), '.') . ' L, your target'
                    : 'no target set for you';
            } elseif ($src === 'auto:customer_health') {
                $targetNote[$a->id] = 'of your own customers';
            } elseif ($src === 'auto:total_pipeline') {
                $targetNote[$a->id] = 'of your own pipeline';
            } elseif ($src === 'auto:customers_retained') {
                $targetNote[$a->id] = 'of your own retained';
            } elseif (str_starts_with($src, 'auto:')) {
                $targetNote[$a->id] = 'counted for you';
            } else {
                $tpl = optional($a->template)->target_value;
                $targetNote[$a->id] = ($tpl !== null && abs((float) $tpl - (float) $a->target_value) > 0.001)
                    ? 'set for you' : 'department default';
            }
        }

        return compact(
            'assignments', 'months', 'grid', 'history', 'earned', 'possible',
            'pendingCount', 'completedCount', 'targetNote'
        );
    }

    /** Employee's own KPI/KRA dashboard: one row per KPI, one column per month. */
    public function my(Request $request)
    {
        $user = Auth::user();

        // ?pdf=YYYY-MM downloads THIS person's scorecard for that month. It is
        // always the signed-in user's own; nobody else's is reachable from here.
        if ($request->filled('pdf')) {
            $month = KpiScorecardService::parseMonth($request->query('pdf'));
            if (!$month) {
                return back()->with('error', 'Pick a month that has already started.');
            }
            $cards = KpiScorecardService::cardsFor(collect([$user]), $month);
            if ($cards->isEmpty()) {
                return back()->with('error', 'You have no KPIs for ' . $month->format('F Y') . '.');
            }

            return KpiScorecardService::download(
                $cards, $month,
                'KPI-Scorecard-' . \Illuminate\Support\Str::slug($user->first_name . ' ' . $user->last_name) . '-' . $month->format('Y-m') . '.pdf'
            );
        }

        [
            'assignments' => $assignments, 'months' => $months, 'grid' => $grid, 'history' => $history,
            'earned' => $earned, 'possible' => $possible, 'pendingCount' => $pendingCount,
            'completedCount' => $completedCount, 'targetNote' => $targetNote,
        ] = self::matrixData($user);

        // This week's self-reported figures, so the page can show one row per
        // manually measured KPI with whatever was already sent.
        $manualAssignments = $assignments->filter(fn ($a) => KpiEntryService::isManual($a))->values();
        // Each KPI reports over its own window — monthly ones once a month,
        // weekly ones per week — so the current entry is looked up per row.
        // Monthly-frequency ones also get last month's entry alongside this
        // month's: the period picker on the page lets someone switch between
        // exactly those two, and switching is pure client-side (no round
        // trip) using whichever of these two was already fetched.
        $entryPeriod = [];
        $weekEntries = collect();
        $lastMonthEntries = collect();
        $lastMonthKey = null;
        foreach ($manualAssignments as $a) {
            [$ps, $pe] = KpiEntryService::periodFor($a);
            $entryPeriod[$a->id] = KpiEntryService::periodLabel($a);
            $row = \App\Models\KpiEntry::where('kpi_assignment_id', $a->id)
                ->whereDate('period_start', $ps->toDateString())->first();
            if ($row) {
                $weekEntries[$a->id] = $row;
            }
            if ($a->frequency === 'monthly') {
                $lastMonthDay = \Carbon\Carbon::today()->subMonthNoOverflow();
                [$lps, $lpe] = KpiEntryService::periodFor($a, $lastMonthDay);
                $lastMonthKey = $lps->toDateString();
                $lastRow = \App\Models\KpiEntry::where('kpi_assignment_id', $a->id)
                    ->whereDate('period_start', $lps->toDateString())->first();
                if ($lastRow) {
                    $lastMonthEntries[$a->id] = $lastRow;
                }
            }
        }
        [$weekStart, $weekEnd] = KpiEntryService::weekOf();
        // Anything sent back needs re-doing, whichever week it was for.
        $sentBack = \App\Models\KpiEntry::where('user_id', $user->id)
            ->where('status', \App\Models\KpiEntry::REJECTED)
            ->orderByDesc('period_start')->get();

        $pdfMonths = KpiScorecardService::months();

        // Two headline figures that people were reading off the grid by eye:
        // the pipeline they are carrying, and this month's attendance. Both are
        // computed the same way their KPIs are, so the cards and the scored
        // rows can never disagree.
        $cardStart = now()->startOfMonth();
        $cardEnd = now()->endOfMonth();

        $totalPipeline = \App\Services\KpiAutoSourceService::totalPipelineValue($user->id);
        $attendancePct = \App\Services\KpiAutoSourceService::attendancePercentFor($user->id, $cardStart, $cardEnd);
        // e.g. Total Leads Allocated this month - shown, carries no marks.
        $infoCards = \App\Services\KpiScorecardService::infoFor($user->id, $cardStart);

        return view('tenant.kpi.my', compact(
            'pdfMonths',
            'assignments', 'months', 'grid', 'history', 'earned', 'possible',
            'pendingCount', 'completedCount', 'totalPipeline', 'attendancePct', 'infoCards',
            'manualAssignments', 'weekEntries', 'weekStart', 'weekEnd', 'sentBack',
            'targetNote', 'entryPeriod', 'lastMonthEntries', 'lastMonthKey'
        ));
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

    // ── Weekly submissions ────────────────────────────────────────────────
    // A person reports their own number each week; HR signs it off and the
    // month's check is rescored from the approved weeks.

    /** Employee submits (or corrects) this week's figure for one KPI. */
    public function submitEntry(Request $request, $id)
    {
        $user = Auth::user();
        $assignment = KpiAssignment::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if (!KpiEntryService::isManual($assignment)) {
            return response()->json(['status' => 'error', 'message' => 'This KPI is calculated automatically.'], 422);
        }

        $data = $request->validate([
            'value' => 'required|numeric|min:0',
            'note'  => 'required|string|max:255',
            'week'  => 'nullable|date',
        ]);

        $day = !empty($data['week']) ? \Carbon\Carbon::parse($data['week']) : null;
        if ($day) {
            // Only the current or a past week/month — nobody reports one that
            // has not happened. Monthly KPIs are further limited to the
            // current month or last month: the period picker on the page only
            // ever offers those two, and this is the real enforcement of that,
            // not just the dropdown's own options.
            if ($day->gt(\Carbon\Carbon::today())) {
                return response()->json(['status' => 'error', 'message' => 'That period has not started yet.'], 422);
            }
            if ($assignment->frequency === 'monthly') {
                $earliest = \Carbon\Carbon::today()->startOfMonth()->subMonthNoOverflow();
                if ($day->lt($earliest)) {
                    return response()->json(['status' => 'error', 'message' => 'You can only submit for the current month or last month.'], 422);
                }
            }
        }

        $entry = KpiEntryService::submit($assignment, (float) $data['value'], $data['note'], $day);

        return response()->json([
            'status'  => 'success',
            'message' => $entry->status === \App\Models\KpiEntry::SUBMITTED
                ? 'Sent to HR for approval.'
                : 'Recorded.',
            'entry_status' => $entry->status,
        ]);
    }

    /** HR/manager approves or sends back one weekly entry. */
    public function reviewEntry(Request $request, $entryId)
    {
        $user = Auth::user();
        if (!KpiEntryService::canOpenApprovals($user)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'note'   => 'nullable|string|max:255',
        ]);
        $entry = \App\Models\KpiEntry::with('assignment')->findOrFail($entryId);
        if (!KpiEntryService::canReview($user, $entry)) {
            return response()->json(['status' => 'error', 'message' => 'You are not the approver for this KPI.'], 403);
        }

        $entry = $data['action'] === 'approve'
            ? KpiEntryService::approve($entry, $user->id, $data['note'] ?? null)
            : KpiEntryService::reject($entry, $user->id, $data['note'] ?? null);

        return response()->json([
            'status'  => 'success',
            'message' => $data['action'] === 'approve' ? 'Approved.' : 'Sent back.',
            'score'   => optional($entry->check)->score,
        ]);
    }

    /** Which query-string tab values are real, and what status each reads. */
    public const APPROVAL_TABS = [
        'pending'  => \App\Models\KpiEntry::SUBMITTED,
        'approved' => \App\Models\KpiEntry::APPROVED,
        'rejected' => \App\Models\KpiEntry::REJECTED,
    ];

    /**
     * The review screen: Pending / Approved / Rejected on one page, tab-
     * switched like Leave Approvals. The four stat cards up top always read
     * the Pending queue specifically -- "days oldest has waited" means
     * nothing on the Approved or Rejected tab.
     */
    public function approvals(Request $request)
    {
        $user = Auth::user();
        if (!KpiEntryService::canOpenApprovals($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'Unauthorized.');
        }
        $departmentId = $request->input('department_id');
        $employeeId = $request->input('employee_id');
        $tab = array_key_exists($request->input('tab'), self::APPROVAL_TABS) ? $request->input('tab') : 'pending';

        $entries = KpiEntryService::queue($departmentId, self::APPROVAL_TABS[$tab], $employeeId, $user);
        $pendingEntries = $tab === 'pending' ? $entries : KpiEntryService::queue($departmentId, \App\Models\KpiEntry::SUBMITTED, $employeeId, $user);

        $tabCounts = KpiEntryService::scopeToReviewer(\App\Models\KpiEntry::query(), $user)
            ->when($departmentId, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('department_id', $departmentId)))
            ->when($employeeId, fn ($q) => $q->where('user_id', $employeeId))
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        // The filter dropdowns list only what this reviewer can act on -- a
        // department head should not get the whole staff directory from here.
        $reviewable = KpiEntryService::reviewsEverything($user)
            ? null
            : KpiEntryService::scopeToReviewer(\App\Models\KpiEntry::query(), $user)->pluck('user_id')->unique();
        $employees = User::notTerminated()->when($reviewable !== null, fn ($q) => $q->whereIn('id', $reviewable))
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'department_id']);
        $departments = \App\Models\Department::orderBy('name')
            ->when($reviewable !== null, fn ($q) => $q->whereIn('id', $employees->pluck('department_id')->filter()->unique()))
            ->get(['id', 'name']);

        return view('tenant.kpi.approvals', compact('entries', 'pendingEntries', 'departments', 'employees', 'departmentId', 'employeeId', 'tab', 'tabCounts'));
    }

    /** Excel export of one tab's entries — same filters as the screen. */
    public function exportApprovals(Request $request)
    {
        $user = Auth::user();
        if (!KpiEntryService::canOpenApprovals($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'Unauthorized.');
        }
        $departmentId = $request->input('department_id');
        $employeeId = $request->input('employee_id');
        $tab = array_key_exists($request->input('tab'), self::APPROVAL_TABS) ? $request->input('tab') : 'pending';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\KpiApprovalsExport(self::APPROVAL_TABS[$tab], $departmentId, $employeeId, $user),
            'kpi-' . $tab . '-' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    // ── Department templates ──────────────────────────────────────────────
    // A KPI/KRA is defined once for a department and stamped onto everyone in
    // it, instead of an admin repeating the same row per employee. Unlike
    // manage(), this covers EVERY department including sales — the exclusion
    // there is about DWM routines, not about who can hold a scorecard.

    private function assertCanManageTemplates(): ?string
    {
        return (Auth::user()->hasRole(['admin', 'hr']) || Auth::user()->can('kpi.manage')) ? null : 'Unauthorized.';
    }

    public function templates(Request $request)
    {
        if ($msg = $this->assertCanManageTemplates()) {
            return redirect()->route('tenant.dashboard')->with('error', $msg);
        }

        $departmentId = $request->input('department_id');

        // "Delete" on this page deactivates rather than removes the row —
        // kpi_checks under it hold real scores that must not vanish — but
        // nothing filtered this list by is_active, so a "deleted" template
        // stayed on screen looking exactly as it did before, indistinguishable
        // from one still in use. Filtered here so delete actually reads as gone.
        $templates = KpiTemplate::with(['department', 'parent'])
            ->where('is_active', true)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('department_id')->orderBy('parent_template_id')->orderBy('category', 'desc')->orderBy('id')
            ->get();

        // How many employees each template currently covers, for the UI count.
        $assignmentCounts = KpiAssignment::whereNotNull('kpi_template_id')
            ->selectRaw('kpi_template_id, COUNT(*) c, SUM(is_active) active')
            ->groupBy('kpi_template_id')->get()->keyBy('kpi_template_id');

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $kras = KpiTemplate::where('category', 'KRA')->where('is_active', true)->orderBy('title')->get(['id', 'title', 'department_id']);
        $approvers = User::notTerminated()->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $designations = \App\Models\Designation::orderBy('name')->get(['id', 'name', 'department_id']);

        // Built from the service rather than a second list in the view, so a new
        // calculation shows up in the dropdown the moment it is added and the
        // two can never disagree about what exists.
        $autoSources = \App\Services\KpiAutoSourceService::SOURCES;

        return view('tenant.kpi.templates', compact(
            'templates', 'departments', 'kras', 'approvers', 'departmentId',
            'assignmentCounts', 'autoSources', 'designations'
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
            'trend'              => 'nullable|in:increase,decrease',
            'result_cap_percent' => 'nullable|numeric|min:100|max:1000',
            'designation_ids'    => 'nullable|array',
            'designation_ids.*'  => 'integer|exists:designations,id',
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
        $data['trend'] = $data['trend'] ?? 'increase';
        $data['designation_ids'] = !empty($data['designation_ids']) ? array_values(array_map('intval', $data['designation_ids'])) : null;
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
        $data['trend'] = $data['trend'] ?? 'increase';
        $data['designation_ids'] = !empty($data['designation_ids']) ? array_values(array_map('intval', $data['designation_ids'])) : null;
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

        $oldTarget = $t->target_value;
        $t->update($data);

        // Existing assignments follow the edit, so a weight change does not
        // leave half the department scored on the old number.
        $res = KpiTemplateService::apply($t, true);
        // The target too, for everyone who was still on the old one -- a
        // personalised target stays the person's own.
        $retargeted = KpiTemplateService::followTargetChange($t->fresh(), $oldTarget);

        return response()->json([
            'status' => 'success',
            'message' => "Saved. {$res['updated']} existing assignment(s) refreshed, {$res['created']} added"
                . ($retargeted ? ", {$retargeted} moved to the new target." : '.'),
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
