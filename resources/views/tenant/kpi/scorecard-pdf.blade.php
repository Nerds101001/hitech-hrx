<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>KPI Scorecard {{ $month->format('F Y') }}</title>
<style>
  /* Colours are the HRX portal's own (hitech-portal.scss): teal #005a5a/#007a7a, slate table
     headers #f1f5f9 on #475569, #e2e8f0 rules, the theme's success/danger/amber tones. */
  @page { margin: 20px 24px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.6pt; color: #1f2937; margin: 0; }
  .dense .grid td { padding: 2px 6px; }
  .dense .basis { display: none; }
  .dense .sign { margin-top: 16px; }
  table { width: 100%; border-collapse: collapse; }
  .info { table-layout: fixed; }

  .band { background: #005a5a; color: #fff; padding: 10px 14px 9px; border-bottom: 3px solid #007a7a; }
  .band .kicker { font-size: 6.6pt; letter-spacing: 1px; color: #cfe6e5; text-transform: uppercase; }
  .band h1 { font-size: 15pt; margin: 3px 0 1px; letter-spacing: .4px; }
  .band .sub { font-size: 7pt; color: #cfe6e5; }

  .section { color: #005a5a; font-weight: bold; font-size: 7.8pt; letter-spacing: .8px; text-transform: uppercase;
             padding: 3px 0 3px 8px; border-left: 3px solid #007a7a; border-bottom: 2px solid #cbd5e1; margin-top: 10px; }

  .info td { border: 1px solid #e2e8f0; padding: 4px 7px; vertical-align: top; }
  .info .k { background: #f8fafc; color: #475569; width: 10%; font-size: 6.8pt; text-transform: uppercase; letter-spacing: .3px; }
  .info .v { width: 23.3%; font-weight: bold; color: #0f172a; word-wrap: break-word; }
  .info .grade { background: #f0f7f6; color: #005a5a; }

  .tiles { margin-top: 8px; }
  .tiles td { width: 16.66%; text-align: center; border: 1px solid #dee2e6; border-bottom: 3px solid #007a7a; padding: 6px 3px; background: #fff; }
  .tiles .n { font-size: 12.5pt; font-weight: bold; color: #005a5a; }
  .tiles .l { font-size: 6.3pt; color: #64748b; text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }

  .grid th { background: #f1f5f9; color: #475569; font-size: 6.8pt; text-transform: uppercase; letter-spacing: .3px; text-align: left;
             padding: 4px 6px; border: 1px solid #e2e8f0; border-bottom: 2px solid #cbd5e1; }
  .grid td { border: 1px solid #e2e8f0; padding: 4px 6px; vertical-align: top; }
  .grid tr.alt td { background: #fbfcfd; }
  .grid tr.kra td { background: #f0f7f6; font-weight: bold; color: #00706e; }
  .grid .total td { background: #005a5a; color: #fff; font-weight: bold; border-color: #005a5a; padding: 5px 6px; }
  .num { text-align: right; white-space: nowrap; }
  .ctr { text-align: center; }
  .basis { color: #64748b; font-size: 6.4pt; margin-top: 1px; }
  .muted { color: #94a3b8; }

  .good { background: #ebf3f3; color: #005a5a; }
  .warn { background: #fbf1e6; color: #d97706; }
  .bad  { background: #ffeded; color: #ff4d49; }
  .idle { background: #f1f5f9; color: #475569; }
  .chip { display: inline-block; padding: 1px 5px; font-size: 6.4pt; font-weight: bold; }

  .verdict { margin-top: 8px; border: 1px solid #cfe3e2; border-left: 3px solid #007a7a; background: #f0f7f6; padding: 6px 10px; font-size: 8pt; }
  .band.slim { padding: 6px 14px 5px; }
  .notes td { padding: 0; height: 21px; vertical-align: bottom; }
  .notes .who { width: 17%; color: #475569; font-size: 6.8pt; text-transform: uppercase; letter-spacing: .3px; }
  .notes .ruled { border-bottom: 1px solid #cbd5e1; }
  .sign { margin-top: 30px; }
  .sign td { width: 33%; padding: 0 16px 0 0; vertical-align: bottom; }
  .sign .line { border-top: 1px solid #94a3b8; padding-top: 3px; color: #475569; font-size: 6.8pt; }
  .foot { margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 4px; color: #64748b; font-size: 6.4pt; }
</style>
</head>
<body>
@php
  $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2), '0'), '.');
  $company = trim((string) ($companyName ?? ''));
@endphp
@foreach($cards as $card)
  @php
    $u = $card->user;
    $rows = collect($card->rows);
    $reported = $rows->filter(fn ($r) => $r->status !== 'Not reported')->count();
    $awaiting = $rows->count() - $reported;
    $onTime = $rows->where('status', 'On time')->count();
    $letter = $card->grade ? strtoupper(substr($card->grade, 0, 1)) : '—';
    $group = null;
    $joined = $u->date_of_joining ? \Carbon\Carbon::parse($u->date_of_joining) : null;
    $asOf = $month->copy()->endOfMonth()->min(now());
    $span = ($joined && $joined->lte($asOf)) ? $joined->diff($asOf) : null;
    $tenure = $span ? trim(($span->y ? $span->y . ' yr ' : '') . $span->m . ' mo') : '—';
  @endphp
  <div class="{{ $rows->count() > 14 ? 'dense' : '' }}" style="page-break-after: always">
    <div class="band">
      <div class="kicker">{{ $company !== '' ? $company . '  |  ' : '' }}Monthly Performance Review  |  {{ $month->format('F Y') }}</div>
      <h1>MONTHLY KPI SCORECARD</h1>
      <div class="sub">Built from the KPI checks and approved entries recorded for the month</div>
    </div>

    <div class="section">Employee Details</div>
    <table class="info">
      <tr>
        <td class="k">Full name</td><td class="v">{{ trim($u->first_name . ' ' . $u->last_name) }}</td>
        <td class="k">Emp. code</td><td class="v">{{ $u->code ?: '—' }}</td>
        <td class="k">Department</td><td class="v">{{ optional($u->department)->name ?: '—' }}</td>
      </tr>
      <tr>
        <td class="k">Designation</td><td class="v">{{ optional($u->designation)->name ?: '—' }}</td>
        <td class="k">Email</td><td class="v">{{ $u->email ?: '—' }}</td>
        <td class="k">Contact</td><td class="v">{{ $u->official_phone ?: '—' }}</td>
      </tr>
      <tr>
        <td class="k">Manager</td><td class="v">{{ $card->manager ?: '—' }}</td>
        <td class="k">Joined</td><td class="v">{{ $joined ? $joined->format('d M Y') : '—' }}</td>
        <td class="k">Tenure</td><td class="v">{{ $tenure }}</td>
      </tr>
      <tr>
        <td class="k">Location</td><td class="v">{{ optional($u->site)->name ?: '—' }}</td>
        <td class="k">Month</td><td class="v">{{ $month->format('F Y') }}</td>
        <td class="k">Grade</td><td class="v grade">{{ $card->grade ?: '—' }}</td>
      </tr>
    </table>

    <table class="tiles">
      <tr>
        <td><div class="n">{{ $fmt($card->earned) }}<span class="muted" style="font-size:8pt"> / {{ $fmt($card->possible) }}</span></div><div class="l">Total score</div></td>
        <td><div class="n">{{ $card->pct === null ? '—' : $fmt($card->pct) . '%' }}</div><div class="l">Achievement</div></td>
        <td><div class="n">{{ $letter }}</div><div class="l">Grade</div></td>
        <td><div class="n">{{ $reported }}<span class="muted" style="font-size:8pt"> of {{ $rows->count() }}</span></div><div class="l">KPIs reported</div></td>
        <td><div class="n">{{ $onTime }}</div><div class="l">On time</div></td>
        <td><div class="n">{{ $awaiting }}</div><div class="l">Not reported</div></td>
      </tr>
    </table>

    <div class="section">Performance Scorecard</div>
    <table class="grid">
      <thead>
        <tr>
          <th style="width:4%">#</th>
          <th style="width:33%">KPI</th>
          <th class="num" style="width:10%">Target</th>
          <th class="num" style="width:11%">Achieved</th>
          <th class="num" style="width:9%">Result</th>
          <th class="num" style="width:7%">Max</th>
          <th class="num" style="width:8%">Earned</th>
          <th class="ctr" style="width:18%">Status</th>
        </tr>
      </thead>
      <tbody>
        @php $i = 0; @endphp
        @foreach($card->rows as $r)
          @if($r->kra !== $group)
            @php $group = $r->kra; @endphp
            <tr class="kra"><td colspan="8">{{ $group }}</td></tr>
          @endif
          @php
            $i++;
            $earnTone = $r->max > 0 ? ($r->score >= $r->max * 0.8 ? 'good' : ($r->score >= $r->max * 0.5 ? 'warn' : 'bad')) : 'idle';
            if (in_array($r->status, ['Not reported', 'Awaiting approval', 'In progress'], true)) { $earnTone = 'idle'; }
            $chip = ['On time' => 'good', 'Late' => 'warn', 'Missed' => 'bad', 'Awaiting approval' => 'warn'][$r->status] ?? 'idle';
          @endphp
          <tr class="{{ $i % 2 === 0 ? 'alt' : '' }}">
            <td class="muted">{{ $i }}</td>
            <td>
              {{ $r->title }}@if($r->checks > 1) <span class="muted">({{ $r->checks }} checks)</span>@endif
              @if($r->basis)<div class="basis">{{ \Illuminate\Support\Str::limit($r->basis, 70) }}</div>@endif
            </td>
            <td class="num">{{ $fmt($r->target) }}</td>
            <td class="num">{{ $fmt($r->achieved) }}</td>
            <td class="num">{{ $r->result === null ? '—' : $fmt(round($r->result, 1)) . '%' }}</td>
            <td class="num">{{ $fmt($r->max) }}</td>
            <td class="num {{ $earnTone }}"><b>{{ $fmt($r->score) }}</b></td>
            <td class="ctr"><span class="chip {{ $chip }}">{{ $r->status }}</span></td>
          </tr>
        @endforeach
        <tr class="total">
          <td colspan="5">TOTAL</td>
          <td class="num">{{ $fmt($card->possible) }}</td>
          <td class="num">{{ $fmt($card->earned) }}</td>
          <td class="ctr">{{ $card->pct === null ? '' : $fmt($card->pct) . '%' }}</td>
        </tr>
      </tbody>
    </table>

    @if($card->grade)
      <div class="verdict">
        <b>{{ $month->format('F Y') }} result:</b> {{ $fmt($card->earned) }} of {{ $fmt($card->possible) }} marks ({{ $fmt($card->pct) }}%)
        &nbsp;—&nbsp; <b>{{ $card->grade }}</b> <span class="muted">({{ \App\Services\KpiScorecardService::gradeBand($card->grade) }})</span>
      </div>
    @endif

    <div class="foot">{{ trim($u->first_name . ' ' . $u->last_name) }} &nbsp;·&nbsp; {{ $u->code ?: '—' }} &nbsp;·&nbsp; {{ $month->format('F Y') }} &nbsp;—&nbsp; page 1 of 2</div>
  </div>

  {{-- Page two: the year so far, what needs attention, room for remarks, sign-off. --}}
  <div @if(!$loop->last) style="page-break-after: always" @endif>
    <div class="band slim">
      <div class="kicker">{{ $company !== '' ? $company . '  |  ' : '' }}Monthly KPI Scorecard, continued  |  {{ $month->format('F Y') }}</div>
      <h1 style="font-size:11.5pt">{{ trim($u->first_name . ' ' . $u->last_name) }} <span style="font-weight:normal;font-size:8pt"> &nbsp;{{ $u->code ?: '' }} &nbsp;·&nbsp; {{ optional($u->department)->name }}</span></h1>
    </div>

    <div class="section">Year to date &nbsp;·&nbsp; April – {{ $month->format('F Y') }}</div>
    <table class="grid">
      <thead>
        <tr>
          <th style="width:30%">Month</th>
          <th class="num" style="width:20%">Earned</th>
          <th class="num" style="width:20%">Possible</th>
          <th class="num" style="width:12%">%</th>
          <th style="width:18%">Grade</th>
        </tr>
      </thead>
      <tbody>
        @php $ytdEarned = 0; $ytdPossible = 0; @endphp
        @forelse($card->ytd as $y)
          @php $ytdEarned += $y->earned; $ytdPossible += $y->possible; @endphp
          <tr class="{{ $y->current ? 'kra' : '' }}">
            <td>{{ $y->label }}@if($y->current) <span class="muted">(this report)</span>@endif</td>
            <td class="num">{{ $fmt($y->earned) }}</td>
            <td class="num">{{ $fmt($y->possible) }}</td>
            <td class="num">{{ $y->pct === null ? '—' : $fmt($y->pct) . '%' }}</td>
            <td>{{ $y->grade ? strtoupper(substr($y->grade, 0, 1)) : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted">No earlier months recorded.</td></tr>
        @endforelse
        @if(count($card->ytd) > 1)
          <tr class="total">
            <td>TOTAL, {{ count($card->ytd) }} months</td>
            <td class="num">{{ $fmt($ytdEarned) }}</td>
            <td class="num">{{ $fmt($ytdPossible) }}</td>
            <td class="num">{{ $ytdPossible > 0 ? $fmt(round($ytdEarned / $ytdPossible * 100, 1)) . '%' : '—' }}</td>
            <td>&nbsp;</td>
          </tr>
        @endif
      </tbody>
    </table>

    <div class="section">Needs attention this month</div>
    @if(count($card->attention))
      <table class="grid">
        <thead><tr><th style="width:38%">KPI</th><th>What happened</th><th class="num" style="width:9%">Marks</th></tr></thead>
        <tbody>
          @foreach($card->attention as $n => $a)
            <tr class="{{ $n % 2 ? 'alt' : '' }}">
              <td><b>{{ $a->title }}</b></td>
              <td>{{ $a->why }}</td>
              <td class="num">{{ $fmt($a->max) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="verdict">Every KPI was reported and scored at or above half its marks.</div>
    @endif

    <div class="section">Remarks</div>
    <table class="notes">
      <tr><td class="who">Reporting manager</td><td class="ruled">&nbsp;</td></tr>
      <tr><td class="who">&nbsp;</td><td class="ruled">&nbsp;</td></tr>
      <tr><td class="who">HR</td><td class="ruled">&nbsp;</td></tr>
      <tr><td class="who">&nbsp;</td><td class="ruled">&nbsp;</td></tr>
      <tr><td class="who">Employee</td><td class="ruled">&nbsp;</td></tr>
    </table>

    <table class="sign">
      <tr>
        <td><div class="line">Employee</div></td>
        <td><div class="line">Reporting manager{{ $card->manager ? ' — ' . $card->manager : '' }}</div></td>
        <td><div class="line">HR</div></td>
      </tr>
    </table>

    <div class="foot">Confidential — internal use only &nbsp;·&nbsp; Generated {{ now()->format('d M Y, H:i') }} &nbsp;—&nbsp; page 2 of 2</div>
  </div>
@endforeach
</body>
</html>
