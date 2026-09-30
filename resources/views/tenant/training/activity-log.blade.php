@extends('layouts.layoutMaster')

@section('title', 'Training Activity Log')

@section('page-style')
<style>
    .tl-page { --tl-primary: #0b5f68; --tl-primary-dark: #07464d; --tl-border: #e3ebee; --tl-ink: #1e293b; --tl-muted: #64748b; }
    .tl-hero { background: linear-gradient(120deg, #07464d 0%, #0b6b73 55%, #11a3ad 100%); border-radius: 18px; padding: 24px 28px; margin-bottom: 18px; color: #fff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 12px 32px rgba(7, 70, 77, .22); }
    .tl-hero h1 { font-size: 1.7rem; font-weight: 800; margin: 0 0 4px; color: #fff; }
    .tl-hero p { margin: 0; color: rgba(255,255,255,.85); font-size: .9rem; }
    .tl-hero-btn { display: inline-flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,.45); background: rgba(255,255,255,.12); color: #fff; border-radius: 12px; padding: 10px 18px; font-weight: 600; font-size: .85rem; text-decoration: none; }
    .tl-hero-btn:hover { background: rgba(255,255,255,.22); color: #fff; }
    .tl-panel { background: #fff; border: 1px solid var(--tl-border); border-radius: 14px; box-shadow: 0 2px 10px rgba(15, 23, 42, .04); }
    .tl-filter { padding: 14px 18px; margin-bottom: 18px; }
    .tl-filter label { display: block; font-size: .76rem; font-weight: 600; color: var(--tl-ink); margin-bottom: 5px; }
    .tl-page .tl-apply { height: 46px; border-radius: 12px; font-weight: 700; background: var(--tl-primary); border-color: var(--tl-primary); padding: 0 28px; }
    .tl-page .tl-apply:hover { background: var(--tl-primary-dark); border-color: var(--tl-primary-dark); }
    .tl-reset { display: inline-flex; align-items: center; gap: 6px; height: 46px; font-weight: 600; font-size: .86rem; color: var(--tl-ink); text-decoration: none; white-space: nowrap; }
    .tl-reset:hover { color: var(--tl-primary); }
    .tl-kpi { display: flex; gap: 12px; align-items: flex-start; padding: 16px; height: 100%; }
    .tl-kpi-icon { width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
    .tl-kpi-value { font-size: 1.45rem; font-weight: 800; color: var(--tl-ink); line-height: 1.1; }
    .tl-kpi-label { font-size: .8rem; color: var(--tl-ink); font-weight: 500; }
    .tl-kpi-sub { font-size: .7rem; color: var(--tl-muted); margin-top: 2px; }
    .c-teal { background: #e0f2f1; color: #0b6b73; } .c-blue { background: #dbeafe; color: #2563eb; } .c-green { background: #dcfce7; color: #16a34a; }
    .c-red { background: #fee2e2; color: #dc2626; } .c-orange { background: #fef3c7; color: #d97706; } .c-purple { background: #ede9fe; color: #7c3aed; }
    .tl-tabs { display: flex; gap: 6px; padding: 14px 18px 0; border-bottom: 1px solid var(--tl-border); flex-wrap: wrap; }
    .tl-tabs a { padding: 10px 16px; font-weight: 600; font-size: .88rem; color: var(--tl-muted); text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -1px; }
    .tl-tabs a:hover { color: var(--tl-primary); }
    .tl-tabs a.active { color: var(--tl-primary); border-bottom-color: var(--tl-primary); }
    .tl-bar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 12px 18px; }
    .tl-bar .note { font-size: .78rem; color: var(--tl-muted); }
    .tl-export { height: 40px; border-radius: 10px; border: 1px solid var(--tl-border); background: #fff; color: var(--tl-ink); font-weight: 600; font-size: .84rem; display: inline-flex; align-items: center; gap: 8px; padding: 0 16px; text-decoration: none; }
    .tl-export:hover { border-color: var(--tl-primary); color: var(--tl-primary); }
    .tl-table-wrap { overflow-x: auto; }
    .tl-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .tl-table th { font-size: .72rem; font-weight: 700; color: var(--tl-ink); background: #f5f8f9; padding: 10px 12px; text-align: left; white-space: nowrap; border-bottom: 1px solid var(--tl-border); }
    .tl-table th a { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; }
    .tl-table th a:hover, .tl-table th a.active { color: var(--tl-primary); }
    .tl-table td { padding: 10px 12px; border-bottom: 1px solid #f0f4f5; color: var(--tl-ink); vertical-align: middle; }
    .tl-table tbody tr:hover td { background: #f8fcfc; }
    .tl-table .sub { display: block; font-size: .72rem; color: var(--tl-muted); }
    .tl-table .num { text-align: center; }
    .tl-pill { font-size: .72rem; font-weight: 700; padding: 4px 11px; border-radius: 6px; white-space: nowrap; display: inline-block; }
    .p-passed, .p-completed { background: #dcfce7; color: #15803d; } .p-failed { background: #fee2e2; color: #b91c1c; }
    .p-in_progress, .p-started { background: #dbeafe; color: #1d4ed8; } .p-attempt { background: #f1f5f9; color: #475569; }
    .tl-att { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; height: 26px; border-radius: 13px; font-weight: 800; font-size: .8rem; padding: 0 8px; }
    .a-1 { background: #dcfce7; color: #15803d; } .a-2 { background: #fef3c7; color: #b45309; } .a-3 { background: #fee2e2; color: #b91c1c; } .a-0 { background: #f1f5f9; color: #64748b; }
    .tl-score-ok { color: #15803d; font-weight: 700; } .tl-score-bad { color: #b91c1c; font-weight: 700; }
    .tl-link { color: var(--tl-primary); text-decoration: none; font-weight: 600; }
    .tl-link:hover { text-decoration: underline; }
    .tl-empty { padding: 40px 16px; text-align: center; color: var(--tl-muted); }
    .tl-foot { padding: 12px 18px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; font-size: .8rem; color: var(--tl-muted); }
    .tl-foot .pagination { margin: 0; }
</style>
@endsection

@section('content')
@php
    $fmt = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d M Y') : '—';
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v) : null;
    $tabUrl = fn ($t) => request()->fullUrlWithQuery(['tab' => $t, 'page' => null, 'sort' => null, 'dir' => null, 'export' => null]);
    $sortLink = function ($key, $label) use ($sort, $dir) {
        $active = $sort === $key;
        $next = $active && $dir === 'desc' ? 'asc' : 'desc';
        $url = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null]);
        return '<a href="' . e($url) . '" class="' . ($active ? 'active' : '') . '">' . e($label) . ($active ? ($dir === 'asc' ? ' ↑' : ' ↓') : '') . '</a>';
    };
    $att = fn ($n) => 'a-' . ($n <= 0 ? 0 : ($n === 1 ? 1 : ($n <= 3 ? 2 : 3)));
@endphp
<div class="tl-page">

    <div class="tl-hero">
        <div>
            <h1>Training Activity Log</h1>
            <p>Every attempt, and how many it took to pass.</p>
        </div>
        <a href="{{ route('training.activity') }}" class="tl-hero-btn"><i class="bx bx-left-arrow-alt"></i> Training Dashboard</a>
    </div>

    {{-- Filters: they narrow the whole page --}}
    <form method="GET" action="{{ route('training.activity') }}" class="tl-panel tl-filter">
        <input type="hidden" name="view" value="log">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-6 col-xl-3">
                <label for="tlQ">Search</label>
                <input id="tlQ" type="text" name="q" value="{{ $f['q'] }}" class="form-control" placeholder="Name, email or code">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="tlDept">Department</label>
                <select id="tlDept" name="department" class="form-select">
                    <option value="">All departments</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected($f['department'] === (string) $d->id)>{{ $d->name }}</option>
                    @endforeach
                    <option value="none" @selected($f['department'] === 'none')>No department</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="tlModule">Module</label>
                <select id="tlModule" name="module" class="form-select">
                    <option value="">All modules</option>
                    @foreach($modules->groupBy(fn ($m) => optional($m->phase)->title ?? 'Other') as $phaseTitle => $group)
                        <optgroup label="{{ $phaseTitle }}">
                            @foreach($group as $m)
                                <option value="{{ $m->id }}" @selected($f['module'] === (string) $m->id)>{{ $m->title }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            @if($tab === 'log')
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="tlKind">Show</label>
                    <select id="tlKind" name="kind" class="form-select">
                        <option value="">Starts and attempts</option>
                        <option value="attempts" @selected($f['kind'] === 'attempts')>Quiz attempts only</option>
                    </select>
                </div>
            @else
                <div class="col-6 col-md-3 col-xl-1">
                    <label for="tlStatus">Status</label>
                    <select id="tlStatus" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="passed" @selected($f['status'] === 'passed')>Passed</option>
                        <option value="failed" @selected($f['status'] === 'failed')>Failed</option>
                        <option value="in_progress" @selected($f['status'] === 'in_progress')>In progress</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-1">
                    <label for="tlAtt">Attempts</label>
                    <select id="tlAtt" name="attempts" class="form-select">
                        <option value="">Any</option>
                        @foreach([2, 3, 5] as $n)<option value="{{ $n }}" @selected($f['attempts'] === $n)>{{ $n }}+</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="col-6 col-md-3 col-xl-1">
                <label for="tlFrom">From</label>
                <input id="tlFrom" type="date" name="from" value="{{ $f['from'] }}" class="form-control">
            </div>
            <div class="col-6 col-md-3 col-xl-1">
                <label for="tlTo">To</label>
                <input id="tlTo" type="date" name="to" value="{{ $f['to'] }}" class="form-control">
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-primary tl-apply">Apply</button></div>
            <div class="col-auto"><a href="{{ route('training.activity', ['view' => 'log', 'tab' => $tab]) }}" class="tl-reset"><i class="bx bx-refresh"></i> Reset</a></div>
        </div>
    </form>

    {{-- Totals for what the filters select --}}
    <div class="row g-3 mb-3">
        @php
            $kpis = [
                ['bx bx-group', 'c-teal', $cards->people, 'People', 'with training activity'],
                ['bx bx-edit', 'c-blue', $cards->attempts, 'Total attempts', 'every quiz submission'],
                ['bx bx-check-circle', 'c-green', $cards->passed, 'Modules passed', 'person × module'],
                ['bx bx-x-circle', 'c-red', $cards->failed_attempts, 'Failed attempts', 'attempts that did not pass'],
                ['bx bx-repeat', 'c-orange', $cards->avg_to_pass === null ? '—' : $cards->avg_to_pass, 'Avg attempts to pass', 'most on one module: ' . $cards->max_attempts],
                ['bx bx-target-lock', 'c-purple', $cards->first_try_pct === null ? '—' : $cards->first_try_pct . '%', 'Passed first try', 'of modules passed'],
            ];
        @endphp
        @foreach($kpis as [$icon, $tone, $value, $label, $sub])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="tl-panel tl-kpi">
                    <div class="tl-kpi-icon {{ $tone }}"><i class="{{ $icon }}"></i></div>
                    <div style="min-width:0;">
                        <div class="tl-kpi-value">{{ $value }}</div>
                        <div class="tl-kpi-label">{{ $label }}</div>
                        <div class="tl-kpi-sub">{{ $sub }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="tl-panel">
        <nav class="tl-tabs">
            <a href="{{ $tabUrl('attempts') }}" class="{{ $tab === 'attempts' ? 'active' : '' }}"><i class="bx bx-user me-1"></i>Attempts by person</a>
            <a href="{{ $tabUrl('modules') }}" class="{{ $tab === 'modules' ? 'active' : '' }}"><i class="bx bx-layer me-1"></i>By module</a>
            <a href="{{ $tabUrl('log') }}" class="{{ $tab === 'log' ? 'active' : '' }}"><i class="bx bx-history me-1"></i>Activity log</a>
        </nav>
        <div class="tl-bar">
            <span class="note">
                @if($tab === 'log')
                    Each start, pass and fail as it happened{{ $logSince ? ' - recorded from ' . \Carbon\Carbon::parse($logSince)->format('d M Y') . '; attempts before then are in the counts, not in this log' : '' }}.
                @elseif($tab === 'modules')
                    How many attempts each module takes to pass.
                @else
                    One row per person per module. Attempts counts every quiz submission.
                @endif
                @if($filtered) &middot; filtered @endif
            </span>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}" class="tl-export"><i class="bx bx-download"></i> Export CSV</a>
        </div>

        <div class="tl-table-wrap">
        @if($tab === 'attempts')
            <table class="tl-table">
                <thead>
                    <tr>
                        <th>{!! $sortLink('name', 'Employee') !!}</th>
                        <th>{!! $sortLink('department', 'Department') !!}</th>
                        <th>{!! $sortLink('module', 'Module') !!}</th>
                        <th>{!! $sortLink('status', 'Status') !!}</th>
                        <th class="num">{!! $sortLink('attempts', 'Attempts') !!}</th>
                        <th class="num">{!! $sortLink('score', 'Last score') !!}</th>
                        <th>{!! $sortLink('started', 'Started') !!}</th>
                        <th>{!! $sortLink('passed', 'Passed on') !!}</th>
                        <th>{!! $sortLink('last', 'Last activity') !!}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td><b>{{ $r->name }}</b><span class="sub">{{ $r->code ?: '—' }}</span></td>
                            <td>{{ $r->department ?: '—' }}</td>
                            <td>{{ $r->module }}<span class="sub">{{ $r->phase }}</span></td>
                            <td><span class="tl-pill p-{{ $r->status === 'completed' ? 'passed' : $r->status }}">{{ \App\Support\TrainingActivityLog::statusLabel($r->status) }}</span></td>
                            <td class="num"><span class="tl-att {{ $att((int) $r->attempts) }}" title="{{ $r->status === 'completed' ? 'Passed on attempt ' . $r->attempts : $r->attempts . ' attempt(s) so far' }}">{{ $r->attempts }}</span></td>
                            <td class="num">
                                @if($r->assessment_score === null)<span class="text-muted">—</span>
                                @else<span class="{{ $r->status === 'completed' ? 'tl-score-ok' : 'tl-score-bad' }}">{{ round((float) $r->assessment_score, 1) }}%</span>@endif
                            </td>
                            <td>{{ $fmt($r->started_at) }}</td>
                            <td>{{ $r->status === 'completed' ? $fmt($r->completed_at) : '—' }}</td>
                            <td title="{{ $r->updated_at }}">{{ $when($r->updated_at)?->diffForHumans() ?? '—' }}</td>
                            <td><a href="{{ route('training.report-card', $r->user_id) }}" class="tl-link" title="Report card">Results</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="tl-empty">No training activity matches these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif($tab === 'modules')
            <table class="tl-table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th class="num">People who attempted</th>
                        <th class="num">Passed</th>
                        <th class="num">Avg attempts to pass</th>
                        <th class="num">Passed first try</th>
                        <th class="num">Failed attempts</th>
                        <th class="num">Most attempts</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td><a class="tl-link" href="{{ route('training.activity', array_filter(['view' => 'log', 'tab' => 'attempts', 'module' => $r->id, 'department' => $f['department'], 'q' => $f['q']])) }}">{{ $r->module }}</a><span class="sub">{{ $r->phase }}</span></td>
                            <td class="num">{{ $r->tried }}</td>
                            <td class="num">{{ $r->passed }}</td>
                            <td class="num">@if($r->avg_to_pass === null)—@else<span class="tl-att {{ $att((int) ceil($r->avg_to_pass)) }}">{{ $r->avg_to_pass }}</span>@endif</td>
                            <td class="num">{{ $r->first_try_pct === null ? '—' : $r->first_try_pct . '%' }}</td>
                            <td class="num">{{ $r->failed_attempts }}</td>
                            <td class="num"><span class="tl-att {{ $att($r->max_attempts) }}">{{ $r->max_attempts }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="tl-empty">No training activity matches these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="tl-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Module</th>
                        <th>Event</th>
                        <th class="num">Attempt</th>
                        <th class="num">Score</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $e)
                        <tr>
                            <td title="{{ $e->at }}"><b>{{ \Carbon\Carbon::parse($e->at)->format('d M Y, H:i') }}</b><span class="sub">{{ \Carbon\Carbon::parse($e->at)->diffForHumans() }}</span></td>
                            <td><b>{{ $e->name }}</b><span class="sub">{{ $e->code ?: '—' }}</span></td>
                            <td>{{ $e->department ?: '—' }}</td>
                            <td>{{ $e->module }}<span class="sub">{{ $e->phase }}</span></td>
                            <td><span class="tl-pill p-{{ strtolower($e->event) }}">{{ $e->event }}</span></td>
                            <td class="num">{{ $e->attempt ?? '—' }}</td>
                            <td class="num">
                                @if($e->score === null)<span class="text-muted">—</span>
                                @else<span class="{{ $e->event === 'Passed' ? 'tl-score-ok' : 'tl-score-bad' }}">{{ $e->score }}%</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="tl-empty">No activity matches these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
        </div>

        @if($tab !== 'modules' && method_exists($rows, 'links'))
            <div class="tl-foot">
                {{ $rows->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        @elseif($tab === 'modules')
            <div class="tl-foot"><span>{{ count($rows) }} {{ \Illuminate\Support\Str::plural('module', count($rows)) }}</span></div>
        @endif
    </div>
</div>
@endsection
