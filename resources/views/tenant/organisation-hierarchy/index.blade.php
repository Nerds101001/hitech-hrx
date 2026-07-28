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
                    <div class="icon-wrap bg-light rounded-pill p-2"><i class="bx bx-barcode-reader text-muted"></i></div>
                    <div class="content"><div class="label small text-muted">Employee Code</div><div id="pop-code" class="value fw-bold small">...</div></div>
                </div>
                <div class="info-item d-flex align-items-center gap-3">
                    <div class="icon-wrap bg-light rounded-pill p-2"><i class="bx bx-envelope text-muted"></i></div>
                    <div class="content"><div class="label small text-muted">Email</div><div id="pop-email" class="value fw-bold small">...</div></div>
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

  /* ---------- chart ---------- */
  .oc-scroller{overflow:auto;padding:26px 10px 30px;background:linear-gradient(#fbfdfe,#fff)}
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
  .oc-cnt em{font-style:normal;opacity:.55;margin:0 1px}

  /* team block: compact grid, never a long column */
  .oc-team{position:relative;margin-top:26px;padding:15px 12px 11px;
    border:1.5px dashed #cbd8e0;border-radius:12px;background:#fbfdfe;
    display:grid;gap:7px;grid-template-columns:repeat(var(--cols,3),minmax(0,1fr))}
  .oc-team::before{content:'';position:absolute;top:-26px;left:50%;width:2px;height:26px;background:#cbd8e0}
  .oc-team-lbl{position:absolute;top:-10px;left:14px;background:#fbfdfe;padding:0 7px;
    font-size:.6rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#94a3b8}
  .oc-team-i{min-width:0}

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
    function applyZoom(z) {
        // Floor of 0.25 — below that the cards are unreadable smudges, so a
        // very wide org "fits" to 25% and scrolls rather than vanishing.
        zoom = Math.min(1.4, Math.max(0.25, z));
        chart.style.transform = 'scale(' + zoom + ')';
        scroller.style.height = (chart.scrollHeight * zoom + 60) + 'px';
    }
    function fit() {
        applyZoom((scroller.clientWidth - 30) / chart.scrollWidth);
        scroller.scrollLeft = 0;
    }
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

    /* ---------- hover drawer (same behaviour as the old chart) ---------- */
    var popover = document.getElementById('userCardPopover');
    var hideTimer = null;

    function showUserCard(el) {
        clearTimeout(hideTimer);
        var d = el.dataset;
        document.getElementById('pop-name').innerText        = d.name || 'Unknown';
        document.getElementById('pop-designation').innerText = d.designation || 'Corporate Root';
        document.getElementById('pop-dept').innerText        = d.department || 'Headquarters';
        document.getElementById('pop-code').innerText        = d.code || 'N/A';
        document.getElementById('pop-email').innerText       = d.email || '—';

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
