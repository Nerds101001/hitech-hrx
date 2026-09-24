<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\KpiDataOwner;
use App\Models\KpiTemplate;
use App\Models\User;
use App\Services\KpiEntryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Filling the manual KPIs for a whole team in one go.
 *
 * The manual KPIs were built as self-reporting with HR approval on each
 * number. That is not how the data actually arrives: call hours come off the
 * GSM gateway, quotation counts out of the CRM, and one person holds the real
 * figure for everybody. Asking seventeen people to each type their own and
 * then queue for approval produced five reports out of seventeen in September
 * and 473 unclaimed marks.
 *
 * So: a KPI can have a data OWNER (see kpi_data_owners), and an owner fills
 * that one KPI for every assignee at once -- on screen or from a spreadsheet.
 * Their figure needs no approval, because they are the source.
 *
 * Admin and HR can fill any KPI and manage who owns what. Everyone else sees
 * only the KPIs they own, for the departments they own them in.
 */
class KpiBulkEntryController extends Controller
{
    /** Admin/HR may fill anything and assign owners. */
    private function isKpiAdmin(?User $user): bool
    {
        return $user && ($user->hasRole(['admin', 'hr']) || $user->can('kpi.manage'));
    }

    /** The KPI templates this user may fill for other people. */
    private function fillableTemplates(User $user)
    {
        $manualTemplateIds = KpiAssignment::where('is_active', 1)
            ->where(fn ($q) => $q->whereNull('data_source')->orWhereIn('data_source', ['approval', 'manual']))
            ->distinct()->pluck('kpi_template_id')->filter();

        $query = KpiTemplate::whereIn('id', $manualTemplateIds)->orderBy('title');

        if (!$this->isKpiAdmin($user)) {
            $owned = KpiDataOwner::templateIdsFor($user);
            if ($owned === []) {
                return collect();
            }
            $query->whereIn('id', $owned);
        }

        return $query->get();
    }

    /** Assignees of $template the current user may fill, honouring department scope. */
    private function assigneesFor(User $user, KpiTemplate $template)
    {
        $query = KpiAssignment::with('user.department')
            ->where('kpi_template_id', $template->id)
            ->where('is_active', 1);

        if (!$this->isKpiAdmin($user)) {
            $depts = KpiDataOwner::departmentScopeFor($user, $template->id);
            if ($depts !== []) {
                $query->whereIn('user_id', User::whereIn('department_id', $depts)->pluck('id'));
            }
        }

        return $query->get()
            ->filter(fn ($a) => $a->user && $a->user->status === 'active')
            ->sortBy(fn ($a) => mb_strtolower($a->user->first_name . ' ' . $a->user->last_name))
            ->values();
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $templates = $this->fillableTemplates($user);

        abort_if($templates->isEmpty() && !$this->isKpiAdmin($user), 403,
            'You are not set as the data owner for any KPI.');

        $templateId = (int) ($request->input('template') ?: ($templates->first()->id ?? 0));
        $template = $templates->firstWhere('id', $templateId) ?: $templates->first();

        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth()
            : Carbon::now()->startOfMonth();

        $rows = collect();
        if ($template) {
            $assignments = $this->assigneesFor($user, $template);

            $existing = DB::table('kpi_entries')
                ->whereIn('kpi_assignment_id', $assignments->pluck('id'))
                ->whereDate('period_start', '>=', $month->toDateString())
                ->whereDate('period_start', '<=', $month->copy()->endOfMonth()->toDateString())
                ->get()->keyBy('kpi_assignment_id');

            $rows = $assignments->map(function ($a) use ($existing) {
                $e = $existing->get($a->id);

                return (object) [
                    'assignment' => $a,
                    'employee' => $a->user,
                    'department' => $a->user->department->name ?? '—',
                    'value' => $e->value ?? null,
                    'status' => $e->status ?? null,
                    'note' => $e->note ?? null,
                ];
            });
        }

        $months = collect(range(0, 11))->map(fn ($i) => Carbon::now()->startOfMonth()->subMonths($i));

        $owners = $this->isKpiAdmin($user)
            ? KpiDataOwner::with('template', 'user', 'department')->get()
            : collect();

        $ownerCandidates = $this->isKpiAdmin($user)
            ? User::where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
            : collect();

        $allManualTemplates = $this->isKpiAdmin($user) ? $templates : collect();
        $departments = $this->isKpiAdmin($user) ? Department::orderBy('name')->get(['id', 'name']) : collect();

        return view('tenant.kpi.bulk-entry', compact(
            'templates', 'template', 'month', 'months', 'rows',
            'owners', 'ownerCandidates', 'allManualTemplates', 'departments'
        ))->with('isKpiAdmin', $this->isKpiAdmin($user));
    }

    /** Save the grid. Values are authoritative: no approval step. */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'template' => 'required|integer',
            'month' => 'required|date_format:Y-m',
            'values' => 'array',
            'values.*' => 'nullable|numeric|min:0',
        ]);

        $template = $this->fillableTemplates($user)->firstWhere('id', (int) $request->input('template'));
        abort_unless($template, 403, 'You may not fill that KPI.');

        $month = Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth();

        // Only ever touch assignments this user is entitled to.
        $allowed = $this->assigneesFor($user, $template)->keyBy('id');

        $saved = 0;
        $cleared = 0;

        foreach ((array) $request->input('values', []) as $assignmentId => $value) {
            $a = $allowed->get((int) $assignmentId);
            if (!$a) {
                continue;
            }

            $value = ($value === null || $value === '') ? null : (float) $value;
            $note = $request->input('notes.' . $assignmentId) ?: null;

            $entry = KpiEntryService::fillForTeam($a, $value, $user->id, $note, $month->copy());
            $entry ? $saved++ : $cleared++;
        }

        $msg = sprintf('%s — %d value%s saved', $template->title, $saved, $saved === 1 ? '' : 's');
        if ($cleared) {
            $msg .= sprintf(', %d cleared', $cleared);
        }
        $msg .= ' for ' . $month->format('F Y') . '. No approval needed.';

        return redirect()
            ->route('tenant.kpi.bulk-entry', ['template' => $template->id, 'month' => $month->format('Y-m')])
            ->with('success', $msg);
    }

    /** A spreadsheet of the current grid, ready to fill and upload back. */
    public function template(Request $request)
    {
        $user = Auth::user();
        $template = $this->fillableTemplates($user)->firstWhere('id', (int) $request->input('template'));
        abort_unless($template, 403);

        $month = Carbon::createFromFormat('Y-m', $request->input('month', Carbon::now()->format('Y-m')))->startOfMonth();
        $rows = $this->assigneesFor($user, $template);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\KpiBulkEntryExport($template, $month, $rows),
            'kpi-' . \Illuminate\Support\Str::slug($template->title) . '-' . $month->format('Y-m') . '.xlsx'
        );
    }

    /** Read a filled spreadsheet back in. */
    public function import(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'template' => 'required|integer',
            'month' => 'required|date_format:Y-m',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $template = $this->fillableTemplates($user)->firstWhere('id', (int) $request->input('template'));
        abort_unless($template, 403, 'You may not fill that KPI.');

        $month = Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth();
        $allowed = $this->assigneesFor($user, $template)->keyBy('id');

        $sheets = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\KpiBulkEntryImport(), $request->file('file'));
        $sheet = $sheets[0] ?? [];

        $saved = 0;
        $cleared = 0;
        $skipped = [];

        foreach ($sheet as $i => $row) {
            // The export writes: assignment id, employee, department, value, note
            $assignmentId = (int) ($row[0] ?? 0);
            if ($assignmentId <= 0) {
                continue;   // header or blank
            }

            $a = $allowed->get($assignmentId);
            if (!$a) {
                $skipped[] = 'row ' . ($i + 1) . ' (not yours to fill)';
                continue;
            }

            $raw = $row[3] ?? null;
            if ($raw !== null && $raw !== '' && !is_numeric($raw)) {
                $skipped[] = 'row ' . ($i + 1) . ' ("' . mb_substr((string) $raw, 0, 12) . '" is not a number)';
                continue;
            }

            $value = ($raw === null || $raw === '') ? null : (float) $raw;
            $note = $row[4] ?? null;

            KpiEntryService::fillForTeam($a, $value, $user->id, $note ?: null, $month->copy())
                ? $saved++ : $cleared++;
        }

        $msg = sprintf('%s — imported %d value%s', $template->title, $saved, $saved === 1 ? '' : 's');
        if ($cleared) {
            $msg .= ', ' . $cleared . ' cleared';
        }
        if ($skipped) {
            $msg .= '. Skipped: ' . implode('; ', array_slice($skipped, 0, 5))
                . (count($skipped) > 5 ? ' and ' . (count($skipped) - 5) . ' more' : '');
        }

        return redirect()
            ->route('tenant.kpi.bulk-entry', ['template' => $template->id, 'month' => $month->format('Y-m')])
            ->with($skipped ? 'warning' : 'success', $msg);
    }

    /** Admin/HR: put someone in charge of a KPI. */
    public function addOwner(Request $request)
    {
        abort_unless($this->isKpiAdmin(Auth::user()), 403);

        $request->validate([
            'kpi_template_id' => 'required|exists:kpi_templates,id',
            'user_id' => 'required|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'note' => 'nullable|string|max:255',
        ]);

        KpiDataOwner::updateOrCreate(
            [
                'kpi_template_id' => $request->kpi_template_id,
                'user_id' => $request->user_id,
                'department_id' => $request->department_id ?: null,
            ],
            ['note' => $request->note, 'created_by_id' => Auth::id()]
        );

        return back()->with('success', 'Data owner saved.');
    }

    public function removeOwner($id)
    {
        abort_unless($this->isKpiAdmin(Auth::user()), 403);

        KpiDataOwner::findOrFail($id)->delete();

        return back()->with('success', 'Data owner removed.');
    }
}
