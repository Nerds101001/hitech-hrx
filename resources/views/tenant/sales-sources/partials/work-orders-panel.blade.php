{{-- All / Pending / Dispatch tabs: stat cards + charts + a fully client-driven, auto-applying filter bar
     and sortable table. ITEM-LINE level (item/party/stock group/qty/amount/financial approval/remarks per
     row, one row per order line), not one row per order -- explicit request 2026-10-19 to match the shape
     of the Tally "Partywise Sale Order" report instead of a collapsed order summary; the per-order expand
     arrow from the earlier version is gone because every line is already its own row now. Self-contained
     JS, its own fetch to panel-data (not financial-data -- that one carries the credit/outstanding join
     this doesn't need). --}}
<div id="woPanel-{{ $panelTab }}" class="wopanel">
  {{-- Explicit layout request: 6 stat cards in one row, then all 4 charts (including By sales group,
       which used to be its own separate full-width row below) together in a second row of 4. --}}
  <div class="row g-3 mb-3 wopanel-stats"></div>

  {{-- h-100 on each card -- a Bootstrap row's columns are already equal-height (flex default), but the
       white card inside one wasn't told to fill that height, so it sized to its own content instead and
       the 4 cards ended up with visibly different bottom edges (the sales-group list is naturally taller
       than a fixed-height donut). h-100 makes every card stretch to match the tallest one in the row. --}}
  <div class="row g-3 mb-3 align-items-stretch">
    <div class="col-12 col-lg-3"><div class="hitech-card-white p-3 h-100"><h6 class="fw-bold mb-2">Order value by day <span class="text-muted small fw-normal">(click to filter)</span></h6><div class="wopanel-trend"></div></div></div>
    <div class="col-12 col-lg-3"><div class="hitech-card-white p-3 h-100"><h6 class="fw-bold mb-2">By unit <span class="text-muted small">(click to filter)</span></h6><div class="wopanel-byunit"></div></div></div>
    <div class="col-12 col-lg-3"><div class="hitech-card-white p-3 h-100"><h6 class="fw-bold mb-2">By dispatch status <span class="text-muted small fw-normal">(click to filter)</span></h6><div class="wopanel-bydispatch"></div></div></div>
    {{-- Tally's own customer ledger GROUP -- for this company routinely a salesperson, territory or brand
         bucket (e.g. "ISHAN SIKKA", "PUNJAB", "Fillezy"), confirmed against real data 2026-10-19. Top 20 by
         value; click to filter the table below to that one group. Toggle switches this SAME card between
         Sales group and Stock group -- explicit request: one card, not a separate 5th chart, since only
         one of the two is ever being looked at at a time. --}}
    <div class="col-12 col-lg-3"><div class="hitech-card-white p-3 h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0">By <span class="wopanel-group-toggle-label">sales group</span> <span class="text-muted small fw-normal">(click to filter)</span></h6>
        <div class="btn-group btn-group-sm wopanel-group-toggle" role="group">
          <button type="button" class="btn btn-outline-secondary active" data-group-mode="group">Sales</button>
          <button type="button" class="btn btn-outline-secondary" data-group-mode="category">Stock</button>
        </div>
      </div>
      <div class="wopanel-bygroup"></div>
    </div></div>
  </div>

  <div class="hitech-card-white overflow-hidden mb-3 position-relative">
    <div class="wopanel-loading" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,.6);z-index:5;"></div>

    {{-- Filter / sort / search toolbar for the table directly below -- kept inside this same card,
         right above the table, per explicit request: filters belong with the table, not detached up
         near the stat cards and charts. --}}
    <div class="p-2 border-bottom wopanel-filterbar">
      <div class="wop-filter-row">
        <input type="text" class="form-control form-control-sm wop-f-search wopanel-search" placeholder="Party or order no.">
        <select class="form-select form-select-sm wop-f-company wopanel-company">
          <option value="hitech" @selected($company === 'hitech')>Hi Tech</option>
          <option value="kif" @selected($company === 'kif')>Keep It Fresh</option>
          <option value="" @selected($company === null)>Both</option>
        </select>
        <select class="form-select form-select-sm wop-f-sm wopanel-unit">
          <option value="">All units</option>
          @foreach($unitPrefixes as $code => $label)
            <option value="{{ $code }}">{{ $label }}</option>
          @endforeach
        </select>
        <select class="form-select form-select-sm wop-f-sm wopanel-category">
          <option value="">All stock groups</option>
          @foreach($categories as $c)
            <option value="{{ $c }}">{{ $c }}</option>
          @endforeach
        </select>
        <select class="form-select form-select-sm wop-f-sm wopanel-group">
          <option value="">All sales groups</option>
        </select>
        {{-- Explicit request: a direct filter for dispatch status, not just the "By dispatch status" chart
             click -- same 3 buckets the chart and the Dispatch badge already use (Not dispatched / Partially
             dispatched / Fully dispatched), matched server-side the same way. --}}
        <select class="form-select form-select-sm wop-f-sm wopanel-dispatch">
          <option value="">All dispatch status</option>
          <option value="not">Not dispatched</option>
          <option value="partial">Partially dispatched</option>
          <option value="full">Fully dispatched</option>
        </select>
        {{-- Moved to sit right before Reset -- explicit request. --}}
        <div class="position-relative wop-f-date">
          <button type="button" class="btn btn-sm btn-outline-secondary w-100 wopanel-date-toggle text-truncate"></button>
          {{-- position:fixed, not absolute -- the filter row scrolls horizontally (.wop-filter-row),
               and overflow-x:auto on an ancestor implicitly forces overflow-y:auto too (a real CSS
               quirk), which clipped this popover to the row's own height when it was absolute. Fixed
               positioning is computed from the button's own rect in JS and isn't clipped by any
               ancestor's overflow. --}}
          <div class="wopanel-date-pop card shadow p-2" style="display:none;position:fixed;z-index:1050;min-width:260px;">
            <div class="d-flex flex-wrap gap-1 mb-2">
              @foreach(['mtd' => 'This month', 'lm' => 'Last month', 'last30' => 'Last 30 days', 'qtd' => 'This quarter', 'fytd' => 'Financial year', 'all' => 'All data'] as $k => $label)
                <button type="button" class="btn btn-sm btn-outline-secondary wopanel-preset" data-preset="{{ $k }}">{{ $label }}</button>
              @endforeach
            </div>
            <div class="d-flex gap-1 align-items-center">
              <input type="date" class="form-control form-control-sm wopanel-from">
              <span class="small text-muted">to</span>
              <input type="date" class="form-control form-control-sm wopanel-to">
            </div>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary wopanel-reset" title="Reset filters"><i class="bx bx-reset"></i></button>
        {{-- Column show/hide -- same checkbox-list content as the Sales Report page's "Columns" button,
             but NOT Bootstrap's dropdown/Popper (same clipping problem as the date popover above: Popper
             still leaves the menu as a DOM descendant of the scrolling row, so it still got clipped).
             Manually toggled, position:fixed, same as the date popover. --}}
        <div class="position-relative">
          <button type="button" class="btn btn-sm btn-outline-secondary wopanel-cols-btn">
            <i class="bx bx-columns me-1"></i>Columns
          </button>
          <div class="wopanel-cols-menu card shadow p-2" style="display:none;position:fixed;z-index:1050;min-width:200px;font-size:.8rem;"></div>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover ss-table mb-0 wop-table">
        {{-- Every column gets an explicit width here, none left for the browser to auto-distribute --
             table-layout:fixed computes column widths ONCE from these <col> tracks, and hiding a column
             later (Columns dropdown) toggles display:none on its <col>, not on individual td/th. Doing it
             the other way (hiding td/th directly with no <colgroup>) is what caused the real bug just
             found: columns without an explicit width went haywire and the header text overlapped/garbled
             the moment any one column was hidden, because the browser had never been told how to
             re-distribute their share. --}}
        @if($panelTab === 'pending')
        {{-- The whole-ORDER rollup columns (Order: dispatched/pending/pending ₹) used to sit right next to
             this line's own "Item pending" -- explicit removal request 2026-10-21: having an order-level
             number and an item-level number side by side, both labeled "pending", kept reading as the same
             figure shown twice (it never was -- confirmed correct via direct API checks both times) rather
             than two different things. Dropped entirely so there's only one "pending" number per row here. --}}
        <colgroup>
          <col style="width:7%"><col style="width:8%"><col style="width:10%"><col style="width:8%">
          <col style="width:6%"><col style="width:5%"><col style="width:6%"><col style="width:6%">
          <col style="width:6%"><col style="width:6%">
          <col style="width:7%"><col style="width:6%"><col style="width:6%">
        </colgroup>
        <thead class="table-light">
          <tr>
            <th class="wopanel-sortable" data-sort="order_date">Date</th>
            <th class="wopanel-sortable" data-sort="order_no">Order no.</th>
            <th class="wopanel-sortable wop-col-item" data-sort="item_name">Item</th>
            <th class="wopanel-sortable wop-col-party" data-sort="party_name">Party</th>
            <th class="wopanel-sortable wop-col-group" data-sort="sales_group">Sales group</th>
            <th class="wopanel-sortable" data-sort="unit">Unit</th>
            <th class="wopanel-sortable wop-col-category" data-sort="stock_group">Stock group</th>
            <th class="ss-num wopanel-sortable" data-sort="quantity">Qty</th>
            <th class="ss-num wopanel-sortable" data-sort="amount">Amount</th>
            {{-- This ITEM's own still-pending quantity -- the Qty column is what was ordered for this
                 line; this is how much of THAT line specifically hasn't shipped yet. Matched per item
                 against deliveries, same number already driving the Dispatch badge text below. --}}
            <th class="ss-num" title="This line's own still-pending quantity (matched per item)">Item pending</th>
            <th class="wop-col-status">Dispatch</th>
            <th class="wop-col-status">Approval</th>
            <th class="wop-col-remarks">Remarks</th>
          </tr>
        </thead>
        <tbody class="wopanel-table-body"><tr><td colspan="13" class="text-center text-muted py-5">Loading…</td></tr></tbody>
        @else
        {{-- All / Dispatch: one row per ORDER (not per item line) -- explicit request 2026-10-21: these two
             tabs were repeating a multi-item order once per item, which read as duplicate orders; Pending
             (above) keeps the item-line shape since "which item is still owed" is the point of that tab.
             The chevron in the first column expands a row into its own item-wise sub-rows, fetched on
             demand from the same order-detail endpoint the modal already uses -- no separate table shape
             to maintain for that detail. --}}
        <colgroup>
          <col style="width:3%">
          <col style="width:7%"><col style="width:9%"><col style="width:14%">
          <col style="width:10%"><col style="width:5%"><col style="width:9%">
          <col style="width:6%"><col style="width:7%">
          <col style="width:9%">
          @if($panelTab === 'dispatch')<col style="width:8%"><col style="width:8%">@endif
          <col style="width:7%"><col style="width:7%">
        </colgroup>
        <thead class="table-light">
          <tr>
            <th></th>
            <th class="wopanel-sortable" data-sort="order_date">Date</th>
            <th class="wopanel-sortable" data-sort="order_no">Order no.</th>
            <th class="wopanel-sortable wop-col-party" data-sort="party_name">Party</th>
            <th class="wopanel-sortable wop-col-group" data-sort="sales_group">Sales group</th>
            <th class="wopanel-sortable" data-sort="unit">Unit</th>
            <th class="wopanel-sortable wop-col-category" data-sort="stock_group">Stock group</th>
            <th class="ss-num wopanel-sortable" data-sort="quantity">Qty</th>
            <th class="ss-num wopanel-sortable" data-sort="amount">Amount</th>
            <th class="wop-col-status">Dispatch</th>
            @if($panelTab === 'dispatch')
              <th class="wop-col-category">Transporter</th>
              <th class="wop-col-category">Docket / vehicle</th>
            @endif
            <th class="wop-col-status">Approval</th>
            <th class="wop-col-remarks">Remarks</th>
          </tr>
        </thead>
        <tbody class="wopanel-table-body"><tr><td colspan="{{ $panelTab === 'dispatch' ? 14 : 12 }}" class="text-center text-muted py-5">Loading…</td></tr></tbody>
        @endif
      </table>
    </div>
    <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="small text-muted wopanel-table-summary"></div>
      <div class="wopanel-pager"></div>
    </div>
  </div>

  {{-- Per-customer pending rollup -- explicit request: "per customer pending order". Moved to the bottom
       of the page (below the main table), out of the way of the stat cards/charts up top. Lists every
       customer with at least one pending line in the current filter, highest pending value first. Click a
       row to filter the table above to just that customer. --}}
  <div class="hitech-card-white p-3 mb-3">
    <h6 class="fw-bold mb-2">Pending by customer <span class="text-muted small fw-normal">(click a row to filter)</span></h6>
    <div class="table-responsive" style="max-height:260px;">
      <table class="table table-sm ss-table mb-0">
        <thead class="table-light"><tr><th>Party</th><th class="ss-num">Orders</th><th class="ss-num">Pending lines</th><th class="ss-num">Pending value</th></tr></thead>
        <tbody class="wopanel-byparty-body"><tr><td colspan="4" class="text-center text-muted py-3">Loading…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<style>
  .wopanel-sortable { cursor: pointer; user-select: none; white-space: nowrap; }
  .wopanel-sortable:hover { color: #127464; }
  .wopanel-sortable .sort-arrow { font-size: .7rem; opacity: .4; margin-left: 3px; }
  .wopanel-sortable.active .sort-arrow { opacity: 1; }
  /* One-row filter bar: a flex row with each control given a small fixed basis instead of Bootstrap's
     12-column grid, which wrapped to a second row once there were 7 controls -- explicit request: every
     filter in one line, with each control made smaller to make room, not stacked. Scrolls horizontally
     only as a last resort (e.g. a very narrow phone), never wraps. */
  .wop-filter-row { display: flex; flex-wrap: nowrap; gap: .4rem; align-items: center; overflow-x: auto; }
  .wop-filter-row > * { flex: 0 0 auto; }
  .wop-f-search { flex: 1 1 140px; min-width: 110px; }
  .wop-f-company { width: 100px; }
  .wop-f-date { width: 130px; }
  .wop-f-sm { width: 130px; }
  .wop-filter-row .form-control, .wop-filter-row .form-select, .wop-filter-row .btn { font-size: .78rem; }
  /* Table must fit without a horizontal scrollbar hiding columns off-screen: cap the free-text columns
     and ellipsis them (full value still available via the native title tooltip and the row's own
     click-through to the order detail) instead of letting them stretch the table wider than the card. */
  .wop-table { table-layout: fixed; width: 100%; font-size: .72rem; }
  .wop-table th, .wop-table td { padding: .35rem .4rem; overflow: hidden; }
  /* Header labels need the same overflow clipping as the data cells (.wop-ellip) -- without it, a header
     like "Stock group" rendered in a narrow column visually spills text over the next column's header,
     which looks exactly like broken/overlapping layout even though the column widths underneath are
     correct. */
  .wop-table th { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .wop-col-item { width: 13%; }
  .wop-col-party { width: 11%; }
  .wop-col-group { width: 9%; }
  .wop-col-category { width: 8%; }
  .wop-col-status { width: 7%; }
  .wop-col-remarks { width: 7%; }
  .wop-ellip { max-width: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  /* Visually marks the 3 whole-ORDER columns as a distinct kind of number from the line-level Qty/Amount
     right next to them -- renaming the headers to "Order: ..." wasn't enough on its own since a narrow
     column truncates the header text and the "Order:" prefix (the actual distinguishing part) is exactly
     what gets cut off first. A tint survives truncation; text doesn't. */
  .wop-row { cursor: pointer; }
  .wop-row:hover { background: #f8fafc; }
  .wop-expand-btn { background: none; border: none; padding: 2px 4px; cursor: pointer; color: #64748b; font-size: 1rem; line-height: 1; }
  .wop-expand-btn:hover { color: #127464; }
  .wop-order-row.wop-expanded .wop-expand-btn { color: #127464; }
  .wop-subrow { background: #fafcfc; cursor: default; }
  .wop-subrow:hover { background: #fafcfc; }
  .wopanel-byparty-body tr { cursor: pointer; }
  .wopanel-byparty-body tr:hover { background: #f8fafc; }
  /* Column show/hide -- same class name as the Sales Report page's "Columns" dropdown. */
  .wop-table .sr-col-hidden { display: none !important; }
  /* Excel-style column resize handle -- same mechanism as the Sales Report page's pipelineTable, adapted
     to resize the <colgroup> <col> (which actually governs width under table-layout:fixed) instead of the
     th itself. */
  .wop-table th { position: relative; }
  .wop-table .col-resize-handle { position: absolute; top: 0; right: 0; bottom: 0; width: 6px; cursor: col-resize; z-index: 50; user-select: none; }
  .wop-table .col-resize-handle:hover, .wop-table .col-resize-handle:active { background: rgba(18,116,100,.25); }
</style>

<script>
window.__woPanelDataUrl = window.__woPanelDataUrl || {!! json_encode(route('sales-sources.work-orders.panel-data')) !!};
</script>
<script>
(function () {
    const PANEL_TAB = {!! json_encode($panelTab) !!};
    const root = document.getElementById('woPanel-' + PANEL_TAB);
    if (!root || root.dataset.wired) return;
    root.dataset.wired = '1';

    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const inr = (v) => '₹' + Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    const crore = (v) => '₹' + (Number(v || 0) / 1e7).toFixed(2) + ' Cr';
    const qty = (v) => v == null ? '—' : Number(v).toLocaleString('en-IN', { maximumFractionDigits: 3 });
    const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const fdate = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? (+m[3]) + ' ' + MONTHS[+m[2]-1] + ' ' + m[1] : '—'; };
    // Local Y-M-D, NOT toISOString() -- toISOString() converts to UTC first, which silently shifts the date
    // back a day for any timezone ahead of UTC (e.g. IST, UTC+5:30): local midnight Oct 1 becomes Sep 30
    // 18:30 UTC. Confirmed live: "This month" was showing Sep 30 as its start date instead of Oct 1.
    const ymd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

    const todayDate = new Date();
    const todayIso = ymd(todayDate);
    const state = { q: '', company: {!! json_encode($company) !!} || '', from: ymd(new Date(Date.now() - 29 * 86400000)), to: todayIso, unit: '', category: '', group: '', dispatch: '', page: 1, sort: 'order_date', dir: 'desc', presetLabel: 'Last 30 days' };
    let trendChart = null, unitChart = null, groupChart = null, dispatchChart = null;
    let lastRenderData = null; // re-render the By sales/stock group chart on toggle without a re-fetch
    state.groupMode = 'group'; // 'group' = Sales group (d.by_group), 'category' = Stock group (d.by_category)
    let requestSeq = 0; // race-condition guard: only the response to the MOST RECENT request is ever rendered
    let groupOptionsFilled = false; // the <select> is populated once from the first response's sales_groups

    const elSearch = root.querySelector('.wopanel-search'), elCompany = root.querySelector('.wopanel-company'),
          elFrom = root.querySelector('.wopanel-from'), elTo = root.querySelector('.wopanel-to'),
          elUnit = root.querySelector('.wopanel-unit'), elCategory = root.querySelector('.wopanel-category'),
          elGroup = root.querySelector('.wopanel-group'), elDispatch = root.querySelector('.wopanel-dispatch'),
          elDateToggle = root.querySelector('.wopanel-date-toggle'), elDatePop = root.querySelector('.wopanel-date-pop'),
          elColsBtn = root.querySelector('.wopanel-cols-btn'), elColsMenu = root.querySelector('.wopanel-cols-menu'),
          elLoading = root.querySelector('.wopanel-loading');

    // Opens a position:fixed popover/menu anchored under its trigger button, clamped so it never runs off
    // the right edge of the viewport. Shared by the date popover and the Columns menu -- both used to be
    // position:absolute inside .wop-filter-row, which scrolls horizontally; overflow-x:auto on that row
    // silently forces overflow-y:auto too (a real CSS rule, not a bug in this code), which clipped both
    // popovers to the row's own height. Fixed positioning, computed here from the button's own rect,
    // isn't clipped by any ancestor's overflow.
    function openFixedPopover(btn, pop) {
        const r = btn.getBoundingClientRect();
        pop.style.display = '';
        const popWidth = pop.offsetWidth || 260;
        let left = r.left;
        if (left + popWidth > window.innerWidth - 8) left = Math.max(8, window.innerWidth - popWidth - 8);
        pop.style.top = (r.bottom + 4) + 'px';
        pop.style.left = left + 'px';
    }

    function updateDateToggleLabel() {
        elDateToggle.innerHTML = esc(state.presetLabel) + ' <i class="bx bx-chevron-down"></i>';
    }

    // Column show/hide -- same dropdown-of-checkboxes pattern as the Sales Report page's "Columns" button:
    // a menu built once from the (static) header row, a hidden-columns list in localStorage keyed by column
    // LABEL text (not index, so Date/Order no. always staying first doesn't matter, and it degrades
    // gracefully on the Dispatch tab's two extra columns that don't exist on the other tabs), and a
    // sr-col-hidden class toggled on the matching cell of every row. Shared across All/Pending/Dispatch
    // (one key) since "hide Remarks" is a preference about the table shape, not any one tab.
    (function setupColumnChooser() {
        const table = root.querySelector('.wop-table');
        const btn = root.querySelector('.wopanel-cols-btn'), menu = root.querySelector('.wopanel-cols-menu');
        if (!table || !btn || !menu) return;
        const headCells = [...table.tHead.rows[0].cells];
        const KEY = 'hrx.workOrders.hiddenCols.panel';
        let hidden = [];
        try { hidden = JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (e) { hidden = []; }
        const label = (c) => c.textContent.replace(/\s+/g, ' ').trim();

        const cols = table.querySelectorAll('colgroup col');
        const apply = () => {
            const off = new Set(headCells.map((c, i) => (i > 1 && hidden.includes(label(c))) ? i : -1).filter(i => i > 1));
            // Hide the <col> track itself, not individual td/th -- with table-layout:fixed, toggling
            // display:none on table cells (instead of their <col>) corrupts the whole table's column
            // widths (confirmed: every header overlapped the moment one column was hidden that way).
            // Hiding the <col> is what actually collapses that column's width cleanly.
            cols.forEach((c, i) => c.classList.toggle('sr-col-hidden', off.has(i)));
            [table.tHead.rows[0], ...table.tBodies[0].rows, ...(table.tFoot ? [table.tFoot.rows[0]] : [])].forEach((row) => {
                if (row.cells.length < headCells.length) return; // a loading/empty-state row spanning the whole table
                for (let i = 0; i < headCells.length; i++) { if (row.cells[i]) row.cells[i].classList.toggle('sr-col-hidden', off.has(i)); }
            });
            btn.innerHTML = '<i class="bx bx-columns me-1"></i>Columns' + (off.size ? ' (' + off.size + ' hidden)' : '');
        };
        const save = () => { try { localStorage.setItem(KEY, JSON.stringify(hidden)); } catch (e) {} };

        headCells.forEach((c, i) => {
            if (i < 2) return; // Date and Order no. always shown, like S.No/Party Name on the Sales Report page
            const name = label(c);
            if (!name) return;
            const row = document.createElement('div');
            row.className = 'form-check mb-1';
            const id = 'wopCol-' + PANEL_TAB + '-' + i;
            row.innerHTML = '<input class="form-check-input" type="checkbox" id="' + id + '">'
                + '<label class="form-check-label" for="' + id + '"></label>';
            row.querySelector('label').textContent = name;
            const box = row.querySelector('input');
            box.checked = !hidden.includes(name);
            box.addEventListener('change', () => {
                hidden = box.checked ? hidden.filter((h) => h !== name) : [...new Set([...hidden, name])];
                save(); apply();
            });
            menu.appendChild(row);
        });
        const showAll = document.createElement('button');
        showAll.type = 'button';
        showAll.className = 'btn btn-sm btn-link p-0 mt-1';
        showAll.textContent = 'Show all columns';
        showAll.addEventListener('click', () => {
            hidden = []; save();
            menu.querySelectorAll('input[type=checkbox]').forEach((b) => { b.checked = true; });
            apply();
        });
        menu.appendChild(showAll);

        root.__applyColumnVisibility = apply;
        apply();
    })();

    // Excel-style drag-to-resize columns -- same mechanism as the Sales Report page's
    // makeTableColumnsResizable(), adapted to resize the <colgroup> <col> (table-layout:fixed takes its
    // column widths from there, not from the th, once a colgroup is present) instead of the th itself.
    // Width persists per column index in localStorage, same "colw_<table>_<idx>" key pattern.
    (function makeColumnsResizable() {
        const table = root.querySelector('.wop-table');
        if (!table || !table.tHead) return;
        const prefix = 'colw_wopTable_';
        const cols = table.querySelectorAll('colgroup col');
        [...table.tHead.rows[0].cells].forEach((th, idx) => {
            const col = cols[idx];
            if (!col) return;
            const saved = localStorage.getItem(prefix + idx);
            if (saved) col.style.width = saved + 'px';
            if (th.querySelector('.col-resize-handle')) return;
            const handle = document.createElement('span');
            handle.className = 'col-resize-handle';
            th.appendChild(handle);
            handle.addEventListener('click', (e) => e.stopPropagation()); // never trigger the th's own sort click
            handle.addEventListener('mousedown', (e) => {
                e.preventDefault(); e.stopPropagation();
                const startX = e.pageX, startWidth = th.offsetWidth;
                document.body.style.userSelect = 'none';
                document.body.style.cursor = 'col-resize';
                const onMove = (e2) => { col.style.width = Math.max(40, startWidth + (e2.pageX - startX)) + 'px'; };
                const onUp = () => {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    document.body.style.userSelect = '';
                    document.body.style.cursor = '';
                    try { localStorage.setItem(prefix + idx, th.offsetWidth); } catch (e3) {}
                };
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    })();

    function setPreset(key, label) {
        const to = new Date(todayDate);
        let from = new Date(to);
        if (key === 'mtd') { from = new Date(to.getFullYear(), to.getMonth(), 1); }
        else if (key === 'lm') { from = new Date(to.getFullYear(), to.getMonth() - 1, 1); state.to = ymd(new Date(to.getFullYear(), to.getMonth(), 0)); }
        else if (key === 'last30') { from.setDate(from.getDate() - 29); state.to = todayIso; }
        else if (key === 'qtd') { from = new Date(to.getFullYear(), Math.floor(to.getMonth() / 3) * 3, 1); state.to = todayIso; }
        else if (key === 'fytd') { const fy = to.getMonth() >= 3 ? to.getFullYear() : to.getFullYear() - 1; from = new Date(fy, 3, 1); state.to = todayIso; }
        else if (key === 'all') { from = new Date(2020, 0, 1); state.to = todayIso; }
        if (key !== 'lm') state.to = todayIso;
        state.from = ymd(from);
        state.presetLabel = label;
        elFrom.value = state.from; elTo.value = state.to;
        updateDateToggleLabel();
        root.querySelectorAll('.wopanel-preset').forEach(b => { b.classList.toggle('btn-primary', b.dataset.preset === key); b.classList.toggle('btn-outline-secondary', b.dataset.preset !== key); });
        state.page = 1;
        elDatePop.style.display = 'none';
        load();
    }

    async function load() {
        state.page = state.page || 1;
        const mySeq = ++requestSeq;
        elLoading.style.display = '';
        const params = new URLSearchParams({ tab: PANEL_TAB, from: state.from, to: state.to, unit: state.unit, category: state.category, group: state.group, dispatch: state.dispatch, party: state.q, page: state.page, sort: state.sort, dir: state.dir });
        if (state.company) params.set('company', state.company);
        let data;
        try {
            const r = await fetch(window.__woPanelDataUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } });
            if (!r.ok) return;
            data = await r.json();
        } catch (e) { return; }
        if (mySeq !== requestSeq) return; // a newer request has since started -- this stale response is discarded
        elLoading.style.display = 'none';
        render(data);
    }

    function chart(el, opts) {
        if (!el || !window.ApexCharts) return null;
        const c = new ApexCharts(el, opts);
        c.render();
        return c;
    }

    // Hoisted out of render() -- toggleExpand() (item sub-rows) needs the same badge markup as the main
    // order rows so an expanded item lines up visually with its own order row.
    const dispatchBadge = (r) => {
        const notDispatched = r.delivered_qty === null;
        const fullyDispatched = !notDispatched && Number(r.pending_qty) <= 0.0001;
        return notDispatched
            ? '<span class="ss-flag">Not shipped</span>'
            : fullyDispatched
                ? '<span class="ss-flag ok">Shipped</span>'
                : '<span class="ss-flag warn">Pending ' + qty(r.pending_qty) + '</span>';
    };
    const approvalBadgeHtml = (r) => {
        const approval = r.financial_approval || 'Not set';
        const cls = approval === 'Ok' ? 'ok' : (approval === 'Make and Hold' ? 'bad' : 'warn');
        return '<span class="ss-flag ' + cls + '">' + esc(approval) + '</span>';
    };

    // One chart card toggles between Sales group and Stock group (data.by_group / data.by_category, same
    // shape) instead of a separate 5th card -- explicit request. Redraws from the already-fetched response,
    // no re-fetch on toggle; picking a bar filters the table via that mode's own select (group or category).
    function renderGroupChart(d) {
        if (!d) return;
        const isCategory = state.groupMode === 'category';
        const series = isCategory ? d.by_category : d.by_group;
        root.querySelector('.wopanel-group-toggle-label').textContent = isCategory ? 'stock group' : 'sales group';
        if (groupChart) groupChart.destroy();
        groupChart = chart(root.querySelector('.wopanel-bygroup'), {
            chart: { type: 'bar', height: 280, toolbar: { show: false }, events: { dataPointSelection: (e, c, cfg) => {
                const g = series[cfg.dataPointIndex];
                if (!g) return;
                const val = g.group === 'Not set' ? '' : g.group;
                if (isCategory) { state.category = val; elCategory.value = val; } else { state.group = val; elGroup.value = val; }
                state.page = 1; load();
            } } },
            series: [{ name: 'Value', data: series.map(g => Number(g.value)) }],
            xaxis: { categories: series.map(g => g.group), labels: { formatter: (v) => crore(v) } },
            // Bar-end data labels were printing the raw unformatted number (e.g. "37127294.93999999")
            // right over the bar, unreadable and overlapping the next row -- the tooltip already shows the
            // formatted value on hover, so the in-chart label is just switched off instead of reformatted.
            dataLabels: { enabled: false },
            plotOptions: { bar: { horizontal: true, borderRadius: 3 } },
            colors: ['#127464'], tooltip: { y: { formatter: (v) => inr(v) } },
        });
    }

    function render(d) {
        const s = d.stats;
        const lt = s.lead_time || {}, ltBill = lt.to_billing, ltDel = lt.to_delivery;
        const labels = (PANEL_TAB === 'dispatch'
            ? [['Value', crore(s.total_value), s.orders + ' orders, ' + s.lines + ' lines'], ['Billed', crore(s.billed_value), ''], ['Units involved', s.unit_count, '']]
            : [['Value', crore(s.total_value), s.orders + ' orders, ' + s.lines + ' lines'], ['Dispatched', crore(s.dispatched_value), ''], ['Pending', crore(s.pending_value), ''], ['Units involved', s.unit_count, '']]
        ).concat([
            ['Avg. order → billing', ltBill && ltBill.avg_days != null ? ltBill.avg_days + ' days' : '—', ltBill ? ltBill.n + ' orders, this filter' : ''],
            ['Avg. order → delivery', ltDel && ltDel.avg_days != null ? ltDel.avg_days + ' days' : '—', ltDel ? ltDel.n + ' orders, this filter' : ''],
        ]);
        root.querySelector('.wopanel-stats').innerHTML = labels.map(([lbl, val, sub]) =>
            '<div class="col-6 col-lg-2"><div class="ss-stat"><div class="lbl">' + esc(lbl) + '</div><div class="val">' + val + '</div>' + (sub ? '<div class="ss-sub">' + esc(sub) + '</div>' : '') + '</div></div>'
        ).join('');

        if (trendChart) trendChart.destroy();
        // Short DD/MM axis labels, not every single one shown (tickAmount lets ApexCharts pick an evenly-
        // spaced subset instead of cramming ~30 daily labels into a now-quarter-width card), and no
        // always-on bar labels (same overlap problem the sales-group chart had -- the value shows on
        // hover via the tooltip instead, which already had the full formatted amount).
        const fdateShort = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? m[3] + '/' + m[2] : ''; };
        // Click a day's bar to filter the table down to just that one day -- explicit request ("Order
        // value by day" was the one chart on this card that wasn't click-to-filter like the others).
        trendChart = chart(root.querySelector('.wopanel-trend'), {
            chart: { type: 'bar', height: 300, toolbar: { show: false }, events: { dataPointSelection: (e, c, cfg) => {
                const t = d.trend[cfg.dataPointIndex];
                if (!t) return;
                state.from = t.d; state.to = t.d;
                elFrom.value = state.from; elTo.value = state.to;
                state.presetLabel = fdate(state.from);
                updateDateToggleLabel();
                root.querySelectorAll('.wopanel-preset').forEach((b) => b.classList.remove('btn-primary'));
                root.querySelectorAll('.wopanel-preset').forEach((b) => b.classList.add('btn-outline-secondary'));
                state.page = 1; load();
            } } },
            series: [{ name: 'Order value', data: d.trend.map(t => Number(t.value)) }],
            dataLabels: { enabled: false },
            xaxis: { categories: d.trend.map(t => fdateShort(t.d)), tickAmount: Math.min(10, d.trend.length - 1 || 1), labels: { rotate: -45 } },
            yaxis: { labels: { formatter: (v) => crore(v) } },
            colors: ['#127464'], plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
            tooltip: { y: { formatter: (v) => inr(v) } },
        });

        if (unitChart) unitChart.destroy();
        unitChart = chart(root.querySelector('.wopanel-byunit'), {
            chart: { type: 'donut', height: 340, events: { dataPointSelection: (e, c, cfg) => { const u = d.by_unit[cfg.dataPointIndex]; if (u) { state.unit = u.unit; elUnit.value = u.unit; state.page = 1; load(); } } } },
            series: d.by_unit.map(u => Number(u.value)), labels: d.by_unit.map(u => d.unit_labels[u.unit] || u.unit),
            legend: { position: 'bottom', fontSize: '11px' }, tooltip: { y: { formatter: (v) => inr(v) } },
        });

        if (dispatchChart) dispatchChart.destroy();
        // Click a slice to filter the table to that dispatch status -- same 3 buckets as the new
        // wopanel-dispatch <select>, kept in sync both ways.
        const dispatchStatusToCode = { 'Not dispatched': 'not', 'Partially dispatched': 'partial', 'Fully dispatched': 'full' };
        dispatchChart = chart(root.querySelector('.wopanel-bydispatch'), {
            chart: { type: 'donut', height: 340, events: { dataPointSelection: (e, c, cfg) => {
                const x = d.by_dispatch[cfg.dataPointIndex];
                if (!x) return;
                state.dispatch = dispatchStatusToCode[x.status] || '';
                elDispatch.value = state.dispatch;
                state.page = 1; load();
            } } },
            series: d.by_dispatch.map(x => Number(x.value)), labels: d.by_dispatch.map(x => x.status),
            colors: ['#15803d', '#f59e0b', '#94a3b8'],
            legend: { position: 'bottom', fontSize: '11px' }, tooltip: { y: { formatter: (v) => inr(v) } },
        });

        lastRenderData = d;
        renderGroupChart(d);

        // The <select> options come from the server (every real customer-ledger group, not just the ones
        // in this filtered page) and are filled once -- re-filling on every response would reset whatever
        // the user just picked mid-click.
        if (!groupOptionsFilled && d.sales_groups) {
            groupOptionsFilled = true;
            elGroup.insertAdjacentHTML('beforeend', d.sales_groups.map(g => '<option value="' + esc(g) + '">' + esc(g) + '</option>').join(''));
        }

        root.querySelector('.wopanel-byparty-body').innerHTML = d.by_party.length ? d.by_party.map((p) => (
            '<tr class="wopanel-byparty-row" data-party="' + esc(p.party) + '">'
            + '<td class="wop-ellip" title="' + esc(p.party) + '">' + esc(p.party) + '</td>'
            + '<td class="ss-num">' + p.orders + '</td><td class="ss-num">' + p.lines + '</td>'
            + '<td class="ss-num fw-semibold">' + inr(p.value) + '</td></tr>'
        )).join('') : '<tr><td colspan="4" class="text-center text-muted py-3">No pending lines in this filter.</td></tr>';

        root.querySelectorAll('.wopanel-sortable').forEach(th => {
            th.classList.toggle('active', th.dataset.sort === state.sort);
            let arrow = th.querySelector('.sort-arrow');
            if (!arrow) { arrow = document.createElement('i'); arrow.className = 'bx sort-arrow'; th.appendChild(arrow); }
            arrow.className = 'bx sort-arrow ' + (th.dataset.sort === state.sort ? (state.dir === 'asc' ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt') : 'bx-sort-alt-2');
        });

        const t = d.table;
        const emptyColspan = PANEL_TAB === 'pending' ? 13 : (PANEL_TAB === 'dispatch' ? 14 : 12);
        root.querySelector('.wopanel-table-body').innerHTML = t.rows.length ? t.rows.map((r) => {
            if (PANEL_TAB === 'pending') {
                // Whole-ORDER rollup columns were dropped from this row (see the thead comment) -- just this
                // line's own pending_qty is shown now, no order-level number sitting next to it to confuse.
                return '<tr class="wop-row" data-wo-order="' + esc(r.order_no) + '">'
                    + '<td class="text-nowrap">' + fdate(r.order_date) + '</td><td class="fw-semibold text-nowrap">' + esc(r.order_no) + '</td>'
                    + '<td class="wop-ellip" title="' + esc(r.item_name || '') + '">' + esc(r.item_name || '—') + '</td>'
                    + '<td class="wop-ellip" title="' + esc(r.party_name) + '">' + esc(r.party_name) + '</td>'
                    + '<td class="wop-ellip" title="' + esc(r.sales_group || '') + '">' + esc(r.sales_group || '—') + '</td>'
                    + '<td>' + esc(d.unit_labels[r.unit] || r.unit) + '</td>'
                    + '<td class="wop-ellip" title="' + esc(r.stock_group || '') + '">' + esc(r.stock_group || '—') + '</td>'
                    + '<td class="ss-num">' + qty(r.quantity) + '</td>'
                    + '<td class="ss-num fw-semibold">' + inr(r.amount) + '</td>'
                    + '<td class="ss-num">' + qty(r.pending_qty) + '</td>'
                    + '<td>' + dispatchBadge(r) + '</td>'
                    + '<td><span class="ss-flag ' + (r.financial_approval === 'Ok' ? 'ok' : (r.financial_approval === 'Make and Hold' ? 'bad' : 'warn')) + '">' + esc(r.financial_approval || 'Not set') + '</span></td>'
                    + '<td class="wop-ellip small" title="' + esc(r.remarks || '') + '">' + esc(r.remarks || '') + '</td></tr>';
            }
            // All / Dispatch: ONE row per order (r.quantity/r.amount/r.pending_qty are already the order's
            // own totals, summed server-side in panelData() -- not a line). The expand arrow reveals this
            // order's own item lines on demand instead of the table repeating the order once per item.
            const dispatchExtraCols = PANEL_TAB === 'dispatch'
                ? '<td class="wop-ellip" title="' + esc(r.transporter_name || '') + '">' + esc(r.transporter_name || '—') + '</td>'
                    + '<td class="wop-ellip" title="' + esc([r.docket_no, r.vehicle_number].filter(Boolean).join(' / ')) + '">' + esc([r.docket_no, r.vehicle_number].filter(Boolean).join(' / ') || '—') + '</td>'
                : '';
            const multi = (r.line_count || 1) > 1;
            return '<tr class="wop-row wop-order-row" data-wo-order="' + esc(r.order_no) + '" data-wo-company="' + esc(r.company) + '">'
                + '<td class="text-center">' + (multi ? '<button type="button" class="wop-expand-btn" aria-label="Expand"><i class="bx bx-chevron-right"></i></button>' : '') + '</td>'
                + '<td class="text-nowrap">' + fdate(r.order_date) + '</td><td class="fw-semibold text-nowrap">' + esc(r.order_no) + (multi ? ' <span class="text-muted small">(' + r.line_count + ' items)</span>' : '') + '</td>'
                + '<td class="wop-ellip" title="' + esc(r.party_name) + '">' + esc(r.party_name) + '</td>'
                + '<td class="wop-ellip" title="' + esc(r.sales_group || '') + '">' + esc(r.sales_group || '—') + '</td>'
                + '<td>' + esc(d.unit_labels[r.unit] || r.unit) + '</td>'
                + '<td class="wop-ellip" title="' + esc(r.stock_group || '') + '">' + esc(r.stock_group || '—') + '</td>'
                + '<td class="ss-num">' + qty(r.quantity) + '</td>'
                + '<td class="ss-num fw-semibold">' + inr(r.amount) + '</td>'
                + '<td>' + dispatchBadge(r) + '</td>'
                + dispatchExtraCols
                + '<td>' + approvalBadgeHtml(r) + '</td>'
                + '<td class="wop-ellip small" title="' + esc(r.remarks || '') + '">' + esc(r.remarks || '') + '</td></tr>';
        }).join('') : '<tr><td colspan="' + emptyColspan + '" class="text-center text-muted py-5">No work orders match these filters.</td></tr>';
        if (root.__applyColumnVisibility) root.__applyColumnVisibility(); // tbody was just replaced wholesale -- re-apply hidden columns

        const from = (t.page - 1) * t.per + 1, to = Math.min(t.page * t.per, t.total);
        root.querySelector('.wopanel-table-summary').textContent = t.total ? ('Showing ' + from + '–' + to + ' of ' + t.total.toLocaleString('en-IN') + ' lines') : '';
        const pages = Math.ceil(t.total / t.per);
        let pager = '';
        if (pages > 1) {
            pager = '<div class="btn-group btn-group-sm">'
                + '<button class="btn btn-outline-secondary" ' + (t.page <= 1 ? 'disabled' : '') + ' data-wop-page="' + (t.page - 1) + '">Prev</button>'
                + '<button class="btn btn-outline-secondary" disabled>' + t.page + ' / ' + pages + '</button>'
                + '<button class="btn btn-outline-secondary" ' + (t.page >= pages ? 'disabled' : '') + ' data-wop-page="' + (t.page + 1) + '">Next</button>'
                + '</div>';
        }
        root.querySelector('.wopanel-pager').innerHTML = pager;
    }

    // Expand/collapse an order-level row (All/Dispatch tabs only) into its own item lines, fetched on
    // demand from the same order-detail endpoint the modal uses (window.__woShowUrlBase, set by
    // work-order-modal.blade.php, included on every Work Orders tab) -- no separate table shape to build
    // and maintain just for this inline detail.
    async function toggleExpand(btn) {
        const row = btn.closest('tr');
        const orderNo = row.dataset.woOrder;
        const collapse = () => {
            let next = row.nextElementSibling;
            while (next && next.classList.contains('wop-subrow') && next.dataset.woOrder === orderNo) {
                const after = next.nextElementSibling;
                next.remove();
                next = after;
            }
        };
        if (row.classList.contains('wop-expanded')) {
            row.classList.remove('wop-expanded');
            btn.querySelector('i').className = 'bx bx-chevron-right';
            collapse();
            return;
        }
        row.classList.add('wop-expanded');
        btn.querySelector('i').className = 'bx bx-chevron-down';
        const colCount = row.cells.length;
        const loadingRow = document.createElement('tr');
        loadingRow.className = 'wop-subrow';
        loadingRow.dataset.woOrder = orderNo;
        loadingRow.innerHTML = '<td colspan="' + colCount + '" class="text-muted small py-2 ps-4">Loading items…</td>';
        row.after(loadingRow);

        // Item rows reuse EVERY column the parent order row has -- same shape as the item-wise Pending
        // tab, just read straight off the parent row's own cells for whatever is order-level (Date, Party,
        // Sales group, Approval, Remarks, Transporter/Docket), so they stay correct under the Columns
        // show/hide toggle and column resize without any extra wiring.
        const cells = row.cells;
        const dateHtml = cells[1].innerHTML;
        const partyHtml = cells[3].innerHTML;
        const salesGroupHtml = cells[4].innerHTML;
        // "Unit" in this table means the branch/location (Ramgarh, Patalganga, ...), NOT the item's own
        // measurement unit (Nos/Kg/...) -- same value for every item on the order, so inherited from the
        // parent row exactly like Party/Sales group, not read off the item line itself.
        const unitHtml = cells[5].innerHTML;
        const approvalHtml = cells[cells.length - 2].innerHTML;
        const remarksHtml = cells[cells.length - 1].innerHTML;
        const transporterHtml = PANEL_TAB === 'dispatch' ? cells[10].innerHTML : '';
        const docketHtml = PANEL_TAB === 'dispatch' ? cells[11].innerHTML : '';

        try {
            const r = await fetch(window.__woShowUrlBase + '/' + encodeURIComponent(orderNo), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) throw new Error('fetch failed');
            const data = await r.json();
            const deliveredByItem = {};
            (data.deliveries || []).forEach((note) => (note.items || []).forEach((it) => {
                deliveredByItem[it.item] = (deliveredByItem[it.item] || 0) + Number(it.qty || 0);
            }));
            const lines = data.lines || [];
            const rowsHtml = lines.length ? lines.map((l) => {
                const delivered = Object.prototype.hasOwnProperty.call(deliveredByItem, l.item_name) ? deliveredByItem[l.item_name] : null;
                const itemRow = { delivered_qty: delivered, pending_qty: delivered === null ? Number(l.quantity) : Math.max(0, Number(l.quantity) - delivered) };
                return '<tr class="wop-row wop-subrow" data-wo-order="' + esc(orderNo) + '">'
                    + '<td></td>'
                    + '<td class="text-nowrap">' + dateHtml + '</td>'
                    + '<td class="wop-ellip ps-4"><span class="fw-semibold text-nowrap">' + esc(orderNo) + '</span><br>'
                        + '<span class="small text-muted" title="' + esc(l.item_name || '') + '">' + esc(l.item_name || '—') + '</span></td>'
                    + '<td class="wop-ellip">' + partyHtml + '</td>'
                    + '<td class="wop-ellip">' + salesGroupHtml + '</td>'
                    + '<td>' + unitHtml + '</td>'
                    + '<td class="wop-ellip" title="' + esc(l.stock_group || '') + '">' + esc(l.stock_group || '—') + '</td>'
                    + '<td class="ss-num">' + qty(l.quantity) + '</td>'
                    + '<td class="ss-num fw-semibold">' + inr(l.amount) + '</td>'
                    + '<td>' + dispatchBadge(itemRow) + '</td>'
                    + (PANEL_TAB === 'dispatch' ? ('<td class="wop-ellip">' + transporterHtml + '</td><td class="wop-ellip">' + docketHtml + '</td>') : '')
                    + '<td>' + approvalHtml + '</td>'
                    + '<td class="wop-ellip small">' + remarksHtml + '</td></tr>';
            }).join('') : ('<tr class="wop-subrow" data-wo-order="' + esc(orderNo) + '"><td colspan="' + colCount + '" class="text-muted small py-2 ps-4">No item lines found.</td></tr>');
            loadingRow.insertAdjacentHTML('afterend', rowsHtml);
            loadingRow.remove();
            if (root.__applyColumnVisibility) root.__applyColumnVisibility();
        } catch (e) {
            loadingRow.innerHTML = '<td colspan="' + colCount + '" class="text-danger small py-2 ps-4">Failed to load items.</td>';
        }
    }

    root.addEventListener('click', (e) => {
        const expandBtn = e.target.closest('.wop-expand-btn');
        if (expandBtn) { e.preventDefault(); e.stopPropagation(); toggleExpand(expandBtn); return; }
        const groupModeBtn = e.target.closest('[data-group-mode]');
        if (groupModeBtn) {
            state.groupMode = groupModeBtn.dataset.groupMode;
            root.querySelectorAll('.wopanel-group-toggle [data-group-mode]').forEach((b) => b.classList.toggle('active', b === groupModeBtn));
            renderGroupChart(lastRenderData);
            return;
        }
        const p = e.target.closest('.wopanel-preset'); if (p) { setPreset(p.dataset.preset, p.textContent.trim()); return; }
        const pg = e.target.closest('[data-wop-page]'); if (pg) { state.page = Number(pg.dataset.wopPage); load(); return; }
        const sortTh = e.target.closest('.wopanel-sortable');
        if (sortTh) {
            const key = sortTh.dataset.sort;
            state.dir = (state.sort === key && state.dir === 'desc') ? 'asc' : 'desc';
            state.sort = key; state.page = 1; load(); return;
        }
        const partyRow = e.target.closest('.wopanel-byparty-row');
        if (partyRow) { state.q = partyRow.dataset.party; elSearch.value = state.q; state.page = 1; load(); return; }
        const dt = e.target.closest('.wopanel-date-toggle');
        if (dt) {
            const willOpen = elDatePop.style.display === 'none';
            elColsMenu.style.display = 'none';
            if (willOpen) openFixedPopover(elDateToggle, elDatePop); else elDatePop.style.display = 'none';
            return;
        }
        const colsBtn = e.target.closest('.wopanel-cols-btn');
        if (colsBtn) {
            const willOpen = elColsMenu.style.display === 'none';
            elDatePop.style.display = 'none';
            if (willOpen) openFixedPopover(elColsBtn, elColsMenu); else elColsMenu.style.display = 'none';
            return;
        }
        if (!e.target.closest('.wopanel-date-pop')) elDatePop.style.display = 'none';
        if (!e.target.closest('.wopanel-cols-menu')) elColsMenu.style.display = 'none';
        const reset = e.target.closest('.wopanel-reset');
        if (reset) {
            state.q = ''; state.unit = ''; state.category = ''; state.group = ''; state.dispatch = ''; state.page = 1; state.sort = 'order_date'; state.dir = 'desc';
            elSearch.value = ''; elUnit.value = ''; elCategory.value = ''; elGroup.value = ''; elDispatch.value = '';
            setPreset('last30', 'Last 30 days');
        }
    });

    let searchDebounce = null;
    elSearch.addEventListener('input', (e) => {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => { state.q = e.target.value; state.page = 1; load(); }, 350);
    });
    elCompany.addEventListener('change', (e) => { state.company = e.target.value; state.page = 1; load(); });
    elGroup.addEventListener('change', (e) => { state.group = e.target.value; state.page = 1; load(); });
    elFrom.addEventListener('change', (e) => { state.from = e.target.value; state.presetLabel = fdate(state.from) + ' – ' + fdate(state.to); updateDateToggleLabel(); state.page = 1; load(); });
    elTo.addEventListener('change', (e) => { state.to = e.target.value; state.presetLabel = fdate(state.from) + ' – ' + fdate(state.to); updateDateToggleLabel(); state.page = 1; load(); });
    elUnit.addEventListener('change', (e) => { state.unit = e.target.value; state.page = 1; load(); });
    elCategory.addEventListener('change', (e) => { state.category = e.target.value; state.page = 1; load(); });
    elDispatch.addEventListener('change', (e) => { state.dispatch = e.target.value; state.page = 1; load(); });

    (function waitForApex(triesLeft) {
        if (window.ApexCharts || triesLeft <= 0) { setPreset('last30', 'Last 30 days'); } else { setTimeout(() => waitForApex(triesLeft - 1), 50); }
    })(100);
})();
</script>
