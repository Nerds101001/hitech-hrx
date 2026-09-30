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
 * number. That is not how the data arrives: call hours come off the GSM
 * gateway, quotation counts out of the CRM, and one person holds the real
 * figure for everybody. Asking seventeen people to each type their own and
 * queue for approval produced five reports out of seventeen in September and
 * 473 unclaimed marks.
 *
 * So a KPI can have a data OWNER, and an owner fills it for every assignee at
 * once. Several KPIs can be filled together: the grid and the spreadsheet both
 * become a matrix, one row per person and one column per KPI, which is how the
 * numbers actually arrive -- a single report listing everyone's calls,
 * quotations and upsells side by side.
 *
 * An owner's figure needs no approval, because they are the source.
 */
class KpiBulkEntryController extends Controller
{
    /** Where a parsed upload waits while the user looks at the preview. */
    private const PREVIEW_KEY = 'kpi_bulk_preview';

    private function isKpiAdmin(?User $user): bool
    {
        return $user && ($user->hasRole(['admin', 'hr']) || $user->can('kpi.manage'));
    }

    /** The KPI templates this user may fill for other people. */
    private function fillableTemplates(User $user)
    {
        $manualTemplateIds = KpiAssignment::where('is_active', 1)
            // 'info' figures (e.g. Total Leads Allocated) are typed in here too; they carry no marks.
            ->where(fn ($q) => $q->whereNull('data_source')->orWhereIn('data_source', ['approval', 'manual', KpiAssignment::INFO]))
            ->whereNotNull('kpi_template_id')
            ->distinct()->pluck('kpi_template_id');

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

    /**
     * Active assignments of $templateIds this user may fill.
     *
     * The status test is done in SQL on purpose. users.status is cast to the
     * UserAccountStatus enum, so comparing $a->user->status to the string
     * 'active' in PHP is never true -- it silently filtered out every person
     * and the grid came up empty for a KPI that had seventeen assignees.
     */
    private function assignmentsFor(User $user, array $templateIds)
    {
        if ($templateIds === []) {
            return collect();
        }

        $query = KpiAssignment::with(['user.department'])
            ->whereIn('kpi_template_id', $templateIds)
            ->where('is_active', 1)
            ->whereHas('user', fn ($q) => $q->where('status', 'active')->whereNull('deleted_at'));

        if (!$this->isKpiAdmin($user)) {
            // A department-scoped owner only fills their own teams.
            $scopes = [];
            $unrestricted = false;
            foreach ($templateIds as $tid) {
                $s = KpiDataOwner::departmentScopeFor($user, $tid);
                if ($s === []) {
                    $unrestricted = true;
                    break;
                }
                $scopes = array_merge($scopes, $s);
            }
            if (!$unrestricted && $scopes !== []) {
                $query->whereIn('user_id', User::whereIn('department_id', array_unique($scopes))->pluck('id'));
            }
        }

        return $query->get();
    }

    /** Rows of [employee, department, value per template] for the grid and the sheet. */
    private function matrix($assignments, Carbon $month, array $templateIds): \Illuminate\Support\Collection
    {
        $existing = DB::table('kpi_entries')
            ->whereIn('kpi_assignment_id', $assignments->pluck('id'))
            ->whereDate('period_start', '>=', $month->toDateString())
            ->whereDate('period_start', '<=', $month->copy()->endOfMonth()->toDateString())
            ->get()->keyBy('kpi_assignment_id');

        return $assignments
            ->groupBy('user_id')
            ->map(function ($forUser) use ($existing, $templateIds) {
                $first = $forUser->first();
                $cells = [];

                foreach ($templateIds as $tid) {
                    $a = $forUser->firstWhere('kpi_template_id', $tid);
                    $e = $a ? $existing->get($a->id) : null;
                    $cells[$tid] = (object) [
                        'assignment_id' => $a->id ?? null,
                        'value' => $e->value ?? null,
                        'status' => $e->status ?? null,
                        'note' => $e->note ?? null,
                    ];
                }

                return (object) [
                    'user' => $first->user,
                    'name' => trim(($first->user->first_name ?? '') . ' ' . ($first->user->last_name ?? '')),
                    'department' => $first->user->department->name ?? '—',
                    'cells' => $cells,
                ];
            })
            ->sortBy(fn ($r) => mb_strtolower($r->name))
            ->values();
    }

    /**
     * What each KPI is called on this page, the sheet and the preview, by template id.
     *
     * The title alone, unless another KPI on offer has the same title -- then
     * the department is added ("Cost of Selling — Sales"). New Biz and Sales
     * each have their own "Cost of Selling": with titles alone the picker
     * showed two identical chips, and the upload, which matches sheet columns
     * by their header, read every "Cost of Selling" column as the first one --
     * a Sales-only sheet was rejected outright and a mixed sheet silently
     * dropped every Sales figure.
     */
    public static function labelsFor($templates): array
    {
        $norm = fn ($s) => mb_strtolower(trim((string) $s));
        $counts = $templates->countBy(fn ($t) => $norm($t->title));
        $depts = Department::whereIn('id', $templates->pluck('department_id')->filter()->unique())->pluck('name', 'id');

        $labels = [];
        foreach ($templates as $t) {
            $labels[$t->id] = $counts[$norm($t->title)] > 1
                ? trim($t->title) . ' — ' . ($depts[$t->department_id] ?? 'All departments')
                : trim($t->title);
        }

        return $labels;
    }

    /** Template ids requested, narrowed to the ones this user may actually fill. */
    private function requestedTemplates(Request $request, $fillable): array
    {
        $asked = (array) ($request->input('templates') ?: array_filter([$request->input('template')]));
        $asked = array_values(array_unique(array_map('intval', $asked)));

        $allowed = $fillable->pluck('id')->all();
        $ids = array_values(array_intersect($asked, $allowed));

        // Nothing chosen yet: start on the first one so the page is never blank.
        if ($ids === [] && $fillable->isNotEmpty()) {
            $ids = [$fillable->first()->id];
        }

        return $ids;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $templates = $this->fillableTemplates($user);

        abort_if($templates->isEmpty() && !$this->isKpiAdmin($user), 403,
            'You are not set as the data owner for any KPI.');

        $selectedIds = $this->requestedTemplates($request, $templates);
        $selected = $templates->whereIn('id', $selectedIds)->values();

        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth()
            : Carbon::now()->startOfMonth();

        $assignments = $this->assignmentsFor($user, $selectedIds);
        $rows = $this->matrix($assignments, $month, $selectedIds);

        $months = collect(range(0, 11))->map(fn ($i) => Carbon::now()->startOfMonth()->subMonths($i));

        $owners = $this->isKpiAdmin($user)
            ? KpiDataOwner::with('template', 'user', 'department')->get() : collect();
        $ownerCandidates = $this->isKpiAdmin($user)
            ? User::where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name']) : collect();
        $departments = $this->isKpiAdmin($user) ? Department::orderBy('name')->get(['id', 'name']) : collect();

        $labels = self::labelsFor($templates);

        return view('tenant.kpi.bulk-entry', compact(
            'templates', 'selected', 'selectedIds', 'month', 'months', 'rows',
            'owners', 'ownerCandidates', 'departments', 'labels'
        ))->with('isKpiAdmin', $this->isKpiAdmin($user));
    }

    /** Save the grid. Authoritative: no approval step. */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'month' => 'required|date_format:Y-m',
            'values' => 'array',
            'values.*' => 'nullable|numeric|min:0',
        ]);

        $fillable = $this->fillableTemplates($user);
        $templateIds = $this->requestedTemplates($request, $fillable);
        $month = Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth();

        $allowed = $this->assignmentsFor($user, $templateIds)->keyBy('id');

        $saved = 0;
        $cleared = 0;

        foreach ((array) $request->input('values', []) as $assignmentId => $value) {
            $a = $allowed->get((int) $assignmentId);
            if (!$a) {
                continue;
            }

            $value = ($value === null || $value === '') ? null : (float) $value;
            KpiEntryService::fillForTeam($a, $value, $user->id,
                $request->input('notes.' . $assignmentId) ?: null, $month->copy()) ? $saved++ : $cleared++;
        }

        $msg = sprintf('%d value%s saved', $saved, $saved === 1 ? '' : 's');
        if ($cleared) {
            $msg .= ', ' . $cleared . ' cleared';
        }
        $msg .= ' for ' . $month->format('F Y') . '. No approval needed.';

        return redirect()->route('tenant.kpi.bulk-entry', [
            'templates' => $templateIds, 'month' => $month->format('Y-m'),
        ])->with('success', $msg);
    }

    /** One sheet: a row per person, a column per chosen KPI. */
    public function template(Request $request)
    {
        $user = Auth::user();
        $fillable = $this->fillableTemplates($user);
        $templateIds = $this->requestedTemplates($request, $fillable);

        abort_if($templateIds === [], 403, 'Choose at least one KPI you are allowed to fill.');

        $month = Carbon::createFromFormat('Y-m', $request->input('month', Carbon::now()->format('Y-m')))->startOfMonth();
        $selected = $fillable->whereIn('id', $templateIds)->values();
        $assignments = $this->assignmentsFor($user, $templateIds);
        $rows = $this->matrix($assignments, $month, $templateIds);

        $labels = self::labelsFor($fillable);
        $name = $selected->count() === 1
            ? \Illuminate\Support\Str::slug($labels[$selected->first()->id])
            : 'kpis-' . $selected->count();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\KpiBulkEntryExport($selected, $month, $rows, $labels),
            'kpi-' . $name . '-' . $month->format('Y-m') . '.xlsx'
        );
    }

    /** Read the sheet and show what it would do, before anything is written. */
    public function import(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'month' => 'required|date_format:Y-m',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $fillable = $this->fillableTemplates($user);
        $templateIds = $this->requestedTemplates($request, $fillable);
        $month = Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth();

        $allowed = $this->assignmentsFor($user, $templateIds);
        $byUserAndTemplate = [];
        foreach ($allowed as $a) {
            $byUserAndTemplate[$a->user_id][$a->kpi_template_id] = $a;
        }

        $sheet = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\KpiBulkEntryImport(), $request->file('file'))[0] ?? [];

        // Row 2 is the header; its columns from D onward name the KPIs, in the
        // order the export wrote them. Match on the header text so a reordered
        // or partially deleted sheet still lands in the right KPI. The header is
        // the page's label (title + department when two KPIs share a title); a
        // sheet downloaded before that carries the bare title, which is accepted
        // only while it points at exactly one of the KPIs being uploaded.
        $norm = fn ($s) => mb_strtolower(trim((string) $s));
        $labels = self::labelsFor($fillable);
        $chosen = $fillable->whereIn('id', $templateIds);
        $header = $sheet[1] ?? [];
        $columnTemplate = [];
        $preview = [];
        $problems = [];
        foreach ($header as $i => $label) {
            if ($i < 3 || $label === null || $label === '') {
                continue;
            }
            $t = $chosen->first(fn ($x) => $norm($labels[$x->id]) === $norm($label));
            if (!$t) {
                $byTitle = $chosen->filter(fn ($x) => $norm($x->title) === $norm($label));
                if ($byTitle->count() === 1) {
                    $t = $byTitle->first();
                } elseif ($byTitle->count() > 1) {
                    $problems[] = sprintf('column "%s" could be %s -- download a fresh sheet, whose headers say which',
                        mb_substr((string) $label, 0, 40), $byTitle->map(fn ($x) => $labels[$x->id])->implode(' or '));
                }
            }
            if ($t) {
                $columnTemplate[$i] = $t;
            }
        }

        foreach ($sheet as $i => $row) {
            $userId = (int) ($row[0] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $mine = $byUserAndTemplate[$userId] ?? null;
            if (!$mine) {
                $problems[] = 'row ' . ($i + 1) . ': that person is not yours to fill';
                continue;
            }

            $cells = [];
            foreach ($columnTemplate as $col => $t) {
                $raw = $row[$col] ?? null;
                if ($raw !== null && $raw !== '' && !is_numeric($raw)) {
                    $problems[] = sprintf('row %d, %s: "%s" is not a number', $i + 1, $labels[$t->id], mb_substr((string) $raw, 0, 12));
                    continue;
                }
                $a = $mine[$t->id] ?? null;
                if (!$a) {
                    continue;   // that person does not carry this KPI
                }
                $cells[$t->id] = [
                    'assignment_id' => $a->id,
                    'value' => ($raw === null || $raw === '') ? null : (float) $raw,
                ];
            }

            if ($cells === []) {
                continue;
            }

            $u = $allowed->firstWhere('user_id', $userId)->user ?? null;
            $preview[] = [
                'user_id' => $userId,
                'name' => $u ? trim($u->first_name . ' ' . $u->last_name) : ('#' . $userId),
                'department' => $u->department->name ?? '—',
                'cells' => $cells,
            ];
        }

        if ($preview === []) {
            return back()->with('error',
                'Nothing in that file could be matched. Use the Excel button to get a sheet with the right columns.'
                . ($problems ? ' (' . implode('; ', array_slice($problems, 0, 3)) . ')' : ''));
        }

        session([self::PREVIEW_KEY => [
            'month' => $month->format('Y-m'),
            'template_ids' => array_values(array_map(fn ($t) => $t->id, $columnTemplate)),
            'rows' => $preview,
            'problems' => $problems,
        ]]);

        return redirect()->route('tenant.kpi.bulk-entry.preview');
    }

    /** What the upload will change, before it changes it. */
    public function preview(Request $request)
    {
        $user = Auth::user();
        $data = session(self::PREVIEW_KEY);

        if (!$data) {
            return redirect()->route('tenant.kpi.bulk-entry')
                ->with('error', 'Nothing to preview — upload a file first.');
        }

        $fillable = $this->fillableTemplates($user);
        $selected = $fillable->whereIn('id', $data['template_ids'])->values();
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();

        return view('tenant.kpi.bulk-preview', [
            'selected' => $selected,
            'labels' => self::labelsFor($fillable),
            'month' => $month,
            'rows' => $data['rows'],
            'problems' => $data['problems'],
        ]);
    }

    /** Commit what the preview showed. */
    public function importApply(Request $request)
    {
        $user = Auth::user();
        $data = session(self::PREVIEW_KEY);

        if (!$data) {
            return redirect()->route('tenant.kpi.bulk-entry')->with('error', 'That preview has expired — upload again.');
        }

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();

        // Re-check entitlement at commit time: the preview is only a proposal.
        $allowed = $this->assignmentsFor($user, $data['template_ids'])->keyBy('id');

        $saved = 0;
        $cleared = 0;

        foreach ($data['rows'] as $row) {
            foreach ($row['cells'] as $cell) {
                $a = $allowed->get($cell['assignment_id']);
                if (!$a) {
                    continue;
                }
                KpiEntryService::fillForTeam($a, $cell['value'], $user->id, null, $month->copy())
                    ? $saved++ : $cleared++;
            }
        }

        session()->forget(self::PREVIEW_KEY);

        $msg = sprintf('Imported %d value%s', $saved, $saved === 1 ? '' : 's');
        if ($cleared) {
            $msg .= ', ' . $cleared . ' cleared';
        }
        $msg .= ' for ' . $month->format('F Y') . '.';

        return redirect()->route('tenant.kpi.bulk-entry', [
            'templates' => $data['template_ids'], 'month' => $month->format('Y-m'),
        ])->with('success', $msg);
    }

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
