@extends('layouts.layoutMaster')
@section('title', 'Fill Kingo Bingo Targets')

@section('content')
<style>
.ft-page  { padding: 20px 24px; }
.ft-topbar{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
.ft-title { font-size:1.25rem; font-weight:800; color:#1e3a5f; margin:0; flex:1; }

.ft-card  { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.09); overflow:hidden; }

.ft-hint  { font-size:11.5px; color:#64748b; padding:10px 20px 14px; border-top:1px solid #f1f5f9; }
.ft-hint kbd { background:#e5e7eb; border-radius:3px; padding:1px 5px; font-size:10.5px; color:#374151; }

/* ── table ── */
.ft-wrap { overflow-x:auto; }
table.ft { width:100%; border-collapse:collapse; font-size:11.5px; min-width:1100px; }

/* group header row */
table.ft tr.grp th { padding:5px 6px; font-size:10px; font-weight:700; text-align:center; border:1px solid rgba(0,0,0,.15); letter-spacing:.03em; position:sticky; top:0; z-index:6; }
table.ft tr.grp th.g-sp  { background:#0f2848; color:#fff; text-align:left; padding-left:14px; }
table.ft tr.grp th.g-lbl { background:#0f2848; color:#fff; }
table.ft tr.grp th.g-ann { background:#92400e; color:#fff; }
table.ft tr.grp th.g-qtr { background:#1d4ed8; color:#fff; }
table.ft tr.grp th.g-mon { background:#1e3a5f; color:#fff; }
table.ft tr.grp th.g-cur { background:#0c44a3; color:#fff; }

/* col header row */
table.ft tr.col th { background:#1e3a5f; color:#fff; font-weight:600; text-align:center; padding:6px 4px; font-size:10.5px; border:1px solid #1a3254; white-space:nowrap; position:sticky; top:28px; z-index:6; }
table.ft tr.col th.c-sp  { background:#152d50; text-align:left; padding-left:14px; min-width:155px; max-width:170px; }
table.ft tr.col th.c-lbl { background:#152d50; min-width:50px; }
table.ft tr.col th.c-ann { background:#b45309; }
table.ft tr.col th.c-qtr { background:#2563eb; min-width:64px; }
table.ft tr.col th.c-cur { background:#1d4ed8; }

/* data cells */
table.ft td { border:1px solid #e5e7eb; padding:0; vertical-align:middle; }

td.td-sp {
    font-weight:600; font-size:11px; padding:0 12px;
    background:#f1f5f9; color:#1e3a5f;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    max-width:170px;
    position:sticky; left:0; z-index:3; border-right:2px solid #cbd5e1;
}
td.td-lbl {
    font-size:10px; font-weight:700; text-align:center; padding:0 4px;
    position:sticky; left:155px; z-index:3; border-right:2px solid #d1d5db; min-width:50px;
}
td.td-lbl.k { background:#eef2ff; color:#3730a3; }
td.td-lbl.b { background:#f0fdf4; color:#166534; }

/* value cells */
td.v     { min-width:58px; max-width:72px; }
td.v-ann { min-width:72px; max-width:88px; background:#fef3c7; border-left:2px solid #fde68a; border-right:2px solid #fde68a; }
td.v-qtr { min-width:64px; max-width:78px; background:#eff6ff; }
td.v-cur { background:#dbeafe !important; }

td.v input, td.v-ann input, td.v-qtr input {
    width:100%; border:none; text-align:center; font-size:11.5px;
    padding:5px 2px; background:transparent; outline:none; color:#374151; cursor:pointer;
}
td.v input:focus, td.v-ann input:focus, td.v-qtr input:focus { background:#fef9c3; }

/* filled state */
td.filled       { background:#f0fdf4; }
td.filled input { color:#166534; font-weight:600; }
td.v-ann.filled { background:#fef08a; }
td.v-ann.filled input { color:#78350f; font-weight:700; }
td.v-qtr.filled { background:#dbeafe; }
td.v-qtr.filled input { color:#1d4ed8; font-weight:600; }

/* feedback states */
td.saving input { background:#fef9c3 !important; }
td.ok     input { background:#dcfce7 !important; }
td.err    input { background:#fee2e2 !important; color:#dc2626 !important; }

/* row lines */
tr.row-k td { border-bottom:none; }
tr.row-b td { border-top:1px dashed #e5e7eb; }
tr.row-sep td { height:4px; background:#f1f5f9; border:none; }

/* toast */
.ft-toast {
    position:fixed; bottom:22px; right:22px; z-index:9999;
    background:#1e3a5f; color:#fff; padding:7px 16px; border-radius:8px;
    font-size:12px; display:none; box-shadow:0 4px 12px rgba(0,0,0,.2);
    transition:opacity .2s;
}
</style>

<div class="ft-page">

{{-- topbar --}}
<div class="ft-topbar">
    <h1 class="ft-title"><i class="bx bx-target-lock me-2" style="color:#1d4ed8"></i>Kingo &amp; Bingo Targets</h1>
    <form method="GET" class="d-flex gap-2 align-items-center">
        <select name="fy" class="form-select form-select-sm" style="min-width:140px" onchange="this.form.submit()">
            @foreach($fyOptions as $fy)
            <option value="{{ $fy }}" {{ $selectedFy == $fy ? 'selected' : '' }}>FY {{ $fy }}</option>
            @endforeach
        </select>
    </form>
    <a href="{{ route('sales-visits.kingo-bingo.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
        <i class="bx bx-grid-alt me-1"></i> View ScoreCard
    </a>
</div>

@if($salespersons->isEmpty())
    <div class="alert alert-warning">No salespersons found.</div>
@else

@php
$qLabels = ['Q1 Apr–Jun', 'Q2 Jul–Sep', 'Q3 Oct–Dec', 'Q4 Jan–Mar'];
@endphp

<div class="ft-card">
<div class="ft-wrap">
<table class="ft">

{{-- GROUP HEADER --}}
<thead>
<tr class="grp">
    <th class="g-sp" rowspan="2">Salesperson</th>
    <th class="g-lbl" rowspan="2"></th>
    <th class="g-ann" colspan="1">Annual</th>
    <th class="g-qtr" colspan="4">Quarterly</th>
    <th class="g-mon" colspan="{{ count($months) }}">Monthly &nbsp;Apr → Mar</th>
</tr>

{{-- COL HEADER --}}
<tr class="col">
    <th class="c-ann">{{ $selectedFy }}</th>
    @foreach($quarterKeys as $i => $qk)
    <th class="c-qtr">{{ $qLabels[$i] }}</th>
    @endforeach
    @foreach($months as $m)
    <th class="{{ $m['key'] === $currentMonthKey ? 'c-cur' : '' }}">
        {{ $m['label'] }}@if($m['key'] === $currentMonthKey)<br><span style="font-size:8.5px;opacity:.75">now</span>@endif
    </th>
    @endforeach
</tr>
</thead>

{{-- BODY --}}
<tbody>
@foreach($salespersons as $sp)
@php
    $spT   = $targets[$sp->id] ?? collect();
    $getV  = fn($key, $field) => optional($spT->firstWhere('month_year', $key))->{$field};
@endphp

{{-- KINGO row --}}
<tr class="row-k">
    <td class="td-sp" rowspan="2" title="{{ $sp->name }}">{{ $sp->name }}</td>
    <td class="td-lbl k">KINGO</td>

    {{-- Annual --}}
    @php $v = $getV($annualKey, 'kingo_target'); @endphp
    <td class="v-ann {{ $v > 0 ? 'filled' : '' }}"
        data-sp="{{ $sp->id }}" data-month="{{ $annualKey }}" data-field="kingo_target">
        <input type="number" step="0.01" min="0"
               value="{{ $v > 0 ? $v + 0 : '' }}" placeholder="—"
               data-sp="{{ $sp->id }}" data-month="{{ $annualKey }}" data-field="kingo_target">
    </td>

    {{-- Quarters --}}
    @foreach($quarterKeys as $qk)
    @php $v = $getV($qk, 'kingo_target'); @endphp
    <td class="v-qtr {{ $v > 0 ? 'filled' : '' }}"
        data-sp="{{ $sp->id }}" data-month="{{ $qk }}" data-field="kingo_target">
        <input type="number" step="0.01" min="0"
               value="{{ $v > 0 ? $v + 0 : '' }}" placeholder="—"
               data-sp="{{ $sp->id }}" data-month="{{ $qk }}" data-field="kingo_target">
    </td>
    @endforeach

    {{-- Monthly --}}
    @foreach($months as $m)
    @php $v = $getV($m['key'], 'kingo_target'); $isCur = $m['key'] === $currentMonthKey; @endphp
    <td class="v {{ $isCur ? 'v-cur' : '' }} {{ $v > 0 ? 'filled' : '' }}"
        data-sp="{{ $sp->id }}" data-month="{{ $m['key'] }}" data-field="kingo_target">
        <input type="number" step="0.01" min="0"
               value="{{ $v > 0 ? $v + 0 : '' }}" placeholder="—"
               data-sp="{{ $sp->id }}" data-month="{{ $m['key'] }}" data-field="kingo_target">
    </td>
    @endforeach
</tr>

{{-- BINGO row --}}
<tr class="row-b">
    <td class="td-lbl b">BINGO</td>

    {{-- Annual --}}
    @php $v = $getV($annualKey, 'bingo_target'); @endphp
    <td class="v-ann {{ $v > 0 ? 'filled' : '' }}"
        data-sp="{{ $sp->id }}" data-month="{{ $annualKey }}" data-field="bingo_target">
        <input type="number" step="0.01" min="0"
               value="{{ $v > 0 ? $v + 0 : '' }}" placeholder="—"
               data-sp="{{ $sp->id }}" data-month="{{ $annualKey }}" data-field="bingo_target">
    </td>

    {{-- Quarters --}}
    @foreach($quarterKeys as $qk)
    @php $v = $getV($qk, 'bingo_target'); @endphp
    <td class="v-qtr {{ $v > 0 ? 'filled' : '' }}"
        data-sp="{{ $sp->id }}" data-month="{{ $qk }}" data-field="bingo_target">
        <input type="number" step="0.01" min="0"
               value="{{ $v > 0 ? $v + 0 : '' }}" placeholder="—"
               data-sp="{{ $sp->id }}" data-month="{{ $qk }}" data-field="bingo_target">
    </td>
    @endforeach

    {{-- Monthly --}}
    @foreach($months as $m)
    @php $v = $getV($m['key'], 'bingo_target'); $isCur = $m['key'] === $currentMonthKey; @endphp
    <td class="v {{ $isCur ? 'v-cur' : '' }} {{ $v > 0 ? 'filled' : '' }}"
        data-sp="{{ $sp->id }}" data-month="{{ $m['key'] }}" data-field="bingo_target">
        <input type="number" step="0.01" min="0"
               value="{{ $v > 0 ? $v + 0 : '' }}" placeholder="—"
               data-sp="{{ $sp->id }}" data-month="{{ $m['key'] }}" data-field="bingo_target">
    </td>
    @endforeach
</tr>

<tr class="row-sep"><td colspan="{{ 2 + 1 + count($quarterKeys) + count($months) }}"></td></tr>
@endforeach
</tbody>
</table>
</div>

<div class="ft-hint">
    <strong>How to fill:</strong>
    Click any cell → type a number <em>(in Lakhs)</em> → press <kbd>Tab</kbd> to jump to next. Saves automatically.
    &nbsp;|&nbsp; <span style="background:#fef3c7;border-radius:3px;padding:1px 6px;color:#78350f;font-weight:600;font-size:11px;">Yellow = Annual</span>
    <span style="background:#eff6ff;border-radius:3px;padding:1px 6px;color:#1d4ed8;font-weight:600;font-size:11px;">Blue = Quarterly</span>
    <span style="background:#f0fdf4;border-radius:3px;padding:1px 6px;color:#166534;font-weight:600;font-size:11px;">Green = Filled</span>
    <span style="background:#dbeafe;border-radius:3px;padding:1px 6px;color:#1d4ed8;font-weight:600;font-size:11px;">Highlight = Current Month</span>
</div>
</div>
@endif
</div>

<div class="ft-toast" id="ftToast"></div>

@section('page-script')
<script>
const SAVE_URL = "{{ route('sales-visits.targets.update') }}";
const CSRF     = "{{ csrf_token() }}";
let toastTimer;

function toast(msg, ok = true) {
    const el = document.getElementById('ftToast');
    el.textContent = msg;
    el.style.background = ok ? '#166534' : '#dc2626';
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.style.display = 'none'; }, 1600);
}

function saveCell(input) {
    const spId  = input.dataset.sp;
    const month = input.dataset.month;
    const field = input.dataset.field;
    const val   = input.value.trim();
    const td    = input.closest('td');

    td.classList.remove('ok', 'err');
    td.classList.add('saving');

    const otherField = field === 'kingo_target' ? 'bingo_target' : 'kingo_target';
    const otherInput = document.querySelector(
        `input[data-sp="${spId}"][data-month="${month}"][data-field="${otherField}"]`
    );

    fetch(SAVE_URL, {
        method : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body   : JSON.stringify({
            salesperson_id: spId,
            month_year    : month,
            kingo_target  : field === 'kingo_target' ? val : (otherInput ? otherInput.value.trim() : ''),
            bingo_target  : field === 'bingo_target' ? val : (otherInput ? otherInput.value.trim() : ''),
        })
    })
    .then(r => r.json())
    .then(data => {
        td.classList.remove('saving');
        if (data.success) {
            td.classList.toggle('filled', parseFloat(val) > 0);
            td.classList.add('ok');
            toast('Saved');
            setTimeout(() => td.classList.remove('ok'), 1000);
        } else {
            td.classList.add('err');
            toast('Save failed', false);
        }
    })
    .catch(() => {
        td.classList.remove('saving');
        td.classList.add('err');
        toast('Network error', false);
    });
}

document.querySelectorAll('table.ft input[type="number"]').forEach(inp => {
    inp.addEventListener('change', function() { saveCell(this); });
    inp.addEventListener('focus',  function() { this.placeholder = ''; });
    inp.addEventListener('blur',   function() { if (!this.value) this.placeholder = '—'; });
});
</script>
@endsection
@endsection
