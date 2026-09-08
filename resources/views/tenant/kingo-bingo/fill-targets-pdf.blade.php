{{--
    Print copy of the Fill Targets grid.

    Standalone rather than @extends of the app layout: dompdf has no JavaScript
    and only a small slice of CSS, so the screen page's sticky columns, flexbox
    topbar and inputs would all render wrong. The colours, the six-row block and
    the column order are copied exactly, so this reads as the same table.

    A3 landscape is set by the controller -- nineteen columns will not fit on A4.
--}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Kingo &amp; Bingo Targets — FY {{ $selectedFy }}</title>
<style>
    @page { margin: 12mm 8mm 14mm; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 7pt;
        color: #374151;
        margin: 0;
    }

    .hd { margin-bottom: 8px; }
    .hd h1 {
        font-size: 13pt; font-weight: bold; color: #1e3a5f;
        margin: 0 0 3px;
    }
    .hd .sub { font-size: 7.5pt; color: #64748b; }
    .hd .sub strong { color: #1e3a5f; }

    table.ft { width: 100%; border-collapse: collapse; }
    table.ft th, table.ft td {
        border: 0.5pt solid #e5e7eb;
        padding: 2.5px 2px;
        text-align: center;
        vertical-align: middle;
    }

    /* group header */
    tr.grp th { font-size: 6.5pt; font-weight: bold; border-color: rgba(0,0,0,.15); }
    tr.grp th.g-sp  { background: #0f2848; color: #fff; text-align: left; padding-left: 6px; }
    tr.grp th.g-lbl { background: #0f2848; color: #fff; }
    tr.grp th.g-ann { background: #92400e; color: #fff; }
    tr.grp th.g-qtr { background: #1d4ed8; color: #fff; }
    tr.grp th.g-mon { background: #1e3a5f; color: #fff; }

    /* column header */
    tr.col th { background: #1e3a5f; color: #fff; font-weight: bold; font-size: 6.5pt; border-color: #1a3254; }
    tr.col th.c-sp  { background: #152d50; text-align: left; padding-left: 6px; }
    tr.col th.c-lbl { background: #152d50; }
    tr.col th.c-ann { background: #b45309; }
    tr.col th.c-qtr { background: #2563eb; }
    tr.col th.c-cur { background: #1d4ed8; }

    /* body */
    td.td-sp {
        background: #f1f5f9; color: #1e3a5f;
        font-weight: bold; font-size: 7pt;
        text-align: left; padding-left: 5px;
        border-right: 1.2pt solid #cbd5e1;
        width: 105px;
    }
    td.td-lbl {
        font-size: 6pt; font-weight: bold;
        border-right: 1.2pt solid #d1d5db;
        width: 38px;
    }
    td.td-lbl.sk { background: #fef2f2; color: #991b1b; }
    td.td-lbl.k  { background: #eef2ff; color: #3730a3; }
    td.td-lbl.b  { background: #f0fdf4; color: #166534; }

    td.v-ann { background: #fef3c7; border-left: 1.2pt solid #fde68a; border-right: 1.2pt solid #fde68a; }
    td.v-qtr { background: #eff6ff; }
    td.v-cur { background: #dbeafe; }

    td.filled { background: #f0fdf4; color: #166534; font-weight: bold; }
    td.v-ann.filled { background: #fef08a; color: #78350f; font-weight: bold; }
    td.v-qtr.filled { background: #dbeafe; color: #1d4ed8; font-weight: bold; }

    tr.row-sep td { height: 4px; background: #f1f5f9; border: none; padding: 0; }

    /* Keep a person's six rows together on one page wherever they fit. */
    tbody.sp-block { page-break-inside: avoid; }

    .legend { margin-top: 9px; font-size: 6.5pt; color: #64748b; }
    .legend span { padding: 1px 5px; border-radius: 3px; font-weight: bold; margin-right: 5px; }
</style>
</head>
<body>

@php
    $qLabels = ['Q1 Apr-Jun', 'Q2 Jul-Sep', 'Q3 Oct-Dec', 'Q4 Jan-Mar'];

    // Same six rows, same order and colours as the screen.
    $liveRows = [
        'FCST'      => ['forecast',         '#1d4ed8', '#eff6ff'],
        'Sc. Fcst'  => ['forecast_service', '#7c3aed', '#f5f3ff'],
        'Act. Sale' => ['actual',           '#065f46', '#ecfdf5'],
    ];
@endphp

<div class="hd">
    <h1>Kingo &amp; Bingo Targets — FY {{ $selectedFy }}</h1>
    <div class="sub">
        All figures in <strong>₹ Lakh</strong>
        @if(!empty($scopeLabel)) &nbsp;·&nbsp; Filtered: <strong>{{ $scopeLabel }}</strong> @endif
        &nbsp;·&nbsp; {{ $salespersons->count() }} representative{{ $salespersons->count() === 1 ? '' : 's' }}
        &nbsp;·&nbsp; Generated {{ now()->format('d M Y, h:i A') }}
    </div>
</div>

@if($salespersons->isEmpty())
    <p style="font-size:8pt;color:#991b1b;">No salespersons match this filter.</p>
@else

<table class="ft">
<thead>
    <tr class="grp">
        <th class="g-sp" rowspan="2">Salesperson</th>
        <th class="g-lbl" rowspan="2"></th>
        <th class="g-ann" colspan="1">Annual</th>
        <th class="g-qtr" colspan="{{ count($quarterKeys) }}">Quarterly</th>
        <th class="g-mon" colspan="{{ count($months) }}">Monthly &nbsp;Apr → Mar</th>
    </tr>
    <tr class="col">
        <th class="c-ann">{{ $selectedFy }}</th>
        @foreach($quarterKeys as $i => $qk)
            <th class="c-qtr">{{ $qLabels[$i] ?? 'Q'.($i+1) }}</th>
        @endforeach
        @foreach($months as $m)
            <th class="{{ $m['key'] === $currentMonthKey ? 'c-cur' : '' }}">{{ $m['label'] }}</th>
        @endforeach
    </tr>
</thead>

@foreach($salespersons as $sp)
@php
    $spT  = $targets[$sp->id] ?? collect();
    $live = $liveByCsp[$sp->id] ?? [];
    $getV = fn($key, $field) => optional($spT->firstWhere('month_year', $key))->{$field};

    $targetRows = [
        'S.<br>KINGO' => ['super_kingo_target', 'sk'],
        'KINGO'       => ['kingo_target',       'k'],
        'BINGO'       => ['bingo_target',       'b'],
    ];
@endphp
<tbody class="sp-block">

    @foreach($targetRows as $label => [$field, $cls])
    <tr>
        @if($loop->first)
            <td class="td-sp" rowspan="6">{{ $sp->name }}</td>
        @endif
        <td class="td-lbl {{ $cls }}">{!! $label !!}</td>

        @php $v = $getV($annualKey, $field); @endphp
        <td class="v-ann {{ $v > 0 ? 'filled' : '' }}">{{ $v > 0 ? number_format($v, 2) : '—' }}</td>

        @foreach($quarterKeys as $qk)
            @php $v = $getV($qk, $field); @endphp
            <td class="v-qtr {{ $v > 0 ? 'filled' : '' }}">{{ $v > 0 ? number_format($v, 2) : '—' }}</td>
        @endforeach

        @foreach($months as $m)
            @php $v = $getV($m['key'], $field); @endphp
            <td class="{{ $m['key'] === $currentMonthKey ? 'v-cur' : '' }} {{ $v > 0 ? 'filled' : '' }}">
                {{ $v > 0 ? number_format($v, 2) : '—' }}
            </td>
        @endforeach
    </tr>
    @endforeach

    {{-- Live figures from the Sales Report: monthly facts, so the annual and
         quarter cells stay blank exactly as they do on screen. --}}
    @foreach($liveRows as $label => [$key, $fg, $bg])
    <tr>
        <td class="td-lbl" style="color:{{ $fg }};background:{{ $bg }};">{{ $label }}</td>
        <td class="v-ann"></td>
        @foreach($quarterKeys as $qk)<td class="v-qtr"></td>@endforeach
        @foreach($months as $m)
            @php $val = $live[$m['key']][$key] ?? 0; @endphp
            <td style="background:{{ $bg }};color:{{ $fg }};font-weight:bold;">
                {{ $val > 0 ? number_format($val, 2) : '—' }}
            </td>
        @endforeach
    </tr>
    @endforeach

    <tr class="row-sep"><td colspan="{{ 3 + count($quarterKeys) + count($months) }}"></td></tr>
</tbody>
@endforeach
</table>

<div class="legend">
    <span style="background:#fef3c7;color:#78350f;">Yellow = Annual</span>
    <span style="background:#eff6ff;color:#1d4ed8;">Blue = Quarterly</span>
    <span style="background:#f0fdf4;color:#166534;">Green = Filled</span>
    <span style="background:#dbeafe;color:#1d4ed8;">Highlight = Current Month</span>
</div>

@endif

</body>
</html>
