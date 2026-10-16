@extends('layouts/layoutMaster')

@section('title', 'Pricing Desk')

@section('content')
<div class="hitech-page-hero mb-4">
  <style>
    .pd-hero-stat{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:12px;padding:10px 16px;min-width:140px}
    .pd-hero-stat .pd-hero-icon{width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;flex-shrink:0}
    .pd-hero-stat .pd-hero-v{font-size:1.25rem;font-weight:800;color:#fff;line-height:1.1}
    .pd-hero-stat .pd-hero-k{font-size:.68rem;color:rgba(255,255,255,.75);text-transform:uppercase;letter-spacing:.04em;font-weight:600}
    @media (max-width:860px){#pdHeroStats{display:none}}
  </style>
  <div class="hitech-page-hero-text d-flex justify-content-between align-items-center w-100 flex-wrap gap-3">
    <div>
      <div class="text-white-50 text-uppercase fw-bold mb-1" style="letter-spacing:.08em;font-size:.72rem">Sales &amp; Pricing</div>
      <h4 class="greeting text-white mb-1">Pricing Desk</h4>
      <p class="sub-text text-white-50 mb-0">Price list, discount approval finder, product calculators and quotes.
        <span class="badge rounded-pill ms-1" style="background:rgba(255,255,255,.18);font-weight:600;font-size:.72rem;padding:4px 10px;vertical-align:middle">
          <span id="verLabel"></span>
        </span>
      </p>
    </div>
    <div class="d-flex gap-2 flex-wrap" id="pdHeroStats">
      <div class="pd-hero-stat"><span class="pd-hero-icon"><i class="bx bx-grid-alt"></i></span><div><div class="pd-hero-v" id="pdStatFamilies">–</div><div class="pd-hero-k">Product Families</div></div></div>
      <div class="pd-hero-stat"><span class="pd-hero-icon"><i class="bx bx-package"></i></span><div><div class="pd-hero-v" id="pdStatProducts">–</div><div class="pd-hero-k">Products</div></div></div>
      <div class="pd-hero-stat"><span class="pd-hero-icon"><i class="bx bx-shield-quarter"></i></span><div><div class="pd-hero-v" id="pdStatBands">–</div><div class="pd-hero-k">Approval Bands</div></div></div>
    </div>
  </div>
</div>

<div id="pricingDeskRoot">
<style>
#pricingDeskRoot{
  --bg:#f4f6f9;--panel:#ffffff;--ink:#16202c;--muted:#5d6b7a;--line:#dfe4ea;--soft:#eef2f6;
  --brand:var(--hitech-primary, #008080);--brand-ink:#ffffff;--accent:var(--hitech-primary, #008080);
  --ok:#1f7a45;--ok-bg:#e3f4ea;--warn:#8a5a00;--warn-bg:#fdf1d6;--ch:#9a4a07;--ch-bg:#fde6d3;--bad:#a4262c;--bad-bg:#fbe3e4;
  --focus:#2f6fb3;
  background:var(--bg);color:var(--ink);font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
  border-radius:12px; overflow:hidden;
}
#pricingDeskRoot *{box-sizing:border-box}
#pricingDeskRoot button,#pricingDeskRoot input,#pricingDeskRoot select,#pricingDeskRoot textarea{font:inherit;color:inherit}
#pricingDeskRoot main{padding:18px 16px 32px}
#pricingDeskRoot .pricing-desk-tabs-wrap{background:#fff;padding-bottom:14px}
#pricingDeskRoot .banner{background:var(--warn-bg);color:var(--warn);border:1px solid var(--line);padding:8px 12px;border-radius:8px;margin-bottom:12px;font-size:13px}
#pricingDeskRoot .panel{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:16px}
#pricingDeskRoot .pd-row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
#pricingDeskRoot .grow{flex:1}
#pricingDeskRoot h2{font-size:16px;margin:0 0 10px}
#pricingDeskRoot h3{font-size:14px;margin:14px 0 8px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
#pricingDeskRoot .muted{color:var(--muted)}
#pricingDeskRoot .small{font-size:12px}
#pricingDeskRoot input[type=text],#pricingDeskRoot input[type=number],#pricingDeskRoot input[type=search],#pricingDeskRoot input[type=password],#pricingDeskRoot input[type=date],#pricingDeskRoot select,#pricingDeskRoot textarea{background:var(--panel);border:1px solid var(--line);border-radius:6px;padding:7px 9px;min-width:0}
#pricingDeskRoot input:focus,#pricingDeskRoot select:focus,#pricingDeskRoot textarea:focus,#pricingDeskRoot button:focus-visible{outline:2px solid var(--focus);outline-offset:1px}
#pricingDeskRoot .btn{background:var(--soft);border:1px solid var(--line);border-radius:6px;padding:7px 12px;cursor:pointer;white-space:nowrap}
#pricingDeskRoot .btn:hover{border-color:var(--muted)}
#pricingDeskRoot .btn.primary{background:var(--accent);border-color:var(--accent);color:#fff;font-weight:600}
#pricingDeskRoot .btn.danger{color:var(--bad)}
#pricingDeskRoot .seg{display:inline-flex;flex-wrap:wrap;border:1px solid var(--line);border-radius:7px;overflow:hidden;max-width:100%}
#pricingDeskRoot .seg button{border:0;background:var(--panel);padding:6px 12px;cursor:pointer}
#pricingDeskRoot .seg button.on{background:var(--brand);color:#fff}
#pricingDeskRoot .tablewrap{overflow:auto;border:1px solid var(--line);border-radius:8px;max-height:66vh}
#pricingDeskRoot table{border-collapse:collapse;width:100%;font-size:13px}
#pricingDeskRoot th,#pricingDeskRoot td{padding:7px 9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
#pricingDeskRoot th{position:sticky;top:0;background:var(--soft);font-weight:600;z-index:1;white-space:nowrap}
#pricingDeskRoot td.n,#pricingDeskRoot th.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
#pricingDeskRoot tr.cat td{background:var(--soft);font-weight:650;color:var(--brand)}
#pricingDeskRoot tbody tr.item{cursor:pointer}
#pricingDeskRoot tbody tr.item:hover td{background:var(--soft)}
#pricingDeskRoot tbody tr.sel td{background:var(--warn-bg)!important}
#pricingDeskRoot .pill{display:inline-block;border-radius:999px;padding:5px 13px;font-size:14px;font-weight:600;white-space:nowrap}
#pricingDeskRoot .b0{background:var(--ok-bg);color:var(--ok)}
#pricingDeskRoot .b1{background:var(--warn-bg);color:var(--warn)}
#pricingDeskRoot .b2{background:var(--ch-bg);color:var(--ch)}
#pricingDeskRoot .b3{background:var(--bad-bg);color:var(--bad)}
#pricingDeskRoot .bx{background:var(--soft);color:var(--muted)}
#pricingDeskRoot .grid2>*{min-width:0}
#pricingDeskRoot .grid2{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px}
@media (max-width:900px){#pricingDeskRoot .grid2{grid-template-columns:minmax(0,1fr)}#pricingDeskRoot .side{position:static}}
@media (max-width:520px){#pricingDeskRoot main{padding:12px 10px 32px}#pricingDeskRoot .form{grid-template-columns:1fr 1fr}#pricingDeskRoot label[style*="span 2"]{grid-column:1/-1!important}#pricingDeskRoot #pSearch{min-width:0!important;width:100%}}
#pricingDeskRoot .side{position:sticky;top:12px;align-self:start}

/* ---- Calculators tab: same sidebar+panel component as the header's Quick Calculator popup ---- */
#pricingDeskRoot .qc-embed{min-height:560px;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}
#pricingDeskRoot .qc-embed #pdcWrap{display:flex;min-height:560px;max-height:78vh}
#pricingDeskRoot .qc-sidebar{width:300px;flex-shrink:0;border-right:1px solid var(--line);display:flex;flex-direction:column;background:#fbfcfd}
#pricingDeskRoot .qc-sidebar-head{padding:16px;border-bottom:1px solid var(--line)}
#pricingDeskRoot .qc-search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa5b1;font-size:.9rem}
#pricingDeskRoot .qc-list{flex-grow:1;overflow-y:auto;padding:6px 0;max-height:70vh}
#pricingDeskRoot .qc-group-head{display:flex;align-items:center;gap:8px;width:100%;border:0;background:none;padding:9px 16px;font-weight:600;font-size:.86rem;color:#2b2f33}
#pricingDeskRoot .qc-group-head:hover{background:var(--soft)}
#pricingDeskRoot .qc-group-icon{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#fff;flex-shrink:0}
#pricingDeskRoot .qc-group-count{margin-left:auto;background:var(--soft);color:#6b7684;font-size:.72rem;font-weight:700;border-radius:999px;padding:1px 8px}
#pricingDeskRoot .qc-group-chevron{transition:transform .15s ease;color:#9aa5b1}
#pricingDeskRoot .qc-group-head.collapsed .qc-group-chevron{transform:rotate(-90deg)}
#pricingDeskRoot .qc-item{display:block;width:100%;text-align:left;border:0;background:none;padding:7px 16px 7px 50px;font-size:.84rem;color:#4a525c;border-left:3px solid transparent}
#pricingDeskRoot .qc-item:hover{background:var(--soft)}
#pricingDeskRoot .qc-item.active{background:var(--hitech-primary-light, rgba(0,128,128,.08));color:var(--hitech-primary, #008080);font-weight:600;border-left-color:var(--hitech-primary, #008080)}
#pricingDeskRoot .qc-panel{flex-grow:1;padding:24px 28px;overflow-y:auto;max-height:70vh}
#pricingDeskRoot .qc-product-icon{width:56px;height:56px;border-radius:12px;background:var(--hitech-primary-light, rgba(0,128,128,.08));color:var(--hitech-primary, #008080);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
#pricingDeskRoot .qc-unit-seg .btn.active{background:var(--hitech-primary, #008080);border-color:var(--hitech-primary, #008080);color:#fff}
#pricingDeskRoot #pdcFields label,#pricingDeskRoot #pdcRateSection label{font-size:.78rem;font-weight:600;color:#4a525c}
#pricingDeskRoot .req{color:#e35b5b}
#pricingDeskRoot .qc-hero{border:1px solid transparent;border-radius:12px;padding:14px 16px;height:100%;display:flex;align-items:center;gap:14px}
#pricingDeskRoot .qc-hero.a,#pricingDeskRoot .qc-hero.b{background:var(--hitech-primary-light, rgba(0,128,128,.07));border-color:rgba(0,128,128,.16)}
#pricingDeskRoot .qc-hero-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;background:#fff}
#pricingDeskRoot .qc-hero-icon.a,#pricingDeskRoot .qc-hero-icon.b{color:var(--hitech-primary, #008080)}
#pricingDeskRoot .qc-hero .qc-k{font-size:.78rem;color:#8a94a6;font-weight:600}
#pricingDeskRoot .qc-hero .qc-v{font-size:1.15rem;font-weight:700;color:#1f2937}
@media (max-width:767px){#pricingDeskRoot .qc-embed #pdcWrap{flex-direction:column;max-height:none}#pricingDeskRoot .qc-sidebar{width:100%;max-height:240px}}
#pricingDeskRoot .form{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px 14px}
#pricingDeskRoot .form label{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--muted)}
#pricingDeskRoot .form>*{min-width:0}
#pricingDeskRoot .form label input,#pricingDeskRoot .form label select{font-size:14px;color:var(--ink);width:100%;min-width:0}
#pricingDeskRoot .results{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;margin-top:14px}
#pricingDeskRoot .stat{border:1px solid var(--line);border-radius:8px;padding:10px 12px;background:var(--panel)}
#pricingDeskRoot .stat .k{font-size:12px;color:var(--muted)}
#pricingDeskRoot .stat .v{font-size:19px;font-weight:650;font-variant-numeric:tabular-nums}
#pricingDeskRoot .stat.hi{border-color:var(--accent);box-shadow:inset 3px 0 0 var(--accent)}
#pricingDeskRoot .note{background:var(--soft);border-radius:8px;padding:9px 12px;font-size:12.5px;color:var(--muted);margin-top:12px}
#pricingDeskRoot .kv{display:grid;grid-template-columns:auto 1fr;gap:4px 12px;font-size:13px}
#pricingDeskRoot .kv div:nth-child(odd){color:var(--muted)}
#pricingDeskRoot .hide{display:none!important}
#pricingDeskRoot .qtable input{width:100%;padding:5px 6px}
#pricingDeskRoot .qtable td{vertical-align:middle}
#pricingDeskRoot .totals{margin-left:auto;min-width:280px}
#pricingDeskRoot .totals div{display:flex;justify-content:space-between;padding:4px 0}
#pricingDeskRoot .totals .gt{font-weight:700;font-size:16px;border-top:1px solid var(--line);padding-top:8px}
#pricingDeskRoot #pTable td:first-child{white-space:nowrap}
#pricingDeskRoot #bandLegend{overflow-x:auto}
#pricingDeskRoot #bandLegend .pill{padding:2px 6px;font-size:12px}
#pricingDeskRoot #bandLegend td,#pricingDeskRoot #bandLegend th{padding:6px 5px}
#pricingDeskRoot #printArea{display:none}
@media print{
  body *{visibility:hidden}
  #printArea,#printArea *{visibility:visible}
  #printArea{display:block;position:absolute;left:0;top:0;padding:10mm;font-size:12px;color:#000;background:#fff}
  #printArea table th{background:#eee;position:static}
  #printArea td,#printArea th{border:1px solid #bbb}
}
</style>
<main>
  <div class="pricing-desk-tabs-wrap">
  <ul class="nav nav-pills hitech-tabs-sm mb-0" id="tabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-prices" data-t="prices" type="button" role="tab">
        <i class="bx bx-list-ul"></i> Price list
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-calc" data-t="calc" type="button" role="tab">
        <i class="bx bx-calculator"></i> Calculators
      </button>
    </li>
    @if($canManage)
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-admin" data-t="admin" type="button" role="tab">
        <i class="bx bx-cog"></i> Admin
      </button>
    </li>
    @endif
  </ul>
  </div>
  <div id="overrideBanner" class="banner hide">This browser is using locally edited prices that are not yet published to the team.</div>

  <div class="tab-content">
  <!-- PRICE LIST -->
  <section id="t-prices" class="tab-pane fade show active" role="tabpanel">
    <div class="grid2">
      <div class="panel">
        <div class="pd-row" style="margin-bottom:12px">
          <div class="seg" id="listSeg"></div>
          <div class="search-wrapper-hitech grow" style="min-width:180px;max-width:320px;">
            <i class="bx bx-search text-muted ms-3"></i>
            <input type="search" id="pSearch" class="form-control" placeholder="Search code, product, size…">
          </div>
          <select id="pCat" class="form-select form-select-sm" style="max-width:220px;"></select>
          <span class="small muted" id="pCount" style="white-space:nowrap;flex:0 0 auto"></span>
          <button class="btn" id="pExport" type="button" style="flex:0 0 auto;margin-left:auto"><i class="bx bx-download me-1"></i>Export</button>
        </div>
        <div class="tablewrap"><table id="pTable"></table></div>
        <div class="small muted" style="margin-top:8px" id="pFoot"></div>
      </div>
      <div class="side">
        <div class="panel" id="typePanel">
          <h2>Customer type</h2>
          <div class="muted small" style="margin-bottom:8px">Channel pricing only clears without extra approval when quoting an actual channel partner. Set this before checking a price below.</div>
          <select id="qType" class="grow"><option>Direct customer</option><option>Channel partner</option></select>
        </div>
        <div class="panel" id="checkPanel" style="margin-top:12px">
          <h2>Price check</h2>
          <div class="muted small" id="chkEmpty">Click a product in the list to check a customer price against the approval bands.</div>
          <div id="chkBody" class="hide">
            <div style="font-weight:650" id="chkName"></div>
            <div class="muted small" id="chkSpec" style="margin-bottom:10px"></div>
            <div class="kv" id="chkTiers"></div>
            <div class="form" style="margin-top:12px;grid-template-columns:1fr 1fr">
              <label><span>Offered price (₹/<span id="chkUnit"></span>)</span><input type="number" id="chkPrice" step="0.01" min="0"></label>
              <label>Quantity<input type="number" id="chkQty" step="1" min="0" value="1"></label>
            </div>
            <div id="chkResult" style="margin-top:12px"></div>
            <div id="chkWarn"></div>
          </div>
        </div>
        <div class="panel" style="margin-top:12px">
          <h2>Approval bands</h2>
          <div id="bandLegend" class="small"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- CALCULATORS -->
  <section id="t-calc" class="tab-pane fade" role="tabpanel">
    <div class="qc-embed">
      <div id="pdcLoading" class="text-muted small py-5 text-center">Loading…</div>
      <div id="pdcWrap" class="d-none">
        <div class="qc-sidebar">
          <div class="qc-sidebar-head">
            <div class="fw-bold">Product Catalogue</div>
            <div class="text-muted small">Search and select a product</div>
            <div class="position-relative mt-2">
              <i class="bx bx-search qc-search-icon"></i>
              <input type="text" id="pdcSearch" class="form-control form-control-sm ps-4" placeholder="Search product, category…" autocomplete="off">
            </div>
          </div>
          <div id="pdcList" class="qc-list"></div>
        </div>
        <div class="qc-panel">
          <div id="pdcEmpty" class="text-muted text-center py-5">
            <i class="bx bx-left-arrow-alt bx-sm d-block mb-2"></i>
            Pick a product from the catalogue on the left.
          </div>
          <div id="pdcBody" class="d-none">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
              <div class="d-flex align-items-start gap-3">
                <div class="qc-product-icon" id="pdcProductIcon"><i class="bx bx-cube"></i></div>
                <div>
                  <h5 class="mb-0 fw-bold" id="pdcTitle"></h5>
                  <div class="text-muted small" id="pdcDesc" style="max-width:480px;"></div>
                </div>
              </div>
              <div class="btn-group btn-group-sm qc-unit-seg" id="pdcUnitSeg" role="group"></div>
            </div>
            <div class="row g-3 mt-1" id="pdcFields"></div>
            <div id="pdcRateSection"></div>
            <div class="row g-3 mt-2" id="pdcResults"></div>
            <div id="pdcBand" class="mt-3"></div>
            <div id="pdcError" class="alert alert-danger small mt-3 d-none"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- REFERENCE -->
  <section id="t-ref" class="hide">
    <div class="panel">
      <div class="pd-row" style="margin-bottom:12px">
        <div class="seg" id="refSeg"><button data-r="hsn" class="on">HSN &amp; GST</button><button data-r="pack">Standard packing</button><button data-r="shrink">Shrink film sizes</button><button data-r="sugg">Rules of thumb</button></div>
        <input type="search" id="refSearch" class="grow" placeholder="Search…">
      </div>
      <div id="refBody"></div>
    </div>
  </section>

  @if($canManage)
  <!-- ADMIN -->
  <section id="t-admin" class="tab-pane fade" role="tabpanel">
    <div id="adminBody">
      <div class="panel">
        <h2>Publish workflow</h2>
        <ol class="small muted" style="margin:0 0 10px;padding-left:18px">
          <li>Edit prices below.</li>
          <li>Click <b>Save to server</b>. Every viewer sees the new prices on refresh — no file to download or re-upload.</li>
        </ol>
        <div class="pd-row">
          <label class="small muted">Price list version label <input type="text" id="aVer" style="width:220px"></label>
          <button class="btn primary" id="aSaveServer">Save to server</button>
          <button class="btn danger" id="aReset">Discard local edits</button>
        </div>
        <div id="aMsg" class="small" style="margin-top:8px"></div>
      </div>
      <div class="panel" style="margin-top:12px">
        <h2>Discount rules (% off DLP)</h2>
        <div class="form" id="aRules"></div>
        <div class="pd-row" style="margin-top:10px"><button class="btn" id="aRecalc">Recalculate all tier prices from DLP</button><span class="small muted">Lubricants round to whole rupees; other lists to paise.</span></div>
        <h3>Calculator factors</h3>
        <div class="form" id="aFactors"></div>
      </div>
      <div class="panel" style="margin-top:12px">
        <div class="pd-row" style="margin-bottom:10px">
          <h2 style="margin:0">Edit price list</h2>
          <div class="seg" id="aListSeg"></div>
          <div class="search-wrapper-hitech grow" style="min-width:160px;max-width:280px;">
            <i class="bx bx-search text-muted ms-3"></i>
            <input type="search" id="aSearch" class="form-control" placeholder="Filter…">
          </div>
          <button class="btn primary" id="aAdd" style="flex:0 0 auto;margin-left:auto">+ Add product</button>
        </div>
        <p class="small muted" style="margin-top:0">Changing DLP recalculates the three tier prices automatically. You can still overwrite a tier by hand.</p>
        <div class="tablewrap"><table id="aTable" class="qtable"></table></div>
      </div>
    </div>
  </section>
  @endif
  </div>
</main>
<div id="printArea"></div>

<script id="seed" type="application/json">/*PRICEDATA_START*/{!! $seedJson !!}/*PRICEDATA_END*/</script>
<script>
(function(){
'use strict';
const CAN_MANAGE = @json($canManage);
const SAVE_URL = @json(route('sales-visits.pricing-desk.save'));
const SEED = JSON.parse(document.getElementById('seed').textContent.replace('/*PRICEDATA_START*/','').replace('/*PRICEDATA_END*/',''));
const LS = {
  get(k){try{const v=localStorage.getItem(k);return v?JSON.parse(v):null}catch(e){return null}},
  set(k,v){try{localStorage.setItem(k,JSON.stringify(v))}catch(e){}},
  del(k){try{localStorage.removeItem(k)}catch(e){}}
};
let DATA = LS.get('spd_override') || clone(SEED);
function clone(o){return JSON.parse(JSON.stringify(o))}
const LISTS=()=>DATA.lists||[{k:'vci',label:'VCI Packaging',short:'VCI',round:2,rich:true},{k:'lubes',label:'Lubricants & RP Oils',short:'Lubes',round:0,rich:false}];
const LM=k=>LISTS().find(x=>x.k===k)||{k,label:k,short:k,round:2,rich:true};
function segHtml(cur){return LISTS().filter(m=>DATA[m.k]).map(m=>`<button data-l="${m.k}" class="${m.k===cur?'on':''}">${esc(m.label)}</button>`).join('')}
const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
const esc=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const fmt=(n,d=2)=>n==null||isNaN(n)?'–':Number(n).toLocaleString('en-IN',{minimumFractionDigits:d,maximumFractionDigits:d});
const fmt0=n=>fmt(n,0);
const r2=n=>Math.round(n*100)/100;

/* ---------- approval bands ---------- */
const BAND_TXT=['No approval needed','GM approval','Channel price','Director approval'];
function tierNames(list){const r=DATA.rules[list];return [LM(list).dlpLabel||`DLP`,`No approval (${r[0]}% off)`,`GM (${r[1]}% off)`,`Channel (${r[2]}% off)`]}
// returns {lvl, label, disc}
function band(price, dlp, t, custType, force){
  if(price==null||isNaN(price)||price<=0) return null;
  if(force) return {lvl:3,eff:3,label:force,disc:dlp>0?(1-price/dlp)*100:null,above:false,forced:true};
  const tol=0.005;
  let lvl;
  if(price>=t[0]-tol) lvl=0; else if(price>=t[1]-tol) lvl=1; else if(price>=t[2]-tol) lvl=2; else lvl=3;
  let label=BAND_TXT[lvl];
  if(lvl===2){ label = custType==='Channel partner' ? 'Channel price — OK for channel partner' : 'Channel price — Director approval for a direct customer'; }
  if(lvl===3) label='Below channel floor — Director approval';
  const disc = dlp>0 ? (1-price/dlp)*100 : null;
  let eff = lvl; if(lvl===2 && custType!=='Channel partner') eff=3;
  return {lvl, eff, label, disc, above: dlp>0 && price>dlp};
}
function pill(b){ if(!b) return '<span class="pill bx">—</span>'; if(b.forced) return '<span class="pill b3">Management approval</span>'; const cls=b.lvl===2&&b.eff===2?'b0':'b'+b.lvl; return `<span class="pill ${b.lvl===2?'b2':cls}">${esc(b.lvl===2?(b.eff===2?'Channel OK':'Channel → Director'):BAND_TXT[b.lvl])}</span>`; }

/* ---------- tabs ---------- */
// Tab switching is native Bootstrap (data-bs-toggle="tab") now, not a
// custom show/hide — this just reacts to a pane becoming active to lazily
// render it (the calculator loads its data on first visit; Admin re-reads
// DATA each time in case something else just changed it).
let calcInited=false;
$('#tabs').addEventListener('shown.bs.tab',e=>{
  const t=e.target.dataset.t;
  if(t==='calc'&&!calcInited){calcInited=true;initCalcTab()}
  if(t==='admin'&&CAN_MANAGE)renderAdmin();
  LS.set('spd_tab',t);
});

/* ---------- PRICE LIST ---------- */
let curList='vci', selIdx=null;
function renderSegs(){$('#listSeg').innerHTML=segHtml(curList);if(CAN_MANAGE)$('#aListSeg').innerHTML=segHtml(aList)}
$('#listSeg').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;curList=b.dataset.l;$$('#listSeg button').forEach(x=>x.classList.toggle('on',x===b));selIdx=null;fillCats();renderPrices();renderCheck()});
$('#pSearch').addEventListener('input',renderPrices);
$('#pCat').addEventListener('change',renderPrices);
function fillCats(){const cats=[...new Set(DATA[curList].map(x=>x.cat))];$('#pCat').innerHTML='<option value="">All categories</option>'+cats.map(c=>`<option>${esc(c)}</option>`).join('')}
function itemText(x){return [x.code,x.name,x.spec,x.size,x.colour,x.cat].join(' ').toLowerCase()}
let sortKey=null, sortDir=1;
const SORT_ACCESSORS={code:x=>x.code||'',name:x=>x.name||'',unit:x=>x.unit||'',dlp:x=>x.dlp||0,t0:x=>x.t[0]||0,t1:x=>x.t[1]||0,t2:x=>x.t[2]||0,cls:x=>x.cls||''};
function sortTh(key,label,extra){
  const active=sortKey===key;
  const icon=active?(sortDir===1?'bx-chevron-up':'bx-chevron-down'):'bx-sort-alt-2';
  return `<th data-sk="${key}" style="cursor:pointer;user-select:none">${esc(label)} <i class="bx ${icon}" style="font-size:12px;opacity:${active?'1':'.4'}"></i>${extra||''}</th>`;
}
function renderPrices(){
  const q=$('#pSearch').value.trim().toLowerCase(), cat=$('#pCat').value, L=DATA[curList], tn=tierNames(curList);
  const isV=LM(curList).rich, dp=LM(curList).round;
  const r=DATA.rules[curList];
  let h=`<thead><tr>${sortTh('code','Code')}${isV?sortTh('name','Product'):''}${sortTh('unit','Unit')}${sortTh('dlp',esc(tn[0]))}${sortTh('t0','No approval',`<div class="small muted">${r[0]}% off</div>`)}${sortTh('t1','GM',`<div class="small muted">${r[1]}% off</div>`)}${sortTh('t2','Channel',`<div class="small muted">${r[2]}% off</div>`)}${isV?'':sortTh('cls','Class')}</tr></thead><tbody>`;
  let rows=L.map((x,i)=>({x,i})).filter(({x})=>(!cat||x.cat===cat)&&(!q||itemText(x).includes(q)));
  if(sortKey){const acc=SORT_ACCESSORS[sortKey];rows=rows.slice().sort((a,b)=>{const av=acc(a.x),bv=acc(b.x);return (av<bv?-1:av>bv?1:0)*sortDir})}
  let last=null;
  rows.forEach(({x,i})=>{
    if(x.cat!==last){h+=`<tr class="cat"><td colspan="7">${esc(x.cat)}</td></tr>`;last=x.cat}
    h+=`<tr class="item${i===selIdx?' sel':''}" data-i="${i}"><td><b>${esc(x.code)}</b></td>${isV?`<td>${esc(x.name)}<div class="small muted">${esc([x.spec,x.size,x.colour].filter(Boolean).join(' · '))}</div>${x.note?`<div class="small" style="color:var(--bad)">${esc(x.note)}</div>`:''}</td>`:''}<td>${esc(x.unit||'')}</td><td class="n">${x.dlp?'₹ '+fmt(x.dlp,dp):`<span class="muted">${x.force?'on request':'net'}</span>`}</td><td class="n">${x.t[0]?'₹ '+fmt(x.t[0],dp):'–'}</td><td class="n">${x.t[1]?'₹ '+fmt(x.t[1],dp):'–'}</td><td class="n">${x.t[2]?'₹ '+fmt(x.t[2],dp):'–'}</td>${isV?'':`<td>${esc(x.cls||'')}</td>`}</tr>`;
  });
  $('#pTable').innerHTML=h+'</tbody>';
  $('#pFoot').textContent=`${rows.length} of ${L.length} products · ${LM(curList).label} · ${LM(curList).source||DATA.version}`;
  $('#pCount').textContent=`Showing ${rows.length} product${rows.length===1?'':'s'}`;
}
$('#pTable').addEventListener('click',e=>{
  const th=e.target.closest('th[data-sk]');
  if(th){const k=th.dataset.sk;sortDir=(sortKey===k)?-sortDir:1;sortKey=k;renderPrices();return}
  const tr=e.target.closest('tr.item');if(!tr)return;selIdx=+tr.dataset.i;renderPrices();renderCheck();$('#chkPrice').focus()
});
$('#pExport').addEventListener('click',()=>{
  const rows=[...document.querySelectorAll('#pTable tbody tr.item')].map(tr=>tr.dataset.i).map(i=>DATA[curList][+i]);
  if(!rows.length){alert('Nothing to export — adjust the filters first.');return}
  const isV=LM(curList).rich, tn=tierNames(curList);
  const head=['Code',...(isV?['Product']:[]),'Unit',tn[0],tn[1],tn[2],tn[3],...(isV?[]:['Class'])];
  const csvCell=v=>`"${String(v==null?'':v).replace(/"/g,'""')}"`;
  const lines=[head.map(csvCell).join(',')];
  rows.forEach(x=>{
    const cells=[x.code,...(isV?[x.name]:[]),x.unit||'',x.dlp||'',x.t[0]||'',x.t[1]||'',x.t[2]||'',...(isV?[]:[x.cls||''])];
    lines.push(cells.map(csvCell).join(','));
  });
  const blob=new Blob([lines.join('\r\n')],{type:'text/csv;charset=utf-8;'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`${LM(curList).label.replace(/\s+/g,'-')}-price-list.csv`;a.click();URL.revokeObjectURL(a.href);
});
function renderCheck(){
  const x=selIdx==null?null:DATA[curList][selIdx];
  $('#chkEmpty').classList.toggle('hide',!!x);$('#chkBody').classList.toggle('hide',!x);if(!x)return;
  const isV=LM(curList).rich, tn=tierNames(curList);
  $('#chkName').textContent=x.code+(x.name?' — '+x.name:'');
  $('#chkSpec').textContent=[x.spec,x.size,x.colour,x.cat].filter(Boolean).join(' · ');
  $('#chkUnit').textContent=x.unit||'unit';
  $('#chkTiers').innerHTML=[[tn[0],x.dlp],[tn[1],x.t[0]],[tn[2],x.t[1]],[tn[3],x.t[2]]].map(([k,v])=>`<div>${esc(k)}</div><div><b>₹ ${v?fmt(v,LM(curList).round):'—'}</b></div>`).join('');
  $('#chkPrice').value=x.t[0]; updCheck();
}
function isKgOnly(x,l){l=l||curList;return !!x.perPc || (l==='vci' && /KG PRICE ONLY PER PC/i.test(x.cat||'') && (x.unit||'').toUpperCase()==='KG')}
function updCheck(){
  const x=DATA[curList][selIdx]; if(!x)return;
  const p=parseFloat($('#chkPrice').value), q=parseFloat($('#chkQty').value)||0, b=band(p,x.dlp,x.t,$('#qType').value,x.force);
  if(!b){$('#chkResult').innerHTML='';return}
  $('#chkResult').innerHTML=`<div class="pd-row" style="justify-content:space-between">${pill(b)}<span class="small muted">${b.disc!=null?fmt(b.disc,1)+'% off '+esc(tierNames(curList)[0]):'net-priced item'}</span></div>
  <div class="small" style="margin-top:6px">${esc(b.label)}${b.above?' · above DLP':''}</div>
  <div class="kv" style="margin-top:8px"><div>Line value</div><div><b>₹ ${fmt(p*q)}</b> + GST</div></div>`;
  $('#chkWarn').innerHTML=(isKgOnly(x)?`<div class="note" style="color:var(--bad)"><b>Do not quote per kg.</b> This product must be quoted per piece — use the Calculators tab to convert this rate to ₹/pc.</div>`:'')+(x.note&&!isKgOnly(x)||x.force?`<div class="note" style="color:var(--bad)">${esc(x.note||x.force)}</div>`:'');
}
$('#chkPrice').addEventListener('input',updCheck);$('#chkQty').addEventListener('input',updCheck);
$('#qType').addEventListener('change',updCheck);
function renderLegend(){
  const r=DATA.rules;
  const ls=LISTS().filter(m=>r[m.k]);const cell=(k,op)=>ls.map(m=>`<td class="n">${op} ${r[m.k][k]}%</td>`).join('');
  $('#bandLegend').innerHTML=`<table><tr><th></th>${ls.map(m=>`<th class="n">${esc(m.short)}</th>`).join('')}</tr>
  <tr><td><span class="pill b0">No approval</span></td>${cell(0,'≤')}</tr>
  <tr><td><span class="pill b1">GM approval</span></td>${cell(1,'≤')}</tr>
  <tr><td><span class="pill b2">Channel price</span></td>${cell(2,'≤')}</tr>
  <tr><td><span class="pill b3">Director approval</span></td>${cell(2,'&gt;')}</tr></table>
  <p class="muted" style="margin:8px 0 0">Discounts are off DLP (MRP for Dr. Bio, list rate for Fillezy). Channel price is for channel partners only; for a direct customer it needs Director approval. Set customer type above.</p>`;
}

/* ---------- CALCULATORS ---------- */
const F=()=>DATA.factors;
function prod(code,unit){return DATA.vci.find(x=>x.code===code&&(!unit||x.unit===unit))}
// field helpers: d = dimension (inch|mm toggle), n = plain number
const d=(k,label,inDef,mmDef)=>({k,label,dim:true,inDef,mmDef});
const n=(k,label,def,suf)=>({k,label,def,suf});
const sel=(k,label,opts,def)=>({k,label,opts,def});
const CALCS=[
 {id:'2d',g:'VCI plastic',t:'2D VCI bag',desc:'Flat bag, two layers. Weight = L × W × micron ÷ 3,300,000 × 4 × wastage.',
  fields:[d('L','Length',8,200),d('W','Width',10,250),n('mic','Thickness (micron)',100)],rate:{unit:'KG',codes:['VCI 702','VCI 703 G','VCI 703 FL'],def:'VCI 702'},
  calc(v){const kg=v.L*v.W*v.mic/3300000*4*F().vci;return{kg,unit:'pc',desc:`2D VCI bag ${v._dims('L','W')}, ${v.mic} mic`}}},
 {id:'sheet',g:'VCI plastic',t:'VCI plastic sheet (single layer)',desc:'Cut sheet, one layer. Weight = L × W × micron ÷ 6,600,000 × 4 × wastage.',
  fields:[d('L','Length',28,700),d('W','Width',39.37,1000),n('mic','Thickness (micron)',50)],rate:{unit:'KG',codes:['VCI 701'],def:'VCI 701'},
  calc(v){const kg=v.L*v.W*v.mic/6600000*4*F().vci;return{kg,unit:'pc',desc:`VCI plastic sheet ${v._dims('L','W')}, ${v.mic} mic`}}},
 {id:'3d6',g:'VCI plastic',t:'3D pallet bag — with flap (6-side)',desc:'Enter the BOX / pallet size. Area = 2(LW + HW + LH).',
  fields:[d('L','Box length',48,1200),d('W','Box width',40,1000),d('H','Box height',40,1000),n('mic','Thickness (micron)',100)],rate:{unit:'KG',codes:['VCI 703 FL','VCI 703 G'],def:'VCI 703 FL'},
  calc(v){const kg=(v.L*v.W+v.H*v.W+v.L*v.H)*2*v.mic/1550000*F().vci;return{kg,unit:'pc',desc:`VCI 3D bag with flap (6-side) for box ${v._dims('L','W','H')}, ${v.mic} mic`}}},
 {id:'3d5',g:'VCI plastic',t:'3D pallet bag — no flap (5-side)',desc:'Open-top cover. Area = LW + 2HW + 2LH.',
  fields:[d('L','Box length',48,1200),d('W','Box width',40,1000),d('H','Box height',40,1000),n('mic','Thickness (micron)',100)],rate:{unit:'KG',codes:['VCI 703 FL','VCI 703 G'],def:'VCI 703 FL'},
  calc(v){const kg=(v.L*v.W+2*v.H*v.W+2*v.L*v.H)*v.mic/1550000*F().vci5;return{kg,unit:'pc',desc:`VCI 3D cover without flap (5-side) for box ${v._dims('L','W','H')}, ${v.mic} mic`}}},
 {id:'liner',g:'VCI plastic',t:'Gusseted carton liner',desc:'Enter the CARTON size. Adds 2" to (L+W) and 4" to (H+W) for overlap.',
  fields:[d('L','Carton length',15,381),d('W','Carton width',12,305),d('H','Carton height',12,305),n('mic','Thickness (micron)',75)],rate:{unit:'KG',codes:['VCI 703 G','VCI 702'],def:'VCI 703 G'},
  calc(v){const g=(v.L+v.W+2)*(v.H+v.W+4)/3300*v.mic*4*F().vci;return{kg:g/1000,unit:'pc',desc:`VCI gusseted carton liner for carton ${v._dims('L','W','H')}, ${v.mic} mic`}}},
 {id:'roll',g:'VCI plastic',t:'Tube roll / sheet roll',desc:'Width in inch or mm, length in metres. Tube = two layers.',
  fields:[sel('type','Roll type',['Tube roll','Sheet roll'],'Tube roll'),d('W','Width',14,350),n('Lm','Roll length (metres)',500),n('mic','Thickness (micron)',35)],rate:{unit:'KG',codes:['VCI 701'],def:'VCI 701'},
  calc(v){const wm=v._mm?v.W*25.4/1000:v.W/40;const kg=wm*v.Lm*v.mic*(v.type==='Tube roll'?2:1)*F().vci/1000;return{kg,unit:'roll',desc:`VCI ${v.type.toLowerCase()} ${v._dims('W')} × ${v.Lm} m, ${v.mic} mic`}}},
 {id:'paper',g:'VCI paper & barrier',t:'VCI paper — cut sheet',desc:'Price per sheet from ₹/sqm. Area includes 10% wastage.',
  fields:[d('L','Length',5,1000),d('W','Width',8,1000),n('gsm','GSM',70)],rate:{unit:'SQM',codes:['VCI 602','VCI 603','VCI 604','VCI 605','VCI 606','VC 607','VCI 608','VCI 609','VCI 612','VCI 613','VCI 614','VCI 615','VCI 617','VCI 619','VCI 631','VCI 641'],def:'VCI 602'},
  calc(v){const sqm=v.L*v.W/1550*F().vci;return{sqm,kg:sqm*v.gsm/1000,unit:'pc',desc:`VCI paper sheet ${v._dims('L','W')}, ${v.gsm} GSM`}}},
 {id:'bag6',g:'VCI paper & barrier',t:'HDPE paper / ALBAR / Metbar bag — 6-side',desc:'Laminated or barrier 3D bag with flap, priced per sqm.',
  fields:[d('L','Box length',26,1000),d('W','Box width',38,1000),d('H','Box height',48,1000),n('gsm','GSM / micron',180)],rate:{unit:'SQM',codes:['VCI 813','VCI 712','VCI 713','VCI 720','NVCI 995','NVCI 997','VCI 999','VCI 762','VCI 764','VCI 882','VCI 884','VCI 886'],def:'VCI 813'},
  calc(v){const sqm=(2*v.L*v.W+2*(v.W*v.H+v.L*v.H))/1550*F().vci;return{sqm,kg:sqm*v.gsm/1000,unit:'pc',desc:`6-side bag for box ${v._dims('L','W','H')}`}}},
 {id:'bag5',g:'VCI paper & barrier',t:'HDPE paper / ALBAR / Metbar bag — 5-side',desc:'Open-top cover, priced per sqm.',
  fields:[d('L','Box length',42,700),d('W','Box width',42,600),d('H','Box height',24,590),n('gsm','GSM / micron',90)],rate:{unit:'SQM',codes:['VCI 813','VCI 712','VCI 713','VCI 720','NVCI 995','NVCI 997','VCI 999','VCI 762','VCI 764','VCI 882','VCI 884','VCI 886'],def:'VCI 813'},
  calc(v){const sqm=(v.L*v.W+2*(v.W*v.H+v.L*v.H))/1550*F().vci;return{sqm,kg:sqm*v.gsm/1000,unit:'pc',desc:`5-side cover for box ${v._dims('L','W','H')}`}}},
 {id:'kgsqm',g:'VCI paper & barrier',t:'₹/kg ⇄ ₹/sqm converter',desc:'Uses GSM. ₹/sqm = ₹/kg × GSM ÷ 1000.',noUnits:true,
  fields:[n('gsm','GSM',90),n('pkg','Price ₹/kg (leave 0 to convert from ₹/sqm)',250),n('psqm','Price ₹/sqm',0)],
  calc(v){return{info:[['₹ per sqm',v.pkg>0?v.pkg*v.gsm/1000:v.psqm,2,true],['₹ per kg',v.pkg>0?v.pkg:(v.gsm?1000*v.psqm/v.gsm:0),2,true]]}}},
 {id:'tuff',g:'Tuffpaulin',t:'Tuffpaulin sheet (made-up size)',desc:'Size in feet. Weight adds 80 g for eyelets/rope. FG size price adds 10% for finishing.',noUnits:true,
  fields:[n('Wf','Width (ft)',22),n('Lf','Length (ft)',8),sel('gsm','GSM',['70','90','120','150','200','250','300'],'120'),n('oq','Order quantity (kg) for pcs estimate',1000)],rate:{unit:'SQM',codes:['TUFF70','TUFF90','TUFF120','TUFF150','TUFF200','TUFF250','TUFF300'],def:'TUFF120',auto:v=>'TUFF'+v.gsm},
  calc(v){const sqft=v.Wf*v.Lf, sqm=sqft/10.7639, kg=sqft*(+v.gsm)/10.76/1000+0.08;return{sqm,kg,unit:'pc',fg:F().tuffFG,desc:`Tuffpaulin sheet ${v.Wf} × ${v.Lf} ft, ${v.gsm} GSM`,extra:r=>[['Rate ₹/sqft',r/10.7639,2],['Sheets in order qty',kg?v.oq/kg:0,0]]}}},
 {id:'conv1',g:'Size converters',t:'3D box → 2D / gusset bag (with flap)',desc:'Type 1 — box size known, bag closes over the top.',
  fields:[d('L','Box length',52,450),d('W','Box width',44,350),d('H','Box height',85,675)],
  calc(v){const a=v._mm?25:1,b=v._mm?50:2,u=v._mm?'mm':'inch',ce=(x,s)=>Math.ceil(x/s)*s, raw=k=>v._raw[k];const L=raw('L'),W=raw('W'),H=raw('H');
   return{info:[[`2D bag width (${u})`,W+L+a,0,true],[`2D bag length (${u})`,W+H+b,0,true],[`Gusset bag width (${u})`,ce(L,10)+a,0],[`Gusset each side G1 = G2 (${u})`,W/2,1],[`Gusset bag length (${u})`,ce(W+H+b,5),0]]}}},
 {id:'conv2',g:'Size converters',t:'3D bag without flap → 2D / gusset',desc:'Type 2 — open-top bag.',
  fields:[d('L','Box length',52,425),d('W','Box width',44,275),d('H','Box height',85,350)],
  calc(v){const a=v._mm?25:1,u=v._mm?'mm':'inch',ce=(x,s)=>Math.ceil(x/s)*s,L=v._raw.L,W=v._raw.W,H=v._raw.H;
   return{info:[[`2D bag width (${u})`,W+L+a,0,true],[`2D bag length (${u})`,H+W/2+a,1,true],[`Gusset bag width (${u})`,ce(L,10)+a,0],[`Gusset each side (${u})`,W/2,1],[`Gusset bag length (${u})`,H+W/2+a,1]]}}},
 {id:'part',g:'Size converters',t:'Round part → 2D bag size',desc:'For shafts, rolls, cylinders. Width = circumference ÷ 2 + 1″; length = part length + dia + 2″.',
  fields:[d('D','Diameter',4,100),d('L','Part length',72,1800)],
  calc(v){const a=v._mm?25:1,b=v._mm?50:2,u=v._mm?'mm':'inch',D=v._raw.D,L=v._raw.L,c=3.14*D;
   return{info:[[`Circumference (${u})`,c,1],[`2D bag width (${u})`,c/2+a,1,true],[`2D bag length (${u})`,L+D+b,1,true]]}}},
 {id:'spec3d',g:'Size converters',t:'3D bag production spec',desc:'Tube, cutting length and gusset for the plant (inch output).',
  fields:[d('L','Box length',100,1200),d('W','Box width',100,1000),d('H','Box height',100,1000)],
  calc(v){const L=v._raw.L,W=v._raw.W,H=v._raw.H;if(v._mm)return{info:[['Tube (inch)',(H+26)/25.4,2,true],['Cutting (inch)',Math.ceil(((L+W)*2+104)/25.4),0,true],['Gusset (inch)',(L+26)/25.4,2],['× (inch)',(W+26)/25.4,2]]};
   return{info:[['Tube (inch)',H+1,0,true],['Cutting (inch)',Math.ceil((L+W)*2+4),0,true],['Gusset (inch)',L+1,0],['× (inch)',W+1,0]]}}},
 {id:'kg2sqm',g:'Size converters',t:'Plastic kg → square metres',desc:'Coverage from weight. sqm = kg × 1000 ÷ (micron × 0.92).',noUnits:true,
  fields:[n('kg','Quantity (kg)',200),n('mic','Thickness (micron)',100)],
  calc(v){return{info:[['Square metres',v.mic?v.kg*1000/(v.mic*0.92):0,1,true]]}}},
 {id:'pouch',g:'Size converters',t:'Emitter / desiccant quantity',desc:'Enclosure volume → number of pouches. VCI 4200: one 20 g pouch per 4 cu ft. VCI 19000: one 50 g pouch per 10 cu ft.',
  fields:[d('L','Length',87,1000),d('W','Width',48,1000),d('H','Height',94,1000)],
  calc(v){const cft=v.L*v.W*v.H/1728;return{info:[['Volume (cu m)',cft/35.3147,2],['Volume (cu ft)',cft,1],['VCI 4200 — 20 g pouches',Math.ceil(cft/4),0,true],['VCI 19000 — 50 g pouches',Math.ceil(cft/10),0,true]]}}},
 {id:'box',g:'Size converters',t:'Pieces per box',desc:'Packing count from box weight and bag weight. Bundle: 100–500 pcs/box → 50 per sleeve; 500–2000 → 100; 2000–4000 → 200; 4000–8000 → 500.',noUnits:true,
  fields:[n('bk','Box weight (kg)',20),n('gb','Weight per bag (grams)',34)],
  calc(v){const p=v.gb?v.bk*1000/v.gb:0;const s=p<=500?50:p<=2000?100:p<=4000?200:500;return{info:[['Pieces per box',Math.floor(p),0,true],['Pieces per sleeve',s,0]]}}},
 {id:'db2d',g:'Dr. Bio compostable',t:'Dr. Bio 2D bag',desc:'Compostable film; wastage/density factor 1.3.',
  fields:[d('L','Length',12,300),d('W','Width',10,250),n('mic','Thickness (micron)',35)],rate:{unit:'KG',list:'drbio',codes:['DB-1005','DB-1006','DB-1007','DB-1008','DB-1009','DB-1010','DB-1011','DB-1012','DB-1013','DB-1014','DB-1015','DB-1016','DB-1017','DB-1018','DB-1019','DB-1020'],def:'DB-1005'},
  calc(v){const kg=v.L*v.W*v.mic/3300000*4*F().drbio;return{kg,unit:'pc',desc:`Dr. Bio compostable 2D bag ${v._dims('L','W')}, ${v.mic} mic`}}},
 {id:'db3d6',g:'Dr. Bio compostable',t:'Dr. Bio 3D bag — with flap',desc:'Enter the box size.',
  fields:[d('L','Box length',37,1280),d('W','Box width',48,880),d('H','Box height',20,630),n('mic','Thickness (micron)',150)],rate:{unit:'KG',list:'drbio',codes:['DB-1005','DB-1006','DB-1007','DB-1008','DB-1009','DB-1010','DB-1011','DB-1012','DB-1013','DB-1014','DB-1015','DB-1016','DB-1017','DB-1018','DB-1019','DB-1020'],def:'DB-1005'},
  calc(v){const kg=(v.L*v.W+v.H*v.W+v.L*v.H)*2*v.mic/1550000*F().drbio;return{kg,unit:'pc',desc:`Dr. Bio 3D bag with flap for box ${v._dims('L','W','H')}, ${v.mic} mic`}}},
 {id:'db3d5',g:'Dr. Bio compostable',t:'Dr. Bio 3D bag — no flap',desc:'Enter the box size.',
  fields:[d('L','Box length',41,1000),d('W','Box width',39.37,1000),d('H','Box height',39.37,1000),n('mic','Thickness (micron)',100)],rate:{unit:'KG',list:'drbio',codes:['DB-1005','DB-1006','DB-1007','DB-1008','DB-1009','DB-1010','DB-1011','DB-1012','DB-1013','DB-1014','DB-1015','DB-1016','DB-1017','DB-1018','DB-1019','DB-1020'],def:'DB-1005'},
  calc(v){const kg=(v.L*v.W+2*v.H*v.W+2*v.L*v.H)*v.mic/1550000*F().drbio;return{kg,unit:'pc',desc:`Dr. Bio 3D cover without flap for box ${v._dims('L','W','H')}, ${v.mic} mic`}}},
 {id:'dbcarry',g:'Dr. Bio compostable',t:'Carry bag / T-shirt bag with gusset',desc:'Gusset = left + right together (e.g. 4.5″ + 4.5″ = 9″).',
  fields:[d('L','Length',26,660),d('W','Width',14,355),d('G','Total gusset',9,230),n('mic','Thickness (micron)',38)],rate:{unit:'KG',list:'drbio',codes:['DB-1001','DB-1002','DB-1003','DB-1004'].concat(['DB-1033','DB-1034','DB-1035','DB-1036','DB-1037','DB-1038','DB-1039','DB-1040','DB-1041','DB-1042','DB-1043','DB-1044']),def:'DB-1001'},
  calc(v){const kg=(v.W+v.G)*v.L*v.mic*4*F().drbio/3300/1000;return{kg,unit:'pc',desc:`Dr. Bio carry bag ${v._dims('W','L')} + ${v._dims('G')} gusset, ${v.mic} mic`}}},
 {id:'dbcourier',g:'Dr. Bio compostable',t:'Courier bag (tamper-proof tape)',desc:'Flap + tape. Suggested: 9×12+2 → 35 µ (3 kg); 13×16+2 → 40 µ (5 kg); 16×20+2 → 40 µ (8 kg); 18×24+2 → 45 µ (15 kg).',
  fields:[d('W','Width',8,200),d('L','Length',6,150),d('Fl','Flap',2,50),n('mic','Thickness (micron)',35),n('tape','Tape cost ₹ per inch of width',0.04)],rate:{unit:'KG',list:'drbio',codes:['DB-1021','DB-1022','DB-1023','DB-1024','DB-1025','DB-1026','DB-1027','DB-1028','DB-1029','DB-1030','DB-1031','DB-1032'],def:'DB-1021'},
  calc(v){const kg=(v.L+v.Fl)*v.W*v.mic/3300*4*F().drbio/1000;return{kg,add:v.tape*v.W,unit:'pc',desc:`Dr. Bio courier bag ${v._dims('W','L')} + ${v._dims('Fl')} flap, ${v.mic} mic, tamper-proof tape`}}},
 {id:'dbreclose',g:'Dr. Bio compostable',t:'Reclosable / seal-tape bag',desc:'Bag with flap and reclosable seal tape.',
  fields:[d('W','Width',10,300),d('L','Length',12,250),d('Fl','Flap',2,50),n('mic','Thickness (micron)',25),n('tape','Tape cost ₹ per inch of width',0.016)],rate:{unit:'KG',list:'drbio',codes:['DB-1033','DB-1034','DB-1035','DB-1036','DB-1037','DB-1038','DB-1039','DB-1040','DB-1041','DB-1042','DB-1043','DB-1044'].concat(['DB-1005','DB-1006','DB-1007','DB-1008','DB-1009','DB-1010','DB-1011','DB-1012','DB-1013','DB-1014','DB-1015','DB-1016','DB-1017','DB-1018','DB-1019','DB-1020']),def:'DB-1033'},
  calc(v){const kg=(v.L+v.Fl)*v.W*v.mic/3300*4*F().drbio/1000;return{kg,add:v.tape*v.W,unit:'pc',desc:`Dr. Bio reclosable bag ${v._dims('W','L')} + ${v._dims('Fl')} flap, ${v.mic} mic`}}},
 {id:'dbgarb',g:'Dr. Bio compostable',t:'Garbage bags (per roll)',desc:'Priced per roll of bags.',
  fields:[d('L','Length',30,760),d('W','Width',28,710),n('mic','Thickness (micron)',20),n('per','Bags per roll',15)],rate:{unit:'KG',list:'drbio',codes:['DB-1058','DB-1059','DB-1060','DB-1061','DB-1062','DB-1063'].concat(['DB-1049','DB-1050','DB-1051','DB-1052','DB-1053','DB-1054','DB-1055','DB-1056','DB-1057']),def:'DB-1058'},
  calc(v){const kg=v.L*v.W*v.mic/3300000*4*F().drbio*v.per;return{kg,unit:'roll',desc:`Dr. Bio garbage bags ${v._dims('W','L')}, ${v.mic} mic, ${v.per} bags/roll`}}},
 {id:'dbshrink',g:'Dr. Bio compostable',t:'Shrink film — roll or bags',desc:'Tube (centre-fold), sheet roll, or individual bags. Factor 1.25.',
  fields:[sel('type','Format',['Tube roll','Sheet roll','Individual bag'],'Tube roll'),d('W','Width',14,350),n('Lm','Roll length (m) — or bag length in inch/mm for individual bags',500),n('mic','Thickness (micron)',35)],rate:{unit:'KG',list:'drbio',codes:['DB-1065','DB-1066'],def:'DB-1065'},
  calc(v){const f=F().shrink;if(v.type==='Individual bag'){const Lb=v._mm?v.Lm/25.4:v.Lm;const kg=v.W*Lb*v.mic*4*f/3300/1000;return{kg,unit:'pc',desc:`Dr. Bio shrink bag ${v._dims('W')} × ${v.Lm} ${v._mm?'mm':'inch'}, ${v.mic} mic`}}
   const wm=v._mm?v.W*25.4/1000:v.W/40;const kg=wm*v.Lm*v.mic*(v.type==='Tube roll'?2:1)*f/1000;return{kg,unit:'roll',desc:`Dr. Bio shrink ${v.type.toLowerCase()} ${v._dims('W')} × ${v.Lm} m, ${v.mic} mic`}}},
 {id:'dbstretch',g:'Dr. Bio compostable',t:'Stretch film roll',desc:'Machine or hand wrap. Individual pack adds ₹15 for packaging.',
  fields:[sel('type','Format',['Machine wrap','Hand wrap','Individual pack'],'Machine wrap'),d('W','Width',18,450),n('Lm','Roll length (metres)',500),n('mic','Thickness (micron)',20)],rate:{unit:'KG',list:'drbio',codes:['DB-1064'],def:'DB-1064'},
  calc(v){const wi=v.W;const kg=(wi/40)*v.Lm*v.mic*4*F().drbio/6600;return{kg,add:v.type==='Individual pack'?15:0,unit:'roll',desc:`Dr. Bio stretch film (${v.type.toLowerCase()}) ${v._dims('W')} × ${v.Lm} m, ${v.mic} mic`}}},
];
const GROUP_META={
  'VCI plastic':{icon:'bx-shield-quarter',color:'#009688'},
  'VCI paper & barrier':{icon:'bx-file',color:'#c77b2e'},
  'Tuffpaulin':{icon:'bx-cube',color:'#3f6fd1'},
  'Size converters':{icon:'bx-transfer-alt',color:'#7e57c2'},
  'Dr. Bio compostable':{icon:'bx-leaf',color:'#4caf7d'},
};
let curCalc=null, calcUnit=LS.get('spd_unit')||'in', calcCollapsed={};
function qcGroups(list){const seen=[],map={};list.forEach(c=>{if(!map[c.g]){map[c.g]=[];seen.push(c.g)}map[c.g].push(c)});return seen.map(g=>({g,items:map[g]}))}
function renderCalcList(filter){
  const q=(filter||'').trim().toLowerCase();
  const matches=CALCS.filter(c=>!q||c.t.toLowerCase().includes(q)||c.g.toLowerCase().includes(q));
  const box=$('#pdcList');
  if(!matches.length){box.innerHTML='<div class="text-muted small p-3">No match.</div>';return}
  let h='';
  qcGroups(matches).forEach(({g,items})=>{
    const meta=GROUP_META[g]||{icon:'bx-cube',color:'#6c757d'};
    const isCollapsed=!!calcCollapsed[g]&&!q;
    h+=`<button type="button" class="qc-group-head${isCollapsed?' collapsed':''}" data-g="${esc(g)}">
        <span class="qc-group-icon" style="background:${meta.color}"><i class="bx ${meta.icon}"></i></span>
        <span>${esc(g)}</span><span class="qc-group-count">${items.length}</span>
        <i class="bx bx-chevron-down qc-group-chevron"></i></button>`;
    if(!isCollapsed)items.forEach(c=>{h+=`<button type="button" class="qc-item${curCalc&&curCalc.id===c.id?' active':''}" data-c="${c.id}">${esc(c.t)}</button>`});
  });
  box.innerHTML=h;
}
function selectCalc(id){
  curCalc=CALCS.find(c=>c.id===id); if(!curCalc)return;
  LS.set('spd_calc',curCalc.id);
  $('#pdcError').classList.add('d-none');
  $('#pdcEmpty').classList.add('d-none');
  $('#pdcBody').classList.remove('d-none');
  $('#pdcTitle').textContent=curCalc.t;
  $('#pdcDesc').textContent=curCalc.desc;
  const meta=GROUP_META[curCalc.g]||{icon:'bx-cube'};
  $('#pdcProductIcon').innerHTML=`<i class="bx ${meta.icon}"></i>`;
  renderCalcList($('#pdcSearch').value);
  const unitSeg=$('#pdcUnitSeg');
  if(curCalc.noUnits){unitSeg.innerHTML=''}
  else{unitSeg.innerHTML=`<button type="button" class="btn btn-outline-secondary ${calcUnit==='in'?'active':''}" data-u="in">Inches</button>
    <button type="button" class="btn btn-outline-secondary ${calcUnit==='mm'?'active':''}" data-u="mm">mm</button>`}
  try{renderCalcFields()}catch(e){showCalcError(e)}
}
function showCalcError(e){const box=$('#pdcError');box.textContent='Something went wrong computing this calculator: '+e.message;box.classList.remove('d-none');console.error(e)}
function renderCalcFields(){
  const c=curCalc; let h='';
  c.fields.forEach(f=>{
    const val=f.dim?(calcUnit==='mm'?f.mmDef:f.inDef):f.def;
    if(f.opts)h+=`<div class="col-6 col-md-4"><label class="form-label mb-1">${esc(f.label)} <span class="req">*</span></label>
      <select class="form-select form-select-sm" data-k="${f.k}">${f.opts.map(o=>`<option${o==val?' selected':''}>${esc(o)}</option>`).join('')}</select></div>`;
    else h+=`<div class="col-6 col-md-4"><label class="form-label mb-1">${esc(f.label)}${f.dim?` (${calcUnit==='mm'?'mm':'inch'})`:''} <span class="req">*</span></label>
      <input type="number" step="any" class="form-control form-control-sm" data-k="${f.k}" value="${esc(val)}"></div>`;
  });
  $('#pdcFields').innerHTML=h;

  const rateSection=$('#pdcRateSection');
  if(c.rate){
    const list=DATA[c.rate.list||'vci']||[];
    const opts=list.map((x,i)=>({x,i})).filter(o=>c.rate.codes.includes(o.x.code)&&o.x.unit===c.rate.unit);
    const defCode=c.rate.auto?c.rate.auto({gsm:c.fields.find(f=>f.k==='gsm').def}):c.rate.def;
    const pi=(opts.find(o=>o.x.code===defCode)||opts[0]||{}).i;
    rateSection.innerHTML=opts.length?`
      <h6 class="text-uppercase text-muted mt-4 mb-2" style="font-size:.74rem;letter-spacing:.04em;">Rate basis</h6>
      <div class="row g-3">
        <div class="col-12 col-md-6"><label class="form-label mb-1">Price-list product (SKU) <span class="req">*</span></label>
          <select class="form-select form-select-sm" id="pdcProd">${opts.map(o=>`<option value="${o.i}"${o.i===pi?' selected':''}>${esc(o.x.code+' — '+o.x.name+(o.x.spec?' · '+o.x.spec:''))}</option>`).join('')}</select></div>
        <div class="col-12 col-md-6"><label class="form-label mb-1">Price level <span class="req">*</span></label>
          <select class="form-select form-select-sm" id="pdcTier">${tierNames(c.rate.list||'vci').map((t,k)=>`<option value="${k}">${esc(t)}</option>`).join('')}<option value="m">Custom rate…</option></select></div>
        <div class="col-6 hide" id="pdcRateWrap"><label class="form-label mb-1">Custom rate (₹/${c.rate.unit.toLowerCase()})</label>
          <input type="number" step="any" class="form-control form-control-sm" id="pdcRate"></div>
      </div>` : '<div class="text-muted small mt-2">No price-list rate configured for this item yet — weight only.</div>';
  } else { rateSection.innerHTML='' }
  try{computeCalc()}catch(e){showCalcError(e)}
}
function qcReadVals(c){
  const v={_mm:calcUnit==='mm'&&!c.noUnits,_raw:{}};
  c.fields.forEach(f=>{const el=$(`#pdcFields [data-k="${f.k}"]`);let x=el?el.value:f.def;if(!f.opts)x=parseFloat(x)||0;v._raw[f.k]=x;v[f.k]=(f.dim&&v._mm)?x/25.4:x});
  v._dims=(...ks)=>ks.map(k=>fmtDim(v._raw[k])).join(' × ')+(v._mm?' mm':'″');
  return v;
}
function fmtDim(x){return Number.isInteger(x)?String(x):String(r2(x))}
function qcGetRate(c){
  if(!c.rate)return null;
  const list=DATA[c.rate.list||'vci']||[];
  const pi=+(($('#pdcProd')||{}).value);const x=list[pi];if(!x)return{rate:0,prod:null};
  const tier=($('#pdcTier')||{}).value||'1';
  const rate=tier==='m'?(parseFloat(($('#pdcRate')||{}).value)||0):(tier==='0'?x.dlp:x.t[+tier-1]);
  return{rate,prod:x};
}
function qcHeroCard(label,value,variant){
  const icons={weight:'bx-package',price:'bx-coin-stack',area:'bx-crop',count:'bx-hash',generic:'bx-bar-chart-alt-2'};
  const lower=label.toLowerCase();
  const icon=lower.includes('weight')?icons.weight:(lower.includes('price')||lower.includes('₹'))?icons.price:(lower.includes('sqm')||lower.includes('area')||lower.includes('square'))?icons.area:(lower.includes('pieces')||lower.includes('count')||lower.includes('sheets'))?icons.count:icons.generic;
  return `<div class="col-12 col-md-6"><div class="qc-hero ${variant}"><div class="qc-hero-icon ${variant}"><i class="bx ${icon}"></i></div>
    <div><div class="qc-k">${esc(label)}</div><div class="qc-v">${value}</div></div></div></div>`;
}
function computeCalc(){
  const c=curCalc; const v=qcReadVals(c); const r=c.calc(v); const R=qcGetRate(c);
  let cards=[], b=null, bProd=null;
  if(r.info){r.info.forEach(([k,val,dp],i)=>cards.push(qcHeroCard(k,fmt(val,dp),i%2===0?'a':'b')));}
  else{
    if(r.kg!=null)cards.push(qcHeroCard('Weight',r.kg<1?fmt(r.kg*1000,2)+' g/'+r.unit:fmt(r.kg,3)+' kg/'+r.unit,'a'));
    if(r.sqm!=null)cards.push(qcHeroCard('Area incl. wastage',fmt(r.sqm,3)+' sqm','a'));
    if(r.kg&&r.unit==='pc')cards.push(qcHeroCard('Pieces per kg',fmt(1/r.kg,1),'b'));
    if(R&&R.prod){
      const base=c.rate.unit==='SQM'?r.sqm*R.rate:r.kg*R.rate; const price=base+(r.add||0);
      cards.push(qcHeroCard(`Price per ${r.unit}`,'₹ '+fmt(price,2),'b'));
      if(r.fg)cards.push(qcHeroCard('FG size price (+'+Math.round((r.fg-1)*100)+'%)','₹ '+fmt(price*r.fg,2),'b'));
      if(r.add)cards.push(qcHeroCard('incl. tape / packing','₹ '+fmt(r.add,2),'b'));
      if(r.extra)r.extra(R.rate).forEach(([k,val,dp])=>cards.push(qcHeroCard(k,fmt(val,dp),'a')));
      b=band(R.rate,R.prod.dlp,R.prod.t,$('#qType').value,R.prod.force); bProd=R.prod;
    }
  }
  $('#pdcResults').innerHTML=cards.join('');
  const bandBox=$('#pdcBand');
  if(b&&bProd){
    bandBox.innerHTML=`<div class="pd-row">${pill(b)}<span class="muted" style="font-size:14px">Rate ₹${fmt(R.rate)}/${c.rate.unit.toLowerCase()} vs ${esc(tierNames(c.rate.list||'vci')[0])} ₹${fmt(bProd.dlp)} → ${b.disc!=null?fmt(b.disc,1)+'%':'–'} off</span></div>`;
  } else { bandBox.innerHTML='' }
  if(curCalc.id==='3d6'||curCalc.id==='3d5'||curCalc.id==='2d'||curCalc.id==='liner'){
    bandBox.innerHTML+=`<div class="note">VCI plastic bags: quote the customer <b>per piece only</b> — never share the ₹/kg rate.</div>`;
  }
}
$('#pdcSearch').addEventListener('input',e=>renderCalcList(e.target.value));
$('#pdcList').addEventListener('click',e=>{
  const g=e.target.closest('.qc-group-head');
  if(g){calcCollapsed[g.dataset.g]=!calcCollapsed[g.dataset.g];renderCalcList($('#pdcSearch').value);return}
  const b=e.target.closest('button[data-c]'); if(b)selectCalc(b.dataset.c);
});
$('#pdcFields').addEventListener('input',()=>{try{computeCalc()}catch(e){showCalcError(e)}});
$('#pdcFields').addEventListener('change',()=>{try{computeCalc()}catch(e){showCalcError(e)}});
$('#pdcRateSection').addEventListener('change',e=>{
  if(e.target.id==='pdcTier')$('#pdcRateWrap').classList.toggle('hide',e.target.value!=='m');
  try{computeCalc()}catch(err){showCalcError(err)}
});
$('#pdcUnitSeg').addEventListener('click',e=>{
  const b=e.target.closest('button[data-u]'); if(!b)return;
  calcUnit=b.dataset.u; LS.set('spd_unit',calcUnit);
  [...e.currentTarget.querySelectorAll('button')].forEach(x=>x.classList.toggle('active',x===b));
  try{renderCalcFields()}catch(err){showCalcError(err)}
});
$('#qType').addEventListener('change',()=>{if(curCalc)try{computeCalc()}catch(e){showCalcError(e)}});
function initCalcTab(){
  $('#pdcLoading').classList.add('hide'); $('#pdcWrap').classList.remove('d-none'); $('#pdcWrap').style.display='flex';
  renderCalcList('');
  const want=LS.get('spd_calc'); if(want&&CALCS.some(c=>c.id===want))selectCalc(want);
}

/* ---------- REFERENCE ---------- */
let curRef='hsn';
$('#refSeg').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;curRef=b.dataset.r;$$('#refSeg button').forEach(x=>x.classList.toggle('on',x===b));renderRef()});
$('#refSearch').addEventListener('input',renderRef);
function renderRef(){
  const q=$('#refSearch').value.trim().toLowerCase();let h='';
  if(curRef==='hsn'){h='<div class="tablewrap"><table><thead><tr><th>Product</th><th>HSN</th><th>Description</th><th>GST</th></tr></thead><tbody>';let s=null;
    DATA.hsn.forEach(x=>{if(q&&!(x.p+' '+x.h+' '+x.d+' '+x.sec).toLowerCase().includes(q))return;if(x.sec!==s){h+=`<tr class="cat"><td colspan="4">${esc(x.sec)}</td></tr>`;s=x.sec}h+=`<tr><td>${esc(x.p)}</td><td><b>${esc(x.h)}</b></td><td>${esc(x.d)}</td><td>${esc(x.g)}</td></tr>`});h+='</tbody></table></div><p class="small muted">From HSN list updated 2-5-26. Confirm with accounts before invoicing.</p>';}
  if(curRef==='pack'){const P=DATA.packing;h=`<div class="tablewrap"><table><thead><tr><th>Type</th><th>Product</th>${P.sizes.map(s=>`<th class="n">${s}</th>`).join('')}</tr></thead><tbody>${P.rows.map(r=>`<tr><td>${esc(r[0])}</td><td><b>${esc(r[1])}</b></td>${r[2].map(x=>`<td class="n">${esc(x)}</td>`).join('')}</tr>`).join('')}</tbody></table></div><p class="small muted">Pieces per carton. ${esc(P.notes)}</p>`;}
  if(curRef==='shrink'){h=`<div class="tablewrap"><table><thead><tr><th class="n">Width (m)</th><th class="n">Length (m)</th><th class="n">Micron</th><th>Colour</th><th class="n">VCI ₹/roll</th><th class="n">NVCI ₹/roll</th><th class="n">VCI ₹/kg</th><th class="n">VCI ₹/sqm</th></tr></thead><tbody>${DATA.shrink.map(s=>`<tr><td class="n">${s[0]}</td><td class="n">${s[1]}</td><td class="n">${s[2]}</td><td>${s[3]}</td><td class="n">${fmt(s[4])}</td><td class="n">${fmt(s[5])}</td><td class="n">${fmt(s[4]/(s[0]*s[1]*s[2]/1000))}</td><td class="n">${fmt(s[4]/(s[0]*s[1]))}</td></tr>`).join('')}</tbody></table></div><p class="small muted">From the calculation file (May-26). The Oct-26 price list (VCI 714–718) is the current reference for lamina shrink.</p>`;}
  if(curRef==='sugg'){h=`<div class="tablewrap"><table><thead><tr><th>Topic</th><th>Guidance</th></tr></thead><tbody>${DATA.rules_of_thumb.filter(r=>!q||r.join(' ').toLowerCase().includes(q)).map(r=>`<tr><td><b>${esc(r[0])}</b></td><td>${esc(r[1])}</td></tr>`).join('')}</tbody></table></div>`;}
  $('#refBody').innerHTML=h;
}

/* ---------- ADMIN ---------- */
let aList='vci';
function markDirty(){LS.set('spd_override',DATA);$('#overrideBanner').classList.remove('hide');$('#verLabel').textContent=DATA.version}
function renderAdmin(){
  $('#aVer').value=DATA.version;
  $('#aRules').innerHTML=LISTS().filter(m=>DATA.rules[m.k]).map(m=>m.k).map(l=>DATA.rules[l].map((p,k)=>`<label>${esc(LM(l).short)} — ${['No approval','GM','Channel'][k]} (%)<input type="number" step="0.5" data-rl="${l}" data-rk="${k}" value="${p}"></label>`).join('')).join('');
  const FL={vci:'VCI plastic & paper wastage (×)',vci5:'5-side VCI cover wastage (×)',drbio:'Dr. Bio film factor (×)',shrink:'Dr. Bio shrink factor (×)',tuffFG:'Tuffpaulin FG-size uplift (×)'};
  $('#aFactors').innerHTML=Object.keys(FL).map(k=>`<label>${FL[k]}<input type="number" step="0.01" data-fk="${k}" value="${DATA.factors[k]}"></label>`).join('');
  renderATable();
}
function renderATable(){
  const q=$('#aSearch').value.trim().toLowerCase(),isV=LM(aList).rich,L=DATA[aList];
  let h=`<thead><tr><th>Category</th><th>Code</th>${isV?'<th>Product</th><th>Spec</th><th>Size</th>':'<th>Class</th>'}<th>Unit</th><th class="n">DLP</th><th class="n">No approval</th><th class="n">GM</th><th class="n">Channel</th><th></th></tr></thead><tbody>`;
  L.forEach((x,i)=>{if(q&&!itemText(x).includes(q))return;
    const inp=(f,w,v,num)=>`<input ${num?'type="number" step="any"':'type="text"'} data-ai="${i}" data-af="${f}" value="${esc(v)}" style="width:${w}px">`;
    h+=`<tr><td>${inp('cat',150,x.cat)}</td><td>${inp('code',110,x.code)}</td>${isV?`<td>${inp('name',200,x.name)}</td><td>${inp('spec',110,x.spec)}</td><td>${inp('size',130,x.size)}</td>`:`<td>${inp('cls',50,x.cls)}</td>`}<td>${inp('unit',60,x.unit)}</td><td>${inp('dlp',90,x.dlp,1)}</td><td>${inp('t0',85,x.t[0],1)}</td><td>${inp('t1',85,x.t[1],1)}</td><td>${inp('t2',85,x.t[2],1)}</td><td><button class="btn danger" data-adel="${i}">×</button></td></tr>`});
  $('#aTable').innerHTML=h+'</tbody>';
}
function tiersFrom(l,dlp){const r=DATA.rules[l],d=LM(l).round;return r.map(p=>d===0?Math.round(dlp*(1-p/100)):r2(dlp*(1-p/100)))}
// The Admin section itself only renders in the DOM for managers (Blade's
// canManage check), but this is one shared <script> for every viewer —
// binding straight to '#aXxx' here would throw "Cannot read
// properties of null" for everyone else (an uncaught error that silently
// stops the REST of this script too, including the Price list render at
// the bottom), so every one of these is gated on the same CAN_MANAGE flag.
if(CAN_MANAGE){
$('#aListSeg').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;aList=b.dataset.l;$$('#aListSeg button').forEach(x=>x.classList.toggle('on',x===b));renderATable()});
$('#aSearch').addEventListener('input',renderATable);
$('#aTable').addEventListener('change',e=>{const t=e.target;if(t.dataset.ai==null)return;const x=DATA[aList][+t.dataset.ai],f=t.dataset.af;
  if(f==='dlp'){x.dlp=parseFloat(t.value)||0;if(x.dlp>0){x.t=tiersFrom(aList,x.dlp);}renderATable();}
  else if(/^t\d$/.test(f))x.t[+f[1]]=parseFloat(t.value)||0; else x[f]=t.value;
  markDirty();refreshAll(false)});
$('#aTable').addEventListener('click',e=>{const b=e.target.closest('[data-adel]');if(!b)return;const x=DATA[aList][+b.dataset.adel];if(!confirm('Delete '+x.code+'?'))return;DATA[aList].splice(+b.dataset.adel,1);markDirty();renderATable();refreshAll(false)});
$('#aAdd').addEventListener('click',()=>{const cat=prompt('Category for the new product:',DATA[aList][0].cat);if(cat==null)return;DATA[aList].unshift(LM(aList).rich?{code:'NEW',name:'',spec:'',size:'',colour:'',dlp:0,unit:'PC',t:[0,0,0],cat}:{code:'NEW',cat,dlp:0,t:[0,0,0],cls:'A',unit:''});
  // keep category grouping: move to first row of that category
  const L=DATA[aList],it=L.shift(),pos=L.findIndex(x=>x.cat===cat);L.splice(pos<0?0:pos,0,it);markDirty();$('#aSearch').value='';renderATable()});
$('#aRules').addEventListener('change',e=>{const t=e.target;DATA.rules[t.dataset.rl][+t.dataset.rk]=parseFloat(t.value)||0;markDirty();renderLegend();refreshAll(false)});
$('#aFactors').addEventListener('change',e=>{const t=e.target;DATA.factors[t.dataset.fk]=parseFloat(t.value)||1;markDirty();computeCalc()});
$('#aRecalc').addEventListener('click',()=>{if(!confirm('Overwrite every tier price with DLP × (1 − rule %)? Items with no DLP are left unchanged.'))return;LISTS().forEach(m=>(DATA[m.k]||[]).forEach(x=>{if(x.dlp>0)x.t=tiersFrom(m.k,x.dlp)}));markDirty();renderATable();refreshAll(false)});
$('#aVer').addEventListener('change',e=>{DATA.version=e.target.value;markDirty()});
$('#aReset').addEventListener('click',()=>{if(!confirm('Discard all local edits and go back to the published prices?'))return;LS.del('spd_override');DATA=clone(SEED);$('#overrideBanner').classList.add('hide');renderAdmin();refreshAll(true)});

// Persists DATA to the database (pricing_desk_data table) via PricingDeskController::save().
// Every viewer's next page load reads this same row.
$('#aSaveServer').addEventListener('click',async()=>{
  const btn=$('#aSaveServer');
  btn.disabled=true;btn.textContent='Saving…';
  try{
    const csrf=document.querySelector('meta[name="csrf-token"]').content;
    const res=await fetch(SAVE_URL,{
      method:'PUT',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
      body:JSON.stringify({data:DATA})
    });
    const body=await res.json().catch(()=>({}));
    if(!res.ok){throw new Error(body.message||('Save failed (HTTP '+res.status+').'))}
    LS.del('spd_override');
    $('#overrideBanner').classList.add('hide');
    $('#aMsg').innerHTML='<b>Saved.</b> '+(body.message||'')+' Reloading…';
    setTimeout(()=>location.reload(),900);
  }catch(e){
    $('#aMsg').innerHTML='<span style="color:var(--bad)">Could not save: '+esc(e.message)+'</span>';
    btn.disabled=false;btn.textContent='Save to server';
  }
});
}

/* ---------- boot ---------- */
function updHeroStats(){
  const ls=LISTS().filter(m=>DATA[m.k]);
  $('#pdStatFamilies').textContent=ls.length;
  $('#pdStatProducts').textContent=ls.reduce((s,m)=>s+(DATA[m.k]||[]).length,0).toLocaleString('en-IN');
  $('#pdStatBands').textContent=4;
}
function refreshAll(full){renderSegs();$('#verLabel').textContent=DATA.version;if(full){fillCats();selIdx=null}renderPrices();renderCheck();renderLegend();renderRef();updHeroStats()}
if(LS.get('spd_override'))$('#overrideBanner').classList.remove('hide');
renderSegs();fillCats();refreshAll(true);
// Restore the last tab the user was on (Price list is the default, already
// marked active in the markup) via Bootstrap's own tab API.
const lt=LS.get('spd_tab');
if(lt==='calc'||lt==='admin'){
  const btn=document.querySelector(`#tabs [data-t="${lt}"]`);
  if(btn&&window.bootstrap)new bootstrap.Tab(btn).show();
}
})();
</script>
</div>
@endsection