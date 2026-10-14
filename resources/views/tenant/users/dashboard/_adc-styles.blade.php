{{-- Shared design system for the revamped role dashboards (Admin / HR / Manager /
     Sales / CCare). Include once inside @section('content'). Fully responsive —
     on phones the layout stacks into a native-app-style single column. --}}
<style>
  .adc { --p:#0d9488; --p-d:#0f766e; --info:#2563eb; --success:#16a34a; --warning:#d97706; --danger:#dc2626;
         --ink:#1e293b; --muted:#64748b; --faint:#94a3b8; --line:#eef1f5; --line-strong:#cbd5e1; --bg:#f6f8fb; }
  .adc { color: var(--ink); }
  .adc-tone-primary{--c:var(--p);} .adc-tone-info{--c:var(--info);} .adc-tone-success{--c:var(--success);}
  .adc-tone-warning{--c:var(--warning);} .adc-tone-danger{--c:var(--danger);}

  .adc-hero-title{font-size:1.35rem;font-weight:700;color:var(--ink);}
  .adc-hero-sub{color:var(--muted);font-size:.9rem;}
  .adc-wave{display:inline-block;}
  .adc-chip{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid var(--line);
            border-radius:9px;padding:7px 13px;font-size:.82rem;font-weight:600;color:var(--muted);text-decoration:none;box-shadow:0 1px 2px rgba(15,23,42,.04);}
  .adc-chip-accent{background:var(--p);border-color:var(--p);color:#fff;}
  .adc-chip-accent:hover{color:#fff;background:var(--p-d);}

  /* KPI cards */
  .adc-kpi{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px;box-shadow:0 1px 3px rgba(15,23,42,.05);
           position:relative;overflow:hidden;transition:transform .15s,box-shadow .15s;color:var(--ink);text-decoration:none;}
  .adc-kpi:hover{transform:translateY(-2px);box-shadow:0 8px 24px -10px rgba(15,23,42,.18);color:var(--ink);border-color:var(--c);}
  .adc-kpi::before{content:"";position:absolute;inset:0 auto 0 0;width:4px;background:var(--c);}
  .adc-kpi-go{color:var(--faint);font-size:1.1rem;opacity:0;transition:opacity .15s,transform .15s;margin-left:4px;}
  .adc-kpi:hover .adc-kpi-go{opacity:1;transform:translateX(2px);color:var(--c);}
  .adc-kpi-top{display:flex;align-items:center;gap:6px;margin-bottom:10px;}
  .adc-kpi-top .adc-kpi-icon{margin-right:auto;}
  .adc-kpi-icon{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:1.2rem;
                color:var(--c);background:color-mix(in srgb,var(--c) 12%,#fff);}
  .adc-kpi-trend{display:inline-flex;align-items:center;gap:1px;font-size:.72rem;font-weight:700;padding:2px 7px;border-radius:20px;}
  .adc-kpi-trend.up{color:var(--success);background:rgba(22,163,74,.1);}
  .adc-kpi-trend.down{color:var(--danger);background:rgba(220,38,38,.1);}
  .adc-kpi-value{font-size:1.5rem;font-weight:800;line-height:1.1;letter-spacing:-.02em;}
  .adc-kpi-label{font-size:.82rem;font-weight:600;color:var(--ink);margin-top:2px;}
  .adc-kpi-sub{font-size:.72rem;color:var(--faint);}

  /* Cards */
  .adc-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px 18px 16px;box-shadow:0 1px 3px rgba(15,23,42,.05);}
  .adc-card-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;}
  .adc-card-title{font-size:.98rem;font-weight:700;margin:0;color:var(--ink);display:flex;align-items:center;gap:6px;}
  .adc-card-sub{font-size:.76rem;color:var(--faint);}
  .adc-link{font-size:.78rem;font-weight:600;color:var(--p);text-decoration:none;white-space:nowrap;}
  .adc-link:hover{color:var(--p-d);text-decoration:underline;}
  .adc-mini-label{font-size:.72rem;color:var(--faint);display:block;}
  .adc-mini-value{font-size:1rem;font-weight:700;}
  .adc-kpi-spark{height:34px;margin-top:6px;margin-left:-4px;}

  /* Segmented toggle */
  .adc-seg{display:inline-flex;background:var(--bg);border:1px solid var(--line);border-radius:9px;padding:3px;gap:2px;margin-bottom:6px;}
  .adc-seg button{border:0;background:transparent;font-size:.74rem;font-weight:600;color:var(--muted);padding:4px 10px;border-radius:7px;cursor:pointer;transition:all .12s;}
  .adc-seg button.active{background:#fff;color:var(--p);box-shadow:0 1px 3px rgba(15,23,42,.12);}
  .adc-tgt-figs{display:flex;justify-content:space-around;text-align:center;margin-top:6px;}
  .adc-tgt-figs b{font-size:1rem;font-weight:700;display:block;}
  .adc-tgt-list{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
  .adc-tgt-row{display:flex;flex-direction:column;gap:1px;padding:9px 8px;background:var(--bg);border-radius:11px;text-align:center;}
  .adc-tgt-row .adc-mini-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.03em;}
  .adc-tgt-row b{font-size:1.05rem;font-weight:800;line-height:1.15;}
  .adc-staff{margin-top:10px;border-top:1px solid var(--line);padding-top:8px;}
  .adc-staff-head{font-size:.82rem;font-weight:700;color:var(--ink);margin-bottom:2px;}
  .adc-staff-table td{padding:3px 6px !important;border-bottom:1px solid var(--line);vertical-align:middle;font-size:.8rem;line-height:1.15;}
  .adc-staff-table tbody tr:last-child td{border-bottom:0;}
  .adc-staff-table .adc-bar{height:6px;}
  .adc-staff-table td:first-child{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:190px;}
  .adc-staff-av{width:24px;height:24px;border-radius:50%;margin-right:7px;vertical-align:middle;}
  .adc-staff-scroll{max-height:230px;overflow-y:auto;overflow-x:hidden;}
  .adc-staff-scroll::-webkit-scrollbar{width:6px;}
  .adc-staff-scroll::-webkit-scrollbar-thumb{background:var(--line-strong);border-radius:3px;}
  .adc-staff-scroll::-webkit-scrollbar-track{background:transparent;}

  /* CSS funnel (optional) */
  .adc-funnel-card{display:flex;flex-direction:column;}
  .adc-funnel-wrap{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:stretch;min-height:250px;}
  .adc-funnel{display:flex;flex-direction:column;gap:3px;padding:2px 0;}
  .adc-funnel-seg{flex:1;min-height:34px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.78rem;
    clip-path:polygon(calc(50% - var(--tw)/2) 0,calc(50% + var(--tw)/2) 0,calc(50% + var(--bw)/2) 100%,calc(50% - var(--bw)/2) 100%);}
  .adc-funnel-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;justify-content:center;gap:9px;}
  .adc-funnel-list li{display:flex;gap:9px;align-items:flex-start;}
  .adc-fl-dot{width:11px;height:11px;border-radius:3px;margin-top:3px;flex:none;}
  .adc-fl-stage{font-size:.8rem;font-weight:600;color:var(--ink);line-height:1.2;}
  .adc-fl-val{font-size:.82rem;font-weight:700;}
  .adc-fl-val small{color:var(--faint);font-weight:400;font-size:.72rem;}

  /* Module tiles */
  .adc-tile{display:flex;align-items:center;gap:11px;padding:13px;border-radius:12px;border:1px solid var(--line);
            background:#fff;text-decoration:none;color:var(--ink);transition:all .15s;height:100%;}
  .adc-tile:hover{border-color:var(--c);background:color-mix(in srgb,var(--c) 5%,#fff);transform:translateY(-2px);color:var(--ink);}
  .adc-tile-icon{width:40px;height:40px;flex:none;border-radius:10px;display:grid;place-items:center;font-size:1.25rem;
                 color:var(--c);background:color-mix(in srgb,var(--c) 12%,#fff);}
  .adc-tile-value{font-size:1.05rem;font-weight:800;line-height:1.1;}
  .adc-tile-name{font-size:.8rem;font-weight:600;}
  .adc-tile-sub{font-size:.7rem;color:var(--faint);}

  /* Assets grid */
  .adc-card:has(.adc-asset-grid){display:flex;flex-direction:column;}
  .adc-asset-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;flex:1;}
  .adc-asset-cell{background:var(--bg);border-radius:12px;padding:18px;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:120px;}
  .adc-asset-num{font-size:1.9rem;font-weight:800;line-height:1;}
  .adc-asset-lbl{font-size:.8rem;color:var(--muted);margin-top:8px;font-weight:500;}

  /* Table */
  .adc-table{font-size:.85rem;}
  .adc-table thead th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--faint);font-weight:600;border-bottom:1px solid var(--line);padding:.5rem .5rem;}
  .adc-table tbody td{padding:.62rem .5rem;border-bottom:1px solid var(--line);vertical-align:middle;}
  .adc-table tbody tr:last-child td{border-bottom:0;}
  .adc-bar{flex:1;height:7px;border-radius:5px;background:var(--line);overflow:hidden;}
  .adc-bar span{display:block;height:100%;border-radius:5px;background:var(--c);}

  /* Feed */
  .adc-feed{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:14px;}
  .adc-feed li{display:flex;gap:11px;align-items:flex-start;}
  .adc-feed-dot{width:34px;height:34px;flex:none;border-radius:9px;display:grid;place-items:center;font-size:1.05rem;color:var(--c);background:color-mix(in srgb,var(--c) 12%,#fff);}
  /* Wrapper around the title/meta pair — every dashboard's feed markup uses
     this, but it was never defined, so long entries could not shrink and
     overflowed their row. */
  .adc-feed-body{min-width:0;flex:1;}
  .adc-feed-title{font-size:.85rem;font-weight:600;line-height:1.25;}
  .adc-feed-meta{font-size:.74rem;color:var(--faint);margin-top:1px;}

  /* Badges */
  .adc-badge{display:inline-block;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:20px;color:var(--c);background:color-mix(in srgb,var(--c) 13%,#fff);}

  /* AI insights */
  .adc-ai{background:linear-gradient(180deg,#f0fdfa 0%,#ffffff 60%);border-color:#cffafe;}
  .adc-insights{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:13px;}
  .adc-insights li{display:flex;gap:11px;align-items:flex-start;}
  /* A whole row as one link: keeps the row's own layout (gap / alignment). */
  .adc-rowlink{display:flex;gap:inherit;align-items:inherit;flex:1;min-width:0;color:inherit;text-decoration:none;border-radius:9px;}
  .adc-rowlink:hover{color:inherit;}
  .adc-rowlink:hover .adc-ins-title,.adc-rowlink:hover .adc-ev-label,.adc-rowlink:hover .adc-feed-title,.adc-rowlink:hover .adc-cal-name,.adc-rowlink:hover .adc-appr-name{color:var(--p);}
  .adc-rowlink:focus-visible{outline:2px solid var(--p);outline-offset:2px;}
  .adc-ins-icon{width:32px;height:32px;flex:none;border-radius:9px;display:grid;place-items:center;font-size:1rem;color:var(--c);background:color-mix(in srgb,var(--c) 14%,#fff);}
  .adc-ins-title{font-size:.84rem;font-weight:700;line-height:1.2;}
  .adc-ins-sub{font-size:.74rem;color:var(--muted);line-height:1.25;}

  /* Events */
  .adc-events{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:4px;}
  .adc-events li{display:flex;align-items:center;gap:11px;padding:8px 6px;border-radius:9px;transition:background .12s;}
  .adc-events li:hover{background:var(--bg);}
  .adc-ev-icon{width:32px;height:32px;flex:none;border-radius:9px;display:grid;place-items:center;font-size:1rem;color:var(--c);background:color-mix(in srgb,var(--c) 13%,#fff);}
  .adc-ev-label{flex:1;font-size:.84rem;font-weight:500;}
  .adc-ev-count{font-size:.92rem;font-weight:800;color:var(--ink);}

  /* Calendar */
  .adc-cal,.adc-appr{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:12px;}
  .adc-cal li{display:flex;gap:12px;align-items:center;}
  .adc-cal-date{width:44px;height:44px;flex:none;border-radius:11px;background:var(--bg);display:grid;place-items:center;line-height:1;text-align:center;}
  .adc-cal-date b{font-size:1.05rem;font-weight:800;color:var(--p);}
  .adc-cal-date small{font-size:.62rem;text-transform:uppercase;color:var(--muted);}
  .adc-cal-name{font-size:.85rem;font-weight:600;}
  .adc-cal-day{font-size:.73rem;color:var(--faint);}

  /* Approvals */
  .adc-appr li{display:flex;gap:10px;align-items:center;}
  .adc-appr-av{width:36px;height:36px;border-radius:50%;object-fit:cover;flex:none;}
  .adc-appr-body{flex:1;min-width:0;}
  .adc-appr-name{font-size:.84rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .adc-appr-meta{font-size:.73rem;color:var(--faint);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .adc-rail-scroll{max-height:250px;overflow-y:auto;overflow-x:hidden;padding-right:2px;}
  .adc-rail-scroll::-webkit-scrollbar{width:6px;}
  .adc-rail-scroll::-webkit-scrollbar-thumb{background:var(--line-strong);border-radius:3px;}

  /* Quick Actions */
  .adc-qa{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
  .adc-qa-btn{display:flex;flex-direction:column;align-items:center;gap:5px;padding:13px 6px;border-radius:11px;background:var(--bg);color:var(--ink);text-decoration:none;font-size:.74rem;font-weight:600;text-align:center;border:1px solid transparent;transition:all .12s;}
  .adc-qa-btn:hover{background:#fff;border-color:var(--p);color:var(--p);transform:translateY(-2px);box-shadow:0 6px 16px -8px rgba(15,23,42,.2);}
  .adc-qa-btn i{font-size:1.35rem;color:var(--p);}

  /* Empty states */
  .adc-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:34px 12px;color:var(--faint);text-align:center;}
  .adc-empty i{font-size:1.8rem;}
  .adc-empty span{font-size:.82rem;}
  .adc-empty-sm{padding:18px 12px;flex-direction:row;}

  @media (max-width:1199px){ .adc-asset-num{font-size:1.3rem;} }

  /* ---- Premium motion & live polish ---- */
  @keyframes adcFadeUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:none; } }
  .adc-kpi, .adc-card, .adc-tile { animation: adcFadeUp .5s cubic-bezier(.22,.61,.36,1) both; }
  @media (prefers-reduced-motion: reduce){ .adc-kpi,.adc-card,.adc-tile{animation:none;} }
  .adc-kpi-value, .adc-asset-num, .adc-tier-pct { font-variant-numeric: tabular-nums; }
  .adc-live-flash { animation: adcFlash .9s ease; }
  @keyframes adcFlash { 0%{ background: color-mix(in srgb, var(--p) 16%, transparent); } 100%{ background: transparent; } }

  /* LIVE indicator pill */
  .adc-live{display:inline-flex;align-items:center;gap:6px;font-family:inherit;font-size:.72rem;font-weight:700;letter-spacing:.04em;
            color:var(--success);background:color-mix(in srgb,var(--success) 12%,#fff);border:1px solid color-mix(in srgb,var(--success) 25%,#fff);
            padding:5px 11px;border-radius:20px;text-transform:uppercase;}
  .adc-live-dot{width:8px;height:8px;border-radius:50%;background:var(--success);position:relative;}
  .adc-live-dot::after{content:"";position:absolute;inset:-4px;border-radius:50%;background:var(--success);opacity:.5;animation:adcPulse 1.6s ease-out infinite;}
  @keyframes adcPulse { 0%{transform:scale(.6);opacity:.6;} 100%{transform:scale(1.8);opacity:0;} }
  .adc-live.syncing .adc-live-dot{background:var(--warning);}
  .adc-live.syncing .adc-live-dot::after{background:var(--warning);}

  /* Skeleton shimmer */
  .adc-skel{background:linear-gradient(90deg,#eef1f5 25%,#f7f9fb 37%,#eef1f5 63%);background-size:400% 100%;animation:adcShimmer 1.3s ease infinite;border-radius:8px;color:transparent!important;}
  @keyframes adcShimmer { 0%{background-position:100% 0;} 100%{background-position:-100% 0;} }

  /* Inline approve/reject actions */
  .adc-act{display:inline-flex;gap:5px;flex:none;}
  .adc-act button{width:28px;height:28px;border-radius:8px;border:1px solid var(--line);background:#fff;cursor:pointer;display:grid;place-items:center;font-size:1rem;transition:all .12s;padding:0;line-height:1;}
  .adc-act .adc-ok{color:var(--success);} .adc-act .adc-ok:hover{background:var(--success);color:#fff;border-color:var(--success);}
  .adc-act .adc-no{color:var(--danger);} .adc-act .adc-no:hover{background:var(--danger);color:#fff;border-color:var(--danger);}
  .adc-act button:disabled{opacity:.5;cursor:default;}
  .adc-appr li.adc-done{opacity:.45;} .adc-appr li.adc-removing{animation:adcOut .35s ease forwards;}
  @keyframes adcOut { to { opacity:0; transform:translateX(20px); height:0; margin:0; padding:0; } }

  /* ============================================================
     RESPONSIVE — tablet & native-app-style mobile
     ============================================================ */
  @media (max-width: 991.98px){
    .adc-card{padding:16px 15px 14px;}
    .adc-hero-title{font-size:1.2rem;}
  }
  @media (max-width: 767.98px){
    /* App-like single column: tighter rhythm, full-width cards, no overflow */
    .adc, .adc *{max-width:100%;}
    .adc .row.g-4{--bs-gutter-y:.85rem;}
    .adc .row.g-3{--bs-gutter-y:.7rem;}
    .adc .mt-4{margin-top:.85rem !important;}
    .adc-hero{flex-direction:column;align-items:flex-start;gap:8px;}
    .adc-hero-title{font-size:1.12rem;}
    .adc-hero-sub{font-size:.8rem;}
    .adc-card{padding:14px 13px 12px;border-radius:15px;}
    /* Sales Target card (admin + sales/production manager dashboards):
       neither the title nor the adc-seg (Bingo/Kingo/Super K) segmented
       control could shrink or wrap, so the control's right edge was cut
       off the card at phone width once both sat side by side. */
    .adc-card-head{margin-bottom:10px;flex-wrap:wrap;gap:6px;}
    .adc-card-title{font-size:.92rem;min-width:0;}
    .adc-kpi{padding:12px 11px;border-radius:13px;}
    .adc-kpi-value{font-size:1.3rem;}
    .adc-kpi-icon{width:32px;height:32px;font-size:1rem;border-radius:9px;}
    .adc-kpi-go{display:none;}
    .adc-kpi-label{font-size:.76rem;}
    .adc-tile{padding:11px;gap:9px;}
    .adc-tile-icon{width:36px;height:36px;font-size:1.1rem;}
    .adc-asset-cell{min-height:92px;padding:13px;}
    .adc-asset-num{font-size:1.5rem;}
    .adc-seg button{padding:4px 9px;}
    .adc-tgt-row{padding:8px 6px;}
    .adc-tgt-row b{font-size:.92rem;}
    .adc-staff-scroll,.adc-rail-scroll{max-height:44vh;-webkit-overflow-scrolling:touch;}
    /* comfortable touch targets */
    .adc-qa-btn,.adc-events li,.adc-appr li,.adc-tile{min-height:44px;}
    .adc-qa-btn{padding:14px 6px;}
  }
  @media (max-width: 575.98px){
    .adc-hero .adc-chip{padding:6px 10px;font-size:.76rem;}
    .adc-kpi-value{font-size:1.22rem;}
    .adc-kpi-sub{font-size:.66rem;}
    /* Quick actions 3-up on phones for a compact app grid */
    .adc-qa{grid-template-columns:repeat(3,1fr);gap:7px;}
    .adc-qa-btn{font-size:.68rem;padding:11px 4px;}
    .adc-qa-btn i{font-size:1.2rem;}
  }

  /* Short laptop windows (e.g. 1291x671, 1366x768-class): wide enough to
     be classed "desktop" by every rule above (>=992px), so none of the
     width-based compacting ever kicks in - but a window this short still
     only shows a sliver of the page. Reuses this file's own already-
     proven mobile/tablet figures (the 767.98px block above) rather than
     inventing new ones, just triggered by height instead of width. */
  @media (max-height: 800px) and (min-width: 992px){
    .adc-hero-title{font-size:1.2rem;}
    .adc-card{padding:16px 15px 14px;}
    .adc-card-head{margin-bottom:10px;}
    .adc-card-title{font-size:.92rem;}
    .adc-kpi{padding:12px 11px;border-radius:13px;}
    .adc-kpi-value{font-size:1.3rem;}
    .adc-kpi-icon{width:32px;height:32px;font-size:1rem;border-radius:9px;}
    .adc-kpi-label{font-size:.76rem;}
    .adc-tile{padding:11px;gap:9px;}
    .adc-tile-icon{width:36px;height:36px;font-size:1.1rem;}
    .adc-asset-cell{min-height:92px;padding:13px;}
    .adc-asset-num{font-size:1.5rem;}
    .adc-tgt-row{padding:8px 6px;}
    .adc-tgt-row b{font-size:.92rem;}
    /* !important: .adc-rail-scroll also carries an inline
       style="max-height:300px" (admin-index.blade.php's approvals rail),
       which otherwise always wins over any stylesheet rule regardless of
       selector weight - this is the one place that needs it to actually
       govern a short viewport. */
    .adc-staff-scroll,.adc-rail-scroll{max-height:28vh !important;-webkit-overflow-scrolling:touch;}
    .adc .row.g-4{--bs-gutter-y:.85rem;}
    .adc .row.g-3{--bs-gutter-y:.7rem;}
    .adc .mt-4{margin-top:.85rem !important;}

    /* Safety net: whatever is still inside a card (a chart, a list, both
       stacked) at least cannot make that ONE card taller than the window
       itself - it scrolls internally instead of pushing the rest of the
       page down. This is what actually fixed the dashboard: a single
       .adc-card (My Tasks + Sales Target row) measured 651px tall against
       a 671px-tall window on its own, and no amount of padding/font-size
       trimming on its contents was ever going to close a gap that large -
       the chart heights above are the other half of that fix. */
    .adc-card{max-height:60vh;overflow-y:auto;}
  }
</style>
