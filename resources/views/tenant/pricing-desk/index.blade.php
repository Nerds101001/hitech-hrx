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
      <p class="sub-text text-white-50 mb-0">Price list, discount approval finder, product calculators and quotes.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap" id="pdHeroStats">
      <div class="pd-hero-stat"><span class="pd-hero-icon"><i class="bx bx-grid-alt"></i></span><div><div class="pd-hero-v" id="pdStatFamilies">–</div><div class="pd-hero-k">Product Families</div></div></div>
      <div class="pd-hero-stat"><span class="pd-hero-icon"><i class="bx bx-package"></i></span><div><div class="pd-hero-v" id="pdStatProducts">–</div><div class="pd-hero-k">Products</div></div></div>
      <div class="pd-hero-stat"><span class="pd-hero-icon"><i class="bx bx-shield-quarter"></i></span><div><div class="pd-hero-v" id="pdStatBands">–</div><div class="pd-hero-k">Approval Bands</div></div></div>
    </div>
  </div>
</div>

<div id="pd2Root">
<style>
/* ============ Design tokens — a self-contained system for this redesign ============ */
#pd2Root{
  --ink:#172B4D;--sub:#64748B;--label:#475569;--line:#DCE5ED;--line-soft:#E2E8F0;
  --page-bg:#F3F6F9;--card-bg:#FFFFFF;--chip-bg:#F3F6F9;
  --brand:#006B68;--brand-dark:#005653;--brand-soft:#E6F5F3;--brand-soft2:#F0FAF9;
  --placeholder:#94A3B8;--focus:#0A9995;--focus-ring:rgba(10,153,149,.12);
  --head-bg:#EDF2F7;--head-text:#334155;
  --row-alt:#F8FAFC;--row-divider:#E8EEF4;--row-hover:#F0FAF9;--row-sel:#FFF4D6;--row-sel-bar:#0A9995;
  --ok-bg:#DCFCE7;--ok-text:#166534;
  --gm-bg:#FEF3C7;--gm-text:#92400E;
  --ch-bg:#DBEAFE;--ch-text:#1D4ED8;
  --bad-bg:#FEE2E2;--bad-text:#991B1B;
  --danger:#DC2626;
  background:var(--page-bg);color:var(--ink);font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
  border-radius:12px;padding:16px;
}
#pd2Root *{box-sizing:border-box}
#pd2Root button,#pd2Root input,#pd2Root select,#pd2Root textarea{font:inherit;color:inherit}
#pd2Root input:focus,#pd2Root select:focus,#pd2Root textarea:focus,#pd2Root button:focus-visible{outline:none;border-color:var(--focus)!important;box-shadow:0 0 0 3px var(--focus-ring)}
#pd2Root .hide{display:none!important}
#pd2Root .muted{color:var(--sub)}

/* top-level Price List / Calculators / Admin tabs */
#pd2Root .pd2-toplevel{background:#fff;border:1px solid var(--line);border-radius:10px;padding:10px;margin-bottom:14px;display:flex;gap:4px;flex-wrap:wrap}
#pd2Root .pd2-toplevel button{border:0;background:transparent;color:var(--label);padding:9px 18px;border-radius:50px;font-weight:600;font-size:13.5px;display:flex;align-items:center;gap:7px;cursor:pointer}
#pd2Root .pd2-toplevel button.active{background:var(--brand);color:#fff}
#pd2Root .pd2-toplevel button i{font-size:16px}

/* ---- Price List tab layout ---- */
#pd2Root .pd2-grid{display:grid;grid-template-columns:minmax(0,74fr) minmax(0,26fr);gap:14px;align-items:start}
@media (max-width:1550px){#pd2Root .pd2-grid{grid-template-columns:minmax(0,1fr)}#pd2Root .pd2-side{position:static}}
#pd2Root .pd2-side{position:sticky;top:12px;display:flex;flex-direction:column;gap:12px}
#pd2Root .pd2-card{background:var(--card-bg);border:1px solid var(--line);border-radius:10px;padding:14px 16px}
#pd2Root .pd2-card h2{font-size:15px;font-weight:650;margin:0 0 4px;display:flex;align-items:center;gap:7px;color:var(--ink)}
#pd2Root .pd2-card h2 i{color:var(--sub);font-size:15px}
#pd2Root .pd2-card .pd2-desc{font-size:12px;line-height:1.5;color:var(--sub);margin-bottom:10px}

/* family tabs */
#pd2Root .pd2-famtabs{background:#F3F6F9;border:1px solid var(--line);border-radius:9px;padding:5px;display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px}
#pd2Root .pd2-famtabs button{height:36px;border:0;background:transparent;color:var(--label);padding:0 14px;border-radius:7px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:7px;cursor:pointer;white-space:nowrap}
#pd2Root .pd2-famtabs button i{font-size:15px}
#pd2Root .pd2-famtabs button.on{background:#006B68;color:#fff}

/* toolbar */
#pd2Root .pd2-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
#pd2Root .pd2-search{position:relative;flex:1 1 260px;min-width:200px;max-width:320px}
#pd2Root .pd2-search i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--placeholder);font-size:16px}
#pd2Root .pd2-search input{width:100%;height:38px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:0 10px 0 34px;font-size:13px}
#pd2Root .pd2-search input::placeholder{color:var(--placeholder)}
#pd2Root select.pd2-select{height:38px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:0 28px 0 12px;font-size:13px;min-width:150px;max-width:210px;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
#pd2Root .pd2-count{font-size:12.5px;color:var(--sub);white-space:nowrap}
#pd2Root .pd2-spacer{flex:1 1 auto;min-width:4px}
#pd2Root .pd2-btn{height:38px;padding:0 16px;border-radius:8px;font-weight:650;font-size:13px;display:inline-flex;align-items:center;gap:7px;cursor:pointer;white-space:nowrap}
#pd2Root .pd2-btn i{font-size:16px}
#pd2Root .pd2-btn-ghost{background:#fff;color:var(--brand);border:1px solid #A8D9D5}
#pd2Root .pd2-btn-ghost:hover{background:var(--brand-soft);border-color:var(--focus)}
#pd2Root .pd2-btn-solid{background:var(--brand);color:#fff;border:1px solid var(--brand)}
#pd2Root .pd2-btn-solid:hover{background:var(--brand-dark);border-color:var(--brand-dark)}
#pd2Root .pd2-btn-subtle{background:var(--head-bg);color:var(--head-text);border:1px solid var(--line)}
#pd2Root .pd2-btn-subtle:hover{background:#E2E8F0}
#pd2Root .pd2-btn:disabled{background:#CBD5E1!important;color:#64748B!important;border-color:#CBD5E1!important;cursor:not-allowed}

/* table card */
#pd2Root .pd2-tablecard{background:#fff;border:1px solid var(--line);border-radius:10px;overflow:hidden}
#pd2Root .pd2-tablewrap{overflow:auto;max-height:62vh;scrollbar-width:thin;scrollbar-color:#94A3B8 var(--head-bg)}
#pd2Root .pd2-tablewrap::-webkit-scrollbar{height:10px;width:10px}
#pd2Root .pd2-tablewrap::-webkit-scrollbar-track{background:var(--head-bg)}
#pd2Root .pd2-tablewrap::-webkit-scrollbar-thumb{background:#94A3B8;border-radius:6px}
#pd2Root table.pd2-table{border-collapse:collapse;width:100%;font-size:13px}
#pd2Root table.pd2-table th{position:sticky;top:0;background:var(--head-bg);color:var(--head-text);font-size:12px;font-weight:650;height:44px;padding:0 10px;text-align:left;white-space:nowrap;border-bottom:1px solid var(--line);z-index:1;cursor:pointer;user-select:none}
#pd2Root table.pd2-table th.n{text-align:right}
#pd2Root table.pd2-table th.c{text-align:center}
#pd2Root table.pd2-table th .pd2-sub{font-size:10.5px;font-weight:500;color:#7A8CA3}
#pd2Root table.pd2-table th i.bx-sort-up,#pd2Root table.pd2-table th i.bx-sort-down{color:var(--brand)}
#pd2Root table.pd2-table td{padding:7px 10px;border-bottom:1px solid var(--row-divider);vertical-align:top;line-height:1.3}
#pd2Root table.pd2-table td.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
#pd2Root table.pd2-table td.c{text-align:center}
#pd2Root table.pd2-table tr.cat td{background:var(--head-bg);font-weight:650;color:var(--brand);font-size:12px}
#pd2Root table.pd2-table tr.cat.cat-note td{background:var(--gm-bg);color:var(--gm-text)}
#pd2Root table.pd2-table tr.cat.cat-note td i{margin-right:5px}
#pd2Root table.pd2-table tbody tr.item{cursor:pointer;min-height:48px}
#pd2Root table.pd2-table tbody tr.item:nth-child(even){background:var(--row-alt)}
#pd2Root table.pd2-table tbody tr.item:hover td{background:var(--row-hover)}
#pd2Root table.pd2-table tbody tr.item.sel td{background:var(--row-sel)!important;box-shadow:inset 3px 0 0 var(--row-sel-bar)}
#pd2Root .pd2-code{font-size:12.5px;font-weight:650}
#pd2Root .pd2-pname{font-size:12.5px;font-weight:500}
#pd2Root .pd2-pspec{font-size:11px;color:#64748B;margin-top:1px}
#pd2Root .pd2-actions-btn{width:28px;height:28px;border-radius:7px;border:0;background:transparent;color:#64748B;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
#pd2Root .pd2-actions-btn:hover{background:var(--row-hover);color:var(--brand)}
#pd2Root .pd2-menu{position:fixed;background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 10px 30px rgba(23,43,77,.18);padding:4px;min-width:170px;z-index:2000}
#pd2Root .pd2-menu button{display:flex;width:100%;align-items:center;gap:8px;padding:8px 10px;border:0;background:transparent;border-radius:6px;font-size:12.5px;text-align:left;cursor:pointer;color:var(--ink)}
#pd2Root .pd2-menu button:hover{background:var(--row-hover)}
#pd2Root .pd2-menu button i{font-size:15px;color:var(--sub)}

/* table footer / pagination */
#pd2Root .pd2-tfoot{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:10px 14px;border-top:1px solid var(--line);font-size:12.5px;color:var(--sub)}
#pd2Root .pd2-pager{display:flex;align-items:center;gap:4px;margin-left:auto}
#pd2Root .pd2-pager button{width:28px;height:28px;border-radius:6px;border:1px solid var(--line);background:#fff;color:var(--ink);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:12px}
#pd2Root .pd2-pager button.on{background:var(--brand);color:#fff;border-color:var(--brand)}
#pd2Root .pd2-pager button:disabled{opacity:.4;cursor:not-allowed}
#pd2Root select.pd2-pagesize{height:28px;border:1px solid var(--line);border-radius:6px;font-size:12px;padding:0 6px;background:#fff}

/* badges / pills */
#pd2Root .pd2-pill{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:4px 11px;font-size:12px;font-weight:650;white-space:nowrap}
#pd2Root .pd2-pill i{font-size:14px}
#pd2Root .pd2-pill.lg{padding:9px 18px;font-size:15.5px}
#pd2Root .pd2-pill.lg i{font-size:19px}
#pd2Root .pd2-pill.ok{background:var(--ok-bg);color:var(--ok-text)}
#pd2Root .pd2-pill.gm{background:var(--gm-bg);color:var(--gm-text)}
#pd2Root .pd2-pill.ch{background:var(--ch-bg);color:var(--ch-text)}
#pd2Root .pd2-pill.bad{background:var(--bad-bg);color:var(--bad-text)}
#pd2Root .pd2-pill.neutral{background:var(--head-bg);color:var(--sub)}

/* customer type / price check / bands cards */
#pd2Root .pd2-kv{display:grid;grid-template-columns:1fr auto;gap:5px 10px;font-size:13px}
#pd2Root .pd2-kv .k{color:var(--sub)}
#pd2Root .pd2-kv .v{text-align:right;font-variant-numeric:tabular-nums;font-weight:600}
#pd2Root .pd2-empty{background:#F8FAFC;border-radius:8px;padding:22px 14px;text-align:center;color:var(--sub);font-size:12.5px}
#pd2Root .pd2-empty i{font-size:26px;color:#A8B6C6;display:block;margin-bottom:8px}
#pd2Root .pd2-field{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--label);font-weight:550}
#pd2Root .pd2-field input,#pd2Root .pd2-field select{height:36px;border:1px solid var(--line);border-radius:7px;padding:0 10px;font-size:13px;color:var(--ink);background:#fff}
#pd2Root .pd2-bandtable{width:100%;border-collapse:collapse;font-size:11.5px}
#pd2Root .pd2-bandtable th{font-size:11px;color:var(--sub);font-weight:650;padding:5px 6px;text-align:right}
#pd2Root .pd2-bandtable th:first-child{text-align:left}
#pd2Root .pd2-bandtable td{padding:5px 6px;text-align:right;white-space:nowrap}
#pd2Root .pd2-bandtable td:first-child{text-align:left}
#pd2Root .pd2-bandwrap{overflow-x:auto}
#pd2Root .pd2-note{background:#EDF4F8;color:var(--label);border-radius:7px;padding:10px 12px;font-size:11.5px;margin-top:10px;display:flex;gap:8px;align-items:flex-start}
#pd2Root .pd2-note i{font-size:16px;color:#64748B;flex-shrink:0}

/* ---- Calculators tab ---- */
#pd2Root .pd2-calc-shell{display:flex;min-height:600px;max-height:76vh;border:1px solid var(--line);border-radius:12px;overflow:hidden;background:#fff}
#pd2Root .pd2-cat{width:250px;flex-shrink:0;border-right:1px solid var(--line);display:flex;flex-direction:column;background:#fbfcfd}
#pd2Root .pd2-cat-head{padding:16px}
#pd2Root .pd2-cat-head h3{font-size:17px;font-weight:700;color:var(--ink);margin:0}
#pd2Root .pd2-cat-head p{font-size:12px;color:var(--sub);margin:2px 0 10px}
#pd2Root .pd2-cat-search{position:relative}
#pd2Root .pd2-cat-search i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--placeholder);font-size:15px}
#pd2Root .pd2-cat-search input{width:100%;height:36px;border:1px solid var(--line);border-radius:8px;padding:0 10px 0 32px;font-size:12.5px;background:#fff}
#pd2Root .pd2-cat-list{flex:1;overflow-y:auto;padding:4px 0}
#pd2Root .pd2-cat-group{width:100%;display:flex;align-items:center;gap:8px;border:0;background:none;padding:8px 14px;font-size:12.5px;font-weight:650;color:#2b2f33;cursor:pointer}
#pd2Root .pd2-cat-group:hover{background:#F8FAFC}
#pd2Root .pd2-cat-group .gi{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;flex-shrink:0}
#pd2Root .pd2-cat-group .badge-count{margin-left:auto;background:var(--head-bg);color:#6b7684;font-size:11px;font-weight:700;border-radius:999px;padding:1px 8px}
#pd2Root .pd2-cat-group .chev{transition:transform .15s;color:#9aa5b1;font-size:13px}
#pd2Root .pd2-cat-group.collapsed .chev{transform:rotate(-90deg)}
#pd2Root .pd2-cat-item{display:block;width:100%;text-align:left;border:0;background:none;border-left:3px solid transparent;padding:7px 14px 7px 44px;font-size:12.5px;color:#4a525c;cursor:pointer}
#pd2Root .pd2-cat-item:hover{background:#F8FAFC}
#pd2Root .pd2-cat-item.active{background:var(--brand-soft);color:var(--brand);font-weight:650;border-left-color:var(--brand)}

#pd2Root .pd2-work{flex:1;overflow-y:auto;padding:20px 22px}
#pd2Root .pd2-work-head{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;margin-bottom:16px}
#pd2Root .pd2-work-title{display:flex;align-items:flex-start;gap:12px}
#pd2Root .pd2-work-icon{width:52px;height:52px;border-radius:10px;background:#F1F5F9;color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
#pd2Root .pd2-work-title h4{font-size:19px;font-weight:700;color:var(--ink);margin:0 0 2px}
#pd2Root .pd2-work-title p{font-size:12px;color:var(--sub);line-height:1.5;margin:0;max-width:460px}
#pd2Root .pd2-unitseg{display:inline-flex;background:#F1F5F9;border:1px solid var(--line);border-radius:9px;height:38px;padding:3px}
#pd2Root .pd2-unitseg button{border:0;background:transparent;color:var(--label);padding:0 16px;border-radius:7px;font-size:12.5px;font-weight:600;cursor:pointer}
#pd2Root .pd2-unitseg button.active{background:var(--brand);color:#fff}
#pd2Root .pd2-section-head{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--sub);margin:18px 0 10px;padding-top:14px;border-top:1px solid var(--line)}
#pd2Root .pd2-section-head:first-of-type{border-top:0;padding-top:0;margin-top:0}
#pd2Root .pd2-section-head i{font-size:14px}
#pd2Root .pd2-fgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px}
@media (max-width:520px){#pd2Root .pd2-fgrid{grid-template-columns:1fr}}
#pd2Root .pd2-field .req{color:var(--danger)}
#pd2Root .pd2-field input:invalid,#pd2Root .pd2-field input.invalid{border-color:var(--danger)}
#pd2Root .pd2-field .err{color:var(--danger);font-size:11px}
#pd2Root .pd2-rategrid{display:grid;grid-template-columns:minmax(0,42fr) minmax(0,28fr) minmax(0,28fr);gap:12px}
@media (max-width:760px){#pd2Root .pd2-rategrid{grid-template-columns:1fr}}

#pd2Root .pd2-results{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-top:16px}
#pd2Root .pd2-rescard{min-height:82px;padding:14px 16px;border-radius:10px;border:1px solid var(--line);display:flex;align-items:center;gap:12px}
#pd2Root .pd2-rescard .ic{width:50px;height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0}
#pd2Root .pd2-rescard .lbl{font-size:12px;font-weight:600;color:#475569}
#pd2Root .pd2-rescard .val{font-size:24px;font-weight:750;line-height:1.1;word-break:break-word}
#pd2Root .pd2-rescard.v-weight{background:#E8FAF6}
#pd2Root .pd2-rescard.v-weight .ic{background:#C7F4EA;color:#006B68}
#pd2Root .pd2-rescard.v-count{background:#EFF8FF}
#pd2Root .pd2-rescard.v-count .ic{background:#D7EEFF;color:#1769AA}
#pd2Root .pd2-rescard.v-price{background:#FFF8E7}
#pd2Root .pd2-rescard.v-price .ic{background:#FDECC0;color:#A16207}

#pd2Root .pd2-approval-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:16px;background:#F8FAFC;border:1px solid var(--line);border-radius:10px;padding:14px 16px}
#pd2Root .pd2-approval-row .rate-txt{font-size:14.5px;color:var(--label);font-weight:600}
#pd2Root .pd2-actionbar{display:flex;justify-content:flex-end;gap:10px;margin-top:22px;padding-top:16px;border-top:1px solid var(--line)}
#pd2Root .pd2-empty-work{text-align:center;color:var(--sub);padding:60px 20px}
#pd2Root .pd2-empty-work i{font-size:34px;display:block;margin-bottom:10px;color:#A8B6C6}
@media (max-width:860px){#pd2Root .pd2-calc-shell{flex-direction:column;max-height:none}#pd2Root .pd2-cat{width:100%;max-height:260px}}

/* ---- Admin tab ---- */
#pd2Root .pd2-admin-shell{display:flex;flex-direction:column;gap:12px}
#pd2Root .pd2-admincard{background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px 16px;box-shadow:0 2px 10px rgba(23,43,77,.035)}
#pd2Root .pd2-admincard h2{font-size:16px;font-weight:700;color:var(--ink);margin:0 0 2px;display:flex;align-items:center;gap:8px}
#pd2Root .pd2-admincard h2 i{font-size:16px;color:var(--sub)}
#pd2Root .pd2-admincard .sub{font-size:12px;color:var(--sub);margin:0 0 12px}
#pd2Root .pd2-dataio{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
#pd2Root .pd2-dataio .ic{width:44px;height:44px;border-radius:9px;background:var(--brand-soft);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
#pd2Root .pd2-dataio .txt h3{font-size:15px;font-weight:700;color:var(--ink);margin:0}
#pd2Root .pd2-dataio .txt p{font-size:12px;color:var(--sub);margin:0}
#pd2Root .pd2-dataio .acts{margin-left:auto;display:flex;gap:10px}
#pd2Root .pd2-rulegrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px 14px}
#pd2Root .pd2-rulegrid label{display:flex;flex-direction:column;gap:4px;font-size:11.5px;color:var(--sub);font-weight:550}
#pd2Root .pd2-rulegrid input{height:36px;border:1px solid var(--line);border-radius:7px;padding:0 9px;font-size:13px;color:var(--ink)}
#pd2Root .pd2-factorgrid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
@media (max-width:980px){#pd2Root .pd2-factorgrid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:620px){#pd2Root .pd2-factorgrid{grid-template-columns:repeat(2,minmax(0,1fr))}}
#pd2Root .pd2-factorgrid label{display:flex;flex-direction:column;gap:4px;font-size:11.5px;color:var(--sub);font-weight:550}
#pd2Root .pd2-factorgrid input{height:38px;border:1px solid var(--line);border-radius:7px;padding:0 9px;font-size:13px}
#pd2Root .pd2-savebar{display:flex;align-items:center;gap:10px;margin-top:12px;padding-top:12px;border-top:1px solid var(--line)}
#pd2Root .pd2-savemsg{font-size:12px}
#pd2Root .pd2-savemsg.ok{color:var(--ok-text)}
#pd2Root .pd2-savemsg.bad{color:var(--danger)}
#pd2Root table.pd2-atable{border-collapse:collapse;width:100%;font-size:12px}
#pd2Root table.pd2-atable th{background:var(--head-bg);color:var(--head-text);font-size:11px;font-weight:650;height:38px;padding:0 8px;text-align:left;position:sticky;top:0;white-space:nowrap;border-bottom:1px solid var(--line);z-index:1}
#pd2Root table.pd2-atable td{padding:4px 6px;border-bottom:1px solid var(--line-soft);vertical-align:middle}
#pd2Root table.pd2-atable tbody tr:nth-child(even){background:var(--row-alt)}
#pd2Root table.pd2-atable tbody tr:hover{background:var(--row-hover)}
#pd2Root table.pd2-atable input{height:30px;border:1px solid var(--line);border-radius:5px;padding:0 7px;font-size:11.5px;width:100%}
#pd2Root table.pd2-atable td.n input{text-align:right;font-variant-numeric:tabular-nums}
#pd2Root .pd2-deletebtn{width:28px;height:28px;border-radius:6px;border:0;background:#F8FAFC;color:var(--danger);cursor:pointer}
#pd2Root .pd2-deletebtn:hover{background:var(--bad-bg)}

/* modal */
#pd2Root .pd2-modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.40);display:flex;align-items:center;justify-content:center;z-index:3000;padding:16px}
#pd2Root .pd2-modal{background:#fff;border-radius:12px;max-width:680px;width:100%;max-height:88vh;display:flex;flex-direction:column;overflow:hidden}
#pd2Root .pd2-modal-head{padding:18px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center}
#pd2Root .pd2-modal-head h3{font-size:18px;font-weight:700;margin:0;color:var(--ink)}
#pd2Root .pd2-modal-close{border:0;background:none;font-size:20px;color:var(--sub);cursor:pointer;width:32px;height:32px;border-radius:7px}
#pd2Root .pd2-modal-close:hover{background:#F8FAFC}
#pd2Root .pd2-modal-body{padding:18px 20px;overflow-y:auto}
#pd2Root .pd2-modal-foot{padding:14px 20px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;gap:10px}
#pd2Root .pd2-dropzone{border:1px dashed #94A3B8;background:#F8FAFC;border-radius:10px;padding:26px 16px;text-align:center;cursor:pointer}
#pd2Root .pd2-dropzone.drag{background:var(--brand-soft);border-color:var(--focus)}
#pd2Root .pd2-dropzone i{font-size:26px;color:#64748B}
#pd2Root .pd2-importrow{display:flex;align-items:center;gap:8px;font-size:12px;padding:6px 10px;border-radius:7px;margin-top:4px}
#pd2Root .pd2-importrow.err{background:var(--bad-bg);color:var(--bad-text)}
#pd2Root .pd2-importrow.warn{background:var(--gm-bg);color:var(--gm-text)}
#pd2Root .pd2-importrow.ok{background:var(--ok-bg);color:var(--ok-text)}
</style>

<!-- ======= Top-level tabs ======= -->
<div class="pd2-toplevel" id="pd2Tabs">
  <button type="button" class="active" data-t="prices"><i class="bx bx-list-ul"></i> Price List</button>
  <button type="button" data-t="calc"><i class="bx bx-calculator"></i> Calculators</button>
  @if($canManage)
  <button type="button" data-t="admin"><i class="bx bx-cog"></i> Admin</button>
  @endif
</div>

<!-- ======= PRICE LIST ======= -->
<section id="pd2-pane-prices" class="pd2-pane">
  <div class="pd2-grid">
    <div>
      <div class="pd2-tablecard" style="padding:14px 16px 0">
        <div class="pd2-famtabs" id="pd2FamTabs"></div>
        <div class="pd2-toolbar">
          <div class="pd2-search">
            <i class="bx bx-search"></i>
            <input type="search" id="pd2Search" placeholder="Search code, product, size...">
          </div>
          <select id="pd2Cat" class="pd2-select"></select>
          <span class="pd2-count" id="pd2Count"></span>
          <div class="pd2-spacer"></div>
          <button class="pd2-btn pd2-btn-ghost" id="pd2Export" type="button"><i class="bx bx-download"></i>Export</button>
        </div>
      </div>
      <div class="pd2-tablecard">
        <div class="pd2-tablewrap"><table class="pd2-table" id="pd2Table"></table></div>
        <div class="pd2-tfoot" id="pd2TFoot"></div>
      </div>
    </div>
    <div class="pd2-side">
      <div class="pd2-card">
        <h2><i class="bx bx-user"></i>Customer type</h2>
        <div class="pd2-desc">Channel pricing only clears without extra approval when quoting an actual channel partner. Set this before checking a price.</div>
        <select id="pd2QType" class="pd2-select" style="width:100%;max-width:none">
          <option>Direct customer</option><option>Channel partner</option>
        </select>
      </div>
      <div class="pd2-card" id="pd2CheckCard">
        <h2><i class="bx bx-calculator"></i>Price check</h2>
        <div id="pd2CheckEmpty" class="pd2-empty"><i class="bx bx-calculator"></i>Select a product from the list to check its price.</div>
        <div id="pd2CheckBody" class="hide">
          <div style="font-weight:650;font-size:13.5px" id="pd2ChkName"></div>
          <div class="muted" style="font-size:11.5px;margin-bottom:10px" id="pd2ChkSpec"></div>
          <div class="pd2-kv" id="pd2ChkTiers"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px">
            <div class="pd2-field"><span>Offered price (₹/<span id="pd2ChkUnit"></span>)</span><input type="number" id="pd2ChkPrice" step="0.01" min="0"></div>
            <div class="pd2-field"><span>Quantity</span><input type="number" id="pd2ChkQty" step="1" min="0" value="1"></div>
          </div>
          <div id="pd2ChkResult" style="margin-top:12px"></div>
          <div id="pd2ChkWarn"></div>
        </div>
      </div>
      <div class="pd2-card">
        <h2><i class="bx bx-shield-quarter"></i>Approval bands</h2>
        <div class="pd2-bandwrap"><div id="pd2Bands"></div></div>
      </div>
    </div>
  </div>
</section>

<!-- ======= CALCULATORS ======= -->
<section id="pd2-pane-calc" class="pd2-pane hide">
  <div class="pd2-calc-shell">
    <div class="pd2-cat">
      <div class="pd2-cat-head">
        <h3>Product Catalogue</h3>
        <p>Search and select a product</p>
        <div class="pd2-cat-search"><i class="bx bx-search"></i><input type="text" id="pd2cSearch" placeholder="Search product, category..." autocomplete="off"></div>
      </div>
      <div id="pd2cList" class="pd2-cat-list"></div>
    </div>
    <div class="pd2-work">
      <div id="pd2cEmpty" class="pd2-empty-work"><i class="bx bx-left-arrow-alt"></i>Pick a product from the catalogue on the left.</div>
      <div id="pd2cBody" class="hide">
        <div class="pd2-work-head">
          <div class="pd2-work-title">
            <div class="pd2-work-icon" id="pd2cIcon"><i class="bx bx-cube"></i></div>
            <div><h4 id="pd2cTitle"></h4><p id="pd2cDesc"></p></div>
          </div>
          <div class="pd2-unitseg" id="pd2cUnitSeg"></div>
        </div>

        <div class="pd2-section-head"><i class="bx bx-ruler"></i>Dimensions &amp; specification</div>
        <div class="pd2-fgrid" id="pd2cFields"></div>

        <div id="pd2cRateSection"></div>

        <div class="pd2-results" id="pd2cResults"></div>
        <div class="pd2-approval-row" id="pd2cBand"></div>
        <div id="pd2cNote"></div>
        <div id="pd2cError" class="pd2-importrow err hide" style="margin-top:14px"></div>

        <div class="pd2-actionbar">
          <button type="button" class="pd2-btn pd2-btn-ghost" id="pd2cReset"><i class="bx bx-reset"></i>Reset</button>
          <button type="button" class="pd2-btn pd2-btn-solid" id="pd2cCalc"><i class="bx bx-calculator"></i>Calculate</button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ======= ADMIN ======= -->
@if($canManage)
<section id="pd2-pane-admin" class="pd2-pane hide">
  <div class="pd2-admin-shell">

    <div class="pd2-admincard">
      <div class="pd2-dataio">
        <div class="ic"><i class="bx bx-data"></i></div>
        <div class="txt"><h3>Price List Data</h3><p>Import or export the current price list.</p></div>
        <div class="acts">
          <button type="button" class="pd2-btn pd2-btn-ghost" id="pd2aImportBtn"><i class="bx bx-upload"></i>Import</button>
          <button type="button" class="pd2-btn pd2-btn-solid" id="pd2aExportBtn"><i class="bx bx-download"></i>Export</button>
        </div>
      </div>
    </div>

    <div class="pd2-admincard">
      <h2><i class="bx bx-percentage"></i>Discount Rules (% off DLP)</h2>
      <div class="sub">Changing a rule does not retroactively touch saved prices — use Recalculate below to apply it.</div>
      <div class="pd2-rulegrid" id="pd2aRules"></div>
      <div class="pd2-savebar">
        <button class="pd2-btn pd2-btn-subtle" id="pd2aRecalc" type="button"><i class="bx bx-calculator"></i>Recalculate all tier prices from DLP</button>
        <span class="muted" style="font-size:11.5px">Lubricants round to whole rupees; other lists to paise.</span>
        <div class="pd2-spacer"></div>
        <button class="pd2-btn pd2-btn-solid" id="pd2aSaveRules" type="button"><i class="bx bx-save"></i>Save changes</button>
        <span class="pd2-savemsg" id="pd2aRulesMsg"></span>
      </div>
    </div>

    <div class="pd2-admincard">
      <h2><i class="bx bx-slider-alt"></i>Calculator Factors</h2>
      <div class="sub">These factors feed the Calculators tab's weight/price formulas directly.</div>
      <div class="pd2-factorgrid" id="pd2aFactors"></div>
      <div class="pd2-savebar">
        <div class="pd2-spacer"></div>
        <button class="pd2-btn pd2-btn-solid" id="pd2aSaveFactors" type="button"><i class="bx bx-save"></i>Save changes</button>
        <span class="pd2-savemsg" id="pd2aFactorsMsg"></span>
      </div>
    </div>

    <div class="pd2-admincard">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px">
        <h2 style="margin:0"><i class="bx bx-table"></i>Edit price list</h2>
        <button class="pd2-btn pd2-btn-solid" id="pd2aAdd" type="button"><i class="bx bx-plus"></i>Add product</button>
      </div>
      <div class="pd2-famtabs" id="pd2aFamTabs" style="margin-bottom:10px"></div>
      <div class="pd2-toolbar" style="margin-bottom:10px">
        <div class="pd2-search" style="max-width:280px">
          <i class="bx bx-search"></i>
          <input type="search" id="pd2aSearch" placeholder="Search products...">
        </div>
        <select id="pd2aCat" class="pd2-select"></select>
      </div>
      <div class="muted" style="font-size:11.5px;margin-bottom:8px">Changing DLP recalculates the three tier prices automatically. You can still overwrite a tier by hand.</div>
      <div class="pd2-tablewrap" style="max-height:60vh;border:1px solid var(--line);border-radius:8px"><table class="pd2-atable" id="pd2aTable"></table></div>
      <div class="pd2-savebar">
        <div class="pd2-spacer"></div>
        <button class="pd2-btn pd2-btn-solid" id="pd2aSaveTable" type="button"><i class="bx bx-save"></i>Save changes</button>
        <span class="pd2-savemsg" id="pd2aTableMsg"></span>
      </div>
    </div>

  </div>
</section>
@endif

<!-- Add product modal -->
<div id="pd2AddModal" class="pd2-modal-overlay hide">
  <div class="pd2-modal">
    <div class="pd2-modal-head"><h3>Add product</h3><button class="pd2-modal-close" id="pd2AddClose" type="button"><i class="bx bx-x"></i></button></div>
    <div class="pd2-modal-body">
      <div class="pd2-fgrid" id="pd2AddFields"></div>
      <div id="pd2AddErr" class="pd2-importrow err hide" style="margin-top:12px"></div>
    </div>
    <div class="pd2-modal-foot">
      <button class="pd2-btn pd2-btn-subtle" id="pd2AddCancel" type="button">Cancel</button>
      <button class="pd2-btn pd2-btn-solid" id="pd2AddConfirm" type="button"><i class="bx bx-plus"></i>Add product</button>
    </div>
  </div>
</div>

<!-- Import modal -->
<div id="pd2ImportModal" class="pd2-modal-overlay hide">
  <div class="pd2-modal">
    <div class="pd2-modal-head"><h3>Import price list (CSV)</h3><button class="pd2-modal-close" id="pd2ImportClose" type="button"><i class="bx bx-x"></i></button></div>
    <div class="pd2-modal-body">
      <p class="muted" style="font-size:12px;margin-top:0">Import a CSV in the same column layout as this page's own Export (Code, Product, Unit, DLP, three tier prices…). Rows matching an existing code are updated; new codes are appended. Only CSV is supported right now.</p>
      <div class="pd2-dropzone" id="pd2ImportDrop">
        <i class="bx bx-file-blank"></i>
        <div style="margin-top:8px;font-size:13px;font-weight:600">Drop a .csv file here</div>
        <div class="muted" style="font-size:11.5px;margin:4px 0 10px">or</div>
        <button type="button" class="pd2-btn pd2-btn-subtle" id="pd2ImportBrowse">Browse files</button>
        <input type="file" id="pd2ImportFile" accept=".csv" class="hide">
      </div>
      <div id="pd2ImportSummary" class="hide" style="margin-top:14px">
        <div style="font-size:13px;font-weight:650" id="pd2ImportFileName"></div>
        <div class="muted" style="font-size:12px;margin-bottom:8px" id="pd2ImportCounts"></div>
        <div id="pd2ImportRows" style="max-height:260px;overflow-y:auto"></div>
      </div>
    </div>
    <div class="pd2-modal-foot">
      <button class="pd2-btn pd2-btn-subtle" id="pd2ImportCancel" type="button">Cancel</button>
      <button class="pd2-btn pd2-btn-solid" id="pd2ImportConfirm" type="button" disabled><i class="bx bx-upload"></i>Import products</button>
    </div>
  </div>
</div>

<script id="pd2Seed" type="application/json">/*PRICEDATA_START*/{!! $seedJson !!}/*PRICEDATA_END*/</script>
<script>
(function(){
'use strict';
const CAN_MANAGE = @json($canManage);
const SAVE_URL = @json(route('sales-visits.pricing-desk.save'));
const SEED = JSON.parse(document.getElementById('pd2Seed').textContent.replace('/*PRICEDATA_START*/','').replace('/*PRICEDATA_END*/',''));
const LS = {
  get(k){try{const v=localStorage.getItem(k);return v?JSON.parse(v):null}catch(e){return null}},
  set(k,v){try{localStorage.setItem(k,JSON.stringify(v))}catch(e){}},
  del(k){try{localStorage.removeItem(k)}catch(e){}}
};
let DATA = LS.get('spd_override') || clone(SEED);
function clone(o){return JSON.parse(JSON.stringify(o))}
const LISTS=()=>DATA.lists||[{k:'vci',label:'VCI Packaging',short:'VCI',round:2,rich:true},{k:'lubes',label:'Lubricants & RP Oils',short:'Lubes',round:0,rich:false}];
const LM=k=>LISTS().find(x=>x.k===k)||{k,label:k,short:k,round:2,rich:true};
const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
const esc=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const fmt=(n,d=2)=>n==null||isNaN(n)?'–':Number(n).toLocaleString('en-IN',{minimumFractionDigits:d,maximumFractionDigits:d});
const r2=n=>Math.round(n*100)/100;

const FAM_ICON={vci:'bx-package',lubes:'bx-droplet',fillezy:'bx-box',drbio:'bx-leaf',kif:'bx-cube-alt'};

/* ================= approval bands (unchanged math) ================= */
const BAND_TXT=['No approval needed','GM approval','Channel price','Director approval'];
function tierNames(list){const r=DATA.rules[list];return [LM(list).dlpLabel||`DLP`,`No approval (${r[0]}% off)`,`GM (${r[1]}% off)`,`Channel (${r[2]}% off)`]}
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
const PILL_CLS=['ok','gm','ch','bad'];
const PILL_ICON=['bx-check-circle','bx-time-five','bx-group','bx-shield-alt-2'];
function pill(b,lg){
  const cls='pd2-pill'+(lg?' lg':'');
  if(!b) return `<span class="${cls} neutral">—</span>`;
  if(b.forced) return `<span class="${cls} bad"><i class="bx bx-shield-alt-2"></i>Management approval</span>`;
  if(b.lvl===2){
    const ok=b.eff===2;
    return `<span class="${cls} ${ok?'ok':'bad'}"><i class="bx ${ok?'bx-check-circle':'bx-shield-alt-2'}"></i>${ok?'Channel OK':'Channel → Director'}</span>`;
  }
  return `<span class="${cls} ${PILL_CLS[b.lvl]}"><i class="bx ${PILL_ICON[b.lvl]}"></i>${esc(BAND_TXT[b.lvl])}</span>`;
}

/* ================= top-level tabs ================= */
let calcInited=false;
function showPane(t){
  $$('.pd2-pane').forEach(p=>p.classList.add('hide'));
  $(`#pd2-pane-${t}`).classList.remove('hide');
  $$('#pd2Tabs button').forEach(b=>b.classList.toggle('active',b.dataset.t===t));
  if(t==='calc'&&!calcInited){calcInited=true;initCalcTab()}
  if(t==='admin'&&CAN_MANAGE)renderAdmin();
  LS.set('spd_tab',t);
}
$('#pd2Tabs').addEventListener('click',e=>{const b=e.target.closest('button[data-t]');if(b)showPane(b.dataset.t)});

/* ================= PRICE LIST ================= */
let curList='vci', selIdx=null, pdPage=1, pdPageSize=25;
function famHtml(cur,idPrefix){return LISTS().filter(m=>DATA[m.k]).map(m=>`<button data-l="${m.k}" class="${m.k===cur?'on':''}"><i class="bx ${FAM_ICON[m.k]||'bx-cube'}"></i>${esc(m.label)}</button>`).join('')}
function renderFamTabs(){
  $('#pd2FamTabs').innerHTML=famHtml(curList);
  if(CAN_MANAGE)$('#pd2aFamTabs').innerHTML=famHtml(aList);
}
$('#pd2FamTabs').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;curList=b.dataset.l;renderFamTabs();selIdx=null;pdPage=1;fillCats();renderPrices();renderCheck()});
$('#pd2Search').addEventListener('input',()=>{pdPage=1;renderPrices()});
$('#pd2Cat').addEventListener('change',()=>{pdPage=1;renderPrices()});
function fillCats(){const cats=[...new Set(DATA[curList].map(x=>x.cat))];$('#pd2Cat').innerHTML='<option value="">All categories</option>'+cats.map(c=>`<option>${esc(c)}</option>`).join('')}
function itemText(x){return [x.code,x.name,x.spec,x.size,x.colour,x.cat].join(' ').toLowerCase()}
let sortKey=null, sortDir=1;
const SORT_ACCESSORS={code:x=>x.code||'',name:x=>x.name||'',unit:x=>x.unit||'',dlp:x=>x.dlp||0,t0:x=>x.t[0]||0,t1:x=>x.t[1]||0,t2:x=>x.t[2]||0,cls:x=>x.cls||''};
function sortTh(key,label,extra,align){
  const active=sortKey===key;
  const icon=active?(sortDir===1?'bx-sort-up':'bx-sort-down'):'bx-sort-alt-2';
  return `<th data-sk="${key}" class="${align||''}">${esc(label)} <i class="bx ${icon}" style="font-size:12px"></i>${extra||''}</th>`;
}
function filteredRows(){
  const q=$('#pd2Search').value.trim().toLowerCase(), cat=$('#pd2Cat').value, L=DATA[curList];
  let rows=L.map((x,i)=>({x,i})).filter(({x})=>(!cat||x.cat===cat)&&(!q||itemText(x).includes(q)));
  if(sortKey){const acc=SORT_ACCESSORS[sortKey];rows=rows.slice().sort((a,b)=>{const av=acc(a.x),bv=acc(b.x);return (av<bv?-1:av>bv?1:0)*sortDir})}
  return rows;
}
function renderPrices(){
  const L=DATA[curList], tn=tierNames(curList);
  const isV=LM(curList).rich, dp=LM(curList).round;
  const r=DATA.rules[curList];
  let h=`<thead><tr>${sortTh('code','Code')}${isV?sortTh('name','Product'):''}${sortTh('unit','Unit','','c')}${sortTh('dlp',esc(tn[0]),'','n')}`+
    `<th class="n" data-sk="t0">No approval <i class="bx ${sortKey==='t0'?(sortDir===1?'bx-sort-up':'bx-sort-down'):'bx-sort-alt-2'}" style="font-size:12px"></i><div class="pd2-sub">${r[0]}% off</div></th>`+
    `<th class="n" data-sk="t1">GM <i class="bx ${sortKey==='t1'?(sortDir===1?'bx-sort-up':'bx-sort-down'):'bx-sort-alt-2'}" style="font-size:12px"></i><div class="pd2-sub">${r[1]}% off</div></th>`+
    `<th class="n" data-sk="t2">Channel <i class="bx ${sortKey==='t2'?(sortDir===1?'bx-sort-up':'bx-sort-down'):'bx-sort-alt-2'}" style="font-size:12px"></i><div class="pd2-sub">${r[2]}% off</div></th>`+
    `${isV?'':sortTh('cls','Class')}<th class="c">Actions</th></tr></thead><tbody>`;
  let rows=filteredRows();
  const total=rows.length;
  const pageCount=Math.max(1,pdPageSize==='all'?1:Math.ceil(total/pdPageSize));
  if(pdPage>pageCount)pdPage=pageCount;
  const pageRows=pdPageSize==='all'?rows:rows.slice((pdPage-1)*pdPageSize,pdPage*pdPageSize);
  let last=null;
  pageRows.forEach(({x,i})=>{
    if(x.cat!==last){
      const isNote=/KG PRICE ONLY PER PC/i.test(x.cat||'');
      h+=`<tr class="cat${isNote?' cat-note':''}"><td colspan="8">${isNote?'<i class="bx bx-error-circle"></i>':''}${esc(x.cat)}</td></tr>`;
      last=x.cat;
    }
    h+=`<tr class="item${i===selIdx?' sel':''}" data-i="${i}">`+
      `<td class="pd2-code">${esc(x.code)}</td>`+
      `${isV?`<td><div class="pd2-pname">${esc(x.name)}</div><div class="pd2-pspec">${esc([x.spec,x.size,x.colour].filter(Boolean).join(' · '))}</div>${x.note?`<div class="pd2-pspec" style="color:var(--bad-text)">${esc(x.note)}</div>`:''}</td>`:''}`+
      `<td class="c">${esc(x.unit||'')}</td>`+
      `<td class="n">${x.dlp?'₹ '+fmt(x.dlp,dp):`<span class="muted">${x.force?'on request':'net'}</span>`}</td>`+
      `<td class="n">${x.t[0]?'₹ '+fmt(x.t[0],dp):'–'}</td><td class="n">${x.t[1]?'₹ '+fmt(x.t[1],dp):'–'}</td><td class="n">${x.t[2]?'₹ '+fmt(x.t[2],dp):'–'}</td>`+
      `${isV?'':`<td>${esc(x.cls||'')}</td>`}`+
      `<td class="c"><button class="pd2-actions-btn" data-menu="${i}" type="button"><i class="bx bx-dots-vertical-rounded"></i></button></td>`+
      `</tr>`;
  });
  $('#pd2Table').innerHTML=h+'</tbody>';
  $('#pd2Count').textContent=`Showing ${total} product${total===1?'':'s'}`;
  renderPdFoot(total,pageCount);
}
function renderPdFoot(total,pageCount){
  const sizeOpts=[25,50,100].map(n=>`<option value="${n}"${pdPageSize===n?' selected':''}>${n} / page</option>`).join('')+`<option value="all"${pdPageSize==='all'?' selected':''}>All</option>`;
  let pager='';
  if(pdPageSize!=='all'&&pageCount>1){
    pager+=`<button data-pg="prev" ${pdPage<=1?'disabled':''}><i class="bx bx-chevron-left"></i></button>`;
    const maxBtns=5; let start=Math.max(1,pdPage-2), end=Math.min(pageCount,start+maxBtns-1); start=Math.max(1,end-maxBtns+1);
    for(let p=start;p<=end;p++) pager+=`<button data-pg="${p}" class="${p===pdPage?'on':''}">${p}</button>`;
    pager+=`<button data-pg="next" ${pdPage>=pageCount?'disabled':''}><i class="bx bx-chevron-right"></i></button>`;
  }
  $('#pd2TFoot').innerHTML=`<span>${total} products · ${esc(LM(curList).label)}</span>`+
    `<span>·  ${esc(LM(curList).source||DATA.version||'')}</span>`+
    `<select class="pd2-pagesize" id="pd2PageSize">${sizeOpts}</select>`+
    `<div class="pd2-pager">${pager}</div>`;
  $('#pd2PageSize').addEventListener('change',e=>{pdPageSize=e.target.value==='all'?'all':+e.target.value;pdPage=1;renderPrices()});
  $('#pd2TFoot').querySelectorAll('[data-pg]').forEach(b=>b.addEventListener('click',()=>{
    const v=b.dataset.pg; if(v==='prev')pdPage--; else if(v==='next')pdPage++; else pdPage=+v; renderPrices();
  }));
}
$('#pd2Table').addEventListener('click',e=>{
  const th=e.target.closest('th[data-sk]');
  if(th){const k=th.dataset.sk;sortDir=(sortKey===k)?-sortDir:1;sortKey=k;renderPrices();return}
  const menuBtn=e.target.closest('[data-menu]');
  if(menuBtn){openRowMenu(menuBtn);return}
  const tr=e.target.closest('tr.item');if(!tr)return;selIdx=+tr.dataset.i;renderPrices();renderCheck();$('#pd2ChkPrice').focus()
});
function closeRowMenu(){const m=document.querySelector('.pd2-menu');if(m)m.remove()}
function openRowMenu(btn){
  closeRowMenu();
  const i=+btn.dataset.menu;
  const rect=btn.getBoundingClientRect();
  const menu=document.createElement('div');
  menu.className='pd2-menu';
  menu.style.top=(rect.bottom+4)+'px';
  menu.style.left=Math.max(8,rect.right-180)+'px';
  menu.innerHTML=`<button data-act="check"><i class="bx bx-calculator"></i>Check price</button>`+
    (CAN_MANAGE?`<button data-act="edit"><i class="bx bx-edit-alt"></i>Edit in Admin</button>`:'');
  document.body.appendChild(menu);
  menu.addEventListener('click',e=>{
    const b=e.target.closest('button'); if(!b)return;
    if(b.dataset.act==='check'){selIdx=i;renderPrices();renderCheck();$('#pd2ChkPrice').focus()}
    if(b.dataset.act==='edit'){aList=curList;renderFamTabs();renderATable();showPane('admin');const x=DATA[curList][i];$('#pd2aSearch').value=x.code;renderATable()}
    closeRowMenu();
  });
  setTimeout(()=>document.addEventListener('click',closeRowMenu,{once:true}),0);
}
$('#pd2Export').addEventListener('click',()=>{
  const rows=filteredRows().map(r=>r.x);
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
  $('#pd2CheckEmpty').classList.toggle('hide',!!x);$('#pd2CheckBody').classList.toggle('hide',!x);if(!x)return;
  const tn=tierNames(curList);
  $('#pd2ChkName').textContent=x.code+(x.name?' — '+x.name:'');
  $('#pd2ChkSpec').textContent=[x.spec,x.size,x.colour,x.cat].filter(Boolean).join(' · ');
  $('#pd2ChkUnit').textContent=x.unit||'unit';
  $('#pd2ChkTiers').innerHTML=[[tn[0],x.dlp],[tn[1],x.t[0]],[tn[2],x.t[1]],[tn[3],x.t[2]]].map(([k,v])=>`<div class="k">${esc(k)}</div><div class="v">₹ ${v?fmt(v,LM(curList).round):'—'}</div>`).join('');
  $('#pd2ChkPrice').value=x.t[0]; updCheck();
}
function isKgOnly(x,l){l=l||curList;return !!x.perPc || (l==='vci' && /KG PRICE ONLY PER PC/i.test(x.cat||'') && (x.unit||'').toUpperCase()==='KG')}
function updCheck(){
  const x=DATA[curList][selIdx]; if(!x)return;
  const p=parseFloat($('#pd2ChkPrice').value), q=parseFloat($('#pd2ChkQty').value)||0, b=band(p,x.dlp,x.t,$('#pd2QType').value,x.force);
  if(!b){$('#pd2ChkResult').innerHTML='';return}
  $('#pd2ChkResult').innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">${pill(b)}<span class="muted" style="font-size:12px">${b.disc!=null?fmt(b.disc,1)+'% off '+esc(tierNames(curList)[0]):'net-priced item'}</span></div>`+
    `<div style="font-size:12px;margin-top:6px;color:var(--sub)">${esc(b.label)}${b.above?' · above DLP':''}</div>`+
    `<div class="pd2-kv" style="margin-top:8px"><div class="k">Line value</div><div class="v">₹ ${fmt(p*q)} + GST</div></div>`;
  $('#pd2ChkWarn').innerHTML=(isKgOnly(x)?`<div class="pd2-note" style="background:var(--bad-bg);color:var(--bad-text)"><i class="bx bx-error-circle"></i><div><b>Do not quote per kg.</b> This product must be quoted per piece — use the Calculators tab to convert this rate to ₹/pc.</div></div>`:'')+
    (x.note&&!isKgOnly(x)||x.force?`<div class="pd2-note" style="background:var(--bad-bg);color:var(--bad-text)"><i class="bx bx-error-circle"></i><div>${esc(x.note||x.force)}</div></div>`:'');
}
$('#pd2ChkPrice').addEventListener('input',updCheck);$('#pd2ChkQty').addEventListener('input',updCheck);
$('#pd2QType').addEventListener('change',updCheck);
function renderBands(){
  const r=DATA.rules;
  const ls=LISTS().filter(m=>r[m.k]);const cell=(k,op)=>ls.map(m=>`<td>${op} ${r[m.k][k]}%</td>`).join('');
  $('#pd2Bands').innerHTML=`<table class="pd2-bandtable"><tr><th></th>${ls.map(m=>`<th>${esc(m.short)}</th>`).join('')}</tr>
  <tr><td><span class="pd2-pill ok"><i class="bx bx-check-circle"></i>No approval</span></td>${cell(0,'≤')}</tr>
  <tr><td><span class="pd2-pill gm"><i class="bx bx-time-five"></i>GM approval</span></td>${cell(1,'≤')}</tr>
  <tr><td><span class="pd2-pill ch"><i class="bx bx-group"></i>Channel price</span></td>${cell(2,'≤')}</tr>
  <tr><td><span class="pd2-pill bad"><i class="bx bx-shield-alt-2"></i>Director approval</span></td>${cell(2,'&gt;')}</tr></table>`+
  `<div class="pd2-note"><i class="bx bx-info-circle"></i><div>Discounts are off DLP (MRP for Dr. Bio, list rate for Fillezy). Channel price is for channel partners only; for a direct customer it needs Director approval. Set customer type above.</div></div>`;
}

/* ================= CALCULATORS (engine unchanged) ================= */
const F=()=>DATA.factors;
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
 {id:'kifmap',g:'Keep It Fresh',t:'KIF MAP Bags — custom size',desc:'MAP Bags are flat per-piece items, not priced by weight like VCI/Dr. Bio — there is no micron/density figure in the KIF price list. This estimates a price for a size that is NOT one of the 11 standard sizes already on the KIF price list, from a ₹/sq-inch rate (Admin → Calculator Factors) fitted to those 11 known prices. Always prefer the exact price-list row when the size matches one.',noUnits:true,
  fields:[n('L','Length (inch)',10),n('W','Width (inch)',12)],
  calc(v){const price=v.L*v.W*F().kifMapRate;return{info:[['Estimated price per pc',price,2,true]]}}},
];
const GROUP_META={
  'VCI plastic':{icon:'bx-shield-quarter',color:'#0A9995'},
  'VCI paper & barrier':{icon:'bx-file',color:'#2563EB'},
  'Tuffpaulin':{icon:'bx-cube',color:'#D97706'},
  'Size converters':{icon:'bx-transfer-alt',color:'#7C3AED'},
  'Dr. Bio compostable':{icon:'bx-leaf',color:'#16A34A'},
  'Keep It Fresh':{icon:'bx-cube-alt',color:'#0D9488'},
};
let curCalc=null, calcUnit=LS.get('spd_unit')||'in', calcCollapsed={};
function qcGroups(list){const seen=[],map={};list.forEach(c=>{if(!map[c.g]){map[c.g]=[];seen.push(c.g)}map[c.g].push(c)});return seen.map(g=>({g,items:map[g]}))}
function renderCalcList(filter){
  const q=(filter||'').trim().toLowerCase();
  const matches=CALCS.filter(c=>!q||c.t.toLowerCase().includes(q)||c.g.toLowerCase().includes(q));
  const box=$('#pd2cList');
  if(!matches.length){box.innerHTML='<div class="muted" style="font-size:12px;padding:16px;text-align:center"><i class="bx bx-search-alt" style="font-size:20px;display:block;margin-bottom:6px"></i>No calculators match your search.</div>';return}
  let h='';
  qcGroups(matches).forEach(({g,items})=>{
    const meta=GROUP_META[g]||{icon:'bx-cube',color:'#6c757d'};
    const isCollapsed=!!calcCollapsed[g]&&!q;
    h+=`<button type="button" class="pd2-cat-group${isCollapsed?' collapsed':''}" data-g="${esc(g)}">
        <span class="gi" style="background:${meta.color}"><i class="bx ${meta.icon}"></i></span>
        <span>${esc(g)}</span><span class="badge-count">${items.length}</span>
        <i class="bx bx-chevron-down chev"></i></button>`;
    if(!isCollapsed)items.forEach(c=>{h+=`<button type="button" class="pd2-cat-item${curCalc&&curCalc.id===c.id?' active':''}" data-c="${c.id}">${esc(c.t)}</button>`});
  });
  box.innerHTML=h;
}
function selectCalc(id){
  curCalc=CALCS.find(c=>c.id===id); if(!curCalc)return;
  LS.set('spd_calc',curCalc.id);
  $('#pd2cError').classList.add('hide');
  $('#pd2cEmpty').classList.add('hide');
  $('#pd2cBody').classList.remove('hide');
  $('#pd2cTitle').textContent=curCalc.t;
  $('#pd2cDesc').textContent=curCalc.desc;
  const meta=GROUP_META[curCalc.g]||{icon:'bx-cube'};
  $('#pd2cIcon').innerHTML=`<i class="bx ${meta.icon}"></i>`;
  renderCalcList($('#pd2cSearch').value);
  const unitSeg=$('#pd2cUnitSeg');
  if(curCalc.noUnits){unitSeg.innerHTML=''}
  else{unitSeg.innerHTML=`<button type="button" class="${calcUnit==='in'?'active':''}" data-u="in">Inches</button>
    <button type="button" class="${calcUnit==='mm'?'active':''}" data-u="mm">mm</button>`}
  try{renderCalcFields()}catch(e){showCalcError(e)}
}
function showCalcError(e){const box=$('#pd2cError');box.textContent='Something went wrong computing this calculator: '+e.message;box.classList.remove('hide');console.error(e)}
function renderCalcFields(){
  const c=curCalc; let h='';
  c.fields.forEach(f=>{
    const val=f.dim?(calcUnit==='mm'?f.mmDef:f.inDef):f.def;
    if(f.opts)h+=`<div class="pd2-field"><span>${esc(f.label)} <span class="req">*</span></span>
      <select data-k="${f.k}">${f.opts.map(o=>`<option${o==val?' selected':''}>${esc(o)}</option>`).join('')}</select></div>`;
    else h+=`<div class="pd2-field"><span>${esc(f.label)}${f.dim?` (${calcUnit==='mm'?'mm':'inch'})`:''} <span class="req">*</span></span>
      <input type="number" inputmode="decimal" step="any" min="0" data-k="${f.k}" value="${esc(val)}"></div>`;
  });
  $('#pd2cFields').innerHTML=h;

  const rateSection=$('#pd2cRateSection');
  if(c.rate){
    const list=DATA[c.rate.list||'vci']||[];
    const opts=list.map((x,i)=>({x,i})).filter(o=>c.rate.codes.includes(o.x.code)&&o.x.unit===c.rate.unit);
    const defCode=c.rate.auto?c.rate.auto({gsm:c.fields.find(f=>f.k==='gsm').def}):c.rate.def;
    const pi=(opts.find(o=>o.x.code===defCode)||opts[0]||{}).i;
    rateSection.innerHTML=opts.length?`
      <div class="pd2-section-head"><i class="bx bx-coin-stack"></i>Rate basis</div>
      <div class="pd2-rategrid">
        <div class="pd2-field"><span>Price-list product (SKU) <span class="req">*</span></span>
          <select id="pd2cProd">${opts.map(o=>`<option value="${o.i}"${o.i===pi?' selected':''}>${esc(o.x.code+' — '+o.x.name+(o.x.spec?' · '+o.x.spec:''))}</option>`).join('')}</select></div>
        <div class="pd2-field"><span>Price level <span class="req">*</span></span>
          <select id="pd2cTier">${tierNames(c.rate.list||'vci').map((t,k)=>`<option value="${k}">${esc(t)}</option>`).join('')}<option value="m">Custom rate…</option></select></div>
        <div class="pd2-field" id="pd2cCustWrap"><span>Customer type</span>
          <select id="pd2cCustType">${$('#pd2QType')?$('#pd2QType').innerHTML:'<option>Direct customer</option><option>Channel partner</option>'}</select></div>
      </div>
      <div class="pd2-field hide" id="pd2cRateWrap" style="margin-top:10px;max-width:220px"><span>Custom rate (₹/${c.rate.unit.toLowerCase()})</span>
        <input type="number" step="any" id="pd2cRate"></div>` : '<div class="muted" style="font-size:12px;margin-top:8px">No price-list rate configured for this item yet — weight only.</div>';
  } else { rateSection.innerHTML='' }
  try{computeCalc()}catch(e){showCalcError(e)}
}
function qcReadVals(c){
  const v={_mm:calcUnit==='mm'&&!c.noUnits,_raw:{}};
  c.fields.forEach(f=>{const el=$(`#pd2cFields [data-k="${f.k}"]`);let x=el?el.value:f.def;if(!f.opts)x=parseFloat(x)||0;v._raw[f.k]=x;v[f.k]=(f.dim&&v._mm)?x/25.4:x});
  v._dims=(...ks)=>ks.map(k=>fmtDim(v._raw[k])).join(' × ')+(v._mm?' mm':'″');
  return v;
}
function fmtDim(x){return Number.isInteger(x)?String(x):String(r2(x))}
function qcGetRate(c){
  if(!c.rate)return null;
  const list=DATA[c.rate.list||'vci']||[];
  const pi=+(($('#pd2cProd')||{}).value);const x=list[pi];if(!x)return{rate:0,prod:null};
  const tier=($('#pd2cTier')||{}).value||'1';
  const rate=tier==='m'?(parseFloat(($('#pd2cRate')||{}).value)||0):(tier==='0'?x.dlp:x.t[+tier-1]);
  return{rate,prod:x};
}
function qcHeroCard(label,value,kind){
  const lower=label.toLowerCase();
  const isWeight=lower.includes('weight')||lower.includes('g/')||lower.includes('kg/');
  const isPrice=lower.includes('price')||lower.includes('₹')||lower.includes('rate');
  const isCount=lower.includes('pieces')||lower.includes('sheets')||lower.includes('count');
  const variant=kind||(isPrice?'price':isCount?'count':'weight');
  const icon=isPrice?'bx-coin-stack':isCount?'bx-layer':(lower.includes('area')||lower.includes('sqm')||lower.includes('square'))?'bx-crop':'bx-package';
  return `<div class="pd2-rescard v-${variant}"><div class="ic"><i class="bx ${icon}"></i></div>
    <div><div class="lbl">${esc(label)}</div><div class="val">${value}</div></div></div>`;
}
function computeCalc(){
  const c=curCalc; const v=qcReadVals(c); const r=c.calc(v); const R=qcGetRate(c);
  let cards=[], b=null, bProd=null;
  if(r.info){r.info.forEach(([k,val,dp])=>cards.push(qcHeroCard(k,fmt(val,dp))));}
  else{
    if(r.kg!=null)cards.push(qcHeroCard('Weight',r.kg<1?fmt(r.kg*1000,2)+' g/'+r.unit:fmt(r.kg,3)+' kg/'+r.unit,'weight'));
    if(r.sqm!=null)cards.push(qcHeroCard('Area incl. wastage',fmt(r.sqm,3)+' sqm','weight'));
    if(r.kg&&r.unit==='pc')cards.push(qcHeroCard('Pieces per kg',fmt(1/r.kg,1),'count'));
    if(R&&R.prod){
      const base=c.rate.unit==='SQM'?r.sqm*R.rate:r.kg*R.rate; const price=base+(r.add||0);
      cards.push(qcHeroCard(`Price per ${r.unit}`,'₹ '+fmt(price,2),'price'));
      if(r.fg)cards.push(qcHeroCard('FG size price (+'+Math.round((r.fg-1)*100)+'%)','₹ '+fmt(price*r.fg,2),'price'));
      if(r.add)cards.push(qcHeroCard('incl. tape / packing','₹ '+fmt(r.add,2),'price'));
      if(r.extra)r.extra(R.rate).forEach(([k,val,dp])=>cards.push(qcHeroCard(k,fmt(val,dp))));
      const custType=($('#pd2cCustType')||{}).value||$('#pd2QType').value;
      b=band(R.rate,R.prod.dlp,R.prod.t,custType,R.prod.force); bProd=R.prod;
    }
  }
  $('#pd2cResults').innerHTML=cards.join('');
  const bandBox=$('#pd2cBand'); const noteBox=$('#pd2cNote');
  if(b&&bProd){
    bandBox.innerHTML=`${pill(b,true)}<span class="rate-txt">Rate ₹${fmt(R.rate)}/${c.rate.unit.toLowerCase()} vs ${esc(tierNames(c.rate.list||'vci')[0])} ₹${fmt(bProd.dlp)} — ${b.disc!=null?fmt(b.disc,1)+'%':'–'} off</span>`;
  } else { bandBox.innerHTML='' }
  if(curCalc.id==='3d6'||curCalc.id==='3d5'||curCalc.id==='2d'||curCalc.id==='liner'){
    noteBox.innerHTML=`<div class="pd2-note"><i class="bx bx-info-circle"></i><div>VCI plastic bags: quote the customer <b>per piece only</b> — never share the ₹/kg rate.</div></div>`;
  } else { noteBox.innerHTML='' }
}
$('#pd2cSearch').addEventListener('input',e=>renderCalcList(e.target.value));
$('#pd2cList').addEventListener('click',e=>{
  const g=e.target.closest('.pd2-cat-group');
  if(g){calcCollapsed[g.dataset.g]=!calcCollapsed[g.dataset.g];renderCalcList($('#pd2cSearch').value);return}
  const b=e.target.closest('button[data-c]'); if(b)selectCalc(b.dataset.c);
});
$('#pd2cFields').addEventListener('input',()=>{try{computeCalc()}catch(e){showCalcError(e)}});
$('#pd2cFields').addEventListener('change',()=>{try{computeCalc()}catch(e){showCalcError(e)}});
$('#pd2cRateSection').addEventListener('change',e=>{
  if(e.target.id==='pd2cTier')$('#pd2cRateWrap').classList.toggle('hide',e.target.value!=='m');
  try{computeCalc()}catch(err){showCalcError(err)}
});
$('#pd2cUnitSeg').addEventListener('click',e=>{
  const b=e.target.closest('button[data-u]'); if(!b)return;
  calcUnit=b.dataset.u; LS.set('spd_unit',calcUnit);
  [...e.currentTarget.querySelectorAll('button')].forEach(x=>x.classList.toggle('active',x===b));
  try{renderCalcFields()}catch(err){showCalcError(err)}
});
$('#pd2cReset').addEventListener('click',()=>{if(curCalc){const id=curCalc.id;curCalc=null;selectCalc(id)}});
$('#pd2cCalc').addEventListener('click',async()=>{
  const btn=$('#pd2cCalc'); const orig=btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="bx bx-loader-alt bx-spin"></i>Calculating…';
  await new Promise(r=>setTimeout(r,160));
  try{computeCalc()}catch(e){showCalcError(e)}
  btn.disabled=false; btn.innerHTML=orig;
});
function initCalcTab(){
  renderCalcList('');
  const want=LS.get('spd_calc'); if(want&&CALCS.some(c=>c.id===want))selectCalc(want);
}

/* ================= ADMIN ================= */
let aList='vci';
function saveToServer(onDone){
  return (async()=>{
    try{
      const csrf=document.querySelector('meta[name="csrf-token"]').content;
      const res=await fetch(SAVE_URL,{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({data:DATA})});
      const body=await res.json().catch(()=>({}));
      if(!res.ok){throw new Error(body.message||('Save failed (HTTP '+res.status+').'))}
      LS.del('spd_override');
      onDone(true,body.message||'Saved.');
    }catch(e){ onDone(false,e.message); }
  })();
}
function markDirty(){LS.set('spd_override',DATA)}
function flashMsg(el,ok,text){el.textContent=text;el.className='pd2-savemsg '+(ok?'ok':'bad');if(ok)setTimeout(()=>{el.textContent=''},3500)}
function renderAdmin(){
  $('#pd2aRules').innerHTML=LISTS().filter(m=>DATA.rules[m.k]).map(m=>m.k).map(l=>DATA.rules[l].map((p,k)=>`<label>${esc(LM(l).short)} — ${['No approval','GM','Channel'][k]} (%)<input type="number" step="0.5" data-rl="${l}" data-rk="${k}" value="${p}"></label>`).join('')).join('');
  const FL={vci:'VCI plastic & paper wastage (×)',vci5:'5-side VCI cover wastage (×)',drbio:'Dr. Bio film factor (×)',shrink:'Dr. Bio shrink factor (×)',tuffFG:'Tuffpaulin FG-size uplift (×)',kifMapRate:'KIF MAP Bags — ₹/sq-inch estimate'};
  $('#pd2aFactors').innerHTML=Object.keys(FL).map(k=>`<label>${FL[k]}<input type="number" step="0.01" data-fk="${k}" value="${DATA.factors[k]}"></label>`).join('');
  renderFamTabs();
  fillACats();
  renderATable();
}
function fillACats(){const cats=[...new Set((DATA[aList]||[]).map(x=>x.cat))];$('#pd2aCat').innerHTML='<option value="">All categories</option>'+cats.map(c=>`<option>${esc(c)}</option>`).join('')}
function aFilteredRows(){
  const q=$('#pd2aSearch').value.trim().toLowerCase(), cat=$('#pd2aCat').value, L=DATA[aList];
  return L.map((x,i)=>({x,i})).filter(({x})=>(!cat||x.cat===cat)&&(!q||itemText(x).includes(q)));
}
function renderATable(){
  const isV=LM(aList).rich;
  let h=`<thead><tr><th>Category</th><th>Code</th>${isV?'<th>Product</th><th>Spec</th><th>Size</th>':'<th>Class</th>'}<th>Unit</th><th class="n">DLP</th><th class="n">No approval</th><th class="n">GM</th><th class="n">Channel</th><th></th></tr></thead><tbody>`;
  aFilteredRows().forEach(({x,i})=>{
    const inp=(f,v,num)=>`<input ${num?'type="number" step="any"':'type="text"'} data-ai="${i}" data-af="${f}" value="${esc(v)}">`;
    h+=`<tr><td>${inp('cat',x.cat)}</td><td>${inp('code',x.code)}</td>${isV?`<td>${inp('name',x.name)}</td><td>${inp('spec',x.spec)}</td><td>${inp('size',x.size)}</td>`:`<td>${inp('cls',x.cls)}</td>`}<td>${inp('unit',x.unit)}</td><td class="n">${inp('dlp',x.dlp,1)}</td><td class="n">${inp('t0',x.t[0],1)}</td><td class="n">${inp('t1',x.t[1],1)}</td><td class="n">${inp('t2',x.t[2],1)}</td><td><button class="pd2-deletebtn" data-adel="${i}" type="button"><i class="bx bx-trash"></i></button></td></tr>`;
  });
  $('#pd2aTable').innerHTML=h+'</tbody>';
}
function tiersFrom(l,dlp){const r=DATA.rules[l],d=LM(l).round;return r.map(p=>d===0?Math.round(dlp*(1-p/100)):r2(dlp*(1-p/100)))}
if(CAN_MANAGE){
$('#pd2aFamTabs').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;aList=b.dataset.l;renderFamTabs();fillACats();renderATable()});
$('#pd2aSearch').addEventListener('input',renderATable);
$('#pd2aCat').addEventListener('change',renderATable);
$('#pd2aTable').addEventListener('change',e=>{const t=e.target;if(t.dataset.ai==null)return;const x=DATA[aList][+t.dataset.ai],f=t.dataset.af;
  if(f==='dlp'){x.dlp=parseFloat(t.value)||0;if(x.dlp>0){x.t=tiersFrom(aList,x.dlp);}renderATable();}
  else if(/^t\d$/.test(f))x.t[+f[1]]=parseFloat(t.value)||0; else x[f]=t.value;
  markDirty();refreshAll(false)});
$('#pd2aTable').addEventListener('click',e=>{const b=e.target.closest('[data-adel]');if(!b)return;const x=DATA[aList][+b.dataset.adel];if(!confirm('Delete '+x.code+'?'))return;DATA[aList].splice(+b.dataset.adel,1);markDirty();renderATable();refreshAll(false)});
$('#pd2aRules').addEventListener('change',e=>{const t=e.target;DATA.rules[t.dataset.rl][+t.dataset.rk]=parseFloat(t.value)||0;markDirty();renderBands();refreshAll(false)});
$('#pd2aFactors').addEventListener('change',e=>{const t=e.target;DATA.factors[t.dataset.fk]=parseFloat(t.value)||1;markDirty();if(curCalc)computeCalc()});
$('#pd2aRecalc').addEventListener('click',()=>{if(!confirm('Overwrite every tier price with DLP × (1 − rule %)? Items with no DLP are left unchanged.'))return;LISTS().forEach(m=>(DATA[m.k]||[]).forEach(x=>{if(x.dlp>0)x.t=tiersFrom(m.k,x.dlp)}));markDirty();renderATable();refreshAll(false)});

$('#pd2aSaveRules').addEventListener('click',()=>{const btn=$('#pd2aSaveRules');btn.disabled=true;saveToServer((ok,msg)=>{btn.disabled=false;flashMsg($('#pd2aRulesMsg'),ok,ok?'Saved.':('Could not save: '+msg))})});
$('#pd2aSaveFactors').addEventListener('click',()=>{const btn=$('#pd2aSaveFactors');btn.disabled=true;saveToServer((ok,msg)=>{btn.disabled=false;flashMsg($('#pd2aFactorsMsg'),ok,ok?'Saved.':('Could not save: '+msg))})});
$('#pd2aSaveTable').addEventListener('click',()=>{const btn=$('#pd2aSaveTable');btn.disabled=true;saveToServer((ok,msg)=>{btn.disabled=false;flashMsg($('#pd2aTableMsg'),ok,ok?'Saved.':('Could not save: '+msg))})});

/* ---- Add product modal ---- */
const ADD_FIELDS=[
  {k:'cat',label:'Category',type:'text'},{k:'code',label:'Code',type:'text'},
  {k:'name',label:'Product name',type:'text'},{k:'spec',label:'Specification',type:'text'},
  {k:'size',label:'Size',type:'text'},{k:'unit',label:'Unit',type:'text'},
  {k:'dlp',label:'DLP (₹)',type:'number'},{k:'t0',label:'No approval (₹)',type:'number'},
  {k:'t1',label:'GM (₹)',type:'number'},{k:'t2',label:'Channel (₹)',type:'number'},
];
function openAddModal(){
  const isV=LM(aList).rich;
  const fields=isV?ADD_FIELDS:ADD_FIELDS.filter(f=>!['name','spec','size'].includes(f.k)).concat([{k:'cls',label:'Class',type:'text'}]);
  $('#pd2AddFields').innerHTML=fields.map(f=>`<div class="pd2-field"><span>${esc(f.label)}${['cat','code'].includes(f.k)?' <span class="req">*</span>':''}</span><input data-k="${f.k}" type="${f.type}" ${f.type==='number'?'step="any"':''}></div>`).join('');
  $('#pd2AddErr').classList.add('hide');
  $('#pd2AddModal').classList.remove('hide');
}
$('#pd2aAdd').addEventListener('click',openAddModal);
$('#pd2AddClose').addEventListener('click',()=>$('#pd2AddModal').classList.add('hide'));
$('#pd2AddCancel').addEventListener('click',()=>$('#pd2AddModal').classList.add('hide'));
$('#pd2AddConfirm').addEventListener('click',()=>{
  const vals={}; $$('#pd2AddFields [data-k]').forEach(el=>vals[el.dataset.k]=el.value);
  if(!vals.cat||!vals.code){$('#pd2AddErr').textContent='Category and Code are required.';$('#pd2AddErr').classList.remove('hide');return}
  if(DATA[aList].some(x=>x.code===vals.code)){$('#pd2AddErr').textContent='A product with code "'+vals.code+'" already exists in '+LM(aList).label+'.';$('#pd2AddErr').classList.remove('hide');return}
  const isV=LM(aList).rich;
  const rec=isV?{code:vals.code,name:vals.name||'',spec:vals.spec||'',size:vals.size||'',colour:'',dlp:parseFloat(vals.dlp)||0,unit:vals.unit||'',t:[parseFloat(vals.t0)||0,parseFloat(vals.t1)||0,parseFloat(vals.t2)||0],cat:vals.cat}
            :{code:vals.code,cat:vals.cat,dlp:parseFloat(vals.dlp)||0,t:[parseFloat(vals.t0)||0,parseFloat(vals.t1)||0,parseFloat(vals.t2)||0],cls:vals.cls||'A',unit:vals.unit||''};
  const L=DATA[aList]; const pos=L.findIndex(x=>x.cat===vals.cat); L.splice(pos<0?0:pos,0,rec);
  markDirty(); $('#pd2AddModal').classList.add('hide'); fillACats(); renderATable(); refreshAll(false);
});

/* ---- CSV import ---- */
let importRows=null;
function openImportModal(){
  $('#pd2ImportSummary').classList.add('hide');
  $('#pd2ImportConfirm').disabled=true;
  $('#pd2ImportFile').value='';
  importRows=null;
  $('#pd2ImportModal').classList.remove('hide');
}
$('#pd2aImportBtn').addEventListener('click',openImportModal);
$('#pd2ImportClose').addEventListener('click',()=>$('#pd2ImportModal').classList.add('hide'));
$('#pd2ImportCancel').addEventListener('click',()=>$('#pd2ImportModal').classList.add('hide'));
$('#pd2ImportBrowse').addEventListener('click',()=>$('#pd2ImportFile').click());
$('#pd2ImportDrop').addEventListener('click',()=>$('#pd2ImportFile').click());
$('#pd2ImportDrop').addEventListener('dragover',e=>{e.preventDefault();$('#pd2ImportDrop').classList.add('drag')});
$('#pd2ImportDrop').addEventListener('dragleave',()=>$('#pd2ImportDrop').classList.remove('drag'));
$('#pd2ImportDrop').addEventListener('drop',e=>{e.preventDefault();$('#pd2ImportDrop').classList.remove('drag');if(e.dataTransfer.files[0])handleImportFile(e.dataTransfer.files[0])});
$('#pd2ImportFile').addEventListener('change',e=>{if(e.target.files[0])handleImportFile(e.target.files[0])});
function parseCsv(text){
  const rows=[]; let row=[],cell='',inQ=false;
  for(let i=0;i<text.length;i++){
    const c=text[i];
    if(inQ){ if(c==='"'){ if(text[i+1]==='"'){cell+='"';i++} else inQ=false } else cell+=c; }
    else{
      if(c==='"')inQ=true;
      else if(c===','){row.push(cell);cell=''}
      else if(c==='\n'||c==='\r'){ if(c==='\r'&&text[i+1]==='\n')i++; row.push(cell);cell=''; if(row.some(x=>x!==''))rows.push(row); row=[]; }
      else cell+=c;
    }
  }
  if(cell!==''||row.length){row.push(cell);rows.push(row)}
  return rows;
}
function handleImportFile(file){
  const reader=new FileReader();
  reader.onload=()=>{
    const rows=parseCsv(String(reader.result));
    if(!rows.length){alert('That file has no rows.');return}
    const header=rows[0].map(h=>h.trim());
    const isV=LM(aList).rich;
    const idx={code:header.indexOf('Code'),name:header.indexOf('Product'),unit:header.indexOf('Unit')};
    const tn=tierNames(aList);
    idx.dlp=header.indexOf(tn[0]); idx.t0=header.indexOf(tn[1]); idx.t1=header.indexOf(tn[2]); idx.t2=header.indexOf(tn[3]);
    if(idx.code<0||idx.dlp<0){
      $('#pd2ImportSummary').classList.remove('hide');
      $('#pd2ImportFileName').textContent=file.name;
      $('#pd2ImportCounts').textContent='';
      $('#pd2ImportRows').innerHTML=`<div class="pd2-importrow err"><i class="bx bx-error-circle"></i>This file's header doesn't match the ${esc(LM(aList).label)} export (needs at least Code and ${esc(tn[0])} columns). Export this family first to see the expected layout.</div>`;
      $('#pd2ImportConfirm').disabled=true;
      return;
    }
    const parsed=rows.slice(1).filter(r=>r.some(c=>c!=='')).map(r=>({
      code:(r[idx.code]||'').trim(),
      name:idx.name>=0?(r[idx.name]||'').trim():'',
      unit:idx.unit>=0?(r[idx.unit]||'').trim():'',
      dlp:parseFloat(r[idx.dlp]),
      t0:parseFloat(r[idx.t0]),t1:parseFloat(r[idx.t1]),t2:parseFloat(r[idx.t2]),
    }));
    const seen={};
    const rowsOut=parsed.map(p=>{
      const errs=[];
      if(!p.code)errs.push('missing code');
      if(isNaN(p.dlp))errs.push('DLP not numeric');
      if(p.code&&seen[p.code])errs.push('duplicate code in file');
      if(p.code)seen[p.code]=true;
      const exists=DATA[aList].some(x=>x.code===p.code);
      return {...p,errs,action:errs.length?'skip':(exists?'update':'add')};
    });
    importRows=rowsOut.filter(r=>!r.errs.length);
    const invalid=rowsOut.length-importRows.length;
    const toAdd=importRows.filter(r=>r.action==='add').length, toUpdate=importRows.filter(r=>r.action==='update').length;
    $('#pd2ImportSummary').classList.remove('hide');
    $('#pd2ImportFileName').textContent=file.name+' — '+(rowsOut.length)+' rows';
    $('#pd2ImportCounts').textContent=`${toAdd} new · ${toUpdate} to update · ${invalid} invalid (skipped)`;
    $('#pd2ImportRows').innerHTML=rowsOut.slice(0,60).map(r=>{
      if(r.errs.length)return `<div class="pd2-importrow err"><i class="bx bx-error-circle"></i>${esc(r.code||'(blank)')} — ${esc(r.errs.join(', '))}</div>`;
      if(r.action==='update')return `<div class="pd2-importrow warn"><i class="bx bx-triangle-alert"></i>${esc(r.code)} — will update existing record</div>`;
      return `<div class="pd2-importrow ok"><i class="bx bx-check-circle"></i>${esc(r.code)} — new product</div>`;
    }).join('')+(rowsOut.length>60?`<div class="muted" style="font-size:11.5px;padding:6px">…and ${rowsOut.length-60} more rows</div>`:'');
    $('#pd2ImportConfirm').disabled=!importRows.length;
  };
  reader.readAsText(file);
}
$('#pd2ImportConfirm').addEventListener('click',()=>{
  if(!importRows||!importRows.length)return;
  const isV=LM(aList).rich;
  importRows.forEach(r=>{
    const existing=DATA[aList].find(x=>x.code===r.code);
    if(existing){
      existing.dlp=r.dlp; existing.t=[r.t0||0,r.t1||0,r.t2||0];
      if(r.name&&isV)existing.name=r.name; if(r.unit)existing.unit=r.unit;
    } else {
      const rec=isV?{code:r.code,name:r.name||'',spec:'',size:'',colour:'',dlp:r.dlp||0,unit:r.unit||'',t:[r.t0||0,r.t1||0,r.t2||0],cat:'Imported'}
                   :{code:r.code,cat:'Imported',dlp:r.dlp||0,t:[r.t0||0,r.t1||0,r.t2||0],cls:'A',unit:r.unit||''};
      DATA[aList].push(rec);
    }
  });
  markDirty();
  $('#pd2ImportModal').classList.add('hide');
  fillACats(); renderATable(); refreshAll(false);
  saveToServer((ok,msg)=>{flashMsg($('#pd2aTableMsg'),ok,ok?('Imported and saved '+importRows.length+' rows.'):('Imported locally, but save failed: '+msg))});
});
$('#pd2aExportBtn').addEventListener('click',()=>{
  const rows=DATA[aList];
  const isV=LM(aList).rich, tn=tierNames(aList);
  const head=['Code',...(isV?['Product']:[]),'Unit',tn[0],tn[1],tn[2],tn[3],...(isV?[]:['Class'])];
  const csvCell=v=>`"${String(v==null?'':v).replace(/"/g,'""')}"`;
  const lines=[head.map(csvCell).join(',')];
  rows.forEach(x=>{
    const cells=[x.code,...(isV?[x.name]:[]),x.unit||'',x.dlp||'',x.t[0]||'',x.t[1]||'',x.t[2]||'',...(isV?[]:[x.cls||''])];
    lines.push(cells.map(csvCell).join(','));
  });
  const blob=new Blob([lines.join('\r\n')],{type:'text/csv;charset=utf-8;'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`${LM(aList).label.replace(/\s+/g,'-')}-price-list.csv`;a.click();URL.revokeObjectURL(a.href);
});
}

/* ================= boot ================= */
function updHeroStats(){
  const ls=LISTS().filter(m=>DATA[m.k]);
  $('#pdStatFamilies').textContent=ls.length;
  $('#pdStatProducts').textContent=ls.reduce((s,m)=>s+(DATA[m.k]||[]).length,0).toLocaleString('en-IN');
  $('#pdStatBands').textContent=4;
}
function refreshAll(full){
  renderFamTabs();
  if(full){fillCats();selIdx=null}
  renderPrices();renderCheck();renderBands();updHeroStats();
}
renderFamTabs();fillCats();refreshAll(true);
const lt=LS.get('spd_tab');
if(lt==='calc'||lt==='admin')showPane(lt);
})();
</script>
</div>
@endsection
