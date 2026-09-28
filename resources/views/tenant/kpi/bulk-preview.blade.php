@extends('layouts.layoutMaster')
@section('title', 'Preview import')

@section('content')
<style>
    .pv-page { padding: 8px 4px 24px; }
    .pv-card { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); overflow:hidden; }
    .pv-card + .pv-card { margin-top:18px; }
    .pv-head { padding:14px 20px; border-bottom:1px solid #eef2f6; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .pv-head h6 { margin:0; font-weight:700; color:#1e3a5f; flex:1; }
    .pv-note { font-size:12px; color:#64748b; padding:10px 20px; background:#f8fafc; border-bottom:1px solid #eef2f6; }

    table.pv { width:100%; border-collapse:collapse; font-size:13px; }
    table.pv thead th { background:#0f2848; color:#fff; font-weight:600; font-size:11.5px; text-align:left;
        padding:9px 12px; white-space:nowrap; }
    table.pv thead th.kpi-col { text-align:center; background:#0f766e; }
    table.pv tbody td { border-bottom:1px solid #eef2f6; padding:7px 12px; }
    .pv-emp { font-weight:600; color:#1e3a5f; }
    .pv-dept { font-size:11.5px; color:#64748b; }
    .pv-val { text-align:center; font-weight:700; color:#0f766e; }
    .pv-clear { text-align:center; color:#b45309; font-size:11.5px; font-style:italic; }
    .pv-na { text-align:center; color:#cbd5e1; }
</style>

<div class="pv-page">

    <div class="d-flex align-items-center gap-2 mb-3">
        <h1 style="font-size:1.25rem;font-weight:800;color:#1e3a5f;margin:0;flex:1">
            <i class="bx bx-search-alt me-2" style="color:#0f766e"></i>Preview import — {{ $month->format('F Y') }}
        </h1>
        <a href="{{ route('tenant.kpi.bulk-entry') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="bx bx-x me-1"></i> Cancel
        </a>
    </div>

    @if($problems)
        <div class="alert alert-warning">
            <strong>{{ count($problems) }} {{ \Illuminate\Support\Str::plural('row', count($problems)) }} skipped:</strong>
            <ul class="mb-0 mt-1 small">
                @foreach(array_slice($problems, 0, 8) as $p)<li>{{ $p }}</li>@endforeach
                @if(count($problems) > 8)<li>and {{ count($problems) - 8 }} more</li>@endif
            </ul>
        </div>
    @endif

    <div class="pv-card">
        <div class="pv-head">
            <h6>{{ count($rows) }} {{ \Illuminate\Support\Str::plural('person', count($rows)) }} will be updated</h6>
            <span class="badge bg-label-secondary">
                {{ $selected->count() }} {{ \Illuminate\Support\Str::plural('KPI', $selected->count()) }}
            </span>
        </div>
        <div class="pv-note">
            Nothing has been saved yet. Check the numbers, then confirm — they count immediately, with no approval step.
            A blank cell in the sheet <em>clears</em> that figure rather than storing a zero.
        </div>

        <div style="max-height:60vh; overflow:auto;">
            <table class="pv">
                <thead>
                    <tr>
                        <th style="width:44px">#</th>
                        <th>Employee</th>
                        <th style="width:160px">Department</th>
                        @foreach($selected as $t)
                            <th class="kpi-col">{{ $labels[$t->id] ?? $t->title }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $r)
                        <tr>
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td><div class="pv-emp">{{ $r['name'] }}</div></td>
                            <td class="pv-dept">{{ $r['department'] }}</td>
                            @foreach($selected as $t)
                                @php $cell = $r['cells'][$t->id] ?? null; @endphp
                                @if(!$cell)
                                    <td class="pv-na">—</td>
                                @elseif($cell['value'] === null)
                                    <td class="pv-clear">clear</td>
                                @else
                                    <td class="pv-val">{{ rtrim(rtrim(number_format((float)$cell['value'], 2), '0'), '.') }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top d-flex justify-content-end gap-2">
            <a href="{{ route('tenant.kpi.bulk-entry') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
            <form method="POST" action="{{ route('tenant.kpi.bulk-entry.import.apply') }}">
                @csrf
                <button class="btn btn-hitech-primary rounded-pill px-4">
                    <i class="bx bx-check me-1"></i> Confirm and import
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
