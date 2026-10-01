{{-- One person's training report card: the same sheet on the page and in
     the PDF (tables only, so dompdf lays it out the same way). $t comes from
     App\Support\TrainingTranscript; $company is the company name or null. --}}
@php
  $u = $t->user;
  $d = fn ($c, $f = 'd M Y') => $c ? $c->format($f) : '—';
  $tone = fn ($s) => ['Passed' => 'good', 'Completed' => 'good', 'Watched' => 'good', 'Failed' => 'bad', 'In progress' => 'warn', 'Started' => 'warn'][$s] ?? 'idle';
  $resultTone = in_array($t->result, ['PASSED', 'APPROVED BY HR'], true) ? 'good' : ($t->result === 'IN PROGRESS' ? 'warn' : 'idle');
  $company = trim((string) ($company ?? ''));
  $i = 0;
@endphp
<div class="rc-sheet">
  <div class="band">
    <div class="kicker">{{ $company !== '' ? $company . '  |  ' : '' }}Employee Training Programme  |  As of {{ now()->format('d F Y') }}</div>
    <h1>TRAINING REPORT CARD</h1>
    <div class="sub">Read from the training records at the moment this sheet was printed</div>
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
      <td class="k">Manager</td><td class="v">{{ $t->manager ?: '—' }}</td>
      <td class="k">Joined</td><td class="v">{{ $d($t->joined) }}</td>
      <td class="k">Tenure</td><td class="v">{{ $t->tenure ?: '—' }}</td>
    </tr>
    <tr>
      <td class="k">Location</td><td class="v">{{ optional($u->site)->name ?: '—' }}</td>
      <td class="k">Training status</td><td class="v">{{ $t->status }}</td>
      <td class="k">Result</td><td class="v grade {{ $resultTone }}">{{ $t->result }}</td>
    </tr>
  </table>

  <table class="tiles">
    <tr>
      <td><div class="n">{{ $t->compDone }}<span class="muted small"> / {{ $t->compTotal }}</span></div><div class="l">Compulsory passed</div></td>
      <td><div class="n">{{ $t->compPct }}%</div><div class="l">Compulsory progress</div></td>
      <td><div class="n">{{ $t->avgScore === null ? '—' : rtrim(rtrim(number_format($t->avgScore, 1), '0'), '.') . '%' }}</div><div class="l">Average score</div></td>
      <td><div class="n">{{ $t->allDone }}<span class="muted small"> of {{ $t->allTotal }}</span></div><div class="l">Modules passed</div></td>
      <td><div class="n">{{ $t->attempts }}</div><div class="l">Quiz attempts</div></td>
      <td><div class="n">{{ $t->watched === null ? '—' : $t->watched }}<span class="muted small">{{ $t->watched === null ? '' : ' / ' . $t->required }}</span></div><div class="l">Sessions watched</div></td>
    </tr>
  </table>

  <div class="section">Training Scorecard</div>
  <table class="grid">
    <thead>
      <tr>
        <th style="width:4%">#</th>
        <th style="width:36%">Module</th>
        <th style="width:8%">Type</th>
        <th class="num" style="width:7%">Time</th>
        <th class="num" style="width:8%">Attempts</th>
        <th class="num" style="width:8%">Score</th>
        <th class="ctr" style="width:13%">Completed</th>
        <th class="ctr" style="width:16%">Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($t->groups as $g)
        <tr class="kra">
          <td colspan="8">
            {{ $g->title }}
            <span class="kra-meta">&nbsp;·&nbsp; {{ $g->compulsory ? 'Compulsory' : 'Optional' }} &nbsp;·&nbsp; {{ $g->done }} of {{ $g->total }} {{ $g->library ? 'sessions watched' : 'passed' }}</span>
          </td>
        </tr>
        @forelse($g->rows as $r)
          @php $i++; @endphp
          <tr class="{{ $i % 2 === 0 ? 'alt' : '' }}">
            <td class="muted">{{ $i }}</td>
            <td>{{ $r->title }}</td>
            <td class="muted">{{ $r->type }}</td>
            <td class="num">{{ $r->minutes ? $r->minutes . ' min' : '—' }}</td>
            <td class="num">{{ $r->attempts === null ? '—' : $r->attempts }}</td>
            <td class="num {{ $r->score === null ? '' : ($r->score >= 80 ? 'good' : 'bad') }}"><b>{{ $r->score === null ? '—' : rtrim(rtrim(number_format($r->score, 1), '0'), '.') . '%' }}</b></td>
            <td class="ctr">{{ $d($r->date) }}</td>
            <td class="ctr"><span class="chip {{ $tone($r->status) }}">{{ $r->status }}</span></td>
          </tr>
        @empty
          <tr><td colspan="8" class="muted empty">
            {{ $g->library ? 'No sessions watched yet - any ' . $t->required . ' of the Learn @ Hi Tech sessions complete this phase.' : 'No modules in this phase yet.' }}
          </td></tr>
        @endforelse
      @endforeach
      <tr class="total">
        <td colspan="4">TOTAL</td>
        <td class="num">{{ $t->attempts }}</td>
        <td class="num">{{ $t->avgScore === null ? '—' : rtrim(rtrim(number_format($t->avgScore, 1), '0'), '.') . '%' }}</td>
        <td class="ctr" colspan="2">{{ $t->allDone }} of {{ $t->allTotal }} modules passed</td>
      </tr>
    </tbody>
  </table>

  <div class="verdict">
    <b>Result:</b> {{ $t->compDone }} of {{ $t->compTotal }} compulsory modules passed ({{ $t->compPct }}%) &nbsp;—&nbsp; <b>{{ $t->result }}</b>
    <span class="muted">({{ $t->status }})</span>
    @if(count($t->failed))
      <div class="note"><b>Needs attention:</b> {{ implode(' · ', array_slice($t->failed, 0, 4)) }} {{ count($t->failed) === 1 ? 'needs' : 'need' }} a retake.</div>
    @elseif($t->next && $t->result !== 'PASSED')
      <div class="note"><b>Next:</b> {{ $t->next }}</div>
    @endif
    <div class="note">
      <b>Started:</b> {{ $d($t->firstAt) }} &nbsp;·&nbsp; <b>Last activity:</b> {{ $d($t->lastAt) }}
      &nbsp;·&nbsp; <b>Compulsory completed:</b> {{ $t->completedAt ? $d($t->completedAt) : 'Not yet' }}
    </div>
  </div>

  <div class="section">Remarks</div>
  <table class="notes">
    <tr><td class="who">Reporting manager</td><td class="ruled">&nbsp;</td></tr>
    <tr><td class="who">HR</td><td class="ruled">&nbsp;</td></tr>
  </table>

  <table class="sign">
    <tr>
      <td><div class="line">Employee</div></td>
      <td><div class="line">Reporting manager{{ $t->manager ? ' — ' . $t->manager : '' }}</div></td>
      <td><div class="line">HR</div></td>
    </tr>
  </table>

  <div class="foot">
    {{ trim($u->first_name . ' ' . $u->last_name) }} &nbsp;·&nbsp; {{ $u->code ?: '—' }}
    &nbsp;—&nbsp; Confidential, internal use only &nbsp;·&nbsp; Generated {{ now()->format('d M Y, H:i') }}
  </div>
</div>
