@extends('layouts.layoutMaster')
@section('title', 'Kingo Bingo ScoreCard')

@section('content')
<style>
:root {
    --sc-navy:#1e3a5f; --sc-blue:#2563eb;
    --sc-green:#16a34a; --sc-orange:#d97706; --sc-red:#dc2626;
}
.kb-page{padding:20px 24px;}
.kb-topbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px;}
.kb-page-title{font-size:1.35rem;font-weight:800;color:var(--sc-navy);margin:0;flex:1;}

/* Card */
.kb-card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.09);margin-bottom:32px;overflow:hidden;}
.kb-card-header{background:linear-gradient(135deg,#1e3a5f 0%,#2c5282 100%);color:#fff;padding:14px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.kb-card-header h3{margin:0;font-size:1.05rem;font-weight:700;}

/* Summary chips */
.kb-chip{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:8px;padding:5px 12px;font-size:11px;line-height:1.4;text-align:center;min-width:90px;}
.kb-chip .cl{opacity:.7;font-size:9.5px;display:block;text-transform:uppercase;letter-spacing:.4px;}
.kb-chip .cv{font-weight:700;font-size:13px;}
.kb-chip.good{background:rgba(22,163,74,.3);border-color:rgba(22,163,74,.6);}
.kb-chip.mid{background:rgba(217,119,6,.3);border-color:rgba(217,119,6,.6);}
.kb-chip.bad{background:rgba(220,38,38,.3);border-color:rgba(220,38,38,.6);}

/* Quarterly strip */
.kb-qtr-strip{background:#f0f4ff;border-bottom:1px solid #e2e8f0;padding:8px 20px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;}
.kb-qtr-pill{font-size:10.5px;padding:4px 10px;border-radius:20px;background:#fff;border:1px solid #c7d2fe;color:#1e40af;font-weight:600;line-height:1.5;}
.kb-qtr-pill .qd{color:#6b7280;font-weight:400;}
.qp-good{background:#dcfce7;color:#166534;border-color:#86efac;}
.qp-mid{background:#fef3c7;color:#92400e;border-color:#fcd34d;}
.qp-bad{background:#fee2e2;color:#991b1b;border-color:#fca5a5;}

/* Table */
.kb-table-wrap{overflow-x:auto;padding:16px 20px 20px;}
table.kb-table{width:100%;border-collapse:separate;border-spacing:0;font-size:11.5px;min-width:900px;table-layout:auto;}
table.kb-table th{background:var(--sc-navy);color:#fff;font-weight:600;text-align:center;padding:6px 4px;font-size:10.5px;border:1px solid #1a3254;white-space:nowrap;}
table.kb-table th.cur-m{background:#1d4ed8;}
table.kb-table th.kpi-h{text-align:left;padding-left:10px;min-width:170px;}

/* Section row */
tr.sec-row td{background:#dbeafe;color:#1e3a5f;font-weight:700;font-size:11.5px;padding:6px 10px;border-top:2px solid #93c5fd;border-bottom:1px solid #bfdbfe;}

/* Data rows */
tr.r-target   td{background:#eef2ff;}
tr.r-achieved td{background:#f0fdf4;}
tr.r-score    td{background:#fffbeb;}
tr.r-manual   td{background:#fff1f2;}

table.kb-table td{border:1px solid #e5e7eb;padding:4px 5px;white-space:nowrap;}
.rl{font-size:10.5px;color:#374151;padding-left:18px!important;font-weight:500;}
tr.r-target   .rl{color:#3730a3;}
tr.r-achieved .rl{color:#166534;}
tr.r-score    .rl{color:#92400e;font-weight:700;}
tr.r-manual   .rl{color:#9f1239;}

/* Score badge */
td.sb{text-align:center;font-weight:700;font-size:11.5px;}
td.sb.sg{background:#dcfce7;color:#166534;}
td.sb.so{background:#fef3c7;color:#92400e;}
td.sb.sr{background:#fee2e2;color:#991b1b;}
td.sb.sz{background:#f3f4f6;color:#9ca3af;}

/* Editable target cells */
td.tc{text-align:right;cursor:pointer;padding-right:8px!important;transition:background .12s;}
td.tc:hover{background:#fffbeb!important;}
td.tc.editing{background:#fef9c3!important;padding:0!important;}
td.tc input{width:100%;border:none;background:transparent;text-align:right;font-size:11.5px;padding:3px 8px;outline:none;}

/* Auto (read-only) achieved cells */
td.ac{text-align:right;padding-right:8px!important;font-size:11.5px;color:#374151;}

tr.kpi-gap td{height:4px;background:#f8fafc;border:none;}
</style>

<div class="kb-page">
<div class="kb-topbar">
    <h1 class="kb-page-title">Kingo Bingo ScoreCard</h1>
    @if($isAdmin || $isCcare || $isNewBiz)
    <a href="{{ route('sales-visits.kingo-bingo.fill-targets') }}" class="btn btn-sm btn-success rounded-pill fw-bold shadow-sm d-flex align-items-center">
        <i class="bx bx-edit me-1"></i> Fill Targets
    </a>
    <a href="{{ route('sales-visits.sales-targets.import') }}" class="btn btn-sm btn-outline-primary rounded-pill fw-bold shadow-sm d-flex align-items-center">
        <i class="bx bx-upload me-1"></i> Import Targets
    </a>
    @endif
    <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
        @if($isAdmin || $isCcare || $isNewBiz)
        <select name="salesperson_id" class="form-select form-select-sm" style="min-width:180px" onchange="this.form.submit()">
            <option value="">— All Salespersons —</option>
            @foreach($allUsers as $u)
            <option value="{{ $u->id }}" {{ request('salesperson_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>
        @endif
        <select name="fy" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach($fyOptions as $fy)
            <option value="{{ $fy }}" {{ $selectedFy == $fy ? 'selected' : '' }}>FY {{ $fy }}</option>
            @endforeach
        </select>
    </form>
</div>

@php
$kpiMeta = [
    'razor_blade'   => ['tf'=>'razor_blade_target',   'mult'=>100, 'tl'=>'Monthly Target'],
    'new_customers' => ['tf'=>'new_customers_target',  'mult'=>100, 'tl'=>'Monthly Target'],
    'upsell'        => ['tf'=>'upsell_target',         'mult'=>125, 'tl'=>'Monthly Target'],
    'payment'       => ['tf'=>'payment_target',        'mult'=>110, 'tl'=>'Target (Lacs)'],
    'rate'          => ['tf'=>'rate_target',           'mult'=>100, 'tl'=>'Monthly Target'],
    'nif'           => ['tf'=>'nif_target',            'mult'=>100, 'tl'=>'Monthly Target'],
    'training'      => ['tf'=>'training_target',       'mult'=>125, 'tl'=>'Monthly Target'],
    'mom'           => ['tf'=>'mom_target',            'mult'=>125, 'tl'=>'Monthly Target'],
];

// ALL achieved values are now auto-calculated from completed visits or pipeline
$kpiAutoKey = [
    'razor_blade'   => 'razor_achieved',
    'new_customers' => 'new_cust',
    'upsell'        => 'upsell_achieved',
    'payment'       => 'payment_achieved',
    'rate'          => 'rate_achieved',
    'nif'           => 'nif_achieved',
    'training'      => 'training_achieved',
    'mom'           => 'mom_achieved',
];

$kpiSections = [
    ['key'=>'razor_blade',   'label'=>'Razor Blade Test',             'icon'=>'🔪'],
    ['key'=>'new_customers', 'label'=>'New Customers',                'icon'=>'🆕'],
    ['key'=>'upsell',        'label'=>'Upgrade / Upsell / Cross-sell','icon'=>'📈'],
    ['key'=>'payment',       'label'=>'Payment Collection (Lacs)',    'icon'=>'💰'],
    ['key'=>'rate',          'label'=>'Rate Increases',               'icon'=>'📊'],
    ['key'=>'nif',           'label'=>'NIF Clients',                  'icon'=>'🏭'],
    ['key'=>'training',      'label'=>'Customer Training Programme',  'icon'=>'🎓'],
    ['key'=>'mom',           'label'=>'MOM Reports',                  'icon'=>'📋'],
];

$sc = function($s) {
    if ($s >= 120) return 'sg';
    if ($s >= 100) return 'sg';
    if ($s >= 60)  return 'so';
    if ($s > 0)    return 'sr';
    return 'sz';
};
@endphp

@foreach($salespersons as $sp)
@php
$spId     = $sp->id;
$spT      = $targets[$spId]    ?? collect();
$spKb     = $kbTargets[$spId]  ?? collect();
$spAuto   = $autoAchieved[$spId] ?? [];

$getT  = fn($mk) => $spT->firstWhere('month_year', $mk);
$getKb = fn($mk) => $spKb->firstWhere('month_year', $mk);
$getA  = fn($mk, $key) => $spAuto[$mk][$key] ?? 0;

// Annual totals
$bingoAnn=0; $kingoAnn=0; $actAnn=0;
foreach ($months as $m) {
    $t=$getT($m['key']);
    $bingoAnn += $t ? (float)$t->bingo_target : 0;
    $kingoAnn += $t ? (float)$t->kingo_target  : 0;
    $actAnn   += $spAuto[$m['key']]['actual_sale'] ?? 0;
}
$fyScoreArr=[];
foreach ($months as $m) {
    $t=$getT($m['key']); $b=$t?(float)$t->bingo_target:0;
    $a=$spAuto[$m['key']]['actual_sale']??0;
    if($b>0 && $a>0) $fyScoreArr[]=($a/$b)*100;
}
$fyScore=count($fyScoreArr)?round(array_sum($fyScoreArr)/count($fyScoreArr),1):0;
$fyScoreCls=$fyScore>=100?'good':($fyScore>=70?'mid':($fyScore>0?'bad':''));

// Quarterly
$qB=[0,0,0,0];$qK=[0,0,0,0];$qA=[0,0,0,0];
foreach ($months as $i=>$m) {
    $q=(int)($i/3); $t=$getT($m['key']);
    $qB[$q]+=$t?(float)$t->bingo_target:0;
    $qK[$q]+=$t?(float)$t->kingo_target:0;
    $qA[$q]+=$spAuto[$m['key']]['actual_sale']??0;
}
$qLabels=['Q1 Apr–Jun','Q2 Jul–Sep','Q3 Oct–Dec','Q4 Jan–Mar'];
@endphp

<div class="kb-card">

{{-- Header --}}
<div class="kb-card-header">
    <h3>{{ $sp->name }}</h3>
    <div class="kb-chip"><span class="cl">Bingo Annual</span><span class="cv">{{ $bingoAnn>0?number_format($bingoAnn,1):'—' }}</span></div>
    <div class="kb-chip"><span class="cl">Kingo Annual</span><span class="cv">{{ $kingoAnn>0?number_format($kingoAnn,1):'—' }}</span></div>
    <div class="kb-chip {{ $actAnn>=$bingoAnn&&$bingoAnn>0?'good':($actAnn>0?'mid':'') }}">
        <span class="cl">Actual Annual</span><span class="cv">{{ $actAnn>0?number_format($actAnn,1):'—' }}</span>
    </div>
    @if($fyScore>0)
    <div class="kb-chip {{ $fyScoreCls }}"><span class="cl">FY Avg Score</span><span class="cv">{{ $fyScore }}%</span></div>
    @endif
</div>

{{-- Quarterly strip --}}
<div class="kb-qtr-strip">
    <span style="font-size:10.5px;font-weight:700;color:#4b5563;margin-right:4px;">Quarterly:</span>
    @foreach($qLabels as $qi=>$ql)
    @php
        $qa=round($qA[$qi],1); $qb=round($qB[$qi],1); $qk=round($qK[$qi],1);
        $qs=$qb>0?round(($qa/$qb)*100,1):0;
        $qpc=$qs>=100?'qp-good':($qs>=70?'qp-mid':($qs>0?'qp-bad':''));
    @endphp
    <span class="kb-qtr-pill {{ $qpc }}">
        {{ $ql }}
        <span class="qd">
            Bingo {{ $qb>0?$qb:'—' }}
            &nbsp;|&nbsp;Kingo {{ $qk>0?$qk:'—' }}
            &nbsp;|&nbsp;Actual {{ $qa>0?$qa:'—' }}
            @if($qs>0) &nbsp;<strong>{{ $qs }}%</strong>@endif
        </span>
    </span>
    @endforeach
</div>

{{-- Legend --}}
<div style="padding:6px 20px 0;display:flex;gap:10px;flex-wrap:wrap;font-size:10.5px;align-items:center;">
    <span style="color:#64748b;font-weight:600;">Legend:</span>
    @if($canEditTargets)
    <span style="background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;padding:2px 8px;border-radius:4px;font-weight:600;">Target — click to edit (admin/CC only)</span>
    @endif
    <span style="background:#f0fdf4;color:#166534;border:1px solid #86efac;padding:2px 8px;border-radius:4px;font-weight:600;">Achieved — auto from completed visits &amp; pipeline</span>
    <span style="background:#fffbeb;color:#92400e;border:1px solid #fde68a;padding:2px 8px;border-radius:4px;font-weight:600;">Score</span>
</div>

{{-- Table --}}
<div class="kb-table-wrap">
<table class="kb-table">
<thead>
<tr>
    <th class="kpi-h">KPI</th>
    @foreach($months as $m)
    <th class="{{ $m['key']===$currentMonthKey?'cur-m':'' }}">{{ $m['display'] }}</th>
    @endforeach
</tr>
</thead>
<tbody>

{{-- ─── BINGO / KINGO ─── --}}
<tr class="sec-row"><td colspan="{{ count($months)+1 }}">🎯 Bingo / Kingo Sales Target</td></tr>
<tr class="r-target">
    <td class="rl">Bingo Target <small style="opacity:.7;font-size:9px;background:#dbeafe;padding:1px 4px;border-radius:3px;color:#1d4ed8;">{{ $canEditTargets?'click to edit':'admin/CC only' }}</small></td>
    @foreach($months as $m)
    @php $t=$getT($m['key']); $v=$t?$t->bingo_target:null; @endphp
    @if($canEditTargets)
    <td class="tc" data-sp="{{ $spId }}" data-month="{{ $m['key'] }}" data-route="target" data-field="bingo_target">{{ $v!==null?$v+0:'' }}</td>
    @else
    <td class="ac" style="text-align:right;">{{ $v!==null?$v+0:'' }}</td>
    @endif
    @endforeach
</tr>
<tr class="r-target">
    <td class="rl">Kingo Target <small style="opacity:.7;font-size:9px;background:#dbeafe;padding:1px 4px;border-radius:3px;color:#1d4ed8;">{{ $canEditTargets?'click to edit':'admin/CC only' }}</small></td>
    @foreach($months as $m)
    @php $t=$getT($m['key']); $v=$t?$t->kingo_target:null; @endphp
    @if($canEditTargets)
    <td class="tc" data-sp="{{ $spId }}" data-month="{{ $m['key'] }}" data-route="target" data-field="kingo_target">{{ $v!==null?$v+0:'' }}</td>
    @else
    <td class="ac" style="text-align:right;">{{ $v!==null?$v+0:'' }}</td>
    @endif
    @endforeach
</tr>
<tr class="r-achieved">
    <td class="rl" style="color:#065f46;">Actual Monthly <small style="opacity:.8;font-size:9px;background:#dcfce7;padding:1px 4px;border-radius:3px;color:#166534;">auto from pipeline</small></td>
    @foreach($months as $m)
    @php $v=round($spAuto[$m['key']]['actual_sale']??0,2); @endphp
    <td class="ac">{{ $v>0?number_format($v,2):'' }}</td>
    @endforeach
</tr>
<tr class="r-score">
    <td class="rl">Score</td>
    @foreach($months as $m)
    @php
        $t=$getT($m['key']); $b=$t?(float)$t->bingo_target:0;
        $a=(float)($spAuto[$m['key']]['actual_sale']??0);
        $s=$b>0?round(($a/$b)*150,1):0;
    @endphp
    <td class="sb {{ $sc($s) }}">{{ $s>0?$s:'' }}</td>
    @endforeach
</tr>
<tr class="kpi-gap"><td colspan="{{ count($months)+1 }}"></td></tr>

{{-- ─── KPI SECTIONS ─── --}}
@foreach($kpiSections as $kpi)
@php $km=$kpiMeta[$kpi['key']]; $autoKey=$kpiAutoKey[$kpi['key']]; @endphp
<tr class="sec-row"><td colspan="{{ count($months)+1 }}">{{ $kpi['icon'] }} {{ $kpi['label'] }}</td></tr>

<tr class="r-target">
    <td class="rl">{{ $km['tl'] }} <small style="opacity:.7;font-size:9px;background:#dbeafe;padding:1px 4px;border-radius:3px;color:#1d4ed8;">{{ $canEditTargets?'click to edit':'admin/CC only' }}</small></td>
    @foreach($months as $m)
    @php $kb=$getKb($m['key']); $tf=$km['tf']; $v=$kb?$kb->$tf:null; @endphp
    @if($canEditTargets)
    <td class="tc" data-sp="{{ $spId }}" data-month="{{ $m['key'] }}" data-route="kpi" data-field="{{ $tf }}">{{ $v!==null?$v+0:'' }}</td>
    @else
    <td class="ac" style="text-align:right;">{{ $v!==null?$v+0:'' }}</td>
    @endif
    @endforeach
</tr>

<tr class="r-achieved">
    <td class="rl">Achieved <small style="opacity:.8;font-size:9px;background:#dcfce7;padding:1px 4px;border-radius:3px;color:#166534;">auto from visits</small></td>
    @foreach($months as $m)
    @php $av = $spAuto[$m['key']][$autoKey] ?? 0; if($kpi['key']==='payment') $av=round($av,2); @endphp
    <td class="ac">{{ $av>0?$av:'' }}</td>
    @endforeach
</tr>

<tr class="r-score">
    <td class="rl">Score</td>
    @foreach($months as $m)
    @php
        $kb=$getKb($m['key']); $tf=$km['tf'];
        $tv=$kb?(float)$kb->$tf:0;
        $av=(float)($spAuto[$m['key']][$autoKey]??0);
        $s=$tv>0?round(($av/$tv)*$km['mult'],1):0;
    @endphp
    <td class="sb {{ $sc($s) }}">{{ $s>0?$s:'' }}</td>
    @endforeach
</tr>
<tr class="kpi-gap"><td colspan="{{ count($months)+1 }}"></td></tr>
@endforeach

{{-- ─── COMPETITOR INSIGHTS & PRODUCT IDEAS (bonus, no score) ─── --}}
<tr class="sec-row"><td colspan="{{ count($months)+1 }}">📊 Visit Insights (from completed visits)</td></tr>
<tr class="r-achieved">
    <td class="rl">Competitor Insights <small style="opacity:.8;font-size:9px;background:#dcfce7;padding:1px 4px;border-radius:3px;color:#166534;">auto</small></td>
    @foreach($months as $m)
    @php $v=$spAuto[$m['key']]['competitor_insights']??0; @endphp
    <td class="ac">{{ $v>0?$v:'' }}</td>
    @endforeach
</tr>
<tr class="r-achieved">
    <td class="rl">Product Ideas <small style="opacity:.8;font-size:9px;background:#dcfce7;padding:1px 4px;border-radius:3px;color:#166534;">auto</small></td>
    @foreach($months as $m)
    @php $v=$spAuto[$m['key']]['product_ideas']??0; @endphp
    <td class="ac">{{ $v>0?$v:'' }}</td>
    @endforeach
</tr>
<tr class="r-achieved">
    <td class="rl">Total Completed Visits <small style="opacity:.8;font-size:9px;background:#dcfce7;padding:1px 4px;border-radius:3px;color:#166534;">auto</small></td>
    @foreach($months as $m)
    @php $v=$spAuto[$m['key']]['total_visits']??0; @endphp
    <td class="ac">{{ $v>0?$v:'' }}</td>
    @endforeach
</tr>
<tr class="kpi-gap"><td colspan="{{ count($months)+1 }}"></td></tr>

{{-- ─── CUSTOMER / ORDER RATIO ─── --}}
<tr class="sec-row"><td colspan="{{ count($months)+1 }}">🔄 Customer / Order Ratio</td></tr>
<tr class="r-achieved">
    <td class="rl">Total Customers <small style="opacity:.6;font-size:9px;">(auto)</small></td>
    @foreach($months as $m)
    @php $v=$spAuto[$m['key']]['total_customers']??0; @endphp
    <td class="ac">{{ $v>0?$v:'' }}</td>
    @endforeach
</tr>
<tr class="r-achieved">
    <td class="rl">Ordered This Month <small style="opacity:.6;font-size:9px;">(auto)</small></td>
    @foreach($months as $m)
    @php $v=$spAuto[$m['key']]['total_orders']??0; @endphp
    <td class="ac">{{ $v>0?$v:'' }}</td>
    @endforeach
</tr>
<tr class="r-score">
    <td class="rl">Score</td>
    @foreach($months as $m)
    @php
        $c=(float)($spAuto[$m['key']]['total_customers']??0);
        $o=(float)($spAuto[$m['key']]['total_orders']??0);
        $s=$c>0?round(($o/$c)*125,1):0;
    @endphp
    <td class="sb {{ $sc($s) }}">{{ $s>0?$s:'' }}</td>
    @endforeach
</tr>
<tr class="kpi-gap"><td colspan="{{ count($months)+1 }}"></td></tr>

{{-- ─── TOTAL SCORE ─── --}}
<tr style="background:#1e3a5f;">
    <td style="color:#fff;font-weight:800;font-size:12.5px;padding:8px 10px;">TOTAL SCORE</td>
    @foreach($months as $m)
    @php
        $t=$getT($m['key']); $kb=$getKb($m['key']);
        $b=$t?(float)$t->bingo_target:0;
        $a=(float)($spAuto[$m['key']]['actual_sale']??0);
        $all=[];
        if($b>0) $all[]=round(($a/$b)*150,1);
        foreach($kpiMeta as $kk=>$km2) {
            $tf=$km2['tf']; $tv=$kb?(float)$kb->$tf:0;
            $autoKey=$kpiAutoKey[$kk];
            $av=(float)($spAuto[$m['key']][$autoKey]??0);
            if($tv>0) $all[]=round(($av/$tv)*$km2['mult'],1);
        }
        $c=(float)($spAuto[$m['key']]['total_customers']??0);
        $o=(float)($spAuto[$m['key']]['total_orders']??0);
        if($c>0) $all[]=round(($o/$c)*125,1);
        $total=count($all)?round(array_sum($all)/count($all),1):0;
        $tc=$total>=100?'#86efac':($total>=60?'#fcd34d':($total>0?'#fca5a5':'#6b7280'));
    @endphp
    <td style="text-align:center;font-weight:800;font-size:12.5px;color:{{ $tc }};border:1px solid #1a3254;">{{ $total>0?$total:'' }}</td>
    @endforeach
</tr>

</tbody>
</table>
</div>
</div>{{-- kb-card --}}
@endforeach

@if($salespersons->isEmpty())
<div class="alert alert-info mt-3">No data. Select a salesperson from the filter above.</div>
@endif
</div>

<script>
// Only target cells are editable — achieved is auto-populated
document.querySelectorAll('td.tc').forEach(td => {
    td.addEventListener('click', function () {
        if (td.classList.contains('editing')) return;
        td.classList.add('editing');
        const old = td.textContent.trim().replace(/,/g,'');
        td.innerHTML = `<input type="number" step="any" value="${old}">`;
        const inp = td.querySelector('input');
        inp.focus(); inp.select();
        inp.addEventListener('blur', () => save(td, inp.value, old));
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter') inp.blur();
            if (e.key === 'Escape') { td.classList.remove('editing'); td.textContent = old; }
        });
    });
});

function save(td, val, old) {
    td.classList.remove('editing');
    td.textContent = val !== '' ? parseFloat(val) : '';
    const route = td.dataset.route;
    const url = route === 'target'
        ? '{{ route("sales-visits.kingo-bingo.target") }}'
        : '{{ route("sales-visits.kingo-bingo.kpi-target") }}';
    fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ salesperson_id: td.dataset.sp, month_year: td.dataset.month, field: td.dataset.field, value: val !== '' ? val : null })
    })
    .then(r => { if (!r.ok) throw r; return r.json(); })
    .then(d => { if (d.success) location.reload(); else { td.textContent = old; alert('Save failed'); } })
    .catch(e => { td.textContent = old; console.error(e); alert('Network error — check console'); });
}
</script>
@endsection
