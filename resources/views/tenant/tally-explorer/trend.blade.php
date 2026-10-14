@extends('tenant.tally-explorer.layout')

@section('tab')
@php
  $inr = function ($n) {
    $n = (int) round($n);
    if ($n === 0) return '0';
    $neg = $n < 0; $n = abs($n);
    $s = (string) $n; $last3 = substr($s, -3); $rest = substr($s, 0, -3);
    if ($rest !== '') { $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest); $last3 = ',' . $last3; }
    return ($neg ? '-' : '') . $rest . $last3;
  };
  $thisFy = now()->month >= 4 ? now()->year : now()->year - 1;
  // Net column total across parts (for the header).
  $headerTot = [];
  foreach ($months as $m) { $t = 0.0; foreach ($partLabels as $pl) { $t += $colTotals[$pl][$m] ?? 0; } $headerTot[$m] = $t; }
@endphp

<form method="GET" id="txTrendForm" class="tx-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Measure</label>
      <select name="measure" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach($measures as $k => $v)<option value="{{ $k }}" @selected($measure === $k)>{{ $v }}</option>@endforeach
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Group by</label>
      <select name="dim" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach($dimLabels as $k => $v)<option value="{{ $k }}" @selected($dim === $k)>{{ $v }}</option>@endforeach
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Sort by</label>
      <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="total" @selected($sortBy === 'total')>Total</option>
        @foreach($months as $m)<option value="{{ $m }}" @selected($sortBy === $m)>{{ \Illuminate\Support\Carbon::parse($m.'-01')->format('M Y') }}</option>@endforeach
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Financial year</label>
      <select name="fy" class="form-select form-select-sm" onchange="this.form.submit()">
        @for($y = $thisFy; $y >= $thisFy - 3; $y--)<option value="{{ $y }}" @selected($fy === $y)>FY {{ $y }}-{{ substr($y + 1, 2) }}</option>@endfor
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Search result</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="in {{ strtolower($dimLabels[$dim]) }}">
    </div>
    <div class="col-6 col-md-2 d-flex gap-2 align-items-end">
      <div class="form-check mb-1"><input type="hidden" name="include0" value="0"><input class="form-check-input" type="checkbox" name="include0" value="1" id="inc0" @checked($include0)><label class="form-check-label small" for="inc0">Incl&nbsp;0</label></div>
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i></button>
      <a href="{{ route('tally-explorer.trend.export', array_merge(request()->query(), ['measure'=>$measure,'dim'=>$dim,'fy'=>$fy])) }}" class="btn btn-outline-secondary btn-sm" title="Export CSV"><i class="bx bx-download"></i></a>
    </div>
  </div>

  {{-- Filter: pick a dimension, then add one or more values as chips --}}
  <div class="row g-2 mt-1 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label small fw-bold mb-1">Filter by</label>
      <select name="filter_dim" id="txFilterDim" class="form-select form-select-sm">
        <option value="">— no filter —</option>
        @foreach($allDimLabels as $k => $v)<option value="{{ $k }}" @selected($filterDim === $k)>{{ $v }}</option>@endforeach
      </select>
    </div>
    <div class="col-12 col-md-9">
      <label class="form-label small fw-bold mb-1">Values</label>
      <div class="tx-chips form-control form-control-sm d-flex flex-wrap align-items-center gap-1" id="txChips" style="min-height:31px; cursor:text; position:relative;">
        @foreach($filterVals as $fv)
          <span class="tx-chip">{{ $fv }}<i class="bx bx-x" data-chip></i><input type="hidden" name="filter_vals[]" value="{{ $fv }}"></span>
        @endforeach
        <input type="text" id="txChipInput" class="tx-chip-input" placeholder="{{ $filterDim ? 'click to see all, or type to search…' : 'pick a dimension first' }}" autocomplete="off" style="border:0;outline:0;flex:1;min-width:160px;font-size:.82rem;">
        <div id="txChipList" class="tx-chip-list"></div>
      </div>
    </div>
  </div>
</form>

<div class="d-flex flex-wrap align-items-center gap-2 mb-2 small text-muted">
  <span><b>{{ $measures[$measure] }}</b> by <b>{{ $dimLabels[$dim] }}</b>@if($filterDim && count($filterVals)), filtered to {{ count($filterVals) }} {{ \Illuminate\Support\Str::lower($allDimLabels[$filterDim]) }}(s)@endif · FY {{ $fy }}-{{ substr($fy+1,2) }} · {{ number_format($rowCount) }} rows</span>
  <span>·
    @foreach($grand as $pl => $g)<b class="text-dark">{{ $pl }}:</b> ₹{{ $inr($g) }}&nbsp; @endforeach
  </span>
  <span class="ms-auto"><span class="tx-up-dot"></span> up &nbsp; <span class="tx-down-dot"></span> down (vs prev month)</span>
</div>

<div class="tx-card overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm tx-table tx-trend mb-0">
      <thead class="table-light">
        <tr>
          <th class="tx-sticky">{{ $dimLabels[$dim] }}</th>
          @if($isCombined)<th>Report Type</th>@endif
          @foreach($months as $m)
            <th class="tx-num">{{ \Illuminate\Support\Carbon::parse($m.'-01')->format('M') }}<br><span class="tx-coltot">{{ $inr($headerTot[$m] ?? 0) }}</span></th>
          @endforeach
          <th class="tx-num tx-sticky-r">Total</th>
        </tr>
      </thead>
      <tbody>
      @forelse($rows as $r)
        @foreach($partLabels as $pi => $pl)
          @php $cells = $r['rows'][$pl] ?? array_fill_keys($months, null); $rowTot = array_sum(array_filter($cells, fn($v)=>$v!==null)); @endphp
          <tr>
            @if($pi === 0)
              <td class="tx-sticky fw-semibold" @if($isCombined) rowspan="{{ count($partLabels) }}" @endif>
                @if($link === 'ledger')<a href="{{ route('tally-explorer.ledgers.show', ['name' => $r['label']]) }}" class="text-decoration-none" style="color:#0f6b78;">{{ $r['label'] ?: '—' }}</a>
                @elseif($link === 'item')<a href="{{ route('tally-explorer.items.show', ['name' => $r['label']]) }}" class="text-decoration-none" style="color:#4338ca;">{{ $r['label'] ?: '—' }}</a>
                @else{{ $r['label'] ?: '—' }}@endif
              </td>
            @endif
            @if($isCombined)<td class="tx-rt">{{ $pl }}</td>@endif
            @php $prev = null; @endphp
            @foreach($months as $m)
              @php
                $v = $cells[$m] ?? null; $cls = '';
                if ($v !== null && $prev !== null) { $cls = $v > $prev ? 'tx-up' : ($v < $prev ? 'tx-down' : ''); }
                if ($v !== null) $prev = $v;
                if ($v !== null && $v < 0) $cls .= ' tx-neg';
              @endphp
              <td class="tx-num {{ $cls }}">{{ $v === null ? '—' : $inr($v) }}</td>
            @endforeach
            <td class="tx-num tx-sticky-r fw-bold {{ $rowTot < 0 ? 'tx-neg' : '' }}">{{ $inr($rowTot) }}</td>
          </tr>
        @endforeach
      @empty
        <tr><td colspan="{{ count($months) + ($isCombined ? 3 : 2) }}" class="text-center text-muted py-5">No data for this selection.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
{{ $rows->links() }}

<script>
(function () {
  const VALUES_URL = "{{ route('tally-explorer.trend.values') }}";
  const chips = document.getElementById('txChips');
  const input = document.getElementById('txChipInput');
  const list = document.getElementById('txChipList');
  const dimSel = document.getElementById('txFilterDim');
  if (!chips) return;
  const esc = s => (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  function addChip(val) {
    if (!val) return;
    if ([...chips.querySelectorAll('input[name="filter_vals[]"]')].some(i => i.value === val)) return;
    const span = document.createElement('span');
    span.className = 'tx-chip';
    span.innerHTML = esc(val) + '<i class="bx bx-x" data-chip></i><input type="hidden" name="filter_vals[]" value="' + esc(val) + '">';
    chips.insertBefore(span, input);
  }
  function loadValues(q) {
    const dim = dimSel.value;
    if (!dim) { list.innerHTML = ''; list.style.display = 'none'; return; }
    list.innerHTML = '<div class="tx-chip-opt text-muted">loading…</div>';
    list.style.display = 'block';
    fetch(VALUES_URL + '?dim=' + encodeURIComponent(dim) + '&q=' + encodeURIComponent(q || ''), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json()).then(d => {
        const vals = d.values || [];
        list.innerHTML = vals.length ? vals.map(v => '<div class="tx-chip-opt" data-v="' + esc(v) + '">' + esc(v) + '</div>').join('') : '<div class="tx-chip-opt text-muted">no values</div>';
        list.style.display = 'block';
      });
  }

  chips.addEventListener('click', e => {
    if (e.target.matches('[data-chip]')) { e.target.closest('.tx-chip').remove(); }
    else { input.focus(); loadValues(input.value.trim()); }
  });
  dimSel.addEventListener('change', () => {
    chips.querySelectorAll('.tx-chip').forEach(c => c.remove());
    input.value = '';
    input.placeholder = dimSel.value ? 'click to see all, or type to search…' : 'pick a dimension first';
    if (dimSel.value) { input.focus(); loadValues(''); } else { list.innerHTML = ''; list.style.display = 'none'; }
  });

  let t;
  input.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => loadValues(input.value.trim()), 220); });
  input.addEventListener('focus', () => { if (dimSel.value) loadValues(input.value.trim()); });
  list.addEventListener('mousedown', e => {
    const opt = e.target.closest('.tx-chip-opt[data-v]');
    if (opt) { addChip(opt.dataset.v); input.value = ''; list.innerHTML = ''; input.focus(); }
  });
  document.addEventListener('click', e => { if (!chips.contains(e.target)) list.style.display = 'none'; });
})();
</script>

<style>
  #txPage .tx-trend th, #txPage .tx-trend td { white-space:nowrap; }
  #txPage .tx-trend .tx-coltot { font-size:.62rem; font-weight:700; color:#94a3b8; }
  #txPage .tx-trend .tx-sticky { position:sticky; left:0; background:#fff; z-index:2; min-width:190px; max-width:260px; overflow:hidden; text-overflow:ellipsis; box-shadow:1px 0 0 #eef2f6; }
  #txPage .tx-trend thead .tx-sticky { background:#f8fafc; z-index:3; }
  #txPage .tx-trend .tx-sticky-r { position:sticky; right:0; background:#fff; box-shadow:-1px 0 0 #eef2f6; }
  #txPage .tx-trend .tx-rt { font-size:.72rem; color:#64748b; font-weight:600; }
  #txPage .tx-trend .tx-up { color:#15803d; } #txPage .tx-trend .tx-down { color:#b91c1c; } #txPage .tx-trend .tx-neg { color:#b91c1c; }
  #txPage .tx-up-dot, #txPage .tx-down-dot { display:inline-block; width:9px; height:9px; border-radius:2px; vertical-align:-1px; }
  #txPage .tx-up-dot { background:#15803d; } #txPage .tx-down-dot { background:#b91c1c; }
  #txPage .tx-chip { display:inline-flex; align-items:center; gap:.2rem; background:#e8f3f1; color:#0e5f52; border-radius:20px; padding:1px 4px 1px 9px; font-size:.74rem; font-weight:600; }
  #txPage .tx-chip i { cursor:pointer; font-size:.95rem; opacity:.7; } #txPage .tx-chip i:hover { opacity:1; }
  #txPage .tx-chip-list { position:absolute; top:100%; left:0; right:0; z-index:20; background:#fff; border:1px solid #e8edf3; border-radius:10px; box-shadow:0 10px 28px rgba(15,23,42,.12); margin-top:4px; max-height:240px; overflow:auto; display:none; }
  #txPage .tx-chip-opt { padding:.4rem .7rem; font-size:.82rem; cursor:pointer; } #txPage .tx-chip-opt:hover { background:#f1f5f9; }
</style>
@endsection
