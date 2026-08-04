<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

class AuditLogController extends Controller
{
    /** Fields that carry no meaning for a reader and only add noise. */
    private const NOISE_FIELDS = [
        'otp_code', 'otp_expires_at', 'remember_token', 'password',
        'updated_at', 'last_login_at', 'fcm_token',
    ];

    public function index(Request $request)
    {
        $q = Audit::query()->with(['user.department']);

        // ── Filters ──
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $q->whereHas('user', function ($w) use ($search) {
                $w->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        if ($dept = $request->input('dept')) {
            $q->whereHas('user', fn($w) => $w->where('department_id', $dept));
        }
        if ($event = $request->input('event')) {
            $q->where('event', $event);
        }
        if ($model = $request->input('model')) {
            $q->where('auditable_type', $model);
        }
        if ($ip = trim((string) $request->input('ip', ''))) {
            $q->where('ip_address', 'like', "%{$ip}%");
        }
        if ($from = $request->input('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $q->whereDate('created_at', '<=', $to);
        }
        // Automated writes (OTP issue/clear, token refresh) have no actor and
        // drown out real activity, so they are hidden unless asked for.
        if (!$request->boolean('system')) {
            $q->whereNotNull('user_id');
        }

        $per = (int) $request->input('per', 25);
        if (!in_array($per, [25, 50, 100, 200], true)) {
            $per = 25;
        }

        $stats = [
            'total'  => (clone $q)->count(),
            'today'  => (clone $q)->whereDate('created_at', now()->toDateString())->count(),
            'people' => (clone $q)->distinct()->count('user_id'),
        ];

        // The whole table used to be loaded at once (23k+ rows); now paginated.
        $logs = $q->orderByDesc('id')->paginate($per)->withQueryString();
        $this->decorate($logs->getCollection());

        // Option lists, built from what the log actually contains.
        $models = Audit::select('auditable_type')->distinct()->pluck('auditable_type')
            ->mapWithKeys(fn($m) => [$m => Str::headline(class_basename($m))])
            ->sort();
        $events = Audit::select('event')->distinct()->orderBy('event')->pluck('event');
        $departments = Department::orderBy('name')->get(['id', 'name']);

        return view('audit-logs.index', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'logs'        => $logs,
            'models'      => $models,
            'events'      => $events,
            'departments' => $departments,
            'stats'       => $stats,
            'per'         => $per,
        ]);
    }

    public function show(Request $request, $id)
    {
        $log = Audit::with(['user.department'])->findOrFail($id);
        $this->decorate(collect([$log]));

        // Field-by-field diff, noise removed.
        $old = (array) $log->old_values;
        $new = (array) $log->new_values;
        $changes = [];
        foreach (array_keys($old + $new) as $field) {
            if (in_array($field, self::NOISE_FIELDS, true)) continue;
            $before = $old[$field] ?? null;
            $after  = $new[$field] ?? null;
            if ($before === $after) continue;
            $changes[] = [
                'field'  => Str::headline($field),
                'before' => $this->stringify($before),
                'after'  => $this->stringify($after),
            ];
        }

        // That person's recent activity, shown alongside this entry.
        $histCount = (int) $request->input('hist', 10);
        if (!in_array($histCount, [10, 20, 50], true)) {
            $histCount = 10;
        }
        $history = collect();
        if ($log->user_id) {
            $history = Audit::where('user_id', $log->user_id)
                ->orderByDesc('id')->limit($histCount)->get();
            $this->decorate($history);
        }

        return view('audit-logs.show', compact('log', 'changes', 'history', 'histCount'));
    }

    /**
     * Attach the display fields the blades need: what kind of record was
     * touched, which record it was, and which fields changed. Names are
     * resolved in bulk per model so a page costs a handful of queries.
     */
    private function decorate($logs): void
    {
        $wanted = [];
        foreach ($logs as $l) {
            $wanted[$l->auditable_type][] = $l->auditable_id;
        }
        $names = [];
        foreach ($wanted as $class => $ids) {
            if (!class_exists($class)) continue;
            try {
                $rows = $class::withTrashed()->whereIn('id', array_unique($ids))->get();
            } catch (\Throwable $e) {
                continue;   // a model we cannot query is not worth failing the page for
            }
            foreach ($rows as $row) {
                $names[$class][$row->getKey()] = $this->labelFor($row);
            }
        }

        foreach ($logs as $l) {
            $l->model_label  = Str::headline(class_basename($l->auditable_type));
            $l->record_label = $names[$l->auditable_type][$l->auditable_id] ?? ('#' . $l->auditable_id);

            $old = (array) $l->old_values;
            $new = (array) $l->new_values;
            $fields = array_diff(array_keys($old + $new), self::NOISE_FIELDS);
            $l->changed_fields = array_values(array_map(fn($f) => Str::headline($f), $fields));
        }
    }

    private function labelFor($row): string
    {
        foreach (['name', 'party_name', 'title', 'label', 'email'] as $attr) {
            $v = $row->{$attr} ?? null;
            if (is_string($v) && trim($v) !== '') return $v;
        }
        $first = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
        return $first !== '' ? $first : ('#' . $row->getKey());
    }

    private function stringify($v): string
    {
        if ($v === null) return '—';
        if (is_bool($v)) return $v ? 'Yes' : 'No';
        if (is_array($v)) return json_encode($v);
        $s = trim((string) $v);
        return $s === '' ? '—' : $s;
    }
}
