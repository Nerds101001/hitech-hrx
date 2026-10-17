{{--
    Quick Calculator: a product-catalogue sidebar + calculator panel popup
    over the product calculators only (no price list browsing, no quote
    builder, no admin editing) — pick a product on the left, get its
    weight/price and approval level on the right. Fed by
    PricingDeskController::calculatorData(), fetched in the background as
    soon as this partial loads (not on click) and cached in sessionStorage,
    so opening the modal itself is instant rather than waiting on a fetch.

    The calculator definitions (CALCS) and the approval-band logic below are
    copied from the full Pricing Desk tool
    (resources/views/tenant/pricing-desk/index.blade.php) rather than shared,
    since this popup is a separate, smaller surface loaded on every page.
--}}
<div class="modal fade" id="quickCalcModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-md-down">
    <div class="modal-content qc-modal">
      <div class="modal-header qc-header">
        <div class="d-flex align-items-center gap-3">
          <div class="qc-header-icon"><i class="bx bx-calculator"></i></div>
          <div>
            <h5 class="modal-title mb-0 text-uppercase">Quick Calculator</h5>
            <div class="qc-header-sub">Calculate weight and price for packaging materials</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div id="qcLoading" class="text-muted small py-5 text-center">Loading…</div>
        <div id="qcWrap" class="d-none">

          <!-- Mobile: the catalogue (search box + list) lives inside one
               collapsed dropdown instead of its own always-visible sidebar
               panel, which ate most of the screen on a phone. Tapping the
               trigger opens the exact same search+list the desktop sidebar
               uses — one component, shown two different ways. -->
          <button type="button" class="qc-mobile-trigger d-md-none" id="qcMobileTrigger">
            <i class="bx bx-search"></i>
            <span id="qcMobileTriggerLabel">Search and select a product…</span>
            <i class="bx bx-chevron-down qc-mobile-trigger-chevron"></i>
          </button>

          <!-- Sidebar: product catalogue (desktop: static column; mobile: a
               dropdown panel opened by qcMobileTrigger, see JS below) -->
          <div class="qc-sidebar" id="qcSidebar">
            <div class="qc-sidebar-head">
              <div class="fw-bold d-none d-md-block">Product Catalogue</div>
              <div class="text-muted small d-none d-md-block">Search and select a product</div>
              <div class="position-relative mt-md-2">
                <i class="bx bx-search qc-search-icon"></i>
                <input type="text" id="qcSearch" class="form-control form-control-sm ps-4" placeholder="Search product, category…" autocomplete="off">
              </div>
            </div>
            <div id="qcList" class="qc-list"></div>
          </div>

          <!-- Main panel -->
          <div class="qc-panel">
            <div id="qcEmpty" class="text-muted text-center py-5">
              <i class="bx bx-left-arrow-alt bx-sm d-block mb-2"></i>
              Pick a product from the catalogue on the left.
            </div>
            <div id="qcBody" class="d-none">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="d-flex align-items-start gap-3">
                  <div class="qc-product-icon" id="qcProductIcon"><i class="bx bx-cube"></i></div>
                  <div>
                    <h5 class="mb-0 fw-bold" id="qcTitle"></h5>
                    <div class="text-muted small" id="qcDesc" style="max-width:480px;"></div>
                  </div>
                </div>
                <div class="btn-group btn-group-sm qc-unit-seg" id="qcUnitSeg" role="group"></div>
              </div>

              <div class="row g-3 mt-1" id="qcFields"></div>
              <div id="qcRateSection"></div>
              <div class="row g-3 mt-2" id="qcResults"></div>
              <div id="qcBand" class="mt-3"></div>

              <div class="qc-note mt-3">
                <i class="bx bx-info-circle"></i>
                <span>Calculation is based on selected product specifications and standard factors. Please confirm final price as per the latest price list and customer terms.</span>
              </div>

              <div id="qcError" class="alert alert-danger small mt-3 d-none"></div>

              <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="qcReset"><i class="bx bx-reset me-1"></i> Reset</button>
                <button type="button" class="btn btn-sm qc-btn-primary" id="qcCalc"><i class="bx bx-calculator me-1"></i> Calculate</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .qc-modal .modal-content { border-radius:14px; overflow:hidden; }
  .qc-header { background:var(--hitech-primary, #008080); color:#fff; border-bottom:0; padding:18px 24px; }
  .qc-header .modal-title { color:#fff; letter-spacing:.03em; font-size:1rem; }
  .qc-header-sub { color:rgba(255,255,255,.75); font-size:.8rem; }
  .qc-header-icon { width:44px; height:44px; border-radius:10px; background:rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; font-size:1.25rem; }

  #qcWrap { display:flex; min-height:560px; max-height:78vh; }
  .qc-sidebar { width:300px; flex-shrink:0; border-right:1px solid var(--hitech-border, #dee2e6); display:flex; flex-direction:column; background:#fbfcfd; }
  .qc-sidebar-head { padding:16px; border-bottom:1px solid var(--hitech-border, #dee2e6); }
  .qc-search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9aa5b1; font-size:.9rem; }
  .qc-list { flex-grow:1; overflow-y:auto; padding:6px 0; }

  .qc-group-head { display:flex; align-items:center; gap:8px; width:100%; border:0; background:none; padding:9px 16px; font-weight:600; font-size:.86rem; color:#2b2f33; }
  .qc-group-head:hover { background:var(--hitech-bg-soft, #f8fafc); }
  .qc-group-icon { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:.85rem; color:#fff; flex-shrink:0; }
  .qc-group-count { margin-left:auto; background:var(--hitech-bg-soft, #f1f3f5); color:#6b7684; font-size:.72rem; font-weight:700; border-radius:999px; padding:1px 8px; }
  .qc-group-chevron { transition:transform .15s ease; color:#9aa5b1; }
  .qc-group-head.collapsed .qc-group-chevron { transform:rotate(-90deg); }

  .qc-item { display:block; width:100%; text-align:left; border:0; background:none; padding:7px 16px 7px 50px; font-size:.84rem; color:#4a525c; border-left:3px solid transparent; }
  .qc-item:hover { background:var(--hitech-bg-soft, #f8fafc); }
  .qc-item.active { background:var(--hitech-primary-light, rgba(0,128,128,.08)); color:var(--hitech-primary, #008080); font-weight:600; border-left-color:var(--hitech-primary, #008080); }

  .qc-panel { flex-grow:1; padding:24px 28px; overflow-y:auto; }
  .qc-product-icon { width:56px; height:56px; border-radius:12px; background:var(--hitech-primary-light, rgba(0,128,128,.08)); color:var(--hitech-primary, #008080); display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0; }
  .qc-unit-seg .btn.active { background:var(--hitech-primary, #008080); border-color:var(--hitech-primary, #008080); color:#fff; }

  #qcFields label, #qcRateSection label { font-size:.78rem; font-weight:600; color:#4a525c; }
  #qcFields .req, #qcRateSection .req { color:#e35b5b; }

  .qc-hero { border:1px solid transparent; border-radius:12px; padding:14px 16px; height:100%; display:flex; align-items:center; gap:14px; overflow:hidden; box-sizing:border-box; }
  .qc-hero.a, .qc-hero.b { background:var(--hitech-primary-light, rgba(0,128,128,.07)); border-color:rgba(0,128,128,.16); }
  .qc-hero-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; background:#fff; }
  .qc-hero-icon.a, .qc-hero-icon.b { color:var(--hitech-primary, #008080); }
  .qc-hero-txt { min-width:0; }
  .qc-hero .qc-k { font-size:.78rem; color:#8a94a6; font-weight:600; overflow-wrap:break-word; }
  .qc-hero .qc-v { font-size:1.15rem; font-weight:700; color:#1f2937; overflow-wrap:break-word; }

  .qc-pill { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:6px 14px; font-size:.95rem; font-weight:600; }
  .qc-pill i { font-size:1.1rem; }
  .qc-pill.lvl0 { background:#e3f4ea; color:#1f7a45; }
  .qc-pill.lvl1 { background:#fdf1d6; color:#8a5a00; }
  .qc-pill.lvl2 { background:#fde6d3; color:#9a4a07; }
  .qc-pill.lvl3 { background:#fbe3e4; color:#a4262c; }
  .qc-band-meta { color:#5d6b7a; font-size:.92rem; }
  .qc-band-meta b { color:#1f2937; font-weight:700; }

  .qc-note { background:#eef5ff; color:#355b8c; border-radius:10px; padding:11px 14px; font-size:.8rem; display:flex; gap:9px; align-items:flex-start; }
  .qc-note i { font-size:1rem; margin-top:1px; }

  .qc-btn-primary { background:var(--hitech-primary, #008080); border-color:var(--hitech-primary, #008080); color:#fff; }
  .qc-btn-primary:hover { background:#006666; border-color:#006666; color:#fff; }

  .qc-mobile-trigger{ display:none; }

  @media (max-width:767px){
    /* The modal is modal-fullscreen-md-down here, so #qcWrap owns the full
       viewport height (minus the header) instead of the fixed 78vh used on
       desktop, and the whole thing becomes a single scrollable column. */
    #qcWrap{ flex-direction:column; max-height:none; height:100%; overflow-y:auto; position:relative; }
    .qc-panel{ padding:18px 16px; overflow-y:visible; }
    .qc-header{ padding:14px 16px; }
    .qc-product-icon{ width:46px; height:46px; font-size:1.2rem; }
    #qcTitle{ font-size:1.05rem; }

    /* Catalogue collapses into one dropdown trigger instead of an
       always-visible list — picking a product this way needs far less
       screen than scrolling a nested list on a phone. Tapping it opens the
       same search+list the desktop sidebar uses, as a floating panel. */
    .qc-mobile-trigger{
      display:flex; align-items:center; gap:8px; width:calc(100% - 32px); margin:14px 16px 0;
      height:44px; padding:0 14px; border:1px solid var(--hitech-border, #dee2e6); border-radius:8px;
      background:#fff; color:#4a525c; font-size:.86rem; text-align:left;
    }
    .qc-mobile-trigger i:first-child{ color:#9aa5b1; font-size:1rem; }
    .qc-mobile-trigger span{ flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .qc-mobile-trigger-chevron{ color:#9aa5b1; transition:transform .15s ease; }
    .qc-mobile-trigger.open .qc-mobile-trigger-chevron{ transform:rotate(180deg); }

    .qc-sidebar{ display:none; }
    .qc-sidebar.qc-sidebar-open{
      display:flex; position:absolute; top:66px; left:16px; right:16px; width:auto; z-index:80;
      max-height:min(60vh,420px); background:#fff; border:1px solid var(--hitech-border, #dee2e6);
      border-radius:10px; box-shadow:0 12px 32px rgba(23,43,77,.18);
    }
    .qc-sidebar.qc-sidebar-open .qc-sidebar-head{ padding:12px; }
    .qc-sidebar.qc-sidebar-open .qc-list{ max-height:340px; }

    /* Bootstrap's col-md-6/col-md-4 fall back to col-12 below the md
       breakpoint, stacking every dimension field and result card into one
       column on a phone — force two per row here instead, since that's
       exactly the width a phone screen has room for. */
    #qcFields > [class*="col-"],
    #qcResults > [class*="col-"]{
      flex:0 0 calc(50% - 6px); max-width:calc(50% - 6px);
    }
    #qcRateSection .row > .col-12.col-md-6{ flex:0 0 100%; max-width:100%; }
    /* A half-width phone card has no room to sit the icon beside the text —
       stack it above instead, so the label/value get the card's full width
       to wrap into rather than overflowing past the rounded corner. */
    .qc-hero{ flex-direction:column; align-items:flex-start; padding:12px; gap:8px; }
    .qc-hero-icon{ width:34px; height:34px; font-size:1rem; }
    .qc-hero .qc-k{ font-size:.72rem; }
    .qc-hero .qc-v{ font-size:1rem; word-break:break-word; }
  }
</style>

<script>
(function(){
  const esc = s => String(s==null?'':s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const fmt = (n,d=2) => n==null||isNaN(n) ? '–' : Number(n).toLocaleString('en-IN',{minimumFractionDigits:d,maximumFractionDigits:d});
  const r2 = n => Math.round(n*100)/100;
  const fmtDim = x => Number.isInteger(x) ? String(x) : String(r2(x));

  const d = (k,label,inDef,mmDef) => ({k,label,dim:true,inDef,mmDef});
  const n = (k,label,def) => ({k,label,def});
  const sel = (k,label,opts,def) => ({k,label,opts,def});

  const CALCS = [
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
    calc(v){return{info:[['₹ per sqm',v.pkg>0?v.pkg*v.gsm/1000:v.psqm,2],['₹ per kg',v.pkg>0?v.pkg:(v.gsm?1000*v.psqm/v.gsm:0),2]]}}},
   {id:'tuff',g:'Tuffpaulin',t:'Tuffpaulin sheet (made-up size)',desc:'Size in feet. Weight adds 80 g for eyelets/rope. FG size price adds 10% for finishing.',noUnits:true,
    fields:[n('Wf','Width (ft)',22),n('Lf','Length (ft)',8),sel('gsm','GSM',['70','90','120','150','200','250','300'],'120'),n('oq','Order quantity (kg) for pcs estimate',1000)],rate:{unit:'SQM',codes:['TUFF70','TUFF90','TUFF120','TUFF150','TUFF200','TUFF250','TUFF300'],def:'TUFF120',auto:v=>'TUFF'+v.gsm},
    calc(v){const sqft=v.Wf*v.Lf, sqm=sqft/10.7639, kg=sqft*(+v.gsm)/10.76/1000+0.08;return{sqm,kg,unit:'pc',fg:F().tuffFG,desc:`Tuffpaulin sheet ${v.Wf} × ${v.Lf} ft, ${v.gsm} GSM`,extra:r=>[['Rate ₹/sqft',r/10.7639,2],['Sheets in order qty',kg?v.oq/kg:0,0]]}}},
   {id:'conv1',g:'Size converters',t:'3D box → 2D / gusset bag (with flap)',desc:'Type 1 — box size known, bag closes over the top.',
    fields:[d('L','Box length',52,450),d('W','Box width',44,350),d('H','Box height',85,675)],
    calc(v){const a=v._mm?25:1,b=v._mm?50:2,u=v._mm?'mm':'inch',ce=(x,s)=>Math.ceil(x/s)*s,L=v._raw.L,W=v._raw.W,H=v._raw.H;
     return{info:[[`2D bag width (${u})`,W+L+a,0],[`2D bag length (${u})`,W+H+b,0],[`Gusset bag width (${u})`,ce(L,10)+a,0],[`Gusset each side G1 = G2 (${u})`,W/2,1],[`Gusset bag length (${u})`,ce(W+H+b,5),0]]}}},
   {id:'conv2',g:'Size converters',t:'3D bag without flap → 2D / gusset',desc:'Type 2 — open-top bag.',
    fields:[d('L','Box length',52,425),d('W','Box width',44,275),d('H','Box height',85,350)],
    calc(v){const a=v._mm?25:1,u=v._mm?'mm':'inch',ce=(x,s)=>Math.ceil(x/s)*s,L=v._raw.L,W=v._raw.W,H=v._raw.H;
     return{info:[[`2D bag width (${u})`,W+L+a,0],[`2D bag length (${u})`,H+W/2+a,1],[`Gusset bag width (${u})`,ce(L,10)+a,0],[`Gusset each side (${u})`,W/2,1],[`Gusset bag length (${u})`,H+W/2+a,1]]}}},
   {id:'part',g:'Size converters',t:'Round part → 2D bag size',desc:'For shafts, rolls, cylinders. Width = circumference ÷ 2 + 1″; length = part length + dia + 2″.',
    fields:[d('D','Diameter',4,100),d('L','Part length',72,1800)],
    calc(v){const a=v._mm?25:1,b=v._mm?50:2,u=v._mm?'mm':'inch',D=v._raw.D,L=v._raw.L,c=3.14*D;
     return{info:[[`Circumference (${u})`,c,1],[`2D bag width (${u})`,c/2+a,1],[`2D bag length (${u})`,L+D+b,1]]}}},
   {id:'spec3d',g:'Size converters',t:'3D bag production spec',desc:'Tube, cutting length and gusset for the plant (inch output).',
    fields:[d('L','Box length',100,1200),d('W','Box width',100,1000),d('H','Box height',100,1000)],
    calc(v){const L=v._raw.L,W=v._raw.W,H=v._raw.H;if(v._mm)return{info:[['Tube (inch)',(H+26)/25.4,2],['Cutting (inch)',Math.ceil(((L+W)*2+104)/25.4),0],['Gusset (inch)',(L+26)/25.4,2],['× (inch)',(W+26)/25.4,2]]};
     return{info:[['Tube (inch)',H+1,0],['Cutting (inch)',Math.ceil((L+W)*2+4),0],['Gusset (inch)',L+1,0],['× (inch)',W+1,0]]}}},
   {id:'kg2sqm',g:'Size converters',t:'Plastic kg → square metres',desc:'Coverage from weight. sqm = kg × 1000 ÷ (micron × 0.92).',noUnits:true,
    fields:[n('kg','Quantity (kg)',200),n('mic','Thickness (micron)',100)],
    calc(v){return{info:[['Square metres',v.mic?v.kg*1000/(v.mic*0.92):0,1]]}}},
   {id:'pouch',g:'Size converters',t:'Emitter / desiccant quantity',desc:'Enclosure volume → number of pouches. VCI 4200: one 20 g pouch per 4 cu ft. VCI 19000: one 50 g pouch per 10 cu ft.',
    fields:[d('L','Length',87,1000),d('W','Width',48,1000),d('H','Height',94,1000)],
    calc(v){const cft=v.L*v.W*v.H/1728;return{info:[['Volume (cu m)',cft/35.3147,2],['Volume (cu ft)',cft,1],['VCI 4200 — 20 g pouches',Math.ceil(cft/4),0],['VCI 19000 — 50 g pouches',Math.ceil(cft/10),0]]}}},
   {id:'box',g:'Size converters',t:'Pieces per box',desc:'Packing count from box weight and bag weight.',noUnits:true,
    fields:[n('bk','Box weight (kg)',20),n('gb','Weight per bag (grams)',34)],
    calc(v){const p=v.gb?v.bk*1000/v.gb:0;const s=p<=500?50:p<=2000?100:p<=4000?200:500;return{info:[['Pieces per box',Math.floor(p),0],['Pieces per sleeve',s,0]]}}},
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
    fields:[d('L','Length',26,660),d('W','Width',14,355),d('G','Total gusset',9,230),n('mic','Thickness (micron)',38)],rate:{unit:'KG',list:'drbio',codes:['DB-1001','DB-1002','DB-1003','DB-1004','DB-1033','DB-1034','DB-1035','DB-1036','DB-1037','DB-1038','DB-1039','DB-1040','DB-1041','DB-1042','DB-1043','DB-1044'],def:'DB-1001'},
    calc(v){const kg=(v.W+v.G)*v.L*v.mic*4*F().drbio/3300/1000;return{kg,unit:'pc',desc:`Dr. Bio carry bag ${v._dims('W','L')} + ${v._dims('G')} gusset, ${v.mic} mic`}}},
   {id:'dbcourier',g:'Dr. Bio compostable',t:'Courier bag (tamper-proof tape)',desc:'Flap + tape.',
    fields:[d('W','Width',8,200),d('L','Length',6,150),d('Fl','Flap',2,50),n('mic','Thickness (micron)',35),n('tape','Tape cost ₹ per inch of width',0.04)],rate:{unit:'KG',list:'drbio',codes:['DB-1021','DB-1022','DB-1023','DB-1024','DB-1025','DB-1026','DB-1027','DB-1028','DB-1029','DB-1030','DB-1031','DB-1032'],def:'DB-1021'},
    calc(v){const kg=(v.L+v.Fl)*v.W*v.mic/3300*4*F().drbio/1000;return{kg,add:v.tape*v.W,unit:'pc',desc:`Dr. Bio courier bag ${v._dims('W','L')} + ${v._dims('Fl')} flap, ${v.mic} mic, tamper-proof tape`}}},
   {id:'dbreclose',g:'Dr. Bio compostable',t:'Reclosable / seal-tape bag',desc:'Bag with flap and reclosable seal tape.',
    fields:[d('W','Width',10,300),d('L','Length',12,250),d('Fl','Flap',2,50),n('mic','Thickness (micron)',25),n('tape','Tape cost ₹ per inch of width',0.016)],rate:{unit:'KG',list:'drbio',codes:['DB-1033','DB-1034','DB-1035','DB-1036','DB-1037','DB-1038','DB-1039','DB-1040','DB-1041','DB-1042','DB-1043','DB-1044','DB-1005','DB-1006','DB-1007','DB-1008','DB-1009','DB-1010','DB-1011','DB-1012','DB-1013','DB-1014','DB-1015','DB-1016','DB-1017','DB-1018','DB-1019','DB-1020'],def:'DB-1033'},
    calc(v){const kg=(v.L+v.Fl)*v.W*v.mic/3300*4*F().drbio/1000;return{kg,add:v.tape*v.W,unit:'pc',desc:`Dr. Bio reclosable bag ${v._dims('W','L')} + ${v._dims('Fl')} flap, ${v.mic} mic`}}},
   {id:'dbgarb',g:'Dr. Bio compostable',t:'Garbage bags (per roll)',desc:'Priced per roll of bags.',
    fields:[d('L','Length',30,760),d('W','Width',28,710),n('mic','Thickness (micron)',20),n('per','Bags per roll',15)],rate:{unit:'KG',list:'drbio',codes:['DB-1058','DB-1059','DB-1060','DB-1061','DB-1062','DB-1063','DB-1049','DB-1050','DB-1051','DB-1052','DB-1053','DB-1054','DB-1055','DB-1056','DB-1057'],def:'DB-1058'},
    calc(v){const kg=v.L*v.W*v.mic/3300000*4*F().drbio*v.per;return{kg,unit:'roll',desc:`Dr. Bio garbage bags ${v._dims('W','L')}, ${v.mic} mic, ${v.per} bags/roll`}}},
   {id:'dbshrink',g:'Dr. Bio compostable',t:'Shrink film — roll or bags',desc:'Tube (centre-fold), sheet roll, or individual bags. Factor 1.25.',
    fields:[sel('type','Format',['Tube roll','Sheet roll','Individual bag'],'Tube roll'),d('W','Width',14,350),n('Lm','Roll length (m) — or bag length in inch/mm for individual bags',500),n('mic','Thickness (micron)',35)],rate:{unit:'KG',list:'drbio',codes:['DB-1065','DB-1066'],def:'DB-1065'},
    calc(v){const f=F().shrink;if(v.type==='Individual bag'){const Lb=v._mm?v.Lm/25.4:v.Lm;const kg=v.W*Lb*v.mic*4*f/3300/1000;return{kg,unit:'pc',desc:`Dr. Bio shrink bag ${v._dims('W')} × ${v.Lm} ${v._mm?'mm':'inch'}, ${v.mic} mic`}}
     const wm=v._mm?v.W*25.4/1000:v.W/40;const kg=wm*v.Lm*v.mic*(v.type==='Tube roll'?2:1)*f/1000;return{kg,unit:'roll',desc:`Dr. Bio shrink ${v.type.toLowerCase()} ${v._dims('W')} × ${v.Lm} m, ${v.mic} mic`}}},
   {id:'dbstretch',g:'Dr. Bio compostable',t:'Stretch film roll',desc:'Machine or hand wrap. Individual pack adds ₹15 for packaging.',
    fields:[sel('type','Format',['Machine wrap','Hand wrap','Individual pack'],'Machine wrap'),d('W','Width',18,450),n('Lm','Roll length (metres)',500),n('mic','Thickness (micron)',20)],rate:{unit:'KG',list:'drbio',codes:['DB-1064'],def:'DB-1064'},
    calc(v){const kg=(v.W/40)*v.Lm*v.mic*4*F().drbio/6600;return{kg,add:v.type==='Individual pack'?15:0,unit:'roll',desc:`Dr. Bio stretch film (${v.type.toLowerCase()}) ${v._dims('W')} × ${v.Lm} m, ${v.mic} mic`}}},
  ];

  const GROUP_META = {
    'VCI plastic':         {icon:'bx-shield-quarter', color:'#009688'},
    'VCI paper & barrier': {icon:'bx-file',           color:'#c77b2e'},
    'Tuffpaulin':          {icon:'bx-cube',           color:'#3f6fd1'},
    'Size converters':     {icon:'bx-transfer-alt',   color:'#7e57c2'},
    'Dr. Bio compostable': {icon:'bx-leaf',           color:'#4caf7d'},
  };
  const BAND_TXT = ['No approval needed','GM approval','Channel price','Director approval'];
  const CACHE_KEY = 'hrx_qc_calc_data_v2';

  let DATA = null;
  let curCalc = null;
  let calcUnit = 'in';
  let custType = 'Direct';
  let collapsed = {};
  const F = () => DATA.factors;

  function fetchData(){
    return fetch(@json(route('sales-visits.pricing-desk.calculator-data')), {headers:{'Accept':'application/json'}})
      .then(r => { if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); });
  }

  // Warms DATA + sessionStorage as soon as this partial loads, so opening
  // the modal later almost never has to wait on a network round trip.
  function ensureData(){
    if (DATA) return Promise.resolve(DATA);
    try {
      const cached = sessionStorage.getItem(CACHE_KEY);
      if (cached) { DATA = JSON.parse(cached); return Promise.resolve(DATA); }
    } catch (e) {}
    return fetchData().then(dt => {
      DATA = dt;
      try { sessionStorage.setItem(CACHE_KEY, JSON.stringify(dt)); } catch (e) {}
      return dt;
    });
  }
  ensureData().catch(() => {}); // fire-and-forget warm-up

  function readVals(c){
    const v = {_mm: calcUnit==='mm' && !c.noUnits, _raw:{}};
    c.fields.forEach(f => {
      const el = document.querySelector(`#qcFields [data-k="${f.k}"]`);
      let x = el ? el.value : f.def;
      if (!f.opts) x = parseFloat(x) || 0;
      v._raw[f.k] = x;
      v[f.k] = (f.dim && v._mm) ? x/25.4 : x;
    });
    v._dims = (...ks) => ks.map(k => fmtDim(v._raw[k])).join(' × ') + (v._mm ? ' mm' : '″');
    return v;
  }

  function getRate(c){
    if (!c.rate) return null;
    const list = DATA[c.rate.list || 'vci'] || [];
    const pi = +((document.getElementById('qcProd')||{}).value);
    const x = list[pi];
    if (!x) return {rate:0, prod:null};
    const tier = (document.getElementById('qcTier')||{}).value || '1';
    const rate = tier==='m'
      ? (parseFloat((document.getElementById('qcRate')||{}).value) || 0)
      : (tier==='0' ? x.dlp : x.t[+tier-1]);
    return {rate, prod:x};
  }

  function tierNames(list){
    const r = (DATA.rules||{})[list] || [40,46,48];
    const lm = (DATA.lists||[]).find(x=>x.k===list) || {};
    return [lm.dlpLabel||'DLP', `No approval (${r[0]}% off)`, `GM (${r[1]}% off)`, `Channel (${r[2]}% off)`];
  }

  function band(price, t){
    if (price==null || isNaN(price) || price<=0 || !t) return null;
    const tol = 0.005;
    let lvl;
    if (price >= t[0]-tol) lvl = 0; else if (price >= t[1]-tol) lvl = 1; else if (price >= t[2]-tol) lvl = 2; else lvl = 3;
    let eff = lvl;
    if (lvl===2 && custType!=='Channel partner') eff = 3;
    return {lvl, eff};
  }

  function bandPill(b){
    if (!b) return '';
    const label = b.eff===3 && b.lvl===2 ? 'Channel price — Director approval (direct customer)' : BAND_TXT[b.eff];
    const icon = b.eff===0 ? 'bx-check-circle' : 'bx-error-circle';
    return `<span class="qc-pill lvl${b.eff}"><i class="bx ${icon}"></i>${esc(label)}</span>`;
  }

  function heroCard(label, value, variant){
    const icons = {weight:'bx-package', price:'bx-coin-stack', area:'bx-crop', count:'bx-hash', generic:'bx-bar-chart-alt-2'};
    const lower = label.toLowerCase();
    const icon = lower.includes('weight') ? icons.weight
      : (lower.includes('price') || lower.includes('₹')) ? icons.price
      : (lower.includes('sqm') || lower.includes('area') || lower.includes('square')) ? icons.area
      : (lower.includes('pieces') || lower.includes('count') || lower.includes('sheets')) ? icons.count
      : icons.generic;
    return `<div class="qc-hero ${variant}">
      <div class="qc-hero-icon ${variant}"><i class="bx ${icon}"></i></div>
      <div class="qc-hero-txt"><div class="qc-k">${esc(label)}</div><div class="qc-v">${value}</div></div>
    </div>`;
  }
  // Cards per row depends on how many there are, so a count like 3 fills one
  // clean row instead of leaving the last card alone with an empty gap beside it.
  function heroColClass(n){ return n===1 ? 'col-12' : n===3 ? 'col-12 col-md-4' : 'col-12 col-md-6'; }

  function groupsInOrder(list){
    const seen = [], map = {};
    list.forEach(c => { if (!map[c.g]) { map[c.g] = []; seen.push(c.g); } map[c.g].push(c); });
    return seen.map(g => ({g, items: map[g]}));
  }

  function renderList(filter){
    const q = (filter||'').trim().toLowerCase();
    const matches = CALCS.filter(c => !q || c.t.toLowerCase().includes(q) || c.g.toLowerCase().includes(q));
    const box = document.getElementById('qcList');
    if (!matches.length) { box.innerHTML = '<div class="text-muted small p-3">No match.</div>'; return; }

    let html = '';
    groupsInOrder(matches).forEach(({g, items}) => {
      const meta = GROUP_META[g] || {icon:'bx-cube', color:'#6c757d'};
      const isCollapsed = !!collapsed[g] && !q; // a search always shows matches, never collapsed
      html += `<button type="button" class="qc-group-head${isCollapsed?' collapsed':''}" data-g="${esc(g)}">
          <span class="qc-group-icon" style="background:${meta.color}"><i class="bx ${meta.icon}"></i></span>
          <span>${esc(g)}</span>
          <span class="qc-group-count">${items.length}</span>
          <i class="bx bx-chevron-down qc-group-chevron"></i>
        </button>`;
      if (!isCollapsed) {
        items.forEach(c => {
          html += `<button type="button" class="qc-item${curCalc && curCalc.id===c.id ? ' active' : ''}" data-c="${c.id}">${esc(c.t)}</button>`;
        });
      }
    });
    box.innerHTML = html;
  }

  // The mobile trigger button mirrors whatever's currently selected, and
  // closes its dropdown the moment a product is picked (the desktop sidebar
  // stays open — it's not a dropdown there, just the permanent left column).
  function closeMobileDropdown(){
    document.getElementById('qcSidebar')?.classList.remove('qc-sidebar-open');
    document.getElementById('qcMobileTrigger')?.classList.remove('open');
  }
  function syncMobileTrigger(){
    const label = document.getElementById('qcMobileTriggerLabel');
    if (label) label.textContent = curCalc ? curCalc.t : 'Search and select a product…';
  }

  function selectCalc(id){
    curCalc = CALCS.find(c => c.id===id);
    if (!curCalc) return;
    document.getElementById('qcError').classList.add('d-none');
    document.getElementById('qcEmpty').classList.add('d-none');
    document.getElementById('qcBody').classList.remove('d-none');
    document.getElementById('qcTitle').textContent = curCalc.t;
    document.getElementById('qcDesc').textContent = curCalc.desc;
    const meta = GROUP_META[curCalc.g] || {icon:'bx-cube', color:'var(--hitech-primary, #008080)'};
    document.getElementById('qcProductIcon').innerHTML = `<i class="bx ${meta.icon}"></i>`;
    renderList(document.getElementById('qcSearch').value);
    syncMobileTrigger();
    closeMobileDropdown();

    const unitSeg = document.getElementById('qcUnitSeg');
    if (curCalc.noUnits) { unitSeg.innerHTML=''; }
    else {
      unitSeg.innerHTML = `
        <button type="button" class="btn btn-outline-secondary ${calcUnit==='in'?'active':''}" data-u="in">Inches</button>
        <button type="button" class="btn btn-outline-secondary ${calcUnit==='mm'?'active':''}" data-u="mm">mm</button>`;
    }
    try { renderFields(); }
    catch (e) { showError(e); }
  }

  function showError(e){
    const box = document.getElementById('qcError');
    box.textContent = 'Something went wrong computing this calculator: ' + e.message;
    box.classList.remove('d-none');
    console.error(e);
  }

  function renderFields(){
    const c = curCalc;
    let h = '';
    c.fields.forEach(f => {
      const val = f.dim ? (calcUnit==='mm' ? f.mmDef : f.inDef) : f.def;
      if (f.opts) {
        h += `<div class="col-6 col-md-4"><label class="form-label mb-1">${esc(f.label)} <span class="req">*</span></label>
          <select class="form-select form-select-sm" data-k="${f.k}">
            ${f.opts.map(o => `<option${o==val?' selected':''}>${esc(o)}</option>`).join('')}
          </select></div>`;
      } else {
        h += `<div class="col-6 col-md-4"><label class="form-label mb-1">${esc(f.label)}${f.dim?` (${calcUnit==='mm'?'mm':'inch'})`:''} <span class="req">*</span></label>
          <input type="number" step="any" class="form-control form-control-sm" data-k="${f.k}" value="${esc(val)}"></div>`;
      }
    });
    document.getElementById('qcFields').innerHTML = h;

    const rateSection = document.getElementById('qcRateSection');
    if (c.rate) {
      const list = DATA[c.rate.list || 'vci'] || [];
      const opts = list.map((x,i) => ({x,i})).filter(o => c.rate.codes.includes(o.x.code) && o.x.unit===c.rate.unit);
      const defCode = c.rate.auto ? c.rate.auto({gsm: c.fields.find(f=>f.k==='gsm').def}) : c.rate.def;
      const pi = (opts.find(o => o.x.code===defCode) || opts[0] || {}).i;
      rateSection.innerHTML = opts.length ? `
        <h6 class="text-uppercase text-muted mt-4 mb-2" style="font-size:.74rem; letter-spacing:.04em;">Rate basis</h6>
        <div class="row g-3">
          <div class="col-12 col-md-6"><label class="form-label mb-1">Price-list product (SKU) <span class="req">*</span></label>
            <select class="form-select form-select-sm" id="qcProd">
              ${opts.map(o => `<option value="${o.i}"${o.i===pi?' selected':''}>${esc(o.x.code+' — '+o.x.name+(o.x.spec?' · '+o.x.spec:''))}</option>`).join('')}
            </select></div>
          <div class="col-6 col-md-3"><label class="form-label mb-1">Price level <span class="req">*</span></label>
            <select class="form-select form-select-sm" id="qcTier">
              ${tierNames(c.rate.list||'vci').map((t,k) => `<option value="${k}">${esc(t)}</option>`).join('')}
              <option value="m">Custom rate…</option>
            </select></div>
          <div class="col-6 col-md-3"><label class="form-label mb-1">Customer type <span class="req">*</span></label>
            <select class="form-select form-select-sm" id="qcCustType">
              <option${custType==='Direct'?' selected':''}>Direct</option>
              <option${custType==='Channel partner'?' selected':''}>Channel partner</option>
            </select></div>
          <div class="col-6 d-none" id="qcRateWrap"><label class="form-label mb-1">Custom rate (₹/${c.rate.unit.toLowerCase()})</label>
            <input type="number" step="any" class="form-control form-control-sm" id="qcRate"></div>
        </div>` : '<div class="text-muted small mt-2">No price-list rate configured for this item yet — weight only.</div>';
    } else {
      rateSection.innerHTML = '';
    }

    try { compute(); } catch (e) { showError(e); }
  }

  function compute(){
    const c = curCalc;
    const v = readVals(c);
    const r = c.calc(v);
    const R = getRate(c);
    let cards = [];
    let b = null, bProd = null;
    if (r.info) {
      r.info.forEach(([k,val,dp], i) => cards.push(heroCard(k, fmt(val,dp), i%2===0?'a':'b')));
    } else {
      if (r.kg != null) cards.push(heroCard('Weight', r.kg<1 ? fmt(r.kg*1000,2)+' g/'+r.unit : fmt(r.kg,3)+' kg/'+r.unit, 'a'));
      if (r.sqm != null) cards.push(heroCard('Area incl. wastage', fmt(r.sqm,3)+' sqm', 'a'));
      if (r.kg && r.unit==='pc') cards.push(heroCard('Pieces per kg', fmt(1/r.kg,1), 'b'));
      if (R && R.prod) {
        const base = c.rate.unit==='SQM' ? r.sqm*R.rate : r.kg*R.rate;
        const price = base + (r.add||0);
        cards.push(heroCard(`Price per ${r.unit}`, '₹ '+fmt(price,2), 'b'));
        if (r.fg) cards.push(heroCard('FG size price (+'+Math.round((r.fg-1)*100)+'%)', '₹ '+fmt(price*r.fg,2), 'b'));
        if (r.add) cards.push(heroCard('incl. tape / packing', '₹ '+fmt(r.add,2), 'b'));
        if (r.extra) r.extra(R.rate).forEach(([k,val,dp]) => cards.push(heroCard(k, fmt(val,dp), 'a')));
        b = band(R.rate, R.prod.t);
        bProd = R.prod;
      }
    }
    const colClass = heroColClass(cards.length);
    document.getElementById('qcResults').innerHTML = cards.map(h => `<div class="${colClass}">${h}</div>`).join('');
    const bandBox = document.getElementById('qcBand');
    if (b && bProd) {
      const disc = bProd.dlp>0 ? (1-R.rate/bProd.dlp)*100 : null;
      bandBox.innerHTML = bandPill(b) + ` <span class="qc-band-meta">Rate <b>₹${fmt(R.rate)}/${c.rate.unit.toLowerCase()}</b> &nbsp;|&nbsp; ULP <b>₹${fmt(bProd.dlp)}</b>` + (disc!=null ? ` &nbsp;|&nbsp; <b>${fmt(disc,1)}%</b> off` : '') + `</span>`;
    } else {
      bandBox.innerHTML = '';
    }
  }

  // ---- wiring ----
  document.getElementById('qcSearch').addEventListener('input', e => renderList(e.target.value));

  document.getElementById('qcMobileTrigger')?.addEventListener('click', e => {
    e.stopPropagation();
    const sidebar = document.getElementById('qcSidebar');
    const trigger = document.getElementById('qcMobileTrigger');
    const willOpen = !sidebar.classList.contains('qc-sidebar-open');
    sidebar.classList.toggle('qc-sidebar-open', willOpen);
    trigger.classList.toggle('open', willOpen);
    if (willOpen) setTimeout(() => document.getElementById('qcSearch')?.focus(), 0);
  });
  document.addEventListener('click', e => {
    const sidebar = document.getElementById('qcSidebar');
    if (!sidebar || !sidebar.classList.contains('qc-sidebar-open')) return;
    if (!sidebar.contains(e.target) && e.target.id !== 'qcMobileTrigger' && !e.target.closest('#qcMobileTrigger')) closeMobileDropdown();
  });

  document.getElementById('qcList').addEventListener('click', e => {
    const g = e.target.closest('.qc-group-head');
    if (g) { collapsed[g.dataset.g] = !collapsed[g.dataset.g]; renderList(document.getElementById('qcSearch').value); return; }
    const b = e.target.closest('button[data-c]');
    if (b) selectCalc(b.dataset.c);
  });

  document.getElementById('qcFields').addEventListener('input', () => { try{compute();}catch(e){showError(e);} });
  document.getElementById('qcFields').addEventListener('change', () => { try{compute();}catch(e){showError(e);} });

  document.getElementById('qcRateSection').addEventListener('change', e => {
    if (e.target.id === 'qcTier') {
      document.getElementById('qcRateWrap').classList.toggle('d-none', e.target.value !== 'm');
    }
    if (e.target.id === 'qcCustType') custType = e.target.value;
    try { compute(); } catch (err) { showError(err); }
  });

  document.getElementById('qcUnitSeg').addEventListener('click', e => {
    const b = e.target.closest('button[data-u]');
    if (!b) return;
    calcUnit = b.dataset.u;
    [...e.currentTarget.querySelectorAll('button')].forEach(x => x.classList.toggle('active', x===b));
    try { renderFields(); } catch (err) { showError(err); }
  });

  document.getElementById('qcCalc').addEventListener('click', () => { try{compute();}catch(e){showError(e);} });
  document.getElementById('qcReset').addEventListener('click', () => { if(curCalc) try{renderFields();}catch(e){showError(e);} });

  let shown = false;
  document.getElementById('quickCalcModal')?.addEventListener('show.bs.modal', function(){
    if (shown) return;
    shown = true;
    ensureData().then(() => {
      document.getElementById('qcLoading').classList.add('d-none');
      document.getElementById('qcWrap').classList.remove('d-none');
      renderList('');
      syncMobileTrigger();
    }).catch(e => {
      document.getElementById('qcLoading').innerHTML = '<span class="text-danger">Could not load calculator data: '+esc(e.message)+'</span>';
    });
  });
})();
</script>
