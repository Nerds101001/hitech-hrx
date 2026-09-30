<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>KPI Scorecard {{ $month->format('F Y') }}</title>
<style>
  /* Colours are the HRX portal's own (hitech-portal.scss): teal #005a5a/#007a7a, slate table
     headers #f1f5f9 on #475569, #e2e8f0 rules, the theme's success/danger/amber tones.

     One person, one page. It is the sheet a manager and an employee sit over
     together, and a review does not survive being turned over -- the figure
     and the conversation about it have to be in front of both at once. So
     everything that was on page two is here, compressed rather than dropped:
     the year so far is a strip instead of a table, and remarks keep two ruled
     lines instead of five. .dense tightens it further for a long scorecard. */
  @page { margin: 16px 20px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.2pt; color: #1f2937; margin: 0; }
  table { width: 100%; border-collapse: collapse; }
  .info { table-layout: fixed; }

  .dense .grid td { padding: 1.5px 6px; }
  .dense .basis { display: none; }
  .dense .info td { padding: 2.5px 7px; }
  .dense .notes td { height: 15px; }
  .dense .sign { margin-top: 12px; }

  /* A 19-row scorecard still has to fit. */
  .tight { font-size: 6.6pt; }
  .tight .grid td { padding: 1px 5px; }
  .tight .grid th { padding: 2px 5px; }
  .tight .info td { padding: 2px 6px; }
  .tight .band { padding: 6px 12px 5px; }
  .tight .band h1 { font-size: 12pt; }
  .tight .tiles td { padding: 3px 3px; }
  .tight .tiles .n { font-size: 10pt; }
  .tight .section { margin-top: 5px; }
  .tight .verdict { margin-top: 4px; padding: 4px 8px; font-size: 6.8pt; }
  .tight .notes td { height: 13px; }
  .tight .sign { margin-top: 8px; }
  .tight .foot { margin-top: 5px; }

  .band { background: #005a5a; color: #fff; padding: 8px 14px 7px; border-bottom: 3px solid #007a7a; }
  .band .kicker { font-size: 6.4pt; letter-spacing: 1px; color: #cfe6e5; text-transform: uppercase; }
  .band h1 { font-size: 13.5pt; margin: 2px 0 1px; letter-spacing: .4px; }
  .band .sub { font-size: 6.6pt; color: #cfe6e5; }

  .section { color: #005a5a; font-weight: bold; font-size: 7.4pt; letter-spacing: .8px; text-transform: uppercase;
             padding: 2px 0 2px 8px; border-left: 3px solid #007a7a; border-bottom: 2px solid #cbd5e1; margin-top: 7px; }

  .info td { border: 1px solid #e2e8f0; padding: 3.5px 7px; vertical-align: top; }
  .info .k { background: #f8fafc; color: #475569; width: 10%; font-size: 6.4pt; text-transform: uppercase; letter-spacing: .3px; }
  .info .v { width: 23.3%; font-weight: bold; color: #0f172a; word-wrap: break-word; }
  .info .grade { background: #f0f7f6; color: #005a5a; }

  .tiles { margin-top: 6px; }
  .tiles td { width: 16.66%; text-align: center; border: 1px solid #dee2e6; border-bottom: 3px solid #007a7a; padding: 5px 3px; background: #fff; }
  .tiles .n { font-size: 11.5pt; font-weight: bold; color: #005a5a; }
  .tiles .l { font-size: 6pt; color: #64748b; text-transform: uppercase; letter-spacing: .4px; margin-top: 1px; }

  .grid th { background: #f1f5f9; color: #475569; font-size: 6.4pt; text-transform: uppercase; letter-spacing: .3px; text-align: left;
             padding: 3px 6px; border: 1px solid #e2e8f0; border-bottom: 2px solid #cbd5e1; }
  .grid td { border: 1px solid #e2e8f0; padding: 3px 6px; vertical-align: top; }
  .grid tr.alt td { background: #fbfcfd; }
  .grid tr.kra td { background: #f0f7f6; font-weight: bold; color: #00706e; padding: 2px 6px; }
  .grid .total td { background: #005a5a; color: #fff; font-weight: bold; border-color: #005a5a; padding: 4px 6px; }
  .num { text-align: right; white-space: nowrap; }
  .ctr { text-align: center; }
  .basis { color: #64748b; font-size: 6pt; margin-top: 1px; }
  .muted { color: #94a3b8; }

  .good { background: #ebf3f3; color: #005a5a; }
  .warn { background: #fbf1e6; color: #d97706; }
  .bad  { background: #ffeded; color: #ff4d49; }
  .idle { background: #f1f5f9; color: #475569; }
  .chip { display: inline-block; padding: 1px 5px; font-size: 6pt; font-weight: bold; }

  .verdict { margin-top: 6px; border: 1px solid #cfe3e2; border-left: 3px solid #007a7a; background: #f0f7f6; padding: 5px 9px; font-size: 7.4pt; }

  /* The year so far, as a strip of months rather than a page of its own. */
  .ytd td, .ytd th { border: 1px solid #e2e8f0; padding: 3px 4px; text-align: center; font-size: 6.6pt; }
  .ytd th { background: #f1f5f9; color: #475569; text-transform: uppercase; letter-spacing: .3px; font-size: 6pt; }
  .ytd .v { font-weight: bold; color: #0f172a; }
  .ytd .now { background: #f0f7f6; color: #005a5a; }
  .ytd .sum { background: #005a5a; color: #fff; font-weight: bold; border-color: #005a5a; }

  .notes td { padding: 0; height: 18px; vertical-align: bottom; }
  .notes .who { width: 17%; color: #475569; font-size: 6.4pt; text-transform: uppercase; letter-spacing: .3px; }
  .notes .ruled { border-bottom: 1px solid #cbd5e1; }
  .sign { margin-top: 16px; }
  .sign td { width: 33%; padding: 0 16px 0 0; vertical-align: bottom; }
  .sign .line { border-top: 1px solid #94a3b8; padding-top: 3px; color: #475569; font-size: 6.4pt; }
  .foot { margin-top: 7px; border-top: 1px solid #e2e8f0; padding-top: 3px; color: #64748b; font-size: 6pt; }
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
    $reported = $rows->filter(fn ($r) => !in_array($r->status, ['Not reported', 'Not measurable'], true))->count();
    $onTime = $rows->where('status', 'On time')->count();
    $letter = $card->grade ? strtoupper(substr($card->grade, 0, 1)) : '—';
    $group = null;
    $joined = $u->date_of_joining ? \Carbon\Carbon::parse($u->date_of_joining) : null;
    $asOf = $month->copy()->endOfMonth()->min(now());
    $span = ($joined && $joined->lte($asOf)) ? $joined->diff($asOf) : null;
    $tenure = $span ? trim(($span->y ? $span->y . ' yr ' : '') . $span->m . ' mo') : '—';
    $attendance = $card->attendance ?? null;
    $ytd = $card->ytd ?? [];
    $attention = $card->attention ?? [];
    // Everything competes for one page, so a long scorecard tightens, and a
    // very long one tightens twice. Thresholds come from the real spread:
    // cards run to 11, 12, 15 and 19 rows.
    $fit = $rows->count() > 16 ? 'dense tight' : ($rows->count() > 10 ? 'dense' : '');
  @endphp
  <div class="{{ $fit }}" @if(!$loop->last) style="page-break-after: always" @endif>
    <div class="band">
      <div class="kicker">{{ $company !== '' ? $company . '  |  ' : '' }}Monthly Performance Review  |  {{ $month->format('F Y') }}</div>
      <h1>MONTHLY KPI SCORECARD</h1>
      <div class="sub">Figures read from source at the moment this sheet was printed</div>
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
        {{-- Attendance stands where "not reported" used to: a count of blanks
             said nothing about the month, and every figure on this sheet was
             earned across the days the person was actually here. --}}
        <td><div class="n">{{ $attendance === null ? '—' : $fmt(round($attendance, 1)) . '%' }}</div><div class="l">Attendance</div></td>
        {{-- Shown, not scored: e.g. Total Leads Allocated for Sales and New Biz. --}}
        @foreach(($card->info ?? []) as $infoLabel => $infoValue)
          <td><div class="n">{{ $fmt($infoValue) }}</div><div class="l">{{ $infoLabel }}</div></td>
        @endforeach
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
            if (in_array($r->status, ['Not reported', 'Awaiting approval', 'In progress', 'Not measurable'], true)) { $earnTone = 'idle'; }
            $chip = ['On time' => 'good', 'Late' => 'warn', 'Missed' => 'bad', 'Awaiting approval' => 'warn'][$r->status] ?? 'idle';
          @endphp
          <tr class="{{ $i % 2 === 0 ? 'alt' : '' }}">
            <td class="muted">{{ $i }}</td>
            <td>
              {{ $r->title }}@if($r->checks > 1) <span class="muted">({{ $r->checks }} checks)</span>@endif
              @if($r->basis)<div class="basis">{{ \Illuminate\Support\Str::limit($r->basis, 70) }}</div>@endif
            </td>
            {{-- A banded KPI is scored by which band the figure lands in; a target and a "% of target" mean nothing for it. --}}
            <td class="num">{{ !empty($r->banded) ? '—' : $fmt($r->target) }}</td>
            <td class="num">{{ $fmt($r->achieved) }}</td>
            <td class="num">{{ (!empty($r->banded) || $r->result === null) ? '—' : $fmt(round($r->result, 1)) . '%' }}</td>
            {{-- Not measurable: no marks either way, and not part of the total. --}}
            <td class="num">{{ !empty($r->na) ? '—' : $fmt($r->max) }}</td>
            <td class="num {{ $earnTone }}"><b>{{ !empty($r->na) ? '—' : $fmt($r->score) }}</b></td>
            <td class="ctr"><span class="chip {{ $chip }}">{{ $r->status }}</span></td>
          </tr>
        @endforeach
        <tr class="total">
          <td colspan="5">TOTAL</td>
          <td class="num">{{ ($card->onPoints && (float) $card->possible >= 100) ? '—' : $fmt($card->possible) }}</td>
          <td class="num">{{ $fmt($card->earned) }}</td>
          <td class="ctr">{{ $card->pct === null ? '' : $fmt($card->pct) . '%' }}</td>
        </tr>
      </tbody>
    </table>

    @if($card->grade)
      <div class="verdict">
        @if($card->onPoints && (float) $card->possible < 100)
          <b>{{ $month->format('F Y') }} result:</b> {{ $fmt($card->earned) }} points out of {{ $fmt($card->possible) }} measurable ({{ $fmt($card->pct) }}%)
          &nbsp;—&nbsp; <b>{{ $card->grade }}</b> <span class="muted">({{ \App\Services\KpiScorecardService::gradeBand($card->grade) }})</span>
        @elseif($card->onPoints)
          <b>{{ $month->format('F Y') }} result:</b> {{ $fmt($card->earned) }} points out of 100
          &nbsp;—&nbsp; <b>{{ $card->grade }}</b> <span class="muted">({{ str_replace('%', ' points', \App\Services\KpiScorecardService::gradeBand($card->grade)) }})</span>
        @else
          <b>{{ $month->format('F Y') }} result:</b> {{ $fmt($card->earned) }} of {{ $fmt($card->possible) }} marks ({{ $fmt($card->pct) }}%)
          &nbsp;—&nbsp; <b>{{ $card->grade }}</b> <span class="muted">({{ \App\Services\KpiScorecardService::gradeBand($card->grade) }})</span>
        @endif
        @if(count($attention))
          <div class="basis" style="margin-top:3px">
            <b>Needs attention:</b>
            @foreach(collect($attention)->take(4) as $n => $a){{ $n ? ' · ' : ' ' }}{{ $a->title }} ({{ $fmt($a->max) }} marks)@endforeach
          </div>
        @endif
      </div>
    @endif

    @if(count($ytd))
      <div class="section">Year to date &nbsp;·&nbsp; April – {{ $month->format('F Y') }}</div>
      @php
        $ytdEarned = collect($ytd)->sum('earned');
        $ytdPossible = collect($ytd)->sum('possible');
      @endphp
      <table class="ytd">
        <tr>
          <th style="width:11%">Month</th>
          @foreach($ytd as $y)<th class="{{ $y->current ? 'now' : '' }}">{{ \Illuminate\Support\Str::substr($y->label, 0, 3) }}</th>@endforeach
          <th class="sum">Total</th>
        </tr>
        <tr>
          <td class="muted">Earned / possible</td>
          @foreach($ytd as $y)
            <td class="v {{ $y->current ? 'now' : '' }}">{{ $fmt($y->earned) }}<span class="muted"> / {{ $fmt($y->possible) }}</span></td>
          @endforeach
          <td class="sum">{{ $fmt($ytdEarned) }} / {{ $fmt($ytdPossible) }}</td>
        </tr>
        <tr>
          <td class="muted">Achievement</td>
          @foreach($ytd as $y)
            <td class="{{ $y->current ? 'now' : '' }}">{{ $y->pct === null ? '—' : $fmt($y->pct) . '%' }}{{ $y->grade ? ' · ' . strtoupper(substr($y->grade, 0, 1)) : '' }}</td>
          @endforeach
          <td class="sum">{{ $ytdPossible > 0 ? $fmt(round($ytdEarned / $ytdPossible * 100, 1)) . '%' : '—' }}</td>
        </tr>
      </table>
    @endif

    <div class="section">Remarks</div>
    <table class="notes">
      <tr><td class="who">Reporting manager</td><td class="ruled">&nbsp;</td></tr>
      <tr><td class="who">Employee</td><td class="ruled">&nbsp;</td></tr>
    </table>

    <table class="sign">
      <tr>
        <td><div class="line">Employee</div></td>
        <td><div class="line">Reporting manager{{ $card->manager ? ' — ' . $card->manager : '' }}</div></td>
        <td><div class="line">HR</div></td>
      </tr>
    </table>

    <div class="foot">
      {{ trim($u->first_name . ' ' . $u->last_name) }} &nbsp;·&nbsp; {{ $u->code ?: '—' }} &nbsp;·&nbsp; {{ $month->format('F Y') }}
      &nbsp;—&nbsp; Confidential, internal use only &nbsp;·&nbsp; Generated {{ now()->format('d M Y, H:i') }}
    </div>
  </div>
@endforeach
</body>
</html>
