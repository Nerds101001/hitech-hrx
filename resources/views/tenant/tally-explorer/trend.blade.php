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
  <div class="d-flex flex-wrap align-items-end gap-1">
    <div class="tx-field" style="width:100px;">
      <label class="form-label small fw-bold mb-1">Measure</label>
      <select name="measure" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach($measures as $k => $v)<option value="{{ $k }}" @selected($measure === $k)>{{ $v }}</option>@endforeach
      </select>
    </div>
    <div class="tx-field" style="width:128px;">
      <label class="form-label small fw-bold mb-1">Group by</label>
      <select name="dim" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach($dimLabels as $k => $v)<option value="{{ $k }}" @selected($dim === $k)>{{ $v }}</option>@endforeach
      </select>
    </div>
    <div class="tx-field" style="width:92px;">
      <label class="form-label small fw-bold mb-1">Sort by</label>
      <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="total" @selected($sortBy === 'total')>Total</option>
        @foreach($months as $m)<option value="{{ $m }}" @selected($sortBy === $m)>{{ \Illuminate\Support\Carbon::parse($m.'-01')->format('M Y') }}</option>@endforeach
      </select>
    </div>
    <div class="tx-field" style="width:86px;">
      <label class="form-label small fw-bold mb-1">FY</label>
      <select name="fy" class="form-select form-select-sm" onchange="this.form.submit()">
        @for($y = $thisFy; $y >= $thisFy - 3; $y--)<option value="{{ $y }}" @selected($fy === $y)>FY {{ $y }}-{{ substr($y + 1, 2) }}</option>@endfor
      </select>
    </div>
    <div class="tx-field" style="width:128px;">
      <label class="form-label small fw-bold mb-1">Filter by</label>
      <select name="filter_dim" id="txFilterDim" class="form-select form-select-sm">
        <option value="">— no filter —</option>
        @foreach($allDimLabels as $k => $v)<option value="{{ $k }}" @selected($filterDim === $k)>{{ $v }}</option>@endforeach
      </select>
    </div>
    <div class="tx-field flex-grow-1" style="min-width:130px;">
      <label class="form-label small fw-bold mb-1">Values</label>
      <div class="tx-chips form-control form-control-sm d-flex flex-wrap align-items-center gap-1" id="txChips" style="min-height:31px; cursor:text; position:relative;">
        @foreach($filterVals as $fv)
          <span class="tx-chip">{{ $fv }}<i class="bx bx-x" data-chip></i><input type="hidden" name="filter_vals[]" value="{{ $fv }}"></span>
        @endforeach
        <input type="text" id="txChipInput" class="tx-chip-input" placeholder="{{ $filterDim ? 'click to see all, or type to search…' : 'pick a dimension first' }}" autocomplete="off" style="border:0;outline:0;flex:1;min-width:90px;font-size:.82rem;">
        <div id="txChipList" class="tx-chip-list"></div>
      </div>
    </div>
    <div class="tx-field" style="width:110px;">
      <label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="in {{ strtolower($dimLabels[$dim]) }}">
    </div>
    <div class="form-check mb-1 flex-shrink-0"><input type="hidden" name="include0" value="0"><input class="form-check-input" type="checkbox" name="include0" value="1" id="inc0" @checked($include0)><label class="form-check-label small text-nowrap" for="inc0">Incl&nbsp;0</label></div>
    <div class="d-flex gap-1 flex-shrink-0">
      <button class="btn btn-primary btn-sm" title="Search"><i class="bx bx-search"></i></button>
      <a href="{{ route('tally-explorer.trend.export', array_merge(request()->query(), ['measure'=>$measure,'dim'=>$dim,'fy'=>$fy])) }}" class="btn btn-outline-secondary btn-sm" title="Export CSV"><i class="bx bx-download"></i></a>
    </div>
  </div>
</form>

<div class="d-flex flex-wrap align-items-center gap-2 mb-2 small text-muted">
  <span><b>{{ $measures[$measure] }}</b> by <b>{{ $dimLabels[$dim] }}</b>@if($filterDim && count($filterVals)), filtered to {{ count($filterVals) }} {{ \Illuminate\Support\Str::lower($allDimLabels[$filterDim]) }}(s)@endif · FY {{ $fy }}-{{ substr($fy+1,2) }} · {{ number_format($rowCount) }} rows</span>
  <span>·
    @foreach($grand as $pl => $g)<b class="text-dark">{{ $pl }}:</b> ₹{{ $inr($g) }}&nbsp; @endforeach
  </span>
  <span class="ms-auto d-flex align-items-center gap-3">
    <span><span class="tx-up-dot"></span> green = up from the previous month with data</span>
    <span><span class="tx-down-dot"></span> red = down from the previous month with data</span>
    <span><span class="tx-same-dot"></span> black = same as, or first month with data</span>
    <span class="text-muted">— = no transactions that month</span>
  </span>
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
              @if($v === null)
                <td class="tx-num {{ $cls }}">—</td>
              @else
                <td class="tx-num tx-cell-link {{ $cls }}" data-dimkey="{{ $r['key'] }}" data-ym="{{ $m }}" title="Click for the underlying transactions">{{ $inr($v) }}</td>
              @endif
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

<script>
(function () {
  const CELL_URL = "{{ route('tally-explorer.trend.cell') }}";
  const EXPORT_URL = "{{ route('tally-explorer.trend.cell.export') }}";
  const table = document.querySelector('.tx-trend');
  if (!table) return;

  const inr = n => {
    n = Math.round(Number(n) || 0);
    if (n === 0) return '0';
    const neg = n < 0; n = Math.abs(n);
    let s = String(n), last3 = s.slice(-3), rest = s.slice(0, -3);
    if (rest !== '') { rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ','); last3 = ',' + last3; }
    return (neg ? '-' : '') + rest + last3;
  };
  const esc = s => (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  // For rate/qty/GST% in the cell popup -- NEVER round these to whole numbers like inr() does for money.
  // A rate of 1.24 rounded to "1" makes qty*rate silently stop matching the taxable amount shown next to it
  // (e.g. 12000 qty * real rate 1.24 = 14880, but a rounded-to-1 rate reads as if it should be 12000).
  // Keeps up to 4 decimal places, trimming trailing zeros so whole numbers still print plainly.
  const fmtQty = n => {
    if (n === null || n === undefined || n === '') return '';
    n = Number(n);
    if (!isFinite(n)) return String(n);
    return n.toLocaleString('en-IN', { maximumFractionDigits: 4 });
  };

  function currentParams(dimkey, ym) {
    const p = new URLSearchParams({
      measure: @json($measure), dim: @json($dim), dimkey: dimkey, ym: ym,
      filter_dim: @json($filterDim),
    });
    @foreach($filterVals as $fv)
      p.append('filter_vals[]', @json($fv));
    @endforeach
    return p;
  }

  let cellData = null; // the last-fetched sections, kept so search filters client-side without a re-fetch

  function renderCellBody(filterQ) {
    const q = (filterQ || '').trim().toLowerCase();
    const body = document.getElementById('txCellBody');
    if (!cellData) return;

    let html = '';
    if (cellData.summary) {
      const s = cellData.summary;
      const monthLabel = s.month ? new Date(s.month + '-02').toLocaleString('en-US', { month: 'long', year: 'numeric' }) : '';
      const prevLabel = s.prev_month ? new Date(s.prev_month + '-02').toLocaleString('en-US', { month: 'short', year: 'numeric' }) : '';
      let changeHtml;
      if (s.pct_change === null) {
        changeHtml = '<span class="text-muted">no data for ' + esc(prevLabel) + ' to compare</span>';
      } else {
        const up = s.pct_change > 0, flat = s.pct_change === 0;
        const cls = flat ? 'text-dark' : (up ? 'tx-up' : 'tx-down');
        const arrow = flat ? '' : (up ? '▲ ' : '▼ ');
        changeHtml = '<span class="' + cls + ' fw-semibold">' + arrow + Math.abs(s.pct_change) + '%</span> <span class="text-muted">vs ' + esc(prevLabel) + ' (₹' + inr(s.prev_total) + ')</span>';
      }
      html += '<div class="tx-salesperson-summary px-3 py-3">'
        + '<div class="row g-3">'
        + '<div class="col-6 col-md-3"><div class="tx-sum-lbl">Salesperson</div><div class="tx-sum-val">' + esc(s.salesperson) + '</div></div>'
        + '<div class="col-6 col-md-3"><div class="tx-sum-lbl">Month</div><div class="tx-sum-val">' + esc(monthLabel) + '</div></div>'
        + '<div class="col-6 col-md-3"><div class="tx-sum-lbl">Total Sales</div><div class="tx-sum-val">₹' + inr(s.total) + '</div></div>'
        + '<div class="col-6 col-md-3"><div class="tx-sum-lbl">Customers Billed</div><div class="tx-sum-val">' + s.customers + '</div></div>'
        + '</div>'
        + '<div class="small mt-2">' + changeHtml + '</div>'
        + '</div>';
    }
    let shownCount = 0, shownTotal = 0;
    (cellData.sections || []).forEach(section => {
      const rows = q ? section.rows.filter(row => Object.values(row).some(v => String(v ?? '').toLowerCase().indexOf(q) !== -1)) : section.rows;
      if (!section.rows.length) { html += '<p class="text-muted small px-3 pt-2">No ' + esc(section.part).toLowerCase() + ' rows.</p>'; return; }
      if (!rows.length) { html += '<p class="text-muted small px-3 pt-2">No ' + esc(section.part).toLowerCase() + ' rows match “' + esc(filterQ) + '”.</p>'; return; }
      if ((cellData.sections || []).length > 1) html += '<h6 class="fw-bold px-3 pt-2 mb-1">' + esc(section.part) + '</h6>';

      html += '<div class="tx-cell-scroll"><table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr>';
      section.columns.forEach(c => { html += '<th class="tx-cell-th">' + esc(c) + '</th>'; });
      html += '</tr></thead><tbody>';
      let rowTotal = 0;
      rows.forEach(row => {
        html += '<tr>';
        Object.keys(row).forEach(k => {
          const v = row[k];
          const money = ['taxable', 'tax', 'total', 'amount'].indexOf(k) !== -1;
          const decimal = ['qty', 'rate', 'gst'].indexOf(k) !== -1;
          let text;
          if (money && v !== null && v !== '') text = inr(v);
          else if (decimal && v !== null && v !== '') text = fmtQty(v);
          else text = esc(v ?? '—');
          html += '<td class="tx-cell-td' + ((money || decimal) ? ' text-end' : '') + '">' + text + '</td>';
        });
        html += '</tr>';
        if (typeof row.total === 'number') rowTotal += row.total;
        else if (typeof row.taxable === 'number') rowTotal += row.taxable;
        else if (typeof row.amount === 'number') rowTotal += row.amount;
      });
      html += '</tbody></table></div>';
      shownCount += rows.length;
      shownTotal += q ? rowTotal : section.total;
    });

    body.innerHTML = html || '<p class="text-muted p-3">No transactions found for this cell.</p>';
    document.getElementById('txCellCount').textContent = shownCount
      ? (q ? shownCount + ' of ' + (cellData.sections || []).reduce((s, sec) => s + sec.rows.length, 0) + ' row(s), ₹' + inr(shownTotal) : shownCount + ' row(s)')
      : '';
  }

  function openCell(dimkey, ym) {
    const modal = new bootstrap.Modal(document.getElementById('txCellModal'));
    cellData = null;
    document.getElementById('txCellTitle').textContent = 'Loading…';
    document.getElementById('txCellBody').innerHTML = '<div class="text-center text-muted py-5"><i class="bx bx-loader-alt bx-spin fs-3"></i></div>';
    document.getElementById('txCellCount').textContent = '';
    document.getElementById('txCellSearch').value = '';
    document.getElementById('txCellExportBtn').href = EXPORT_URL + '?' + currentParams(dimkey, ym).toString();
    modal.show();

    fetch(CELL_URL + '?' + currentParams(dimkey, ym).toString(), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json()).then(d => {
        const monthLabel = ym ? new Date(ym + '-02').toLocaleString('en-US', { month: 'long', year: 'numeric' }) : '';
        document.getElementById('txCellTitle').textContent = (d.label || dimkey) + ' — ' + monthLabel;
        cellData = d;
        renderCellBody('');
      })
      .catch(() => {
        document.getElementById('txCellBody').innerHTML = '<p class="text-danger p-3">Could not load the underlying transactions.</p>';
      });
  }

  table.addEventListener('click', e => {
    const td = e.target.closest('.tx-cell-link');
    if (!td) return;
    openCell(td.dataset.dimkey, td.dataset.ym);
  });

  // #txCellSearch lives in the tab-modals section, rendered later in the
  // page than this script -- delegate from document instead of attaching
  // directly to the (not-yet-existing) element.
  let searchT;
  document.addEventListener('input', e => {
    if (e.target.id !== 'txCellSearch') return;
    clearTimeout(searchT);
    const val = e.target.value;
    searchT = setTimeout(() => renderCellBody(val), 150);
  });
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
  #txPage .tx-up-dot, #txPage .tx-down-dot, #txPage .tx-same-dot { display:inline-block; width:9px; height:9px; border-radius:2px; vertical-align:-1px; }
  #txPage .tx-up-dot { background:#15803d; } #txPage .tx-down-dot { background:#b91c1c; } #txPage .tx-same-dot { background:#1e293b; }
  #txPage .tx-chip { display:inline-flex; align-items:center; gap:.2rem; background:#e8f3f1; color:#0e5f52; border-radius:20px; padding:1px 4px 1px 9px; font-size:.74rem; font-weight:600; }
  #txPage .tx-chip i { cursor:pointer; font-size:.95rem; opacity:.7; } #txPage .tx-chip i:hover { opacity:1; }
  #txPage .tx-chip-list { position:absolute; top:100%; left:0; right:0; z-index:20; background:#fff; border:1px solid #e8edf3; border-radius:10px; box-shadow:0 10px 28px rgba(15,23,42,.12); margin-top:4px; max-height:240px; overflow:auto; display:none; }
  #txPage .tx-chip-opt { padding:.4rem .7rem; font-size:.82rem; cursor:pointer; } #txPage .tx-chip-opt:hover { background:#f1f5f9; }
  /* Uniform column widths -- otherwise each month column sizes to its own
     widest value and the grid looks ragged/misaligned row to row. */
  #txPage .tx-trend .tx-num { min-width:108px; width:108px; }
  #txPage .tx-trend .tx-sticky-r { min-width:120px; width:120px; }
  #txPage .tx-trend .tx-cell-link { cursor:pointer; }
  #txPage .tx-trend .tx-cell-link:hover { background:#eaf6f3; text-decoration:underline; text-underline-offset:2px; }

  /* Cell drill-down modal: ONE bounded box that scrolls both ways, with the
     header pinned -- no nested "modal scrolls, but the table inside is cut
     off with a hard-to-find scrollbar" trap. */
  #txCellModal .tx-cell-scroll { max-height:60vh; overflow:auto; scrollbar-width:thin; scrollbar-color:#94a3b8 #f1f5f9; }
  #txCellModal .tx-cell-scroll::-webkit-scrollbar { height:10px; width:10px; }
  #txCellModal .tx-cell-scroll::-webkit-scrollbar-thumb { background:#94a3b8; border-radius:6px; }
  #txCellModal .tx-cell-scroll::-webkit-scrollbar-track { background:#f1f5f9; }
  #txCellModal table { margin:0; }
  #txCellModal .tx-cell-th { position:sticky; top:0; z-index:1; background:#f8fafc; white-space:nowrap; font-size:.66rem; text-transform:uppercase; letter-spacing:.03em; color:#64748b; padding:.35rem .5rem; }
  #txCellModal .tx-cell-td { white-space:nowrap; font-size:.76rem; padding:.3rem .5rem; }
  #txCellModal .tx-salesperson-summary { background:#f8fafc; border-bottom:1px solid #e8edf3; }
  #txCellModal .tx-sum-lbl { font-size:.64rem; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; font-weight:700; }
  #txCellModal .tx-sum-val { font-size:1.05rem; font-weight:700; color:#1e293b; }
  #txCellModal .tx-salesperson-summary .tx-up { color:#15803d; }
  #txCellModal .tx-salesperson-summary .tx-down { color:#b91c1c; }
</style>
@endsection

@section('tab-modals')
<div class="modal fade" id="txCellModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:min(1400px, 96vw);">
    <div class="modal-content">
      <div class="modal-header py-2">
        <div class="flex-grow-1">
          <h6 class="modal-title mb-0" id="txCellTitle"></h6>
        </div>
        <div class="search-wrapper-hitech mx-3" style="width:220px;">
          <input type="text" id="txCellSearch" class="form-control form-control-sm" placeholder="Search item, bill no…">
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" id="txCellBody"></div>
      <div class="modal-footer py-2">
        <span id="txCellCount" class="small text-muted me-auto"></span>
        <a href="#" id="txCellExportBtn" class="btn btn-outline-secondary btn-sm"><i class="bx bx-download me-1"></i> Export CSV</a>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection
