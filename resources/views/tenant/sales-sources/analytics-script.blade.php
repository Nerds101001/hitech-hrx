<script>
@verbatim
(function () {
'use strict';

const CFG = window.ANCFG;
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// ------------------------------------------------------------------ formatting (Indian grouping, lakh / crore)
const IN0 = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 });
const IN2 = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const IN3 = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 3 });
const BLANK = '<span class="an-muted">—</span>';
const inr = (n, dp = 0) => n == null ? '—' : (dp ? IN2 : IN0).format(n);
const rs = n => n == null ? '—' : '₹' + IN0.format(n);
const crore = n => {
    if (n == null) return '—';
    const a = Math.abs(n);
    if (a >= 1e7) return '₹' + (n / 1e7).toFixed(2) + ' Cr';
    if (a >= 1e5) return '₹' + (n / 1e5).toFixed(2) + ' L';
    return '₹' + IN0.format(n);
};
const short = n => {
    const a = Math.abs(n);
    if (a >= 1e7) return (n / 1e7).toFixed(1).replace(/\.0$/, '') + 'Cr';
    if (a >= 1e5) return (n / 1e5).toFixed(1).replace(/\.0$/, '') + 'L';
    if (a >= 1e3) return (n / 1e3).toFixed(0) + 'K';
    return String(Math.round(n));
};
const num = n => n == null ? '—' : IN0.format(n);
const trunc = (s, n = 24) => { s = String(s ?? ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; };
const dt = (d, year = false) => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-GB', year ? { day: 'numeric', month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short' }) : '';
const span = (a, b) => dt(a) === dt(b) ? dt(a, true) : dt(a) + ' – ' + dt(b, true);

// ------------------------------------------------------------------ state
const DEFAULTS = { type: 'customer', sp: '', series: '' };
const S = {
    f: Object.assign({}, CFG.filters),
    tab: (location.hash || '').slice(1),
    ov: null, ovSeq: 0, grain: null, grainPinned: false,
    charts: {}, seq: {}, itemScope: 'period',
    ts: {
        bills: { page: 1, per: 50, sort: 'date', dir: 'desc', q: '' },
        items: { page: 1, per: 50, sort: '', dir: 'desc', q: '', basis: 'value', group: 'item', family: '' },
        customers: { page: 1, per: 50, sort: 'sales', dir: 'desc', q: '' },
        salespersons: { page: 1, per: 50, sort: 'sales', dir: 'desc', q: '' },
        gst: { page: 1, per: 50, sort: 'taxable', dir: 'desc', q: '' },
        price: { page: 1, per: 50, sort: '', dir: 'desc', q: '', vary: '1' },
    },
};
if (!S.ts[S.tab]) S.tab = 'bills';

const TAB_NOTE = {
    bills: 'Search invoice, customer, code, PO or LR no.',
    items: 'Search item, HSN or family',
    customers: 'Search customer, code or salesperson',
    salespersons: 'Search salesperson',
    gst: 'Search HSN or family',
    price: 'Search item or family',
};

// ------------------------------------------------------------------ data layer: one cached promise per URL
const cache = new Map();
const fparams = () => ({ preset: S.f.preset, from: S.f.from, to: S.f.to, type: S.f.type, sp: S.f.sp, series: S.f.series });
function query(obj) {
    const q = new URLSearchParams();
    Object.entries(obj).forEach(([k, v]) => { if (v !== '' && v != null) q.append(k, v); });
    return q.toString();
}
// `over` swaps some filters (e.g. another quick range) so it can be fetched ahead of time; the URL it produces is the one a later click would build.
const urlFor = (url, params, over) => url + '?' + query(Object.assign({}, fparams(), over || {}, params, { v: CFG.importId }));
function api(url, params, over) {
    const key = urlFor(url, params, over);
    if (!cache.has(key)) {
        if (cache.size > 300) cache.clear();
        const p = fetch(key, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(r => {
            if (r.redirected || !(r.headers.get('content-type') || '').includes('json')) throw new Error('session');
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
        p.catch(() => cache.delete(key));
        cache.set(key, p);
    }
    return cache.get(key);
}
function fail(e, where) {
    console.error(where, e);
    const msg = e && e.message === 'session'
        ? 'Your session has expired. <a href="' + location.pathname + '">Reload the page</a> to sign in again.'
        : 'Could not load ' + esc(where) + ' (' + esc(e && e.message ? e.message : 'error') + '). Try again in a moment.';
    $('#anAlert').innerHTML = '<div class="an-notice bad"><i class="bx bx-error-circle"></i><div>' + msg + '</div></div>';
}

// ------------------------------------------------------------------ chart helper
function merge(a, b) {
    const out = Object.assign({}, a);
    Object.keys(b).forEach(k => {
        out[k] = (b[k] && typeof b[k] === 'object' && !Array.isArray(b[k]) && a[k] && typeof a[k] === 'object' && !Array.isArray(a[k])) ? merge(a[k], b[k]) : b[k];
    });
    return out;
}
function chart(id, opts) {
    const el = document.getElementById(id);
    if (!el) return;
    if (S.charts[id]) { try { S.charts[id].destroy(); } catch (e) { /* already gone */ } delete S.charts[id]; }
    el.innerHTML = '';
    if (!window.ApexCharts) { el.innerHTML = '<div class="text-muted small p-3">The chart library did not load.</div>'; return; }
    const base = {
        chart: { fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: false }, zoom: { enabled: false } },
        noData: { text: 'Nothing to show for these filters', style: { color: '#94a3b8', fontSize: '13px' } },
        grid: { borderColor: '#eef2f6' }, dataLabels: { enabled: false },
    };
    try {
        const c = new ApexCharts(el, merge(base, opts));
        c.render();
        S.charts[id] = c;
    } catch (e) { console.error('chart ' + id, e); el.innerHTML = '<div class="text-danger small p-3">This chart could not be drawn.</div>'; }
}
const PAL = ['#127464', '#2563eb', '#f59e0b', '#7c3aed', '#db2777', '#0891b2', '#65a30d', '#ea580c', '#475569', '#be123c', '#0d9488', '#9333ea'];

// Tally's export-order ledgers are called "TO THE ORDER OF:-290(ITLOG)"; six of those in a row all truncate to the same words.
const niceName = s => String(s ?? '').replace(/^TO THE ORDER OF[:\-\s]*/i, 'To order: ');

function hbar(id, cats, vals, o = {}) {
    const label = o.labelFn || (s => s);
    const shown = cats.map(label);
    chart(id, {
        chart: { type: 'bar', height: Math.max(240, cats.length * 32 + 50), events: { dataPointSelection: (e, c, cfg) => o.onClick && o.onClick(cfg.dataPointIndex) } },
        plotOptions: { bar: { horizontal: true, barHeight: '62%', borderRadius: 3, distributed: !!o.colors, dataLabels: { position: 'top' } } },
        series: [{ name: o.name || 'Sales', data: vals }],
        xaxis: { categories: shown, labels: { formatter: v => short(v) }, max: Math.max.apply(null, vals.concat([1])) * 1.22 },   // headroom so the value label at the end of the longest bar isn't clipped
        yaxis: { labels: { maxWidth: o.labelWidth || 170, style: { fontSize: '10px' }, formatter: v => trunc(v, o.labelChars || 28) } },
        dataLabels: { enabled: true, formatter: v => short(v), offsetX: 28, style: { fontSize: '10px', fontWeight: 600, colors: ['#334155'] } },
        colors: o.colors || [o.color || '#127464'], legend: { show: false },
        tooltip: { y: { formatter: v => rs(v) } },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    });
}

// ------------------------------------------------------------------ filters
function syncFilterUi() {
    $('#anFrom').value = S.f.from || '';
    $('#anTo').value = S.f.to || '';
    $('#anType').value = S.f.type;
    $('#anSp').value = S.f.sp;
    $('#anSeries').value = S.f.series;
    $$('#anPresets .btn').forEach(b => b.classList.toggle('active', b.dataset.preset === S.f.preset));
}
function writeUrl() {
    const q = query({ preset: S.f.preset, from: S.f.preset === 'custom' ? S.f.from : '', to: S.f.preset === 'custom' ? S.f.to : '', type: S.f.type === 'customer' ? '' : S.f.type, sp: S.f.sp, series: S.f.series });
    try { history.replaceState(null, '', location.pathname + (q ? '?' + q : '') + '#' + S.tab); } catch (e) { /* not essential */ }
}
function applyFilters() {
    Object.values(S.ts).forEach(t => { t.page = 1; });
    syncFilterUi();
    writeUrl();
    loadOverview();
    loadTab(S.tab);
}
function setFilter(k, v) {
    S.f[k] = S.f[k] === v ? '' : v;           // clicking the same slice/bar again clears the filter
    applyFilters();
}
function bindFilters() {
    $('#anPresets').addEventListener('click', e => {
        const b = e.target.closest('[data-preset]');
        if (!b) return;
        S.f.preset = b.dataset.preset;
        if (b.dataset.from) { S.f.from = b.dataset.from; S.f.to = b.dataset.to; }
        applyFilters();
    });
    ['anFrom', 'anTo'].forEach(id => $('#' + id).addEventListener('change', () => {
        if (!$('#anFrom').value || !$('#anTo').value) return;
        S.f.from = $('#anFrom').value; S.f.to = $('#anTo').value; S.f.preset = 'custom';
        applyFilters();
    }));
    $('#anType').addEventListener('change', e => { S.f.type = e.target.value; applyFilters(); });
    $('#anSp').addEventListener('change', e => { S.f.sp = e.target.value; applyFilters(); });
    $('#anSeries').addEventListener('change', e => { S.f.series = e.target.value; applyFilters(); });
    $('#anReset').addEventListener('click', () => {
        const mtd = CFG.presets.find(p => p.key === 'mtd') || CFG.presets[0];
        Object.assign(S.f, DEFAULTS, { preset: mtd ? mtd.key : 'custom', from: mtd ? mtd.from : S.f.from, to: mtd ? mtd.to : S.f.to });
        S.grainPinned = false;
        applyFilters();
    });
    $$('#anGrain [data-grain]').forEach(b => b.addEventListener('click', () => { S.grain = b.dataset.grain; S.grainPinned = true; if (S.ov) renderTrend(S.ov); }));
}

// ------------------------------------------------------------------ overview
async function loadOverview() {
    const my = ++S.ovSeq, t0 = performance.now();
    const ready = cache.has(urlFor(CFG.routes.data, { panel: 'overview' }));
    if (!ready) { $('#anMeta').textContent = 'Loading…'; $('#anRoot').classList.add('an-busy'); }
    try {
        const d = await api(CFG.routes.data, { panel: 'overview' });
        if (my !== S.ovSeq) return;
        $('#anAlert').innerHTML = '';
        renderOverview(d);
        $('#anMeta').textContent = S.embedded ? 'Loaded with the page' : (ready ? 'Instant — already loaded' : 'Loaded in ' + Math.round(performance.now() - t0) + ' ms');
        S.embedded = false;
        idle(prefetch);
    } catch (e) { if (my === S.ovSeq) { fail(e, 'the overview'); $('#anMeta').textContent = ''; } }
    finally { if (my === S.ovSeq) $('#anRoot').classList.remove('an-busy'); }
}
function idle(fn) { (window.requestIdleCallback || (f => setTimeout(f, 250)))(fn, { timeout: 1500 }); }
const sleep = ms => new Promise(r => setTimeout(r, ms));
async function prefetch() {
    // Quietly load what a click is likely to ask for next, one request at a time, so the click is instant:
    // every other table, then the other quick date ranges (their overview, and the table that is open).
    const my = S.ovSeq;
    const jobs = Object.keys(S.ts).filter(t => t !== S.tab).map(t => () => api(CFG.routes.data, dsParams(t)));
    CFG.presets.filter(p => p.key !== S.f.preset).forEach(p => {
        const over = { preset: p.key, from: p.from, to: p.to };
        jobs.push(() => api(CFG.routes.data, { panel: 'overview' }, over), () => api(CFG.routes.data, dsParams(S.tab), over));
    });
    for (const job of jobs) {
        if (my !== S.ovSeq) return;              // the filters changed: this list is out of date
        try { await job(); } catch (e) { /* prefetch is best-effort */ }
        await sleep(120);
    }
}
function renderOverview(d) {
    S.ov = d;
    if (d.period.from !== S.f.from || d.period.to !== S.f.to) { S.f.from = d.period.from; S.f.to = d.period.to; syncFilterUi(); }
    const prev = d.period.prev;
    $('#anPeriodLine').innerHTML = 'Showing <strong>' + esc(span(d.period.from, d.period.to)) + '</strong> (' + d.period.days + ' day' + (d.period.days === 1 ? '' : 's') + ')'
        + (prev ? ' &middot; compared with <strong>' + esc(span(prev[0], prev[1])) + '</strong>' : ' &middot; no equal earlier period in the loaded data to compare with');
    const run = fn => { try { fn(d); } catch (e) { console.error(fn.name, e); } };
    // What is at the top of the page is drawn now; the charts further down follow one per tick, so the first update is on screen at once.
    [renderKpis, renderNotices, renderTrend, renderSeries].forEach(run);
    const later = [renderCustomers, renderSalespersons, renderFamilies, renderGst, renderSizes, renderAgeing, renderPlants, renderCities], my = S.ovSeq;
    const next = () => { if (my !== S.ovSeq || !later.length) return; run(later.shift()); setTimeout(next, 0); };
    setTimeout(next, 0);
}

function chip(delta, goodUp) {
    if (delta == null) return '';
    if (delta === 0) return '<span class="an-chip flat">● 0%</span>';
    const up = delta > 0;
    const cls = goodUp === null ? 'flat' : (up === goodUp ? 'up' : 'down');
    return '<span class="an-chip ' + cls + '">' + (up ? '▲' : '▼') + ' ' + Math.abs(delta).toFixed(1) + '%</span>';
}
function renderKpis(d) {
    const k = d.kpis, c = d.coverage, prev = d.period.prev;
    const vs = (v, f) => v == null ? '' : '<span>vs ' + f(v) + '</span>';
    const cov = '<span class="an-chip warn" title="Item and GST detail exists for ' + c.covered_invoices + ' of ' + c.invoices + ' bills">' + (c.value_pct ?? 0) + '% of value</span>';
    const gap = silentInPeriod(d).length ? ' <i class="bx bx-error-circle text-warning" title="Not like-for-like: some invoice series are missing from this period (see the note above)"></i>' : '';
    const card = (label, val, foot, accent, title) => '<div class="col-6 col-lg-3"><div class="an-kpi" style="--an-accent:' + accent + '"><div class="lbl">' + label + '</div><div class="val" title="' + esc(title || '') + '">' + val + '</div><div class="foot">' + foot + '</div></div></div>';
    const openPct = k.sales.value ? (k.open.value / k.sales.value * 100).toFixed(0) : 0;
    $('#anKpis').innerHTML =
        card('Sales incl. GST', crore(k.sales.value), chip(k.sales.delta, true) + vs(k.sales.prev, crore), '#127464', rs(k.sales.value))
        + card('Bills' + gap, num(k.invoices.value), chip(k.invoices.delta, true) + vs(k.invoices.prev, num), '#2563eb')
        + card('Customers billed', num(k.customers.value), chip(k.customers.delta, true) + vs(k.customers.prev, num), '#7c3aed')
        + card('Average bill' + gap, crore(k.avg.value), chip(k.avg.delta, true) + vs(k.avg.prev, crore), '#0891b2', rs(k.avg.value))
        + card('Taxable value', c.covered_invoices ? crore(k.taxable.value) : '—', c.covered_invoices ? chip(k.taxable.delta, true) + cov : '<span>no item detail in this period</span>', '#f59e0b', rs(k.taxable.value))
        + card('GST collected', c.covered_invoices ? crore(k.tax.value) : '—', c.covered_invoices ? chip(k.tax.delta, true) + '<span>' + (c.effective_gst ?? '—') + '% effective</span>' : '', '#db2777', rs(k.tax.value))
        + card('Still open on these bills', crore(k.open.value), '<span>' + num(k.open.bills) + ' bills &middot; ' + openPct + '% of sales</span>' + chip(k.open.delta, null), '#ea580c', rs(k.open.value))
        + card('Top-10 customers', k.top10.value == null ? '—' : k.top10.value + '%', '<span>of the period&rsquo;s sales</span>', '#475569');
}
function silentInPeriod(d) { return (d.notices.silent_series || []).filter(s => d.period.to > s.last); }
function renderNotices(d) {
    let h = '';
    const box = (cls, icon, html) => '<div class="an-notice ' + cls + '"><i class="bx ' + icon + '"></i><div>' + html + '</div></div>';
    const silent = silentInPeriod(d);
    if (silent.length) {
        const list = silent.map(s => '<strong>' + esc(s.series) + '</strong> (' + num(s.invoices) + ' bills' + (s.top_party ? ', mostly ' + esc(s.top_party) : '') + ')').join(', ');
        const total = silent.reduce((a, s) => a + s.sales, 0);
        const last = silent.reduce((a, s) => s.last > a ? s.last : a, silent[0].last);
        h += box('warn', 'bx-error', '<strong>Some invoice series have stopped appearing in the Tally export.</strong> Nothing after ' + esc(dt(last, true)) + ' for ' + list
            + '. Earlier in the data they billed regularly, so if they should still be there, the export may be behind. Until then, <strong>bill counts and average bill compare unlike with unlike</strong>; sales value is barely affected (they were only ' + esc(crore(total)) + ' over the whole loaded period).');
    }
    const c = d.coverage;
    if (c.invoices > 0 && (c.value_pct ?? 100) < 99.5) {
        const gaps = (c.missing_series || []).map(s => esc(s.series)).join(', ');
        h += box('info', 'bx-info-circle', '<strong>Item, GST and price detail covers ' + (c.invoice_pct ?? 0) + '% of bills (' + (c.value_pct ?? 0) + '% of the value) in this period.</strong> It comes from a Tally report that does not list every invoice, so item, HSN, GST, rate and taxable figures describe those bills only'
            + (gaps ? ' &mdash; none at all for ' + gaps : '') + '. Bill totals and customer totals always use every invoice.');
    }
    $('#anNotices').innerHTML = h;
}

function pickGrain(days) { return days <= 45 ? 'day' : (days <= 150 ? 'week' : 'month'); }
function renderTrend(d) {
    let g = S.grain;
    if (!S.grainPinned || !g || d.trend[g].labels.length < 2) g = pickGrain(d.period.days);
    if (d.trend[g].labels.length < 2 && g !== 'day') g = 'day';
    $$('#anGrain [data-grain]').forEach(b => b.classList.toggle('active', b.dataset.grain === g));
    const t = d.trend[g];
    $('#anTrendSub').textContent = 'Invoice value incl. GST, and number of bills, by ' + g + (g === 'month' && d.trend.partial_last_month ? ' (latest month is partial, to ' + d.trend.partial_last_month + ')' : '');
    chart('chTrend', {
        chart: { type: 'line', height: 330 },
        series: [{ name: 'Sales incl. GST', type: 'column', data: t.sales }, { name: 'Bills', type: 'line', data: t.invoices }],
        xaxis: { categories: t.labels, tickAmount: Math.min(t.labels.length, 14), labels: { rotate: -45, rotateAlways: false, style: { fontSize: '10px' } } },
        yaxis: [{ labels: { formatter: v => short(v) } }, { opposite: true, labels: { formatter: v => IN0.format(v) } }],
        stroke: { width: [0, 3], curve: 'smooth' }, colors: ['#127464', '#f59e0b'],
        plotOptions: { bar: { columnWidth: '62%', borderRadius: 3 } },
        tooltip: { shared: true, y: { formatter: (v, o) => o.seriesIndex === 0 ? rs(v) : IN0.format(v) + ' bills' } },
        legend: { position: 'top', horizontalAlign: 'right' },
    });
}

function donut(id, labels, vals, o = {}) {
    const total = vals.reduce((a, b) => a + b, 0);
    chart(id, {
        chart: { type: 'donut', height: 320, events: { dataPointSelection: (e, c, cfg) => o.onClick && o.onClick(cfg.dataPointIndex) } },
        series: vals, labels: labels, colors: o.colors || PAL, legend: { position: 'bottom', fontSize: '11px' },
        plotOptions: { pie: { donut: { size: '62%', labels: { show: true, name: { show: true, fontSize: '11px' }, value: { fontSize: '15px', fontWeight: 700, formatter: v => crore(Number(v)) }, total: { show: true, label: o.totalLabel || 'Total', fontSize: '11px', formatter: () => crore(total) } } } } },
        stroke: { width: 2 }, tooltip: { y: { formatter: v => rs(v) } },
    });
}
function renderSeries(d) {
    const rows = d.series.filter(s => s.sales > 0), top = rows.slice(0, 8);
    const rest = rows.slice(8).reduce((a, s) => a + s.sales, 0);
    const labels = top.map(s => s.series), vals = top.map(s => s.sales);
    if (rest > 0) { labels.push('Others'); vals.push(rest); }
    donut('chSeries', labels, vals, { totalLabel: 'Sales', onClick: i => { if (top[i]) setFilter('series', top[i].series); } });
}
function renderCustomers(d) {
    hbar('chCustomers', d.customers.map(c => c.name), d.customers.map(c => c.sales), { color: '#127464', name: 'Sales incl. GST', labelFn: niceName, labelChars: 24, onClick: i => { if (real(d.customers[i].code)) openCustomer(d.customers[i].code); } });
}
function renderSalespersons(d) {
    const rows = d.salespersons.slice(0, 10);
    hbar('chSalespersons', rows.map(s => s.name), rows.map(s => s.sales), { color: '#2563eb', name: 'Sales incl. GST', onClick: i => setFilter('sp', rows[i].name) });
}
function renderFamilies(d) {
    const rows = d.families.slice(0, 10), c = d.coverage;
    const other = d.families.find(f => f.name === 'Other');
    $('#anFamSub').textContent = 'Taxable value · item detail covers ' + (c.value_pct ?? 0) + '% of the value' + (other && other.share > 5 ? ' · ' + other.share + '% unclassified' : '') + ' · click a bar for its items';
    hbar('chFamilies', rows.map(f => f.name), rows.map(f => f.taxable), { colors: rows.map(f => f.color), name: 'Taxable', onClick: i => openFamily(rows[i].name) });
}
function renderGst(d) {
    const rows = d.gst.filter(g => g.taxable > 0);
    $('#anGstSub').textContent = rows.length ? 'Taxable value by GST rate, on bills with item detail' : 'No item detail in this period';
    donut('chGst', rows.map(g => g.rate == null ? 'Unknown' : g.rate + '%'), rows.map(g => g.taxable), { colors: ['#127464', '#f59e0b', '#2563eb', '#db2777'], totalLabel: 'Taxable' });
}
function renderSizes(d) {
    chart('chSizes', {
        chart: { type: 'bar', height: 320 },
        series: [{ name: 'Sales', data: d.sizes.map(s => s.sales) }],
        xaxis: { categories: d.sizes.map(s => s.label), labels: { style: { fontSize: '10px' }, rotate: -30 } },
        yaxis: { labels: { formatter: v => short(v) } }, colors: ['#7c3aed'], plotOptions: { bar: { columnWidth: '58%', borderRadius: 4 } },
        tooltip: { y: { formatter: (v, o) => rs(v) + ' · ' + IN0.format(d.sizes[o.dataPointIndex].invoices) + ' bills' } },
    });
}
function renderAgeing(d) {
    const a = d.ageing;
    $('#anAgeSub').textContent = num(a.bills) + ' open bills, ' + crore(a.total) + ' in total · all customers, as of today';
    chart('chAgeing', {
        chart: { type: 'bar', height: 320 },
        series: [{ name: 'Outstanding', data: a.buckets.map(b => b.amount) }],
        xaxis: { categories: a.buckets.map(b => b.label) }, yaxis: { labels: { formatter: v => short(v) } },
        colors: ['#16a34a', '#f59e0b', '#ea580c', '#dc2626'], plotOptions: { bar: { columnWidth: '52%', borderRadius: 4, distributed: true } }, legend: { show: false },
        dataLabels: { enabled: true, formatter: v => short(v), style: { fontSize: '10px', colors: ['#334155'] }, offsetY: -18 },
        tooltip: { y: { formatter: v => rs(v) } },
    });
}
function renderPlants(d) {
    const rows = d.plants.filter(p => p.sales > 0);
    hbar('chPlants', rows.map(p => p.name), rows.map(p => p.sales), { color: '#0891b2', name: 'Sales incl. GST' });
}
function renderCities(d) {
    const rows = d.cities.slice(0, 10);
    $('#anCitySub').textContent = 'Taxable value · ' + (d.coverage.value_pct ?? 0) + '% of the value has a city';
    hbar('chCities', rows.map(c => c.name), rows.map(c => c.taxable), { color: '#65a30d', name: 'Taxable' });
}

// ------------------------------------------------------------------ drill-down tabs
// What identifies the DATA of a table (not how it is sorted, searched or paged). One request for this brings either
// every row (small tables: the browser does the rest) or just the first page (big ones: the server pages).
function dsParams(key) {
    const st = S.ts[key];
    const p = { panel: key, per: 50 };
    if (key === 'items') Object.assign(p, { basis: st.basis, group: st.group, family: st.family });
    if (key === 'price') p.vary = st.vary;
    return p;
}
// Only used for big tables, which the server pages.
function tabParams(key) {
    const st = S.ts[key];
    return Object.assign(dsParams(key), { page: st.page, per: st.per, sort: st.sort, dir: st.dir, q: st.q });
}
const hay = r => r._h || (r._h = Object.values(r).filter(v => typeof v === 'string').join(' ').toLowerCase());
function cmp(a, b, dir) {
    if (a === b) return 0;
    if (a == null || a === '') return 1;        // blanks last, whichever way it is sorted
    if (b == null || b === '') return -1;
    return (typeof a === 'string' ? a.localeCompare(b, undefined, { sensitivity: 'base' }) : a - b) * dir;
}
// Sort, search and page a whole table in the browser: no request.
function localView(ds, st) {
    let rows = ds.rows;
    const q = st.q.toLowerCase();
    if (q) rows = rows.filter(r => hay(r).includes(q));
    const sort = st.sort || ds.sort, dir = st.sort ? st.dir : ds.dir;
    if (st.sort) { const m = dir === 'asc' ? 1 : -1; rows = rows.slice().sort((x, y) => cmp(x[st.sort], y[st.sort], m)); }
    const pages = Math.max(1, Math.ceil(rows.length / st.per));
    const page = Math.min(Math.max(1, st.page), pages);
    const view = Object.assign({}, ds, { rows: rows.slice((page - 1) * st.per, page * st.per), total: rows.length, page, pages, per: st.per, sort, dir });
    if (S.tab === 'bills') view.summary = { invoices: rows.length, amount: rows.reduce((a, r) => a + r.amount, 0) };
    else if (q && S.tab !== 'price') view.summary = null;   // the dataset-wide figure would no longer describe what is listed
    return view;
}
function switchTab(key) {
    S.tab = key;
    $$('#anTabs [data-tab]').forEach(b => b.classList.toggle('active', b.dataset.tab === key));
    writeUrl();
    $('#anTabBody').innerHTML = toolbarHtml(key);
    loadTab(key);
}
function toolbarHtml(key) {
    const st = S.ts[key];
    const seg = (name, val, opts) => '<div class="btn-group btn-group-sm an-seg" data-seg="' + name + '">' + opts.map(o => '<button type="button" class="btn btn-outline-secondary' + (val === o[0] ? ' active' : '') + '" data-val="' + o[0] + '">' + o[1] + '</button>').join('') + '</div>';
    let h = '<div class="an-toolbar">';
    if (key === 'items') {
        h += seg('basis', st.basis, [['value', 'Value &amp; GST'], ['qty', 'Quantity only']]) + seg('group', st.group, [['item', 'By item'], ['family', 'By family']]);
        h += '<select class="form-select form-select-sm" style="width:auto;" data-field="family" aria-label="Family"><option value="">All families</option>'
            + Object.keys(CFG.palette).map(f => '<option value="' + esc(f) + '"' + (st.family === f ? ' selected' : '') + '>' + esc(f) + '</option>').join('') + '</select>';
    }
    if (key === 'price') h += '<div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="anVary"' + (st.vary === '1' ? ' checked' : '') + '><label class="form-check-label small" for="anVary">Only items whose price changed</label></div>';
    h += '<input type="search" class="form-control form-control-sm" id="anSearch" placeholder="' + esc(TAB_NOTE[key]) + '" value="' + esc(st.q) + '" style="min-width:220px;">';
    h += '<select class="form-select form-select-sm" style="width:auto;" data-field="per" aria-label="Rows per page">' + [25, 50, 100, 200].map(n => '<option value="' + n + '"' + (st.per === n ? ' selected' : '') + '>' + n + ' rows</option>').join('') + '</select>';
    h += '<span class="small text-muted ms-2" id="anSummary"></span>';
    h += '<a class="btn btn-sm btn-outline-secondary ms-auto" id="anExport" href="#"><i class="bx bx-download"></i> Download CSV</a></div>';
    h += '<div id="anGstArea"></div><div id="anTableArea"><div class="an-skel" style="height:280px;"></div></div>';
    return h;
}
async function loadTab(key) {
    if (key !== S.tab) return;
    const st = S.ts[key];
    const my = S.seq[key] = (S.seq[key] || 0) + 1;
    // Dim the table only if this is really waiting on the server; an in-browser sort or page turn must not flicker.
    const dim = setTimeout(() => { const w = $('#anTableWrap'); if (w) w.classList.add('an-loading'); }, 150);
    try {
        const ds = await api(CFG.routes.data, dsParams(key));
        let d;
        if (ds.local) d = localView(ds, st);
        else if (st.page === 1 && st.per === 50 && !st.sort && !st.q) d = ds;
        else d = await api(CFG.routes.data, tabParams(key));
        if (my !== S.seq[key] || key !== S.tab) return;
        renderTab(key, d);
    } catch (e) { if (my === S.seq[key]) { fail(e, 'this table'); const a = $('#anTableArea'); if (a) a.innerHTML = ''; } }
    finally { clearTimeout(dim); }
}
function renderTab(key, d) {
    const st = S.ts[key];
    st.page = d.page;
    const sm = d.summary;
    let summary = '';
    if (key === 'bills') summary = num(sm.invoices) + ' bills · ' + crore(sm.amount);
    else if (sm) summary = num(d.total) + ' rows' + (sm.label && sm.value != null && typeof sm.value === 'number' && sm.label.indexOf('value') > -1 ? ' · ' + sm.label + ' ' + crore(sm.value) : '');
    else summary = num(d.total) + ' rows';
    $('#anSummary').textContent = summary;
    $('#anExport').href = CFG.routes.export + '?' + query(Object.assign({}, fparams(), tabParams(key), { page: '', v: CFG.importId }));

    if (key === 'gst') { $('#anGstArea').innerHTML = gstAreaHtml(d); drawGstCharts(d); }
    $('#anTableArea').innerHTML = '<div class="an-table-wrap" id="anTableWrap">' + tableHtml(d) + '</div>' + pagerHtml(d) + (key === 'items' ? itemsNote(d) : '') + (key === 'price' ? priceNote(d) : '');
}
function itemsNote(d) {
    if (d.basis === 'qty') return '<p class="small text-muted mt-2 mb-0">Quantity only: every bill lists its items, but this Tally report carries no value, so there is no rate or GST here. Quantities are shown per unit &mdash; litres, kilos and pieces are never added together.</p>';
    const c = S.ov && S.ov.coverage;
    return '<p class="small text-muted mt-2 mb-0">Value, rate and GST come from the Tally report that covers ' + (c ? (c.invoice_pct ?? 0) + '% of bills (' + (c.value_pct ?? 0) + '% of the value)' : 'some bills') + ' in this period. "Avg rate" is taxable value divided by quantity, so it reflects discounts; lowest/highest are the listed rates.</p>';
}
function priceNote(d) {
    return '<p class="small text-muted mt-2 mb-0">Built from ' + num(d.summary.lines) + ' item lines with a listed rate. "Spread" is the highest rate over the lowest for the same item and unit; a wide spread can be a real price change, a customer-specific price, or a different pack size sold under one item name. Open an item to see who paid what.</p>';
}
function cellHtml(col, row) {
    const v = row[col.key];
    switch (col.type) {
        case 'money': return v == null ? BLANK : inr(v);
        case 'money2': return v == null ? BLANK : inr(v, 2);
        case 'num': return v == null ? BLANK : (Number.isInteger(v) ? IN0.format(v) : IN3.format(v));
        case 'pct0': return v == null ? BLANK : v + '%';
        case 'pct1': return v == null ? BLANK : v.toFixed(1) + '%';
        case 'share': return v == null ? BLANK : '<span class="an-share"><span class="bar"><i style="width:' + Math.min(100, Math.max(0, v)) + '%"></i></span>' + v.toFixed(1) + '%</span>';
        case 'delta': return v == null ? BLANK : chip(v, true);
        case 'date': return v ? esc(dt(v, true)) : BLANK;
        case 'party': return real(row.party_code) ? '<a class="an-link" data-open="customer" data-code="' + esc(row.party_code) + '">' + esc(v) + '</a>' : esc(v);
        case 'link': return col.open === 'customer' && !real(row.code) ? esc(v) : '<a class="an-link" data-open="' + col.open + '" ' + linkAttrs(col.open, row) + '>' + esc(v) + '</a>';
        default: return v == null || v === '' ? BLANK : esc(v);
    }
}
function linkAttrs(kind, r) {
    switch (kind) {
        case 'bill': return 'data-invoice="' + esc(r.invoice_no) + '"';
        case 'item': return 'data-item="' + esc(r.item) + '" data-unit="' + esc(r.unit ?? '') + '"';
        case 'customer': return 'data-code="' + esc(r.code) + '"';
        case 'family': return 'data-family="' + esc(r.family) + '"';
        case 'salesperson': return 'data-name="' + esc(r.name) + '"';
    }
    return '';
}
// Tally uses party code 0 for several unrelated parties, so it does not identify a customer and there is nothing to drill into.
const real = c => c != null && c !== '' && c !== '0';
const NUMERIC = ['money', 'money2', 'num', 'pct0', 'pct1', 'share', 'delta'];
function tableHtml(d) {
    const st = S.ts[S.tab];
    const sort = d.sort, dir = d.dir;
    let h = '<table class="table table-sm table-hover an-table"><thead><tr>';
    d.columns.forEach(c => {
        const active = c.sort && c.key === sort;
        h += '<th class="' + (NUMERIC.includes(c.type) ? 'an-num ' : '') + (c.sort ? 'sortable' : '') + '"' + (c.sort ? ' data-sort="' + c.key + '"' : '') + '>' + esc(c.label) + (active ? (dir === 'asc' ? ' ▲' : ' ▼') : '') + '</th>';
    });
    h += '</tr></thead><tbody>';
    if (!d.rows.length) h += '<tr><td colspan="' + d.columns.length + '" class="text-center text-muted py-5">Nothing matches these filters.</td></tr>';
    d.rows.forEach(r => {
        h += '<tr>' + d.columns.map(c => '<td class="' + (NUMERIC.includes(c.type) ? 'an-num' : (c.type === 'link' || c.type === 'party' ? 'wrap' : '')) + '">' + cellHtml(c, r) + '</td>').join('') + '</tr>';
    });
    return h + '</tbody></table>';
}
function pagerHtml(d) {
    const from = d.total ? (d.page - 1) * d.per + 1 : 0, to = Math.min(d.total, d.page * d.per);
    const b = (label, page, dis) => '<button type="button" class="btn btn-sm btn-outline-secondary" data-page="' + page + '"' + (dis ? ' disabled' : '') + '>' + label + '</button>';
    return '<div class="an-pager"><div>Showing ' + num(from) + '–' + num(to) + ' of ' + num(d.total) + '</div><div class="d-flex align-items-center gap-1">'
        + b('«', 1, d.page <= 1) + b('‹ Prev', d.page - 1, d.page <= 1) + '<span class="px-2">Page ' + d.page + ' of ' + d.pages + '</span>' + b('Next ›', d.page + 1, d.page >= d.pages) + b('»', d.pages, d.page >= d.pages) + '</div></div>';
}
function gstAreaHtml(d) {
    const rows = d.slabs.map(s => '<tr><td>' + (s.rate == null ? 'Unknown' : s.rate + '%') + '</td><td class="an-num">' + num(s.bills) + '</td><td class="an-num">' + inr(s.taxable) + '</td><td class="an-num">' + inr(s.tax) + '</td><td class="an-num">' + (s.share ?? 0).toFixed(1) + '%</td></tr>').join('');
    const chk = d.check;
    return '<div class="row g-3 mb-3"><div class="col-lg-4"><div id="chGstTab" style="min-height:280px;"></div></div><div class="col-lg-8"><div id="chGstMonth" style="min-height:280px;"></div></div></div>'
        + '<div class="table-responsive mb-3"><table class="table table-sm an-table" style="max-width:640px;"><thead><tr><th>GST rate</th><th class="an-num">Bills</th><th class="an-num">Taxable (₹)</th><th class="an-num">GST (₹)</th><th class="an-num">Share</th></tr></thead><tbody>' + (rows || '<tr><td colspan="5" class="text-muted">No item detail in this period.</td></tr>') + '</tbody></table></div>'
        + '<p class="small text-muted">Tally reports one combined GST figure per line (no CGST / SGST / IGST split), so that is what is shown. Check: taxable × rate matches the reported GST on <strong>' + num(chk.lines - chk.mismatch) + ' of ' + num(chk.lines) + '</strong> lines' + (chk.mismatch ? ' (' + num(chk.mismatch) + ' differ by more than ₹1.50)' : '') + '. Only bills with item detail are included.</p>';
}
function drawGstCharts(d) {
    const rows = d.slabs.filter(s => s.taxable > 0);
    donut('chGstTab', rows.map(s => s.rate == null ? 'Unknown' : s.rate + '%'), rows.map(s => s.taxable), { colors: ['#127464', '#f59e0b', '#2563eb', '#db2777'], totalLabel: 'Taxable' });
    chart('chGstMonth', {
        chart: { type: 'bar', height: 300, stacked: false },
        series: [{ name: 'Taxable value', data: d.monthly.taxable }, { name: 'GST', data: d.monthly.tax }],
        xaxis: { categories: d.monthly.labels }, yaxis: { labels: { formatter: v => short(v) } }, colors: ['#127464', '#f59e0b'],
        plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } }, legend: { position: 'top', horizontalAlign: 'right' }, tooltip: { y: { formatter: v => rs(v) } },
    });
}
function openFamily(name) {
    Object.assign(S.ts.items, { group: 'item', basis: 'value', family: name, page: 1, sort: '', q: '' });
    switchTab('items');
    const el = $('#anTabs');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function bindTabs() {
    $('#anTabs').addEventListener('click', e => { const b = e.target.closest('[data-tab]'); if (b && b.dataset.tab !== S.tab) switchTab(b.dataset.tab); });
    const body = $('#anTabBody');
    let timer;
    body.addEventListener('input', e => {
        if (e.target.id !== 'anSearch') return;
        clearTimeout(timer);
        timer = setTimeout(() => { const st = S.ts[S.tab]; st.q = e.target.value.trim(); st.page = 1; loadTab(S.tab); }, 250);
    });
    body.addEventListener('change', e => {
        const st = S.ts[S.tab], f = e.target.dataset.field;
        if (e.target.id === 'anVary') { st.vary = e.target.checked ? '1' : '0'; st.page = 1; st.sort = ''; loadTab(S.tab); return; }
        if (f === 'per') { st.per = parseInt(e.target.value, 10); st.page = 1; loadTab(S.tab); }
        if (f === 'family') { st.family = e.target.value; st.page = 1; loadTab(S.tab); }
    });
    body.addEventListener('click', e => {
        const st = S.ts[S.tab];
        const pg = e.target.closest('[data-page]');
        if (pg && !pg.disabled) { st.page = parseInt(pg.dataset.page, 10); loadTab(S.tab); return; }
        const th = e.target.closest('th[data-sort]');
        if (th) { const k = th.dataset.sort; st.dir = (st.sort === k && st.dir === 'desc') ? 'asc' : 'desc'; st.sort = k; st.page = 1; loadTab(S.tab); return; }
        const sg = e.target.closest('[data-seg] [data-val]');
        if (sg) {
            const name = sg.parentElement.dataset.seg;
            st[name] = sg.dataset.val; st.page = 1; st.sort = '';
            $$('[data-seg="' + name + '"] .btn', body).forEach(b => b.classList.toggle('active', b === sg));
            loadTab(S.tab); return;
        }
        const a = e.target.closest('.an-link[data-open]');
        if (!a) return;
        e.preventDefault();
        switch (a.dataset.open) {
            case 'bill': openBill(a.dataset.invoice); break;
            case 'item': openItem(a.dataset.item, a.dataset.unit); break;
            case 'customer': openCustomer(a.dataset.code); break;
            case 'family': openFamily(a.dataset.family); break;
            case 'salesperson': setFilter('sp', a.dataset.name); break;
        }
    });
}

// ------------------------------------------------------------------ modals
let modalBusy = Promise.resolve();
function showModal(id, title, html) {
    const el = document.getElementById(id);
    el.querySelector('.modal-title').textContent = title;
    el.querySelector('.modal-body').innerHTML = html;
    const open = $$('.modal.show').filter(m => m !== el);
    const go = () => bootstrap.Modal.getOrCreateInstance(el).show();
    if (!open.length) { go(); return el; }
    // Only one popup at a time: close the current one, then open this one.
    open.forEach(m => { m.addEventListener('hidden.bs.modal', go, { once: true }); bootstrap.Modal.getInstance(m).hide(); });
    return el;
}
const loading = '<div class="p-4 text-center text-muted small"><div class="an-skel" style="height:14px;width:40%;margin:0 auto 10px;"></div><div class="an-skel" style="height:120px;"></div></div>';
const mini = (l, v, sub) => '<div class="col-6 col-md-3"><div class="an-mini"><div class="lbl">' + l + '</div><div class="val">' + v + '</div>' + (sub ? '<div class="an-sub">' + sub + '</div>' : '') + '</div></div>';
const kv = pairs => '<dl class="an-kv mb-0">' + pairs.filter(p => p[1] != null && p[1] !== '').map(p => '<dt>' + p[0] + '</dt><dd>' + p[1] + '</dd>').join('') + '</dl>';
const smallTable = (heads, rows, numeric = []) => '<div class="an-table-wrap" style="max-height:340px;"><table class="table table-sm an-table"><thead><tr>' + heads.map((h, i) => '<th class="' + (numeric.includes(i) ? 'an-num' : '') + '">' + h + '</th>').join('') + '</tr></thead><tbody>'
    + (rows.length ? rows.map(r => '<tr>' + r.map((c, i) => '<td class="' + (numeric.includes(i) ? 'an-num' : '') + '">' + c + '</td>').join('') + '</tr>').join('') : '<tr><td colspan="' + heads.length + '" class="text-center text-muted py-3">Nothing to show.</td></tr>') + '</tbody></table></div>';

async function openBill(no) {
    const el = showModal('anBillModal', 'Invoice ' + no, loading);
    try {
        const d = await api(CFG.routes.bill, { invoice_no: no });
        const i = d.invoice;
        let h = '<div class="row g-3 mb-3"><div class="col-lg-7">' + kv([
            ['Customer', i.party ? (real(i.party_code) ? '<a class="an-link" data-open="customer" data-code="' + esc(i.party_code) + '">' + esc(i.party) + '</a> <span class="an-muted">(' + esc(i.party_code) + ')</span>' : esc(i.party)) : (i.branch_transfer ? 'Branch transfer' : '')],
            ['Bill date', esc(dt(i.date, true))], ['Series', esc(i.series)], ['Salesperson', esc(i.salesperson)], ['Customer PO', esc(i.po)], ['City', esc(i.city)],
            ['Dispatched from', esc(i.plant)], ['Transporter', esc([i.transporter, i.transporter_phone].filter(Boolean).join(' · '))], ['LR no.', esc(i.lr_no)],
            ['Credit terms', esc(i.credit_terms)], ['Due date', i.due_date ? esc(dt(i.due_date, true)) : ''],
        ]) + '</div><div class="col-lg-5"><div class="row g-2">' + mini('Invoice total', rs(i.amount) + '<div class="an-sub">incl. GST</div>').replace('col-md-3', 'col-md-12') + '</div>';
        if (d.open.length) h += d.open.map(o => '<div class="an-notice warn mt-2 mb-0"><i class="bx bx-time-five"></i><div><strong>Still open: ' + rs(o.amount) + '</strong> &middot; billed ' + o.age + ' days ago' + (o.credit_days ? ' &middot; ' + o.credit_days + ' days credit' : '') + '</div></div>').join('');
        else h += '<div class="an-notice info mt-2 mb-0"><i class="bx bx-check-circle"></i><div>Not on Tally’s outstanding list under this bill number (settled, or booked under another reference).</div></div>';
        h += '</div></div>';
        const heads = d.has_value_detail ? ['Item', 'Family', 'HSN', 'GST', 'Qty', 'Unit', 'Rate (₹)', 'Taxable (₹)', 'GST (₹)'] : ['Item', 'Family', 'Qty', 'Unit'];
        const rows = d.lines.map(l => d.has_value_detail
            ? ['<a class="an-link" data-open="item" data-item="' + esc(l.item) + '" data-unit="' + esc(l.unit ?? '') + '">' + esc(l.item) + '</a>', esc(l.family), esc(l.hsn), l.gst == null ? '' : l.gst + '%', l.qty == null ? '' : IN3.format(l.qty), esc(l.unit), l.rate == null ? '' : inr(l.rate, 2) + (l.rate_unit ? ' /' + esc(l.rate_unit) : ''), inr(l.taxable, 2), l.tax == null ? '' : inr(l.tax, 2)]
            : ['<a class="an-link" data-open="item" data-item="' + esc(l.item) + '" data-unit="' + esc(l.unit ?? '') + '">' + esc(l.item) + '</a>', esc(l.family), l.qty == null ? '' : IN3.format(l.qty), esc(l.unit)]);
        h += smallTable(heads, rows, d.has_value_detail ? [3, 4, 6, 7, 8] : [2]);
        if (d.has_value_detail) {
            const t = d.totals;
            h += '<div class="d-flex flex-wrap justify-content-end gap-4 mt-2 small"><span>Taxable <strong>' + rs(t.taxable) + '</strong></span><span>GST <strong>' + rs(t.tax) + '</strong></span><span>Lines total <strong>' + rs(t.lines_total) + '</strong></span></div>';
            h += d.reconciles ? '<p class="small text-success mt-2 mb-0"><i class="bx bx-check-circle"></i> The item lines add up to the invoice total.</p>'
                : '<p class="small text-warning mt-2 mb-0"><i class="bx bx-error-circle"></i> The item lines add up to ' + rs(t.lines_total) + ' but the invoice total is ' + rs(i.amount) + ' (difference ' + rs(t.diff) + '). Tally’s two reports disagree on this bill; treat its item split with caution.</p>';
        } else {
            h += '<p class="small text-muted mt-2 mb-0">This bill is not in the Tally report that carries rate, HSN and GST, so only items and quantities are known.</p>';
        }
        el.querySelector('.modal-body').innerHTML = h;
    } catch (e) { el.querySelector('.modal-body').innerHTML = '<div class="text-danger small">' + (e.message === 'session' ? 'Your session has expired — reload the page.' : 'Could not load this invoice.') + '</div>'; }
}

async function openItem(name, unit, scope) {
    S.itemScope = scope || S.itemScope || 'period';
    const el = showModal('anItemModal', name, loading);
    try {
        const d = await api(CFG.routes.item, { item: name, unit: unit || '', scope: S.itemScope });
        const k = d.kpis;
        const seg = '<div class="btn-group btn-group-sm an-seg" id="anItemScope"><button type="button" class="btn btn-outline-secondary' + (S.itemScope === 'period' ? ' active' : '') + '" data-scope="period">This period</button><button type="button" class="btn btn-outline-secondary' + (S.itemScope === 'all' ? ' active' : '') + '" data-scope="all">All loaded data</button></div>';
        let h = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div class="small">' + (d.family ? '<span class="an-chip flat">' + esc(d.family) + '</span> ' : '') + (d.hsn ? 'HSN ' + esc(d.hsn) + ' ' : '') + (d.gst != null ? '&middot; GST ' + d.gst + '% ' : '') + (d.unit ? '&middot; per ' + esc(d.unit) : '') + '</div>' + seg + '</div>';
        if (!d.value_detail) {
            h += '<div class="an-notice info"><i class="bx bx-info-circle"></i><div>No value, rate or GST is known for this item in the selected period — none of its bills are in the Tally report that carries them. Quantities only:</div></div>'
                + '<div class="row g-2 mb-3">' + mini('Bills', num(k.bills)) + mini('Customers', num(k.customers)) + mini('Quantity', IN3.format(k.qty ?? 0), esc(d.unit || '')) + '</div>'
                + smallTable(['Date', 'Invoice', 'Customer', 'Qty'], d.recent.map(r => [esc(dt(r.date, true)), '<a class="an-link" data-open="bill" data-invoice="' + esc(r.invoice_no) + '">' + esc(r.invoice_no) + '</a>', esc(r.party), r.qty == null ? '' : IN3.format(r.qty) + ' ' + esc(r.unit || '')]), [3]);
        } else {
            h += '<div class="row g-2 mb-3">' + mini('Taxable value', crore(k.taxable), rs(k.taxable)) + mini('GST', crore(k.tax)) + mini('Quantity', IN3.format(k.qty), esc(d.unit || '')) + mini('Avg rate', k.avg_rate == null ? '—' : '₹' + inr(k.avg_rate, 2), 'taxable ÷ qty')
                + mini('Lowest rate', k.min_rate == null ? '—' : '₹' + inr(k.min_rate, 2)) + mini('Highest rate', k.max_rate == null ? '—' : '₹' + inr(k.max_rate, 2)) + mini('Bills', num(k.bills)) + mini('Customers', num(k.customers)) + '</div>'
                + '<div class="an-card mb-3" style="height:auto;box-shadow:none;border:1px solid #eef2f6;"><h6>Price over time</h6><div class="an-sub">Weighted rate per day, with the lowest and highest listed rate that day</div><div id="chItemPrice" style="min-height:260px;"></div></div>'
                + '<div class="row g-3"><div class="col-lg-6"><h6 class="small fw-bold">Who buys it</h6>'
                + smallTable(['Customer', 'Bills', 'Qty', 'Avg rate', 'Taxable (₹)'], d.customers.map(c => [(real(c.code) ? '<a class="an-link" data-open="customer" data-code="' + esc(c.code) + '">' + esc(c.name) + '</a>' : esc(c.name)), num(c.bills), IN3.format(c.qty), c.avg_rate == null ? '—' : inr(c.avg_rate, 2), inr(c.taxable)]), [1, 2, 3, 4])
                + '</div><div class="col-lg-6"><h6 class="small fw-bold">Recent sales</h6>'
                + smallTable(['Date', 'Invoice', 'Customer', 'Qty', 'Rate', 'Taxable (₹)'], d.recent.map(r => [esc(dt(r.date, true)), '<a class="an-link" data-open="bill" data-invoice="' + esc(r.invoice_no) + '">' + esc(r.invoice_no) + '</a>', esc(trunc(r.party, 26)), r.qty == null ? '' : IN3.format(r.qty), r.rate == null ? '' : inr(r.rate, 2), inr(r.taxable)]), [3, 4, 5]) + '</div></div>';
        }
        h += '<p class="small text-muted mt-3 mb-0">' + (S.itemScope === 'all' ? 'All loaded data' : esc(span(d.range[0], d.range[1]))) + ', with the same salesperson / series / type filters as the page.</p>';
        el.querySelector('.modal-body').innerHTML = h;
        el.querySelector('#anItemScope').addEventListener('click', ev => { const b = ev.target.closest('[data-scope]'); if (b && b.dataset.scope !== S.itemScope) openItem(name, unit, b.dataset.scope); });
        if (d.value_detail) {
            const draw = () => chart('chItemPrice', {
                chart: { type: 'line', height: 260 },
                series: [{ name: 'Weighted rate', data: d.history.avg }, { name: 'Lowest', data: d.history.min }, { name: 'Highest', data: d.history.max }],
                xaxis: { categories: d.history.labels, tickAmount: Math.min(d.history.labels.length, 10) }, yaxis: { labels: { formatter: v => v == null ? '' : inr(v, 0) } },
                stroke: { width: [3, 1.5, 1.5], dashArray: [0, 4, 4], curve: 'straight' }, colors: ['#127464', '#94a3b8', '#f59e0b'], markers: { size: [4, 0, 0] },
                tooltip: { y: { formatter: v => v == null ? '—' : '₹' + inr(v, 2) } }, legend: { position: 'top', horizontalAlign: 'right' },
            });
            if (el.classList.contains('show')) draw(); else el.addEventListener('shown.bs.modal', draw, { once: true });
        }
    } catch (e) { el.querySelector('.modal-body').innerHTML = '<div class="text-danger small">' + (e.message === 'session' ? 'Your session has expired — reload the page.' : 'Could not load this item.') + '</div>'; }
}

async function openCustomer(code) {
    const el = showModal('anCustModal', 'Customer', loading);
    try {
        const d = await api(CFG.routes.customer, { code: code });
        const k = d.kpis, o = d.outstanding;
        el.querySelector('.modal-title').textContent = d.name;
        let h = '<div class="row g-3 mb-3"><div class="col-lg-7">' + kv([
            ['Tally code', esc(d.code)], ['City', esc(d.city)], ['Salesperson', esc((d.salespersons || []).join(', '))],
            ['First / last bill', esc(dt(d.first, true)) + ' → ' + esc(dt(d.last, true))],
            ['In HRX', d.hrx ? esc(d.hrx.name) + ' <span class="an-chip flat">' + esc(d.hrx.status) + '</span>' : '<span class="an-muted">not a customer in HRX</span>'], ['In Setu', d.in_setu ? 'Yes' : '<span class="an-muted">No</span>'],
        ]) + '</div><div class="col-lg-5"><div class="row g-2">' + mini('Owes now', crore(o.total), o.count + ' open bills').replace('col-md-3', 'col-md-6') + mini('Over 90 days', crore(o.over90)).replace('col-md-3', 'col-md-6') + '</div></div></div>';
        h += '<div class="row g-2 mb-3">' + mini('Sales (period)', crore(k.period_sales), rs(k.period_sales)) + mini('Bills (period)', num(k.period_bills)) + mini('Average bill', crore(k.avg)) + mini('Share of period', k.share == null ? '—' : k.share + '%', 'all loaded data: ' + crore(k.all_sales)) + '</div>';
        h += '<div class="an-card mb-3" style="height:auto;box-shadow:none;border:1px solid #eef2f6;"><h6>Sales by month</h6><div class="an-sub">All loaded data, not just the selected period</div><div id="chCustMonthly" style="min-height:240px;"></div></div>';
        h += '<div class="row g-3"><div class="col-lg-6"><h6 class="small fw-bold">What they buy (period, bills with item detail)</h6>'
            + smallTable(['Item', 'Qty', 'Rates (₹)', 'Taxable (₹)'], d.items.map(r => ['<a class="an-link" data-open="item" data-item="' + esc(r.item) + '" data-unit="' + esc(r.unit ?? '') + '">' + esc(trunc(r.item, 40)) + '</a>', IN3.format(r.qty) + ' ' + esc(r.unit || ''), r.rmin == null ? '' : (r.rmin === r.rmax ? inr(r.rmin, 2) : inr(r.rmin, 2) + ' – ' + inr(r.rmax, 2)), inr(r.taxable)]), [1, 2, 3])
            + '</div><div class="col-lg-6"><h6 class="small fw-bold">Recent bills (period)</h6>'
            + smallTable(['Date', 'Invoice', 'Salesperson', 'Amount (₹)'], d.recent.map(r => [esc(dt(r.date, true)), '<a class="an-link" data-open="bill" data-invoice="' + esc(r.invoice_no) + '">' + esc(r.invoice_no) + '</a>', esc(r.salesperson), inr(r.amount)]), [3]) + '</div></div>';
        if (o.bills.length) h += '<h6 class="small fw-bold mt-3">Open bills' + (o.count > o.bills.length ? ' (oldest ' + o.bills.length + ' of ' + o.count + ')' : '') + '</h6>'
            + smallTable(['Bill', 'Date', 'Age (days)', 'Amount (₹)'], o.bills.map(b => [esc(b.ref), esc(dt(b.date, true)), '<span class="' + (b.age > 90 ? 'text-danger fw-bold' : '') + '">' + b.age + '</span>', inr(b.amount, 2)]), [2, 3]);
        el.querySelector('.modal-body').innerHTML = h;
        const draw = () => chart('chCustMonthly', {
            chart: { type: 'line', height: 240 },
            series: [{ name: 'Sales incl. GST', type: 'column', data: d.monthly.sales }, { name: 'Bills', type: 'line', data: d.monthly.bills }],
            xaxis: { categories: d.monthly.labels }, yaxis: [{ labels: { formatter: v => short(v) } }, { opposite: true, labels: { formatter: v => IN0.format(v) } }],
            stroke: { width: [0, 3] }, colors: ['#127464', '#f59e0b'], plotOptions: { bar: { columnWidth: '50%', borderRadius: 3 } }, legend: { position: 'top', horizontalAlign: 'right' },
            tooltip: { shared: true, y: { formatter: (v, o2) => o2.seriesIndex === 0 ? rs(v) : IN0.format(v) + ' bills' } },
        });
        if (el.classList.contains('show')) draw(); else el.addEventListener('shown.bs.modal', draw, { once: true });
    } catch (e) { el.querySelector('.modal-body').innerHTML = '<div class="text-danger small">' + (e.message === 'session' ? 'Your session has expired — reload the page.' : 'Could not load this customer.') + '</div>'; }
}

// Links inside the modals (bill / item / customer / family) behave like the ones in the tables.
document.addEventListener('click', e => {
    const a = e.target.closest('.modal .an-link[data-open]');
    if (!a) return;
    e.preventDefault();
    switch (a.dataset.open) {
        case 'bill': openBill(a.dataset.invoice); break;
        case 'item': openItem(a.dataset.item, a.dataset.unit); break;
        case 'customer': openCustomer(a.dataset.code); break;
    }
});

// ------------------------------------------------------------------ boot
function boot() {
    bindFilters();
    bindTabs();
    syncFilterUi();
    if (CFG.overview) {
        // The page already carries the overview for its filters: draw it without a round trip.
        cache.set(urlFor(CFG.routes.data, { panel: 'overview' }), Promise.resolve(CFG.overview));
        S.embedded = true;
    }
    switchTab(S.tab);
    loadOverview();
}
// The chart library is a deferred module that finishes loading just after this script runs: wait for it (a few ms), then start.
function whenReady(tries) {
    if (window.ApexCharts || tries > 300) return boot();       // after ~9 s carry on; charts then say they could not load
    setTimeout(() => whenReady(tries + 1), 30);
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => whenReady(0));
else whenReady(0);
})();
@endverbatim
</script>
