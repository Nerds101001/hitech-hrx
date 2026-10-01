<?php

namespace App\Support;

use App\Models\Department;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserTrainingProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "All training activity" (/training/activity?view=log, admin/HR only - the
 * caller has already checked): every attempt, and how many it took to pass.
 *
 *  - Attempts: one row per person per module - status, attempts (all of
 *    them: user_training_progress.attempts counts every submission), last
 *    score, started / passed / last activity.
 *  - By module: people who tried, passed, average attempts to pass, first-try
 *    pass rate, most attempts.
 *  - Activity log: each start, pass and fail with its score and attempt
 *    number, read from the audit trail UserTrainingProgress already keeps
 *    (owen-it auditing). The trail starts when auditing was switched on, so
 *    older attempts are in the counts but not in the log.
 *
 * Filters narrow the whole page; ?export=csv downloads the current tab.
 */
class TrainingActivityLog
{
    public const PER_PAGE = 50;
    public const EXPORT_LIMIT = 10000;

    public static function page(Request $request)
    {
        $tab = in_array($request->query('tab'), ['attempts', 'modules', 'log'], true) ? $request->query('tab') : 'attempts';
        $f = [
            'q'          => trim((string) $request->query('q', '')),
            'department' => (string) $request->query('department', ''),
            'module'     => (string) $request->query('module', ''),
            'status'     => (string) $request->query('status', ''),
            'attempts'   => max(0, (int) $request->query('attempts', 0)),
            'from'       => self::date($request->query('from')),
            'to'         => self::date($request->query('to')),
            'kind'       => $request->query('kind') === 'attempts' ? 'attempts' : '',
        ];

        $progress = self::progressQuery($f);
        $cards = self::cards(clone $progress);

        $modules = TrainingModule::with('phase')->get()->sortBy(fn ($m) => [optional($m->phase)->order ?? 99, $m->phase_id, $m->order])->values();
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $logSince = DB::table('audits')->where('auditable_type', UserTrainingProgress::class)->min('created_at');

        if ($tab === 'modules') {
            $rows = self::byModule(clone $progress, $modules);
            if ($request->query('export') === 'csv') {
                return self::csv('training-attempts-by-module', ['Phase', 'Module', 'People who attempted', 'Passed', 'Avg attempts to pass', 'Passed first try %', 'Failed attempts', 'Most attempts'],
                    $rows->map(fn ($r) => [$r->phase, $r->module, $r->tried, $r->passed, $r->avg_to_pass ?? '', $r->first_try_pct ?? '', $r->failed_attempts, $r->max_attempts]));
            }
        } elseif ($tab === 'log') {
            $query = self::logQuery($f);
            if ($request->query('export') === 'csv') {
                $all = self::describe($query->limit(self::EXPORT_LIMIT)->get());
                return self::csv('training-activity-log', ['When', 'Employee', 'Code', 'Department', 'Phase', 'Module', 'Event', 'Attempt', 'Score %'],
                    $all->map(fn ($e) => [$e->at, $e->name, $e->code, $e->department, $e->phase, $e->module, $e->event, $e->attempt ?? '', $e->score ?? '']));
            }
            $rows = $query->paginate(self::PER_PAGE)->withQueryString();
            $rows->setCollection(self::describe($rows->getCollection()));
        } else {
            [$sort, $dir] = self::sort($request);
            $query = self::attemptRows(clone $progress, $sort, $dir);
            if ($request->query('export') === 'csv') {
                return self::csv('training-attempts', ['Employee', 'Code', 'Department', 'Phase', 'Module', 'Status', 'Attempts', 'Last score %', 'Started', 'Passed on', 'Last activity'],
                    $query->limit(self::EXPORT_LIMIT)->get()->map(fn ($r) => [$r->name, $r->code, $r->department, $r->phase, $r->module, self::statusLabel($r->status), $r->attempts,
                        $r->assessment_score === null ? '' : round((float) $r->assessment_score, 1), $r->started_at, $r->completed_at, $r->updated_at]));
            }
            $rows = $query->paginate(self::PER_PAGE)->withQueryString();
        }

        return view('tenant.training.activity-log', [
            'tab' => $tab, 'f' => $f, 'rows' => $rows, 'cards' => $cards, 'modules' => $modules,
            'departments' => $departments, 'logSince' => $logSince,
            'sort' => $sort ?? null, 'dir' => $dir ?? null,
            'filtered' => $f['q'] !== '' || $f['department'] !== '' || $f['module'] !== '' || $f['status'] !== '' || $f['attempts'] > 0 || $f['from'] || $f['to'] || $f['kind'] !== '',
        ]);
    }

    /** Progress rows that carry some activity (not merely unlocked), with the page's filters. */
    private static function progressQuery(array $f)
    {
        $q = DB::table('user_training_progress as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->join('training_modules as m', 'm.id', '=', 'p.module_id')
            ->leftJoin('training_phases as ph', 'ph.id', '=', 'm.phase_id')
            ->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
            ->whereNull('u.deleted_at')
            ->where(fn ($w) => $w->where('p.attempts', '>', 0)->orWhereIn('p.status', ['in_progress', 'completed', 'failed']));
        self::personFilters($q, $f);
        if ($f['module'] !== '') $q->where('p.module_id', (int) $f['module']);
        if ($f['status'] === 'passed') $q->where('p.status', 'completed');
        elseif (in_array($f['status'], ['failed', 'in_progress'], true)) $q->where('p.status', $f['status']);
        if ($f['attempts'] > 0) $q->where('p.attempts', '>=', $f['attempts']);
        if ($f['from']) $q->where('p.updated_at', '>=', $f['from'] . ' 00:00:00');
        if ($f['to']) $q->where('p.updated_at', '<=', $f['to'] . ' 23:59:59');

        return $q;
    }

    private static function personFilters($q, array $f): void
    {
        if ($f['q'] !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
            $q->where(fn ($w) => $w->where('u.first_name', 'like', $like)->orWhere('u.last_name', 'like', $like)
                ->orWhereRaw("CONCAT(u.first_name, ' ', u.last_name) like ?", [$like])->orWhere('u.email', 'like', $like)->orWhere('u.code', 'like', $like));
        }
        if ($f['department'] === 'none') $q->whereNull('u.department_id');
        elseif ($f['department'] !== '') $q->where('u.department_id', (int) $f['department']);
    }

    private static function cards($q): object
    {
        $r = $q->selectRaw("
            COUNT(*) AS rows_n,
            COUNT(DISTINCT p.user_id) AS people,
            COALESCE(SUM(p.attempts), 0) AS attempts,
            SUM(p.status = 'completed') AS passed,
            SUM(p.status = 'completed' AND p.attempts = 1) AS first_try,
            SUM(p.status = 'completed' AND p.attempts >= 1) AS passed_counted,
            AVG(CASE WHEN p.status = 'completed' AND p.attempts >= 1 THEN p.attempts END) AS avg_to_pass,
            COALESCE(SUM(CASE WHEN p.status = 'completed' THEN GREATEST(p.attempts - 1, 0) ELSE p.attempts END), 0) AS failed_attempts,
            MAX(p.attempts) AS max_attempts")->first();

        return (object) [
            'people'          => (int) $r->people,
            'attempts'        => (int) $r->attempts,
            'passed'          => (int) $r->passed,
            'failed_attempts' => (int) $r->failed_attempts,
            'avg_to_pass'     => $r->avg_to_pass === null ? null : round((float) $r->avg_to_pass, 1),
            'first_try_pct'   => (int) $r->passed_counted > 0 ? round($r->first_try / $r->passed_counted * 100) : null,
            'max_attempts'    => (int) $r->max_attempts,
        ];
    }

    public const SORTS = [
        'name' => 'name', 'department' => 'department', 'module' => 'module_sort', 'status' => 'p.status',
        'attempts' => 'p.attempts', 'score' => 'p.assessment_score', 'started' => 'p.started_at',
        'passed' => 'p.completed_at', 'last' => 'p.updated_at',
    ];

    private static function sort(Request $request): array
    {
        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'last';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        return [$sort, $dir];
    }

    private static function attemptRows($q, string $sort, string $dir)
    {
        $q->select([
            'p.id', 'p.user_id', 'p.status', 'p.attempts', 'p.assessment_score', 'p.started_at', 'p.completed_at', 'p.updated_at',
            DB::raw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS name"), 'u.code',
            DB::raw("COALESCE(d.name, '') AS department"), 'm.title AS module', 'ph.title AS phase',
            DB::raw("CONCAT(LPAD(COALESCE(ph.`order`, 99), 3, '0'), LPAD(COALESCE(m.`order`, 0), 3, '0')) AS module_sort"),
        ]);
        $q->orderBy(self::SORTS[$sort], $dir)->orderBy('p.id', 'desc');

        return $q;
    }

    private static function byModule($q, $modules)
    {
        $stats = $q->groupBy('p.module_id')->selectRaw("
            p.module_id,
            SUM(p.attempts >= 1) AS tried,
            SUM(p.status = 'completed') AS passed,
            AVG(CASE WHEN p.status = 'completed' AND p.attempts >= 1 THEN p.attempts END) AS avg_to_pass,
            SUM(p.status = 'completed' AND p.attempts = 1) AS first_try,
            SUM(p.status = 'completed' AND p.attempts >= 1) AS passed_counted,
            COALESCE(SUM(CASE WHEN p.status = 'completed' THEN GREATEST(p.attempts - 1, 0) ELSE p.attempts END), 0) AS failed_attempts,
            MAX(p.attempts) AS max_attempts")->get()->keyBy('module_id');

        return $modules->filter(fn ($m) => $stats->has($m->id))->map(function ($m) use ($stats) {
            $s = $stats[$m->id];
            return (object) [
                'id' => $m->id, 'phase' => optional($m->phase)->title, 'module' => $m->title,
                'tried' => (int) $s->tried, 'passed' => (int) $s->passed,
                'avg_to_pass' => $s->avg_to_pass === null ? null : round((float) $s->avg_to_pass, 1),
                'first_try_pct' => (int) $s->passed_counted > 0 ? round($s->first_try / $s->passed_counted * 100) : null,
                'failed_attempts' => (int) $s->failed_attempts, 'max_attempts' => (int) $s->max_attempts,
            ];
        })->values();
    }

    /** Audit rows that are a start or an attempt; unlocks and the like are left out. */
    private static function logQuery(array $f)
    {
        $q = DB::table('audits as a')
            ->join('user_training_progress as p', 'p.id', '=', 'a.auditable_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->join('training_modules as m', 'm.id', '=', 'p.module_id')
            ->leftJoin('training_phases as ph', 'ph.id', '=', 'm.phase_id')
            ->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
            ->where('a.auditable_type', UserTrainingProgress::class)
            ->whereNull('u.deleted_at');
        if ($f['kind'] === 'attempts') $q->where('a.new_values', 'like', '%"attempts"%');
        else $q->where(fn ($w) => $w->where('a.new_values', 'like', '%"attempts"%')->orWhere('a.new_values', 'like', '%"status":"in_progress"%'));
        self::personFilters($q, $f);
        if ($f['module'] !== '') $q->where('p.module_id', (int) $f['module']);
        if ($f['from']) $q->where('a.created_at', '>=', $f['from'] . ' 00:00:00');
        if ($f['to']) $q->where('a.created_at', '<=', $f['to'] . ' 23:59:59');

        return $q->select([
            'a.id', 'a.event AS audit_event', 'a.old_values', 'a.new_values', 'a.created_at AS at', 'p.user_id', 'p.module_id',
            DB::raw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS name"), 'u.code',
            DB::raw("COALESCE(d.name, '') AS department"), 'm.title AS module', 'ph.title AS phase', 'm.passing_percentage',
        ])->orderByDesc('a.id');
    }

    /** What each audit row means: started, passed (score, attempt n), failed, completed. */
    private static function describe($rows)
    {
        return collect($rows)->map(function ($r) {
            $new = json_decode((string) $r->new_values, true) ?: [];
            $old = json_decode((string) $r->old_values, true) ?: [];
            $score = array_key_exists('assessment_score', $new) && $new['assessment_score'] !== null ? round((float) $new['assessment_score'], 1) : null;
            $attempt = array_key_exists('attempts', $new) ? (int) $new['attempts'] : null;
            if ($attempt !== null) {
                // The server's own rule: a pass is a score at the module's pass mark.
                $pass = (float) ($r->passing_percentage ?? 80);
                $event = $score === null ? (($new['status'] ?? '') === 'completed' ? 'Completed' : 'Attempt')
                    : ($score >= $pass ? 'Passed' : 'Failed');
            } else {
                $event = 'Started';
            }
            $r->event = $event;
            $r->score = $score;
            $r->attempt = $attempt;
            $r->was = $old['status'] ?? null;
            return $r;
        });
    }

    public static function statusLabel(?string $s): string
    {
        return ['completed' => 'Passed', 'failed' => 'Failed', 'in_progress' => 'In progress', 'available' => 'Not started'][$s] ?? 'Not started';
    }

    private static function date($v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private static function csv(string $name, array $head, $rows)
    {
        $file = $name . '-' . now()->format('Y-m-d') . '.csv';
        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // Excel reads it as UTF-8
            fputcsv($out, $head);
            foreach ($rows as $r) {
                // A cell that starts with = + - @ is a formula to Excel: keep it text.
                fputcsv($out, array_map(fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'" . $v : $v, (array) $r));
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
