<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Sales Pricing Desk</title>
<style>
:root{
  --bg:#f4f6f9;--panel:#ffffff;--ink:#16202c;--muted:#5d6b7a;--line:#dfe4ea;--soft:#eef2f6;
  --brand:#12355b;--brand-ink:#ffffff;--accent:#d9661f;
  --ok:#1f7a45;--ok-bg:#e3f4ea;--warn:#8a5a00;--warn-bg:#fdf1d6;--ch:#9a4a07;--ch-bg:#fde6d3;--bad:#a4262c;--bad-bg:#fbe3e4;
  --focus:#2f6fb3;
}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){
  --bg:#0f141a;--panel:#171e26;--ink:#e6ebf0;--muted:#97a4b2;--line:#2a3440;--soft:#1e2731;
  --brand:#1d4a7a;--brand-ink:#ffffff;--accent:#f08a46;
  --ok:#6fd39a;--ok-bg:#16382a;--warn:#f3c463;--warn-bg:#3a2f12;--ch:#f6a668;--ch-bg:#3d2513;--bad:#f28b8f;--bad-bg:#3d1a1c;--focus:#6aa8ec;}}
:root[data-theme="dark"]{
  --bg:#0f141a;--panel:#171e26;--ink:#e6ebf0;--muted:#97a4b2;--line:#2a3440;--soft:#1e2731;
  --brand:#1d4a7a;--brand-ink:#ffffff;--accent:#f08a46;
  --ok:#6fd39a;--ok-bg:#16382a;--warn:#f3c463;--warn-bg:#3a2f12;--ch:#f6a668;--ch-bg:#3d2513;--bad:#f28b8f;--bad-bg:#3d1a1c;--focus:#6aa8ec;}
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
button,input,select,textarea{font:inherit;color:inherit}
header.top{background:var(--brand);color:var(--brand-ink);padding:12px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
header.top h1{font-size:17px;margin:0;font-weight:650;letter-spacing:.2px}
header.top .sub{opacity:.75;font-size:12px}
nav.tabs{display:flex;gap:4px;margin-left:auto;flex-wrap:wrap}
nav.tabs button{background:transparent;border:1px solid rgba(255,255,255,.25);color:var(--brand-ink);padding:6px 12px;border-radius:6px;cursor:pointer}
nav.tabs button.on{background:#fff;color:#12355b;border-color:#fff;font-weight:600}
nav.tabs .badge{background:var(--accent);color:#fff;border-radius:9px;padding:0 6px;font-size:11px;margin-left:4px}
main{max-width:1320px;margin:0 auto;padding:18px 16px 60px}
.banner{background:var(--warn-bg);color:var(--warn);border:1px solid var(--line);padding:8px 12px;border-radius:8px;margin-bottom:12px;font-size:13px}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:16px}
.row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
.grow{flex:1}
h2{font-size:16px;margin:0 0 10px}
h3{font-size:14px;margin:14px 0 8px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.muted{color:var(--muted)}
.small{font-size:12px}
input[type=text],input[type=number],input[type=search],input[type=password],input[type=date],select,textarea{background:var(--panel);border:1px solid var(--line);border-radius:6px;padding:7px 9px;min-width:0}
input:focus,select:focus,textarea:focus,button:focus-visible{outline:2px solid var(--focus);outline-offset:1px}
.btn{background:var(--soft);border:1px solid var(--line);border-radius:6px;padding:7px 12px;cursor:pointer;white-space:nowrap}
.btn:hover{border-color:var(--muted)}
.btn.primary{background:var(--accent);border-color:var(--accent);color:#fff;font-weight:600}
.btn.danger{color:var(--bad)}
.seg{display:inline-flex;flex-wrap:wrap;border:1px solid var(--line);border-radius:7px;overflow:hidden;max-width:100%}
.seg button{border:0;background:var(--panel);padding:6px 12px;cursor:pointer}
.seg button.on{background:var(--brand);color:#fff}
.tablewrap{overflow:auto;border:1px solid var(--line);border-radius:8px;max-height:66vh}
table{border-collapse:collapse;width:100%;font-size:13px}
th,td{padding:7px 9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{position:sticky;top:0;background:var(--soft);font-weight:600;z-index:1;white-space:nowrap}
td.n,th.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
tr.cat td{background:var(--soft);font-weight:650;color:var(--brand)}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]) tr.cat td{color:var(--accent)}}
tbody tr.item{cursor:pointer}
tbody tr.item:hover td{background:var(--soft)}
tbody tr.sel td{background:var(--warn-bg)!important}
.pill{display:inline-block;border-radius:999px;padding:2px 9px;font-size:12px;font-weight:600;white-space:nowrap}
.b0{background:var(--ok-bg);color:var(--ok)}.b1{background:var(--warn-bg);color:var(--warn)}.b2{background:var(--ch-bg);color:var(--ch)}.b3{background:var(--bad-bg);color:var(--bad)}.bx{background:var(--soft);color:var(--muted)}
.grid2>*,.grid-calc>*{min-width:0}
.grid2{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px}
.grid-calc{display:grid;grid-template-columns:250px minmax(0,1fr);gap:16px}
@media (max-width:900px){.grid2,.grid-calc{grid-template-columns:minmax(0,1fr)}.side{position:static}}
@media (max-width:520px){main{padding:12px 10px 40px}.form{grid-template-columns:1fr 1fr}label[style*="span 2"]{grid-column:1/-1!important}#pSearch{min-width:0!important;width:100%}}
.side{position:sticky;top:12px;align-self:start}
.calclist button{display:block;width:100%;text-align:left;background:none;border:0;border-radius:6px;padding:7px 9px;cursor:pointer}
.calclist button:hover{background:var(--soft)}
.calclist button.on{background:var(--brand);color:#fff}
.calclist .grp{font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin:12px 9px 4px}
.form{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px 14px}
.form label{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--muted)}
.form>*{min-width:0}
.form label input,.form label select{font-size:14px;color:var(--ink);width:100%;min-width:0}
.results{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;margin-top:14px}
.stat{border:1px solid var(--line);border-radius:8px;padding:10px 12px;background:var(--panel)}
.stat .k{font-size:12px;color:var(--muted)}
.stat .v{font-size:19px;font-weight:650;font-variant-numeric:tabular-nums}
.stat.hi{border-color:var(--accent);box-shadow:inset 3px 0 0 var(--accent)}
.note{background:var(--soft);border-radius:8px;padding:9px 12px;font-size:12.5px;color:var(--muted);margin-top:12px}
.kv{display:grid;grid-template-columns:auto 1fr;gap:4px 12px;font-size:13px}
.kv div:nth-child(odd){color:var(--muted)}
.hide{display:none!important}
.qtable input{width:100%;padding:5px 6px}
.qtable td{vertical-align:middle}
.totals{margin-left:auto;min-width:280px}
.totals div{display:flex;justify-content:space-between;padding:4px 0}
.totals .gt{font-weight:700;font-size:16px;border-top:1px solid var(--line);padding-top:8px}
#pTable td:first-child{white-space:nowrap}#bandLegend{overflow-x:auto}#bandLegend .pill{padding:2px 6px}#bandLegend td,#bandLegend th{padding:6px 5px}
#printArea{display:none}
@media print{
  body{background:#fff;color:#000}
  header.top,main{display:none!important}
  #printArea{display:block;padding:10mm;font-size:12px;color:#000}
  #printArea table th{background:#eee;position:static}
  #printArea td,#printArea th{border:1px solid #bbb}
}
</style>
</head>
<body>
<header class="top">
  <div>
    <h1>Sales Pricing Desk</h1>
    <div class="sub">Hi-Tech International Group · RUST-X · Dr. Bio · Fillezy · Tuffpaulin · Zorbit — <span id="verLabel"></span></div>
  </div>
  <nav class="tabs" id="tabs">
    <button data-t="prices" class="on">Price list</button>
    <button data-t="disc">Discount finder</button>
    <button data-t="calc">Calculators</button>
    <button data-t="quote">Quote <span class="badge" id="qCount">0</span></button>
    <button data-t="ref">Reference</button>
    @if($canManage)
    <button data-t="admin">Admin</button>
    @endif
  </nav>
</header>
<main>
  <div id="overrideBanner" class="banner hide">This browser is using locally edited prices that are not yet published to the team. Go to Admin → “Download updated tool” to publish them.</div>

  <!-- PRICE LIST -->
  <section id="t-prices">
    <div class="grid2">
      <div class="panel">
        <div class="row" style="margin-bottom:12px">
          <div class="seg" id="listSeg"></div>
          <input type="search" id="pSearch" class="grow" placeholder="Search code, product, size…" style="min-width:180px">
          <select id="pCat"></select>
        </div>
        <div class="tablewrap"><table id="pTable"></table></div>
        <div class="small muted" style="margin-top:8px" id="pFoot"></div>
      </div>
      <div class="side">
        <div class="panel" id="checkPanel">
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
            <div class="row" style="margin-top:12px"><button class="btn primary" id="chkAdd">Add to quote</button></div>
          </div>
        </div>
        <div class="panel" style="margin-top:12px">
          <h2>Approval bands</h2>
          <div id="bandLegend" class="small"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- DISCOUNT FINDER -->
  <section id="t-disc" class="hide">
    <div class="panel">
      <div class="row" style="justify-content:space-between;margin-bottom:6px">
        <div><h2 style="margin:0">Discount finder</h2><div class="muted small">Pick a product, type the customer's price, and see the discount off DLP and who must approve it. Or type a discount % to get the price.</div></div>
        <div class="row">
          <label class="small muted">Customer type <select id="dType"><option>Direct customer</option><option>Channel partner</option></select></label>
          <button class="btn" id="dAdd">+ Add rows</button>
          <button class="btn" id="dCsv">Export CSV</button>
          <button class="btn danger" id="dClear">Clear</button>
        </div>
      </div>
      <datalist id="dProducts"></datalist>
      <div class="tablewrap" style="max-height:none;margin-top:10px"><table class="qtable" id="dTable"></table></div>
      <div class="note">Product not in the list? Leave the product blank, type the DLP yourself and choose which rules apply. Discount % = (DLP − price) ÷ DLP. Net-priced items (no DLP) show the gap to the net price instead.</div>
    </div>
  </section>

  <!-- CALCULATORS -->
  <section id="t-calc" class="hide">
    <div class="grid-calc">
      <div class="panel calclist" id="calcList"></div>
      <div class="panel" id="calcPanel"></div>
    </div>
  </section>

  <!-- QUOTE -->
  <section id="t-quote" class="hide">
    <div class="panel">
      <div class="row" style="justify-content:space-between;margin-bottom:10px">
        <h2 style="margin:0">Quotation</h2>
        <div class="row">
          <button class="btn" id="qCsv">Export CSV</button>
          <button class="btn" id="qPrint">Print / Save PDF</button>
          <button class="btn danger" id="qClear">Clear quote</button>
        </div>
      </div>
      <div class="form">
        <label>Customer<input type="text" id="qCust"></label>
        <label>Contact / location<input type="text" id="qContact"></label>
        <label>Customer type<select id="qType"><option>Direct customer</option><option>Channel partner</option></select></label>
        <label>Sales person<input type="text" id="qSales"></label>
        <label>Quote ref<input type="text" id="qRef"></label>
        <label>Date<input type="date" id="qDate"></label>
        <label>Validity (days)<input type="number" id="qValid" value="15"></label>
        <label>Freight / terms<input type="text" id="qTerms" value="Ex-factory. GST extra as applicable."></label>
      </div>
      <div class="tablewrap" style="margin-top:14px;max-height:none">
        <table class="qtable" id="qTable"></table>
      </div>
      <div class="row" style="margin-top:14px;align-items:flex-start">
        <div class="grow" id="qApproval"></div>
        <div class="totals" id="qTotals"></div>
      </div>
    </div>
  </section>

  <!-- REFERENCE -->
  <section id="t-ref" class="hide">
    <div class="panel">
      <div class="row" style="margin-bottom:12px">
        <div class="seg" id="refSeg"><button data-r="hsn" class="on">HSN &amp; GST</button><button data-r="pack">Standard packing</button><button data-r="shrink">Shrink film sizes</button><button data-r="sugg">Rules of thumb</button></div>
        <input type="search" id="refSearch" class="grow" placeholder="Search…">
      </div>
      <div id="refBody"></div>
    </div>
  </section>

  @if($canManage)
  <!-- ADMIN -->
  <section id="t-admin" class="hide">
    <div id="adminBody">
      <div class="panel">
        <h2>Publish workflow</h2>
        <ol class="small muted" style="margin:0 0 10px;padding-left:18px">
          <li>Edit prices below.</li>
          <li>Click <b>Save to server</b>. Every viewer sees the new prices on refresh — no file to download or re-upload.</li>
        </ol>
        <div class="row">
          <label class="small muted">Price list version label <input type="text" id="aVer" style="width:220px"></label>
          <button class="btn primary" id="aSaveServer">Save to server</button>
          <button class="btn" id="aDownload">Download as standalone file (backup)</button>
          <button class="btn" id="aExport">Export prices (JSON)</button>
          <label class="btn">Import prices (JSON)<input type="file" id="aImport" accept=".json" class="hide"></label>
          <button class="btn danger" id="aReset">Discard local edits</button>
        </div>
        <div id="aMsg" class="small" style="margin-top:8px"></div>
      </div>
      <div class="panel" style="margin-top:12px">
        <h2>Discount rules (% off DLP)</h2>
        <div class="form" id="aRules"></div>
        <div class="row" style="margin-top:10px"><button class="btn" id="aRecalc">Recalculate all tier prices from DLP</button><span class="small muted">Lubricants round to whole rupees; other lists to paise.</span></div>
        <h3>Calculator factors</h3>
        <div class="form" id="aFactors"></div>
      </div>
      <div class="panel" style="margin-top:12px">
        <div class="row" style="margin-bottom:10px">
          <h2 style="margin:0">Edit price list</h2>
          <div class="seg" id="aListSeg"></div>
          <input type="search" id="aSearch" class="grow" placeholder="Filter…">
          <button class="btn" id="aAdd">+ Add product</button>
        </div>
        <p class="small muted" style="margin-top:0">Changing DLP recalculates the three tier prices automatically. You can still overwrite a tier by hand.</p>
        <div class="tablewrap"><table id="aTable" class="qtable"></table></div>
      </div>
    </div>
  </section>
  @endif
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
$('#tabs').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;showTab(b.dataset.t)});
function showTab(t){$$('#tabs button').forEach(x=>x.classList.toggle('on',x.dataset.t===t));['prices','disc','calc','quote','ref','admin'].forEach(k=>document.getElementById('t-'+k)?.classList.toggle('hide',k!==t)); if(t==='quote')renderQuote();if(t==='disc')renderDisc();if(t==='admin'&&CAN_MANAGE)renderAdmin(); LS.set('spd_tab',t)}

/* ---------- PRICE LIST ---------- */
let curList='vci', selIdx=null;
function renderSegs(){$('#listSeg').innerHTML=segHtml(curList);$('#aListSeg').innerHTML=segHtml(aList)}
$('#listSeg').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;curList=b.dataset.l;$$('#listSeg button').forEach(x=>x.classList.toggle('on',x===b));selIdx=null;fillCats();renderPrices();renderCheck()});
$('#pSearch').addEventListener('input',renderPrices);
$('#pCat').addEventListener('change',renderPrices);
function fillCats(){const cats=[...new Set(DATA[curList].map(x=>x.cat))];$('#pCat').innerHTML='<option value="">All categories</option>'+cats.map(c=>`<option>${esc(c)}</option>`).join('')}
function itemText(x){return [x.code,x.name,x.spec,x.size,x.colour,x.cat].join(' ').toLowerCase()}
function renderPrices(){
  const q=$('#pSearch').value.trim().toLowerCase(), cat=$('#pCat').value, L=DATA[curList], tn=tierNames(curList);
  const isV=LM(curList).rich, dp=LM(curList).round;
  const r=DATA.rules[curList];
  let h=`<thead><tr><th>Code</th>${isV?'<th>Product</th>':''}<th>Unit</th><th class="n">${esc(tn[0])}</th><th class="n">No approval<div class="small muted">${r[0]}% off</div></th><th class="n">GM<div class="small muted">${r[1]}% off</div></th><th class="n">Channel<div class="small muted">${r[2]}% off</div></th>${isV?'':'<th>Class</th>'}</tr></thead><tbody>`;
  let last=null,n=0;
  L.forEach((x,i)=>{
    if(cat&&x.cat!==cat)return; if(q&&!itemText(x).includes(q))return;
    if(x.cat!==last){h+=`<tr class="cat"><td colspan="7">${esc(x.cat)}</td></tr>`;last=x.cat}
    n++;
    h+=`<tr class="item${i===selIdx?' sel':''}" data-i="${i}"><td><b>${esc(x.code)}</b></td>${isV?`<td>${esc(x.name)}<div class="small muted">${esc([x.spec,x.size,x.colour].filter(Boolean).join(' · '))}</div>${x.note?`<div class="small" style="color:var(--bad)">${esc(x.note)}</div>`:''}</td>`:''}<td>${esc(x.unit||'')}</td><td class="n">${x.dlp?fmt(x.dlp,dp):`<span class="muted">${x.force?'on request':'net'}</span>`}</td><td class="n">${x.t[0]?fmt(x.t[0],dp):'–'}</td><td class="n">${x.t[1]?fmt(x.t[1],dp):'–'}</td><td class="n">${x.t[2]?fmt(x.t[2],dp):'–'}</td>${isV?'':`<td>${esc(x.cls||'')}</td>`}</tr>`;
  });
  $('#pTable').innerHTML=h+'</tbody>';
  $('#pFoot').textContent=`${n} of ${L.length} products · ${LM(curList).label} · ${LM(curList).source||DATA.version}`;
}
$('#pTable').addEventListener('click',e=>{const tr=e.target.closest('tr.item');if(!tr)return;selIdx=+tr.dataset.i;renderPrices();renderCheck();$('#chkPrice').focus()});
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
  $('#chkResult').innerHTML=`<div class="row" style="justify-content:space-between">${pill(b)}<span class="small muted">${b.disc!=null?fmt(b.disc,1)+'% off '+esc(tierNames(curList)[0]):'net-priced item'}</span></div>
  <div class="small" style="margin-top:6px">${esc(b.label)}${b.above?' · above DLP':''}</div>
  <div class="kv" style="margin-top:8px"><div>Line value</div><div><b>₹ ${fmt(p*q)}</b> + GST</div></div>`;
  $('#chkWarn').innerHTML=(isKgOnly(x)?`<div class="note" style="color:var(--bad)"><b>Do not quote per kg.</b> This product must be quoted per piece — use the Calculators tab to convert this rate to ₹/pc.</div>`:'')+(x.note&&!isKgOnly(x)||x.force?`<div class="note" style="color:var(--bad)">${esc(x.note||x.force)}</div>`:'');
}
$('#chkPrice').addEventListener('input',updCheck);$('#chkQty').addEventListener('input',updCheck);
$('#chkAdd').addEventListener('click',()=>{
  const x=DATA[curList][selIdx]; if(!x)return;
  if(isKgOnly(x) && !confirm('This product should be quoted per piece, not per kg. Add the per-kg line anyway (internal use only)?')) return;
  addLine({desc:x.code+(x.name?' — '+x.name:'')+(x.spec?' ('+x.spec+(x.size?', '+x.size:'')+')':''),unit:x.unit||'unit',qty:parseFloat($('#chkQty').value)||1,rate:parseFloat($('#chkPrice').value)||x.t[0],dlp:x.dlp,t:x.t.slice(),gst:defaultGst(curList,x),list:curList,force:x.force||null});
});
function renderLegend(){
  const r=DATA.rules;
  const ls=LISTS().filter(m=>r[m.k]);const cell=(k,op)=>ls.map(m=>`<td class="n">${op} ${r[m.k][k]}%</td>`).join('');
  $('#bandLegend').innerHTML=`<table><tr><th></th>${ls.map(m=>`<th class="n">${esc(m.short)}</th>`).join('')}</tr>
  <tr><td><span class="pill b0">No approval</span></td>${cell(0,'≤')}</tr>
  <tr><td><span class="pill b1">GM approval</span></td>${cell(1,'≤')}</tr>
  <tr><td><span class="pill b2">Channel price</span></td>${cell(2,'≤')}</tr>
  <tr><td><span class="pill b3">Director approval</span></td>${cell(2,'&gt;')}</tr></table>
  <p class="muted" style="margin:8px 0 0">Discounts are off DLP (MRP for Dr. Bio, list rate for Fillezy). Channel price is for channel partners only; for a direct customer it needs Director approval. Set customer type on the Quote tab.</p>`;
}

/* ---------- GST defaults ---------- */
function defaultGst(list,x){ if(list==='drbio'&&/KG/i.test(x.unit||''))return 5; if(list==='vci'){ if(/^ZB|^ZN/i.test(x.code)&&/CLAY/i.test(x.name))return 5; if(/NON WOVEN/i.test(x.name)&&!/BAG/i.test(x.name))return 5;} return 18; }

/* ---------- DISCOUNT FINDER ---------- */
let DR=LS.get('spd_disc')||Array.from({length:10},()=>({}));
let PL={};
function buildDatalist(){PL={};const o=[];LISTS().filter(m=>DATA[m.k]).forEach(m=>{const l=m.k;DATA[l].forEach((x,i)=>{let lab=m.short+' · '+x.code+(x.name?' — '+x.name:'')+(m.rich&&(x.spec||x.size||x.colour)?' · '+[x.spec,x.size,l==='drbio'?x.colour:''].filter(Boolean).join(' · '):'');while(PL[lab])lab+=' ';PL[lab]={l,i};o.push(`<option value="${esc(lab)}">`)})});$('#dProducts').innerHTML=o.join('')}
function dItem(r){const m=PL[r.p];if(m){const x=DATA[m.l][m.i];if(x)return{x,l:m.l}}return null}
function dCalc(r){
  const it=dItem(r);let dlp,t,unit='',l;
  if(it){dlp=it.x.dlp;t=it.x.t;unit=it.x.unit||'unit';l=it.l}
  else{dlp=parseFloat(r.dlp)||0;l=r.rl||'vci';t=dlp>0?tiersFrom(l,dlp):[0,0,0]}
  let price=parseFloat(r.price);const pct=parseFloat(r.pct);
  if(r.drv==='pct'&&!isNaN(pct)&&dlp>0)price=r2(dlp*(1-pct/100));
  const b=(price>0&&(dlp>0||t[0]>0||(it&&it.x.force)))?band(price,dlp,t,$('#dType').value,it&&it.x.force):null;
  return{it,dlp,t,unit,l,price,b};
}
function renderDisc(){
  const ct=$('#dType').value;
  let h=`<thead><tr><th>#</th><th style="min-width:300px">Product (type to search)</th><th>Unit</th><th class="n">DLP ₹</th><th class="n">Customer price ₹</th><th class="n">Discount %</th><th>Approval</th><th class="n">No-approval price ₹</th><th class="n">Below no-approval by</th><th></th></tr></thead><tbody>`;
  DR.forEach((r,i)=>{const c=dCalc(r);
    const gap=c.price>0&&c.t[0]>0?c.t[0]-c.price:null;
    const pctVal=r.drv==='pct'?(r.pct??''):(c.b&&c.b.disc!=null?r2(c.b.disc):'');
    const priceVal=r.drv==='pct'?(c.price>0?c.price:''):(r.price??'');
    h+=`<tr data-i="${i}"><td>${i+1}</td>
    <td><input type="text" list="dProducts" data-f="p" value="${esc(r.p||'')}" placeholder="e.g. VCI 702, AmpliFi, FEQ, DB-1005">${c.it?'':!(r.man||r.dlp)?`<div class="small" style="margin-top:3px"><a href="#" data-man="${i}" style="color:var(--muted)">Not in list? Enter DLP</a></div>`:`<div class="row small muted" style="margin-top:4px">DLP <input type="number" step="any" data-f="dlp" value="${esc(r.dlp||'')}" style="width:90px"> rules <select data-f="rl">${LISTS().map(m=>`<option value="${m.k}"${(r.rl||'vci')===m.k?' selected':''}>${esc(m.short)}</option>`).join('')}</select></div>`}</td>
    <td>${esc(c.unit)}</td>
    <td class="n">${c.dlp>0?fmt(c.dlp):(c.it?'<span class="muted">net '+fmt(c.t[0])+'</span>':'–')}</td>
    <td class="n"><input type="number" step="0.01" data-f="price" value="${esc(priceVal)}" style="width:110px"></td>
    <td class="n"><input type="number" step="0.1" data-f="pct" value="${esc(pctVal)}" style="width:80px" ${c.dlp>0?'':'disabled'}></td>
    <td>${pill(c.b)}</td>
    <td class="n">${c.t[0]>0?fmt(c.t[0]):'–'}</td>
    <td class="n">${gap==null?'–':gap<=0.005?'<span style="color:var(--ok)">within limit</span>':'₹ '+fmt(gap)+' ('+fmt(gap/c.t[0]*100,1)+'%)'}</td>
    <td style="white-space:nowrap">${c.b?`<button class="btn" data-dq="${i}" title="Add to quote">→ Quote</button> `:''}<button class="btn danger" data-dd="${i}" title="Remove row">×</button></td></tr>`});
  $('#dTable').innerHTML=h+'</tbody>';
}
function saveD(){LS.set('spd_disc',DR)}
$('#dTable').addEventListener('change',e=>{const tr=e.target.closest('tr[data-i]'),f=e.target.dataset.f;if(!tr||!f)return;const r=DR[+tr.dataset.i];
  if(f==='price'){r.price=e.target.value;r.drv='price'}else if(f==='pct'){r.pct=e.target.value;r.drv='pct'}else r[f]=e.target.value;
  if(f==='p'&&PL[r.p]&&!(parseFloat(r.price)>0)){const x=DATA[PL[r.p].l][PL[r.p].i];r.price=String(x.t[0]);r.drv='price'}
  saveD();renderDisc();const nx=$(`#dTable tr[data-i="${tr.dataset.i}"] [data-f="${f==='p'?'price':f}"]`);if(nx&&f==='p')nx.focus()});
$('#dTable').addEventListener('click',e=>{const mn=e.target.closest('[data-man]');if(mn){e.preventDefault();DR[+mn.dataset.man].man=true;saveD();renderDisc();const x=$(`#dTable tr[data-i="${mn.dataset.man}"] [data-f=dlp]`);if(x)x.focus();return}const d=e.target.closest('[data-dd]');if(d){DR.splice(+d.dataset.dd,1);if(!DR.length)DR.push({});saveD();renderDisc();return}
  const q=e.target.closest('[data-dq]');if(q){const r=DR[+q.dataset.dq],c=dCalc(r);addLine({force:c.it&&c.it.x.force||null,desc:c.it?(c.it.x.code+(c.it.x.name?' — '+c.it.x.name:'')+(c.it.x.spec?' ('+c.it.x.spec+')':'')):'Item (manual DLP ₹'+fmt(c.dlp)+')',unit:c.unit||'unit',qty:1,rate:c.price,dlp:c.dlp,t:c.t.slice(),gst:18,list:c.l})}});
$('#dType').addEventListener('change',()=>{$('#qType').value=$('#dType').value;Q.qType=$('#dType').value;saveQ();renderDisc()});
$('#dAdd').addEventListener('click',()=>{for(let k=0;k<5;k++)DR.push({});saveD();renderDisc()});
$('#dClear').addEventListener('click',()=>{if(!confirm('Clear all rows?'))return;DR=Array.from({length:10},()=>({}));saveD();renderDisc()});
$('#dCsv').addEventListener('click',()=>{const rows=[['Product','Unit','DLP','Customer price','Discount %','Approval','No-approval price']];DR.forEach(r=>{const c=dCalc(r);if(!c.b)return;rows.push([c.it?r.p:'Manual',c.unit,c.dlp,c.price,c.b.disc!=null?r2(c.b.disc):'',c.b.label,c.t[0]])});download('discount-check.csv',rows.map(r=>r.map(x=>`"${String(x).replace(/"/g,'""')}"`).join(',')).join('\n'),'text/csv')});

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
let curCalc=LS.get('spd_calc')||'2d', calcUnit=LS.get('spd_unit')||'in';
const calcState=LS.get('spd_cstate')||{};
function renderCalcList(){
  let h='',g=null;CALCS.forEach(c=>{if(c.g!==g){h+=`<div class="grp">${esc(c.g)}</div>`;g=c.g}h+=`<button data-c="${c.id}" class="${c.id===curCalc?'on':''}">${esc(c.t)}</button>`});
  $('#calcList').innerHTML=h;
}
$('#calcList').addEventListener('click',e=>{const b=e.target.closest('button[data-c]');if(!b)return;curCalc=b.dataset.c;LS.set('spd_calc',curCalc);renderCalcList();renderCalc()});
function stKey(c){return c.id+'|'+(c.noUnits?'x':calcUnit)}
function renderCalc(){
  const c=CALCS.find(x=>x.id===curCalc)||CALCS[0];const st=calcState[stKey(c)]||{};
  let h=`<div class="row" style="justify-content:space-between"><div><h2 style="margin:0">${esc(c.t)}</h2><div class="muted small">${esc(c.desc)}</div></div>`;
  if(!c.noUnits)h+=`<div class="seg" id="unitSeg"><button data-u="in" class="${calcUnit==='in'?'on':''}">Inches</button><button data-u="mm" class="${calcUnit==='mm'?'on':''}">mm</button></div>`;
  h+=`</div><div class="form" style="margin-top:14px">`;
  c.fields.forEach(f=>{
    const val=st[f.k]!=null?st[f.k]:(f.dim?(calcUnit==='mm'?f.mmDef:f.inDef):f.def);
    if(f.opts)h+=`<label>${esc(f.label)}<select data-k="${f.k}">${f.opts.map(o=>`<option${o==val?' selected':''}>${esc(o)}</option>`).join('')}</select></label>`;
    else h+=`<label>${esc(f.label)}${f.dim?` (${calcUnit==='mm'?'mm':'inch'})`:''}<input type="number" step="any" data-k="${f.k}" value="${esc(val)}"></label>`;
  });
  h+='</div>';
  if(c.rate){
    if(c.rate.manual){
      h+=`<h3>Rate</h3><div class="form"><label>Selling rate (₹/${c.rate.unit.toLowerCase()})<input type="number" step="any" id="cRate" value="${esc(st._rate!=null?st._rate:c.rate.def)}"></label></div>
      <div class="note">Dr. Bio has no DLP list loaded yet, so no approval band is shown. Sheet guidance: custom print MOQ 1000 kg ≈ ₹300/kg; Dr. Bio standard print MOQ 200 kg ≈ ₹260–280/kg; transparent ≈ ₹340/kg; shrink/stretch ≈ ₹340–360/kg.</div>`;
    } else {
      const RL=c.rate.list||'vci';const opts=(DATA[RL]||[]).map((x,i)=>({x,i})).filter(o=>c.rate.codes.includes(o.x.code)&&o.x.unit===c.rate.unit);
      const defCode=c.rate.auto?c.rate.auto({gsm:st.gsm||c.fields.find(f=>f.k==='gsm').def}):c.rate.def;
      const pi=st._pi!=null&&opts.some(o=>o.i==st._pi)?st._pi:(opts.find(o=>o.x.code===defCode)||opts[0]||{}).i;
      const tier=st._tier||'1';
      h+=`<h3>Rate basis</h3><div class="form">
        <label style="grid-column:span 2">Price-list product (₹/${c.rate.unit.toLowerCase()})<select id="cProd">${opts.map(o=>`<option value="${o.i}"${o.i==pi?' selected':''}>${esc(o.x.code+' — '+o.x.name+(o.x.spec?' · '+o.x.spec:'')+(RL==='drbio'?' · '+o.x.colour:(o.x.size&&o.x.size!=='Custom Size'?' · '+o.x.size:'')))}</option>`).join('')}</select></label>
        <label>Price level<select id="cTier">${tierNames(RL).map((t,k)=>`<option value="${k}"${String(k)===tier?' selected':''}>${esc(t)}</option>`).join('')}<option value="m"${tier==='m'?' selected':''}>Custom rate…</option></select></label>
        <label id="cRateWrap" class="${tier==='m'?'':'hide'}">Custom rate (₹/${c.rate.unit.toLowerCase()})<input type="number" step="any" id="cRate" value="${esc(st._rate!=null?st._rate:'')}"></label>
      </div>`;
    }
  }
  h+=`<div class="results" id="cRes"></div><div id="cBand" style="margin-top:12px"></div>`;
  if(c.rate)h+=`<div class="row" style="margin-top:14px"><label class="small muted">Qty <input type="number" id="cQty" value="${esc(st._qty||1000)}" style="width:110px"></label><button class="btn primary" id="cAdd">Add to quote</button></div>`;
  if(c.id==='3d6'||c.id==='3d5'||c.id==='2d'||c.id==='liner')h+=`<div class="note">VCI plastic bags: quote the customer <b>per piece only</b> — never share the ₹/kg rate.</div>`;
  $('#calcPanel').innerHTML=h;
  const us=$('#unitSeg');if(us)us.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;calcUnit=b.dataset.u;LS.set('spd_unit',calcUnit);renderCalc()});
  computeCalc();
}
$('#calcPanel').addEventListener('input',onCalcInput);$('#calcPanel').addEventListener('change',onCalcInput);
$('#calcPanel').addEventListener('click',e=>{if(e.target.id==='cAdd')addCalcLine()});
function onCalcInput(e){
  const c=CALCS.find(x=>x.id===curCalc),k=stKey(c);calcState[k]=calcState[k]||{};const st=calcState[k];
  const t=e.target;if(t.dataset.k)st[t.dataset.k]=t.tagName==='SELECT'?t.value:t.value;
  if(t.id==='cProd')st._pi=+t.value;
  if(t.id==='cTier'){st._tier=t.value;$('#cRateWrap').classList.toggle('hide',t.value!=='m');}
  if(t.id==='cRate')st._rate=t.value;
  if(t.id==='cQty')st._qty=t.value;
  if(c.rate&&c.rate.auto&&t.dataset.k){const code=c.rate.auto(readVals(c));const i=DATA[c.rate.list||'vci'].findIndex(x=>x.code===code);if(i>=0){st._pi=i;const s=$('#cProd');if(s)s.value=i;}}
  LS.set('spd_cstate',calcState);computeCalc();
}
function readVals(c){
  const v={_mm:calcUnit==='mm'&&!c.noUnits,_raw:{}};
  c.fields.forEach(f=>{const el=$(`#calcPanel [data-k="${f.k}"]`);let x=el?el.value:f.def;if(!f.opts)x=parseFloat(x)||0;v._raw[f.k]=x;v[f.k]=(f.dim&&v._mm)?x/25.4:x;});
  v._dims=(...ks)=>ks.map(k=>fmtDim(v._raw[k])).join(' × ')+(v._mm?' mm':'″');
  return v;
}
function fmtDim(x){return Number.isInteger(x)?String(x):String(r2(x))}
let lastCalc=null;
function getRate(c){
  if(!c.rate)return null;
  if(c.rate.manual){return{rate:parseFloat(($('#cRate')||{}).value)||0,prod:null}}
  const pi=+(($('#cProd')||{}).value);const x=(DATA[c.rate.list||'vci']||[])[pi];if(!x)return{rate:0,prod:null};
  const tier=($('#cTier')||{}).value||'1';
  const rate=tier==='m'?(parseFloat($('#cRate').value)||0):(tier==='0'?x.dlp:x.t[+tier-1]);
  return{rate,prod:x};
}
function computeCalc(){
  const c=CALCS.find(x=>x.id===curCalc);const v=readVals(c);const r=c.calc(v);const R=getRate(c);
  let cards=[];
  if(r.info){r.info.forEach(([k,val,dp,hi])=>cards.push(stat(k,fmt(val,dp),hi)));}
  else{
    if(r.sqm!=null)cards.push(stat('Area incl. wastage',fmt(r.sqm,3)+' sqm'));
    if(r.kg!=null)cards.push(stat('Weight',r.kg<1?fmt(r.kg*1000,2)+' g/'+r.unit:fmt(r.kg,3)+' kg/'+r.unit));
    if(r.kg&&r.unit==='pc')cards.push(stat('Pieces per kg',fmt(1/r.kg,1)));
    let price=null;
    if(R){const base=c.rate.unit==='SQM'?r.sqm*R.rate:r.kg*R.rate;price=base+(r.add||0);
      cards.push(stat(`Price per ${r.unit}`,'₹ '+fmt(price,2),true));
      if(r.fg)cards.push(stat('FG size price (+'+Math.round((r.fg-1)*100)+'%)','₹ '+fmt(price*r.fg,2)));
      if(r.add)cards.push(stat('incl. tape / packing','₹ '+fmt(r.add,2)));
      if(r.extra)r.extra(R.rate).forEach(([k,val,dp])=>cards.push(stat(k,fmt(val,dp))));
    }
    lastCalc={c,v,r,R,price};
  }
  $('#cRes').innerHTML=cards.join('');
  const cb=$('#cBand');
  if(R&&R.prod){const b=band(R.rate,R.prod.dlp,R.prod.t,$('#qType').value,R.prod.force);cb.innerHTML=b?`<div class="row">${pill(b)}<span class="small muted">Rate ₹${fmt(R.rate)}/${c.rate.unit.toLowerCase()} vs ${esc(tierNames(c.rate.list||'vci')[0])} ₹${fmt(R.prod.dlp)} → ${fmt(b.disc,1)}% off · ${esc(b.label)}</span></div>`+(R.prod.note?`<div class="note" style="color:var(--bad)">${esc(R.prod.note)}</div>`:''):''}else if(cb)cb.innerHTML='';
}
function stat(k,v,hi){return`<div class="stat${hi?' hi':''}"><div class="k">${esc(k)}</div><div class="v">${v}</div></div>`}
function addCalcLine(){
  if(!lastCalc||lastCalc.price==null)return;const {c,r,R,price}=lastCalc;
  const qty=parseFloat($('#cQty').value)||1;
  let dlp=null,t=null;
  if(R.prod){const qf=(c.rate.unit==='SQM'?r.sqm:r.kg);dlp=R.prod.dlp*qf+(r.add||0);t=R.prod.t.map(x=>x*qf+(r.add||0));}
  const gst=c.g.startsWith('Dr. Bio')?5:18;
  addLine({force:R.prod&&R.prod.force||null,desc:r.desc,unit:r.unit,qty,rate:r2(price),dlp,t,gst,list:'calc',meta:{kgPerUnit:r.kg,basis:R.prod?R.prod.code:'manual',basisRate:R.rate}});
}

/* ---------- QUOTE ---------- */
let Q=LS.get('spd_quote')||{items:[]};
['qCust','qContact','qType','qSales','qRef','qDate','qValid','qTerms'].forEach(id=>{const el=$('#'+id);if(Q[id]!=null)el.value=Q[id];el.addEventListener('input',()=>{Q[id]=el.value;saveQ();if(id==='qType'){$('#dType').value=el.value;renderQuote();updCheck();if(lastCalc)computeCalc();}})});
if(!$('#qDate').value)$('#qDate').value=new Date().toISOString().slice(0,10);
function saveQ(){LS.set('spd_quote',Q);$('#qCount').textContent=Q.items.length}
function addLine(it){Q.items.push(it);saveQ();flash(`Added to quote (${Q.items.length} lines)`)}
function flash(m){let f=$('#flash');if(!f){f=document.createElement('div');f.id='flash';f.style.cssText='position:fixed;bottom:18px;left:50%;transform:translateX(-50%);background:#16202c;color:#fff;padding:9px 16px;border-radius:8px;z-index:9;font-size:13px';document.body.appendChild(f)}f.textContent=m;f.style.display='block';clearTimeout(f._t);f._t=setTimeout(()=>f.style.display='none',1800)}
function renderQuote(){
  const ct=$('#qType').value;
  let h=`<thead><tr><th>#</th><th style="min-width:260px">Description</th><th class="n">Qty</th><th>Unit</th><th class="n">Rate ₹</th><th class="n">% off DLP</th><th>Approval</th><th class="n">GST %</th><th class="n">Amount ₹</th><th></th></tr></thead><tbody>`;
  let sub=0,gst=0,worst=-1;const reasons=[];
  Q.items.forEach((it,i)=>{
    const b=(it.t||it.force)?band(it.rate,it.dlp,it.t||[0,0,0],ct,it.force):null;if(b){if(b.eff>worst)worst=b.eff;if(b.eff>0)reasons.push(`Line ${i+1}: ${b.label}`)}
    const amt=(it.qty||0)*(it.rate||0);sub+=amt;gst+=amt*(it.gst||0)/100;
    h+=`<tr data-i="${i}"><td>${i+1}</td><td><input type="text" data-f="desc" value="${esc(it.desc)}"></td><td class="n"><input type="number" data-f="qty" value="${esc(it.qty)}" style="width:90px"></td><td>${esc(it.unit)}</td><td class="n"><input type="number" step="0.01" data-f="rate" value="${esc(it.rate)}" style="width:100px"></td><td class="n">${b&&b.disc!=null?fmt(b.disc,1)+'%':'–'}</td><td>${pill(b)}</td><td class="n"><input type="number" data-f="gst" value="${esc(it.gst)}" style="width:60px"></td><td class="n">${fmt(amt)}</td><td><button class="btn danger" data-del="${i}" title="Remove">×</button></td></tr>`;
  });
  if(!Q.items.length)h+=`<tr><td colspan="10" class="muted" style="padding:20px;text-align:center">No lines yet. Add products from the Price list or a Calculator.</td></tr>`;
  $('#qTable').innerHTML=h+'</tbody>';
  $('#qTotals').innerHTML=`<div><span>Sub-total</span><span>₹ ${fmt(sub)}</span></div><div><span>GST</span><span>₹ ${fmt(gst)}</span></div><div class="gt"><span>Total</span><span>₹ ${fmt(sub+gst)}</span></div>`;
  const W=['No approval needed — you can release this quote.','Needs GM approval before release.','Channel pricing — OK for this channel partner.','Needs Director / management approval before release.'];
  $('#qApproval').innerHTML=Q.items.length?`<div class="panel" style="padding:12px"><div class="row">${worst>=0?`<span class="pill b${worst}">${esc(['Release','GM approval','Channel OK','Director approval'][worst])}</span>`:'<span class="pill bx">Manual rates</span>'}<b>${worst>=0?esc(W[worst]):'Dr. Bio / manual lines have no DLP — check rates with your manager.'}</b></div>${reasons.length?`<ul class="small muted" style="margin:8px 0 0;padding-left:18px">${reasons.map(r=>`<li>${esc(r)}</li>`).join('')}</ul>`:''}<div class="small muted" style="margin-top:6px">Approval info is internal and is not printed on the customer copy.</div></div>`:'';
}
$('#qTable').addEventListener('change',e=>{const tr=e.target.closest('tr[data-i]');if(!tr||!e.target.dataset.f)return;const it=Q.items[+tr.dataset.i],f=e.target.dataset.f;it[f]=f==='desc'?e.target.value:parseFloat(e.target.value)||0;saveQ();renderQuote()});
$('#qTable').addEventListener('click',e=>{const b=e.target.closest('[data-del]');if(!b)return;Q.items.splice(+b.dataset.del,1);saveQ();renderQuote()});
$('#qClear').addEventListener('click',()=>{if(!confirm('Clear all quote lines and customer details?'))return;Q={items:[]};['qCust','qContact','qRef'].forEach(id=>$('#'+id).value='');saveQ();renderQuote()});
$('#qCsv').addEventListener('click',()=>{
  const rows=[['#','Description','Qty','Unit','Rate','% off DLP','Approval','GST %','Amount']];
  Q.items.forEach((it,i)=>{const b=(it.t||it.force)?band(it.rate,it.dlp,it.t||[0,0,0],$('#qType').value,it.force):null;rows.push([i+1,it.desc,it.qty,it.unit,it.rate,b&&b.disc!=null?r2(b.disc):'',b?b.label:'manual',it.gst,r2(it.qty*it.rate)])});
  const csv=rows.map(r=>r.map(x=>`"${String(x).replace(/"/g,'""')}"`).join(',')).join('\n');
  download(`quote-${($('#qCust').value||'customer').replace(/\W+/g,'-')}-${$('#qDate').value}.csv`,csv,'text/csv');
});
$('#qPrint').addEventListener('click',()=>{
  let sub=0,gst=0;
  const lines=Q.items.map((it,i)=>{const a=it.qty*it.rate;sub+=a;gst+=a*it.gst/100;return`<tr><td>${i+1}</td><td>${esc(it.desc)}</td><td style="text-align:right">${fmt(it.qty,0)}</td><td>${esc(it.unit)}</td><td style="text-align:right">${fmt(it.rate)}</td><td style="text-align:right">${it.gst}%</td><td style="text-align:right">${fmt(a)}</td></tr>`}).join('');
  $('#printArea').innerHTML=`<div style="display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #12355b;padding-bottom:8px"><div><div style="font-size:20px;font-weight:700;color:#12355b">Hi-Tech International Group</div><div>RUST-X · Dr. Bio · Tuffpaulin · Zorbit · Fillezy · Keep It Fresh</div></div><div style="text-align:right;font-size:18px;font-weight:700">QUOTATION</div></div>
  <table style="width:100%;margin:12px 0;border:0"><tr><td style="border:0"><b>To:</b> ${esc($('#qCust').value)}<br>${esc($('#qContact').value)}</td><td style="border:0;text-align:right"><b>Ref:</b> ${esc($('#qRef').value)}<br><b>Date:</b> ${esc($('#qDate').value)}<br><b>Valid for:</b> ${esc($('#qValid').value)} days</td></tr></table>
  <table style="width:100%;border-collapse:collapse"><thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Unit</th><th>Rate ₹</th><th>GST</th><th>Amount ₹</th></tr></thead><tbody>${lines}</tbody></table>
  <table style="margin:10px 0 0 auto;border-collapse:collapse"><tr><td>Sub-total</td><td style="text-align:right">₹ ${fmt(sub)}</td></tr><tr><td>GST</td><td style="text-align:right">₹ ${fmt(gst)}</td></tr><tr><td><b>Total</b></td><td style="text-align:right"><b>₹ ${fmt(sub+gst)}</b></td></tr></table>
  <p style="margin-top:16px"><b>Terms:</b> ${esc($('#qTerms').value)}</p><p>Sales contact: ${esc($('#qSales').value)}</p>`;
  window.print();
});

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
$('#aExport').addEventListener('click',()=>download('sales-pricing-data.json',JSON.stringify(DATA,null,1),'application/json'));
$('#aImport').addEventListener('change',e=>{const f=e.target.files[0];if(!f)return;const rd=new FileReader();rd.onload=()=>{try{const d=JSON.parse(rd.result);if(!d.vci||!d.lubes)throw 0;DATA=d;markDirty();renderAdmin();refreshAll(true);$('#aMsg').textContent='Imported.'}catch(err){$('#aMsg').textContent='That file is not a valid price export.'}};rd.readAsText(f)});
$('#aReset').addEventListener('click',()=>{if(!confirm('Discard all local edits and go back to the published prices?'))return;LS.del('spd_override');DATA=clone(SEED);$('#overrideBanner').classList.add('hide');renderAdmin();refreshAll(true)});
$('#aDownload').addEventListener('click',async()=>{
  let src=null;
  try{const r=await fetch(location.href,{cache:'no-store'});if(r.ok)src=await r.text()}catch(e){}
  if(!src||src.indexOf('/*PRICEDATA_START*/')<0){src='<!doctype html>\n'+document.documentElement.outerHTML.replace(/<div id="printArea">[\s\S]*?<\/div>\s*<script id="seed"/,'<div id="printArea"></div>\n<script id="seed"');}
  const json=JSON.stringify(DATA).replace(/<\//g,'<\\/');
  const out=src.replace(/\/\*PRICEDATA_START\*\/[\s\S]*?\/\*PRICEDATA_END\*\//,()=>'/*PRICEDATA_START*/'+json+'/*PRICEDATA_END*/');
  download('sales-pricing-desk.html',out,'text/html');
  $('#aMsg').innerHTML='Downloaded <b>sales-pricing-desk.html</b> as a backup copy.';
});
function download(name,text,type){const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([text],{type}));a.download=name;document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove()},500)}

// Persists DATA to the database (pricing_desk_data table) via PricingDeskController::save().
// Every viewer's next page load reads this same row, so there is nothing
// left to download or re-upload — unlike the original file-swap workflow
// aDownload above still supports as an offline backup.
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

/* ---------- boot ---------- */
function refreshAll(full){renderSegs();buildDatalist();if(Q.qType)$('#dType').value=Q.qType;renderDisc();$('#verLabel').textContent=DATA.version;if(full){fillCats();selIdx=null}renderPrices();renderCheck();renderLegend();renderCalc();renderRef();renderQuote()}
if(LS.get('spd_override'))$('#overrideBanner').classList.remove('hide');
renderSegs();fillCats();renderCalcList();refreshAll(true);saveQ();
const lt=LS.get('spd_tab');if(lt&&lt!=='admin')showTab(lt);
})();
</script>
</body>
</html>