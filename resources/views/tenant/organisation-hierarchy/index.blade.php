@extends('layouts/layoutMaster')

@section('title', 'Organization Hierarchy')

@section('vendor-style')
    @vite([
        'resources/assets/vendor/scss/pages/hitech-portal.scss',
        'resources/assets/vendor/libs/animate-css/animate.scss'
    ])
@endsection

@php
    // $deptColour comes from the controller so the chart borders and the
    // legend swatches are guaranteed to agree.
    $deptColour = $deptColour ?? [];

    $teamOf = function ($n) use (&$teamOf) {
        return array_sum(array_map(fn($c) => 1 + $teamOf($c), $n['children'] ?? []));
    };
@endphp

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

    {{-- Standardized Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4 py-2">
        <div>
            <h3 class="fw-extrabold mb-0 text-dark">Organization Hierarchy</h3>
            <p class="text-muted mb-0 small">
                {{ $totalPeople ?? 0 }} people · {{ $deptCount ?? 0 }} departments ·
                {{ $managerCount ?? 0 }} with reports
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-white shadow-sm rounded-pill px-4 border d-flex align-items-center gap-2" id="fullscreen-toggle" title="Toggle Fullscreen">
                <i class="bx bx-fullscreen fs-5 text-muted"></i>
                <span class="fw-semibold">Fullscreen</span>
            </button>
        </div>
    </div>

    <div class="px-4">

        {{-- Directors now sit inside the chart itself, under the company root,
             so the whole organisation reads as a single tree. --}}

        @if (!empty($gmsWithoutReports))
        <div class="hitech-card-white p-4 mb-4 animate__animated animate__fadeInUp" style="animation-delay:.05s">
            <h6 class="oc-band oc-band-gm">General Manager Level</h6>
            <p class="text-muted small mb-3">
                Senior positions with no direct reports recorded, so they have no branch in the
                chart above. GMs who do have a team appear in the tree beside the directors.
            </p>
            <div class="oc-leads">
                @foreach ($gmsWithoutReports as $g)
                    <article class="oc-lead" style="--c:#7c3aed">
                        <span class="oc-av lg" style="--c:#7c3aed">{{ $g['initials'] }}</span>
                        <span>
                            <b>{{ $g['name'] }}</b>
                            <i>{{ $g['designation'] }}</i>
                            <u>{{ $g['department'] }}</u>
                        </span>
                    </article>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ============ THE CHART ============ --}}
        <div class="hitech-card-white animate__animated animate__fadeInUp overflow-hidden mb-4"
             style="animation-delay:.1s; position:relative;">

            <div class="oc-toolbar">
                <div class="oc-search">
                    <i class="bx bx-search"></i>
                    <input type="search" id="ocSearch" placeholder="Search name, designation, department or code…">
                </div>
                <span class="oc-hint" title="Hold Ctrl (⌘ on Mac) and scroll to zoom · click and drag to move around">
                    <i class="bx bx-mouse"></i>
                    <span><kbd>Ctrl</kbd> + scroll to zoom &nbsp;·&nbsp; drag to move</span>
                </span>
                <span class="oc-zoomval" id="ocZoomVal">100%</span>
                <div class="btn-group shadow-sm rounded-pill overflow-hidden border">
                    <button class="btn btn-white px-3" id="ocOut" title="Zoom out"><i class="bx bx-minus"></i></button>
                    <button class="btn btn-white px-3" id="ocIn" title="Zoom in"><i class="bx bx-plus"></i></button>
                    <button class="btn btn-white px-3 fw-semibold" id="ocFit">Fit</button>
                    <button class="btn btn-white px-3 fw-semibold" id="oc100">100%</button>
                </div>
            </div>

            <div class="oc-scroller" id="ocScroller">
                <div class="oc-chart" id="ocChart">
                    @forelse ($treeRoots ?? [] as $root)
                        <div class="oc-root">
                            @include('tenant.organisation-hierarchy.partials.tree-node', ['node' => $root])
                        </div>
                    @empty
                        <div class="text-muted p-5">No reporting structure to display.</div>
                    @endforelse
                </div>
            </div>

            <div class="oc-legend">
                @foreach ($deptColour as $d => $c)
                    <span class="oc-lg"><i style="background: {{ $c }}"></i>{{ $d }}</span>
                @endforeach
            </div>
        </div>

        {{-- ============ NO MANAGER ============ --}}
        @if (!empty($unassigned))
        <div class="hitech-card-white p-4 mb-4 animate__animated animate__fadeInUp">
            <h6 class="oc-band oc-band-un">No Manager ({{ count($unassigned) }})</h6>
            <p class="text-muted small mb-3">
                Active staff with no reporting manager set, so they cannot be placed in the chart above.
                Assign a manager on their profile and they move into the tree automatically.
            </p>
            <div class="oc-leads">
                @foreach ($unassigned as $u)
                    <article class="oc-lead" style="--c:#b45309">
                        <span class="oc-av lg" style="--c:#b45309">{{ $u['initials'] }}</span>
                        <span>
                            <b>{{ $u['name'] }}</b>
                            <i>{{ $u['designation'] }}</i>
                            <u>{{ $u['department'] }}</u>
                        </span>
                    </article>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Details Drawer (Rich Popover) — same interaction as the previous chart --}}
    <div id="userCardPopover" class="details-drawer shadow-lg d-none">
        <div class="drawer-header p-4 pb-0 text-center">
            <div class="avatar-wrap mb-3 position-relative d-inline-block">
                <img id="pop-img" src="" class="avatar-img rounded-circle border border-4 border-white shadow-sm" style="width: 80px; height: 80px;">
                <span id="pop-status" class="status-dot online"></span>
            </div>
            <h5 id="pop-name" class="mb-0 fw-bold">...</h5>
            <div id="pop-designation" class="text-hitech small fw-semibold">...</div>
        </div>
        <div class="drawer-body p-4">
            <div class="info-list d-flex flex-column gap-3">
                <div class="info-item d-flex align-items-center gap-3">
                    <div class="icon-wrap bg-light rounded-pill p-2"><i class="bx bx-building text-muted"></i></div>
                    <div class="content"><div class="label small text-muted">Department</div><div id="pop-dept" class="value fw-bold small">...</div></div>
                </div>
                <div class="info-item d-flex align-items-center gap-3">
                    <div class="icon-wrap bg-light rounded-pill p-2"><i class="bx bx-envelope text-muted"></i></div>
                    <div class="content"><div class="label small text-muted">Email</div><div id="pop-email" class="value fw-bold small">...</div></div>
                </div>
                <div class="info-item d-flex align-items-center gap-3">
                    <div class="icon-wrap bg-light rounded-pill p-2"><i class="bx bx-phone text-muted"></i></div>
                    <div class="content"><div class="label small text-muted">Contact</div><div id="pop-phone" class="value fw-bold small">...</div></div>
                </div>
            </div>
        </div>
        <div class="drawer-footer p-4 pt-0">
            <button class="btn btn-hitech-primary w-100 rounded-pill py-2" id="pop-view-profile">View Full Profile</button>
        </div>
    </div>
</div>
@endsection

@section('page-style')
<style>
  /* ---------- leadership bands ---------- */
  .oc-band{font-size:.72rem;text-transform:uppercase;letter-spacing:.09em;font-weight:800;
    padding-bottom:6px;margin-bottom:14px;border-bottom:2px solid currentColor}
  .oc-band-dir{color:#005a5a} .oc-band-gm{color:#7c3aed} .oc-band-un{color:#b45309}
  .oc-leads{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px}
  .oc-lead{display:flex;gap:10px;align-items:center;border:1px solid #e6ecf1;
    border-top:3px solid var(--c);border-radius:12px;padding:11px 12px;background:#fff}
  .oc-lead b{display:block;font-size:.83rem;color:#0f172a}
  .oc-lead i{display:block;font-style:normal;font-size:.72rem;color:#64748b}
  .oc-lead u{display:block;text-decoration:none;font-size:.67rem;color:#94a3b8;margin-top:1px}

  /* ---------- toolbar ---------- */
  .oc-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;
    padding:14px 18px;border-bottom:1px solid #eef2f6}
  .oc-search{position:relative;flex:1;min-width:220px}
  .oc-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8}
  .oc-search input{width:100%;padding:9px 13px 9px 36px;border:1px solid #e6ecf1;
    border-radius:30px;background:#f8fafc;font-size:.85rem}
  .oc-search input:focus{outline:none;border-color:#0d9488;background:#fff}
  .oc-hint{display:inline-flex;align-items:center;gap:7px;font-size:.72rem;color:#64748b;
    background:#f1f5f9;border:1px solid #e2e8f0;border-radius:30px;padding:6px 13px;white-space:nowrap}
  .oc-hint i{font-size:.95rem;color:#0d9488}
  .oc-hint kbd{background:#fff;border:1px solid #cbd5e1;border-bottom-width:2px;border-radius:5px;
    padding:1px 6px;font-size:.68rem;font-weight:700;color:#334155;font-family:inherit}
  .oc-zoomval{font-size:.72rem;font-weight:800;color:#005a5a;background:#e6f2f1;
    border-radius:20px;padding:6px 12px;min-width:56px;text-align:center}
  /* brief flash when Ctrl+scroll changes the zoom, so the value draws the eye */
  .oc-zoomval.pulse{animation:ocPulse .45s ease}
  @keyframes ocPulse{0%{background:#0d9488;color:#fff;transform:scale(1.12)}100%{background:#e6f2f1;color:#005a5a;transform:scale(1)}}
  @media (max-width:900px){ .oc-hint{display:none} }
  /* .oc-search's own 220px min-width (plus the 4-button zoom group) was the
     toolbar's real overflow source on a narrow phone even with flex-wrap,
     since a wrapped search box still can't shrink below that floor. */
  @media (max-width:480px){ .oc-search{min-width:0;flex:1 1 100%} }

  /* ---------- chart ---------- */
  .oc-scroller{overflow:auto;padding:26px 10px 30px;background:linear-gradient(#fbfdfe,#fff);
    cursor:grab}
  .oc-scroller.oc-grabbing{cursor:grabbing;user-select:none}
  /* while dragging, don't let the cards swallow the pointer or show hovers */
  .oc-scroller.oc-grabbing .oc-box{pointer-events:none}
  .oc-chart{display:flex;gap:48px;align-items:flex-start;width:max-content;min-width:100%;
    padding:0 14px;transform-origin:0 0;transition:transform .18s ease}
  .oc-root{display:flex;flex-direction:column;align-items:center}
  .oc-node{display:flex;flex-direction:column;align-items:center;position:relative}

  .oc-box{width:238px;display:flex;align-items:center;gap:9px;background:#fff;
    border:1.5px solid var(--c,#005a5a);border-radius:12px;padding:8px 10px;
    position:relative;z-index:1;cursor:pointer;transition:box-shadow .18s,transform .18s,border-color .18s}
  .oc-box:hover,.oc-box:focus{box-shadow:0 8px 20px rgba(0,90,90,.16);transform:translateY(-2px);
    border-color:#005a5a;border-width:2.5px;outline:none}
  .oc-box.oc-leaf{width:224px;border-width:1px;background:#fdfefe}
  /* Company root: the single node the whole organisation hangs from. */
  .oc-box.oc-org{width:270px;background:linear-gradient(120deg,#005a5a,#0d9488);
    border-color:#00403f;cursor:default;padding:11px 13px}
  .oc-box.oc-org b{color:#fff;font-size:.92rem}
  .oc-box.oc-org i{color:rgba(255,255,255,.75)}
  .oc-box.oc-org .oc-av{background:rgba(255,255,255,.2);font-size:.72rem}
  .oc-box.oc-org:hover{transform:none;box-shadow:0 8px 22px rgba(0,90,90,.25)}
  .oc-av{width:30px;height:30px;flex:0 0 30px;border-radius:50%;background:var(--c,#005a5a);
    color:#fff;display:grid;place-items:center;font-size:.62rem;font-weight:700}
  .oc-av.lg{width:38px;height:38px;flex:0 0 38px;font-size:.74rem}
  .oc-txt{min-width:0;flex:1}
  .oc-box b{display:block;font-size:.79rem;font-weight:650;color:#0f172a;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .oc-box i{display:block;font-style:normal;font-size:.68rem;color:#64748b;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .oc-box u{display:block;text-decoration:none;font-size:.61rem;font-weight:700;color:var(--c,#005a5a);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .oc-cnt{position:absolute;top:-9px;right:-9px;background:#005a5a;color:#fff;font-size:.6rem;
    font-weight:700;padding:1px 8px;border-radius:20px;border:2px solid #fff}

  /* Designation/department text (.oc-box i/u) was under 11px -- every
     person in the chart renders both, which is why this was flagged on
     ~271 elements. Raised to the audit's 11px floor for this dense a
     chart; the name (.oc-box b) was already above it. */
  @media (max-width:480px){
    .oc-box i, .oc-box u { font-size:.7rem; }
  }
  .oc-cnt em{font-style:normal;opacity:.55;margin:0 1px}

  .oc-team{position:relative}
  .oc-team-i{position:relative;min-width:0}

  /* ---- MID-BRANCH team: compact horizontal block, so the sub-branches
         below stay aligned under their parent. ---- */
  .oc-team-grid{margin-top:26px;padding:15px 12px 11px;
    border:1.5px dashed #cbd8e0;border-radius:12px;background:#fbfdfe;
    display:grid;gap:7px;grid-template-columns:repeat(var(--cols,3),minmax(0,1fr))}
  .oc-team-grid::before{content:'';position:absolute;top:-26px;left:50%;
    width:2px;height:26px;background:#cbd8e0}
  .oc-team-grid > .oc-team-lbl{position:absolute;top:-10px;left:14px;
    background:#fbfdfe;padding:0 7px;font-size:.6rem;font-weight:800;
    letter-spacing:.06em;text-transform:uppercase;color:#94a3b8}

  /* ---- LAST NODE team: stacked one below another off a spine on the LEFT
         (file-tree style), inside the same dashed box the grid teams use so
         both layouts read as one consistent component. ---- */
  .oc-team-stack{margin-top:26px;padding:15px 12px 12px 36px;
    border:1.5px dashed #cbd8e0;border-radius:12px;background:#fbfdfe;
    display:flex;flex-direction:column;gap:6px;align-self:center}
  /* stem from the parent's centre down to the top of the box */
  .oc-team-stack::before{content:'';position:absolute;top:-26px;left:50%;
    width:2px;height:26px;background:#cbd8e0}
  .oc-team-stack > .oc-team-lbl{position:absolute;top:-10px;left:14px;
    background:#fbfdfe;padding:0 7px;font-size:.6rem;font-weight:800;
    letter-spacing:.06em;text-transform:uppercase;color:#94a3b8}
  /* vertical spine running down inside the box */
  .oc-team-stack > .oc-team-i::before{content:'';position:absolute;left:-20px;
    top:0;bottom:0;width:2px;background:#cbd8e0}
  .oc-team-stack > .oc-team-i:first-child::before{top:-12px}
  /* horizontal tick into each card */
  .oc-team-stack > .oc-team-i::after{content:'';position:absolute;left:-20px;
    top:50%;width:16px;height:2px;background:#cbd8e0}
  /* spine stops at the last row's centre, giving the closing elbow */
  .oc-team-stack > .oc-team-i:last-child::before{bottom:50%}

  /* manager children branch sideways */
  .oc-branch{display:flex;justify-content:center;align-items:flex-start;gap:28px;
    padding-top:32px;position:relative}
  .oc-branch::before{content:'';position:absolute;top:0;left:50%;width:2px;height:16px;background:#cbd8e0}
  .oc-branch>.oc-node{padding-top:16px;position:relative}
  .oc-branch>.oc-node::before{content:'';position:absolute;top:0;left:0;width:100%;height:2px;background:#cbd8e0}
  .oc-branch>.oc-node::after{content:'';position:absolute;top:0;left:50%;width:2px;height:16px;background:#cbd8e0}
  .oc-branch>.oc-node:first-child::before{left:50%;width:50%}
  .oc-branch>.oc-node:last-child::before{left:0;width:50%}
  .oc-branch>.oc-node:only-child::before{display:none}

  .oc-box.oc-dim{opacity:.15}
  .oc-box.oc-hit{outline:3px solid #7c3aed;outline-offset:2px}

  .oc-legend{display:flex;flex-wrap:wrap;gap:7px 15px;padding:13px 18px;border-top:1px solid #eef2f6}
  .oc-lg{display:flex;align-items:center;gap:5px;font-size:.68rem;color:#64748b}
  .oc-lg i{width:9px;height:9px;border-radius:2px;display:inline-block}

  /* ---------- Details Drawer (unchanged look) ---------- */
  .details-drawer{position:fixed;top:120px;right:30px;width:320px;
    background:rgba(255,255,255,.9);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,.3);border-radius:24px;z-index:1050;
    box-shadow:0 18px 44px rgba(15,23,42,.18);
    animation:slideInRight .4s cubic-bezier(.16,1,.3,1)}
  @keyframes slideInRight{from{transform:translateX(50px);opacity:0}to{transform:translateX(0);opacity:1}}
  .status-dot{position:absolute;bottom:5px;right:5px;width:15px;height:15px;
    border:3px solid #fff;border-radius:50%}
  .status-dot.online{background:#10b981}
  .info-item .icon-wrap{width:38px;height:38px;display:flex;align-items:center;justify-content:center}

  .oc-scroller:fullscreen{background:#fff;padding:40px}

  @media print{
    .oc-toolbar,.details-drawer,#fullscreen-toggle{display:none!important}
    .oc-scroller{overflow:visible}
  }
</style>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var chart    = document.getElementById('ocChart');
    var scroller = document.getElementById('ocScroller');
    if (!chart || !scroller) return;

    /* ---------- zoom ---------- */
    var zoom = 1;
    var zoomVal = document.getElementById('ocZoomVal');

    // Cap the viewport height so the chart is a pannable window rather than
    // growing to fit its content. Without a cap there is no vertical overflow,
    // so dragging up/down had nothing to scroll — only left/right worked.
    function viewportCap() {
        return document.fullscreenElement
            ? window.innerHeight - 40
            : Math.max(420, Math.round(window.innerHeight * 0.74));
    }

    function applyZoom(z) {
        // Floor of 0.25 — below that the cards are unreadable smudges, so a
        // very wide org "fits" to 25% and scrolls rather than vanishing.
        zoom = Math.min(1.4, Math.max(0.25, z));
        chart.style.transform = 'scale(' + zoom + ')';
        var content = chart.scrollHeight * zoom + 60;
        scroller.style.height = Math.min(content, viewportCap()) + 'px';
        if (zoomVal) { zoomVal.textContent = Math.round(zoom * 100) + '%'; }
        return zoom;
    }

    window.addEventListener('resize', function () { applyZoom(zoom); });
    document.addEventListener('fullscreenchange', function () { applyZoom(zoom); });

    /**
     * Zoom keeping the point under the cursor fixed, so Ctrl+scroll magnifies
     * whatever you are pointing at instead of drifting to a corner.
     * transform-origin is 0 0, so chart coords = (scroll + offset) / zoom.
     */
    function zoomAt(clientX, clientY, target) {
        var box = scroller.getBoundingClientRect();
        var ox = clientX - box.left, oy = clientY - box.top;
        var cx = (scroller.scrollLeft + ox) / zoom;
        var cy = (scroller.scrollTop + oy) / zoom;
        var z  = applyZoom(target);
        scroller.scrollLeft = cx * z - ox;
        scroller.scrollTop  = cy * z - oy;
    }

    function fit() {
        applyZoom((scroller.clientWidth - 30) / chart.scrollWidth);
        scroller.scrollLeft = 0;
    }

    // Ctrl/Cmd + wheel zooms the chart. preventDefault stops the browser's own
    // page zoom taking over; without the passive:false option the browser
    // ignores that and zooms the whole page instead.
    var pulseTimer = null;
    scroller.addEventListener('wheel', function (e) {
        if (!e.ctrlKey && !e.metaKey) return;   // plain scroll still scrolls
        e.preventDefault();
        var step = e.deltaY < 0 ? 1.12 : 1 / 1.12;
        zoomAt(e.clientX, e.clientY, zoom * step);

        if (zoomVal) {
            zoomVal.classList.remove('pulse');
            void zoomVal.offsetWidth;           // restart the animation
            zoomVal.classList.add('pulse');
            clearTimeout(pulseTimer);
            pulseTimer = setTimeout(function () { zoomVal.classList.remove('pulse'); }, 500);
        }
    }, { passive: false });
    function centreOnRoot() {
        var first = chart.querySelector('.oc-root .oc-box');
        if (!first) return;
        var r = first.getBoundingClientRect(), s = scroller.getBoundingClientRect();
        scroller.scrollLeft += (r.left - s.left) - (s.width / 2 - r.width / 2);
    }
    document.getElementById('ocIn').onclick  = function () { applyZoom(zoom * 1.25); };
    document.getElementById('ocOut').onclick = function () { applyZoom(zoom / 1.25); };
    document.getElementById('ocFit').onclick = fit;
    document.getElementById('oc100').onclick = function () { applyZoom(1); centreOnRoot(); };

    applyZoom(1);
    centreOnRoot();

    /* ---------- drag to pan (grab anywhere and move the chart) ---------- */
    var dragging = false, moved = false, sx = 0, sy = 0, sLeft = 0, sTop = 0;

    scroller.addEventListener('mousedown', function (e) {
        if (e.button !== 0) return;               // left button only
        dragging = true; moved = false;
        sx = e.clientX; sy = e.clientY;
        sLeft = scroller.scrollLeft; sTop = scroller.scrollTop;
    });

    window.addEventListener('mousemove', function (e) {
        if (!dragging) return;
        var dx = e.clientX - sx, dy = e.clientY - sy;
        // Small threshold so a normal click on a card still behaves as a click.
        if (!moved && Math.abs(dx) + Math.abs(dy) > 4) {
            moved = true;
            scroller.classList.add('oc-grabbing');
        }
        if (!moved) return;
        e.preventDefault();
        scroller.scrollLeft = sLeft - dx;         // drag left/right
        scroller.scrollTop  = sTop  - dy;         // drag up/down
    });

    function endDrag() {
        if (!dragging) return;
        dragging = false;
        scroller.classList.remove('oc-grabbing');
    }
    window.addEventListener('mouseup', endDrag);
    window.addEventListener('mouseleave', endDrag);
    scroller.addEventListener('dragstart', function (e) { e.preventDefault(); });

    /* ---------- hover drawer (same behaviour as the old chart) ---------- */
    var popover = document.getElementById('userCardPopover');
    var hideTimer = null;

    function showUserCard(el) {
        clearTimeout(hideTimer);
        var d = el.dataset;
        document.getElementById('pop-name').innerText        = d.name || 'Unknown';
        document.getElementById('pop-designation').innerText = d.designation || 'Corporate Root';
        document.getElementById('pop-dept').innerText        = d.department || 'Headquarters';
        document.getElementById('pop-email').innerText       = d.email || '—';
        document.getElementById('pop-phone').innerText       = d.phone || 'N/A';

        var img = document.getElementById('pop-img');
        img.src = (d.img && d.img !== 'null' && d.img !== '')
            ? d.img
            : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(d.name || '') + '&background=005a5a&color=fff';

        var btn = document.getElementById('pop-view-profile');
        btn.onclick = function () {
            if (d.id) window.location.href = "{{ url('/employees/view') }}/" + d.id;
        };

        popover.classList.remove('d-none');
    }

    chart.addEventListener('mouseover', function (e) {
        var box = e.target.closest('.oc-box');
        if (box) showUserCard(box);
    });
    chart.addEventListener('focusin', function (e) {
        var box = e.target.closest('.oc-box');
        if (box) showUserCard(box);
    });
    chart.addEventListener('mouseleave', function () {
        hideTimer = setTimeout(function () { popover.classList.add('d-none'); }, 400);
    });
    popover.addEventListener('mouseenter', function () { clearTimeout(hideTimer); });
    popover.addEventListener('mouseleave', function () { popover.classList.add('d-none'); });

    /* ---------- search ---------- */
    var q = document.getElementById('ocSearch'), boxes = chart.querySelectorAll('.oc-box'), t;
    q.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(function () {
            var v = q.value.trim().toLowerCase();
            boxes.forEach(function (b) { b.classList.remove('oc-dim', 'oc-hit'); });
            if (!v) return;
            var first = null;
            boxes.forEach(function (b) {
                if ((b.dataset.s || '').indexOf(v) === -1) {
                    b.classList.add('oc-dim');
                } else {
                    b.classList.add('oc-hit');
                    if (!first) first = b;
                }
            });
            if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        }, 130);
    });

    /* ---------- fullscreen ---------- */
    var fsBtn = document.getElementById('fullscreen-toggle');
    if (fsBtn) {
        fsBtn.onclick = function () {
            if (!document.fullscreenElement) {
                if (scroller.requestFullscreen) scroller.requestFullscreen();
            } else if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        };
    }
});
</script>
@endsection
