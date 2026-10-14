@extends('layouts/layoutMaster')

@section('title', 'Digital Library')

@section('page-style')
<style>
    :root {
        /* --hitech-primary is already defined app-wide (hitech-portal.scss,
           #008080) — this page was silently redeclaring it as #00897b,
           a slightly different teal nobody chose on purpose, which is why
           this page's accent never quite matched the rest of the app. */
        --hitech-dark: #0d3b33;
        --hitech-bg: #f8fafb;
    }

    body { background-color: var(--hitech-bg); font-family: 'Public Sans', sans-serif; }
    
    /* Navigation Bar — values matched to the frontend-preview mockup: full
       pill tabs, light-tint active state (same treatment side-nav-item.active
       already uses), not a solid fill. */
    .nav-filters .nav-link { padding: 7px 13px; border-radius: 999px; color: #94a3b8; font-weight: 700; font-size: 0.78rem; transition: all 0.2s; border: 1px solid transparent; }
    .nav-filters .nav-link:hover { background: #f0f2f5; }
    .nav-filters .nav-link.active { background: var(--hitech-primary-light); color: var(--hitech-primary) !important; }

    .search-input-wrapper { background: #f8fafb; border-radius: 999px; padding: 8px 16px; display: flex; align-items: center; min-width: 200px; border: 1px solid #eef2f4; }
    .search-input-wrapper input { border: none; background: transparent; font-size: 0.78rem; padding-left: 10px; width: 100%; outline: none; }

    /* One card, three flush panels: the outer wrapper owns the rounded
       corners, border and shadow; banner, type row and brand row are flush
       panels inside it, not separate boxes with gaps between them. */
    /* overflow:hidden here (for the flush-panel rounded corners) was also
       clipping the brand dropdown's popup at the card's edge instead of
       letting it float above the content. Round the first/last child
       directly instead, so the parent no longer needs to clip overflow. */
    .vault-header-card { background: white; border-radius: 16px; border: 1px solid #eef2f4; box-shadow: 0 1px 2px rgba(0,0,0,0.02); margin-bottom: 2rem; }
    .vault-header-card > :first-child { border-radius: 16px 16px 0 0; }
    .vault-header-card > :last-child { border-radius: 0 0 16px 16px; }
    .library-banner { background: linear-gradient(120deg, var(--hitech-primary), var(--hitech-dark)); padding: 26px 28px; color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
    /* A global heading-color reset elsewhere applies directly to h1
       elements — inheriting .library-banner's white doesn't beat that,
       since a direct rule always wins over an inherited one regardless of
       specificity. Without this, the title rendered dark-on-dark. */
    .banner-content h1 { font-size: 1.25rem; font-weight: 800; margin-bottom: 4px; color: #fff; }
    .banner-content p { opacity: 0.85; font-size: 0.8rem; margin-bottom: 0; }
    .banner-btns { display: flex; gap: 12px; flex-wrap: wrap; }
    /* hitech-portal.scss repurposes .btn-white app-wide into the same
       transparent/outline treatment as .btn-secondary (presumably correct
       for its other, light-background uses) — that silently wins over
       Bootstrap's bg-white/text-dark utilities via !important, leaving "AI
       Bulk Upload" a near-invisible teal-on-teal ghost against this dark
       banner. Scoped, not global: only this banner needs an actual solid
       white pill. */
    .banner-btns .btn-white { background: #fff !important; color: #1e293b !important; border-color: #fff !important; }
    .nav-row { border-top: 1px solid #eef2f4; flex-wrap: wrap; row-gap: 10px; }
    .nav-row > .d-flex.align-items-center:first-child { flex-wrap: wrap; row-gap: 8px; }
    /* Base rule had wrap only on .brand-row's own nav-filters; the type-row
       (All Files/SDS/TDS/...) never got it, so above ~767.98px (where the
       one existing media query stops applying) the heading + all 7 tabs
       stayed on one unbroken line and got cut off. */
    .nav-filters { flex-wrap: wrap; }

    /* Phone-only brand dropdown — hidden on desktop, where the filter-tab
       row is the real picker. */
    .brand-select-mobile { display: none; }
    .brand-select-mobile select {
        width: 100%; font: inherit; font-size: 0.85rem; font-weight: 700; color: #333;
        background: #f5f6f8; border: 1px solid #eef2f4; border-radius: 12px;
        height: 44px; padding: 0 14px; margin-bottom: 16px;
    }

    /* Brand filter-tabs — second bar, same .nav-link pill as the type row
       above it (full pill, light-tint active state). */
    .brand-row .nav-filters { flex-wrap: wrap; }
    .brand-tab-add { color: var(--hitech-primary); border: 1px dashed #cfd8dc !important; }
    .brand-tab-add:hover { background: var(--hitech-primary-light); }
    .brand-tab.drag-over { background: #fff3e0 !important; border-color: #ff7043 !important; color: #ff7043 !important; }
    .brand-tab-dot { border: none; background: rgba(0,0,0,0.06); color: inherit; width: 18px; height: 18px; border-radius: 50%; font-size: 0.7rem; line-height: 1; padding: 0; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .brand-tab-dot:hover { background: rgba(0,0,0,0.12); }

    .action-dot {
        width: 30px; height: 30px; display: flex; flex-direction: column; gap: 3px; align-items: center; justify-content: center; 
        cursor: pointer; transition: all 0.2s; border: none; background: transparent; padding: 0;
    }
    .action-dot span { 
        width: 4px; height: 4px; background: #000; border-radius: 50%; display: block;
    }
    .action-dot:hover { background: rgba(0,0,0,0.05); border-radius: 50%; }
    
    .dropdown-menu-custom { 
        border: none; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.12); 
        padding: 8px; min-width: 160px; font-size: 0.8rem; border: 1px solid #f0f0f0;
    }
    .dropdown-item-custom { 
        display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; 
        font-weight: 700; color: #444; transition: all 0.2s;
    }
    .dropdown-item-custom:hover { background: #f8fafb; color: var(--hitech-primary); }
    .dropdown-item-custom.text-danger:hover { background: #fff5f5; color: #dc3545; }

    /* Matrix Layout */
    .main-grid { display: grid; grid-template-columns: 220px 1fr; gap: 22px; }
    .main-grid.no-sidebar { grid-template-columns: 1fr; }
    .brand-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid #eef2f4; }
    .brand-header-dot { width: 13px; height: 13px; border-radius: 50%; }
    .brand-header h2 { font-weight: 800; margin: 0; font-size: 1.4rem; color: #333; }
    .stats-badge { background: var(--hitech-primary-light); color: var(--hitech-primary); font-size: 0.68rem; padding: 4px 13px; border-radius: 20px; font-weight: 800; }

    .side-navigation { background: white; border-radius: 14px; padding: 8px; border: 1px solid #eef2f4; align-self: start; }
    .side-nav-item { padding: 10px 13px; border-radius: 10px; margin-bottom: 3px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: #777; font-weight: 700; font-size: 0.8rem; transition: all 0.2s; text-decoration: none; }
    .side-nav-item:hover { background: #f8fafb; color: #333; }
    .side-nav-item.active { background: var(--hitech-primary-light); color: var(--hitech-primary); }
    .side-nav-item.drag-over { background: #b2dfdb; border-left: 4px solid var(--hitech-primary); }
    .side-nav-item .count { background: #f0f2f5; padding: 1px 7px; border-radius: 8px; font-size: 0.62rem; color: #999; }
    .side-nav-item.active .count { background: #fff; color: var(--hitech-primary); }

    /* View Toggle */
    .view-switcher { display: flex; background: #f0f2f5; padding: 4px; border-radius: 10px; gap: 4px; }
    .view-btn { border: none; background: transparent; color: #999; padding: 6px 14px; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 0.75rem; font-weight: 800; display: flex; align-items: center; gap: 6px; }
    .view-btn.active { background: white; color: var(--hitech-primary); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }

    /* Pagination */
    .vault-pagination { border-top: 1px solid #eef2f4; }
    .page-btn { text-decoration: none; background: white; border: 1px solid #eef2f4; min-width: 34px; height: 34px; padding: 0 10px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color: #666; font-size: 0.78rem; font-weight: 800; transition: all 0.2s; }
    .page-btn:hover:not(.disabled) { border-color: var(--hitech-primary); color: var(--hitech-primary); }
    .page-btn.active { background: var(--hitech-primary); color: white; border-color: var(--hitech-primary); }
    .page-btn.disabled { opacity: 0.35; pointer-events: none; }

    /* Cards */
    .product-card { background: white; border: 1px solid #eef2f4; border-radius: 16px; height: 100%; display: flex; flex-direction: column; overflow: hidden; transition: all 0.2s; position: relative; cursor: grab; }
    .product-card.dragging { opacity: 0.5; border: 2px dashed #ccc; }
    .product-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.06); border-color: #d1d9e0; }
    /* Reserve the top-right corner (badge width + clearance) so the title
       never runs underneath it; .product-title-area had no CSS of its own
       at all (only the markup's .overflow-hidden utility), so the flex
       item sized to its text content instead of being bounded, and
       text-truncate had nothing to truncate against. */
    .card-main-content { padding: 20px 86px 14px 16px; flex-grow: 1; }
    .product-header { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 14px; }
    .product-icon-box { width: 38px; height: 38px; flex-shrink: 0; background: #fff8f8; border: 1px solid #ffebee; border-radius: 9px; display: flex; align-items: center; justify-content: center; }
    .product-title-area { flex: 1; min-width: 0; }
    .product-title { font-weight: 800; color: #333; font-size: 0.88rem; margin-bottom: 3px; line-height: 1.25; }
    .product-subtitle { font-size: 0.68rem; color: #aaa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; }
    .card-top-badge { position: absolute; right: 13px; top: 13px; max-width: 70px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.6rem; font-weight: 900; color: var(--hitech-primary); background: var(--hitech-primary-light); padding: 3px 10px; border-radius: 6px; z-index: 1; letter-spacing: 0.6px; }
    .card-summary { font-size: 0.72rem; color: #666; line-height: 1.55; margin: 0 0 14px; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; font-style: italic; min-height: 68px; }
    .action-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .action-btn { border: 1px solid #f0f2f5; border-radius: 9px; padding: 8px 2px; text-align: center; text-decoration: none; transition: all 0.2s; display: flex; flex-direction: column; align-items: center; gap: 3px; }
    .action-btn:hover:not(.disabled) { background: #f8fafb; border-color: #ddd; }
    .action-btn span:first-child { font-size: 0.62rem; font-weight: 900; color: #444; }
    .action-btn span:last-child { font-size: 0.6rem; color: #bbb; font-weight: 700; }
    .action-btn.disabled { opacity: 0.3; cursor: not-allowed; background: #fafafa; border-style: dashed; }
    /* The video/brand tiers get ONE full-width button instead of the
       4-column grid, sharing .action-btn for the border/radius/hover — but
       its narrow "2px horizontal padding + 0.62rem label" sizing was tuned
       for a quarter-width grid cell, not a full-width primary action. It
       rendered as ~10px text nearly touching the pill's own edges.
       min-height: 38px matches hitech-portal.scss's real .btn min-height
       used app-wide, instead of guessing a padding value that happens to
       add up close to it — padding alone drifted 7px over on its own. */
    .action-btn-wide { grid-column: span 4; flex-direction: row; align-items: center; justify-content: center; gap: 6px; padding: 6px 12px; min-height: 38px; border-radius: 12px; }
    .action-btn-wide span:first-child { font-size: 0.8rem; font-weight: 800; color: inherit; }
    .action-btn-wide i { font-size: 1rem !important; }
    /* Play is a primary action, not a passive "currently selected" state —
       it gets the app's real solid-fill button treatment (matching e.g.
       Add Employee), not the light-tint used for active tabs/cards
       elsewhere on this page. Those are two different UI roles. */
    .action-btn-primary { background: var(--hitech-primary) !important; border-color: var(--hitech-primary) !important; }
    .action-btn-primary span { color: #fff !important; }
    .action-btn-primary:hover { background: #006666 !important; }
    /* Gap ABOVE the button row (video/brand tiers), not the button's own
       padding — the thumbnail/summary above it sits right up against the
       button otherwise, with no breathing room between the two. */
    .action-row-wide { margin-top: 14px; }
    /* .align-items-start on the header computes to align-items:center
       anyway — something else wins that fight. Since .modal-title's own
       row uses w-100, it fills the row regardless and the misalignment
       never showed; the narrower presenter/date row has no w-100, so
       "center" shrink-wraps it into the middle of the header instead of
       flush under the title, floating disconnected from what it's
       describing. */
    #videoPlayerModal .modal-header { align-items: flex-start !important; }
    #videoPlayerMeta { width: 100%; }
    .card-footer { padding: 11px 16px; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; border-top: 1px solid #f8fafb; }
    .file-size { font-size: 0.68rem; color: #bbb; font-weight: 700; }

    /* List View mode */
    .list-view-item { background: white; border: 1px solid #eef2f4; border-radius: 14px; padding: 15px 25px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s; }
    .list-view-item:hover { border-color: var(--hitech-primary); transform: translateX(5px); }
    .list-item-main { display: flex; align-items: center; gap: 20px; }
    .list-item-title { font-weight: 800; font-size: 0.95rem; color: #333; }
    .list-item-meta { font-size: 0.75rem; color: #aaa; font-weight: 600; }
    .list-item-actions { display: flex; gap: 12px; }
    .action-pill { padding: 5px 15px; border-radius: 20px; background: #f5f7f9; font-size: 0.65rem; font-weight: 800; color: #666; text-decoration: none; border: 1px solid transparent; transition: all 0.2s; }
    .action-pill:hover:not(.disabled) { border-color: var(--hitech-primary); color: var(--hitech-primary); background: var(--hitech-primary-light); }
    .action-pill.disabled { opacity: 0.3; cursor: not-allowed; }

    /* Floating Add Document button — always reachable without scrolling
       back up to the banner */
    .vault-fab {
        position: fixed; bottom: 26px; right: 26px; z-index: 1030;
        background: var(--hitech-primary); color: #fff; border: none;
        padding: 13px 22px; border-radius: 999px; font-weight: 800; font-size: 0.82rem;
        box-shadow: 0 10px 24px -6px rgba(0,137,123,0.5); cursor: pointer;
        display: flex; align-items: center; gap: 8px; text-decoration: none;
    }
    .vault-fab:hover { background: #00695c; color: #fff; }
    /* Reserve room so the fixed button never sits on top of real content —
       matters most on a short page (e.g. a brand with only 1-2 products). */
    .container-xxl { padding-bottom: 90px; }

    /* ============ Responsive ============ */
    @media (max-width: 991.98px) {
        .main-grid { grid-template-columns: 200px 1fr; gap: 16px; }
    }

    @media (max-width: 767.98px) {
        .container-xxl { padding-left: 12px; padding-right: 12px; }

        .vault-header-card { border-radius: 14px; }
        .library-banner { padding: 18px 20px; }
        .banner-content h1 { font-size: 1.25rem; }
        .banner-btns { width: 100%; }
        .banner-btns .btn { flex: 1; }

        /* Nav row: title above, tabs scroll horizontally instead of wrapping
           into a tall multi-row block, search moves to its own full row.
           min-width: 0 is load-bearing here — without it, a flex/grid child
           with overflow-x:auto still won't shrink below its content's
           natural width, so it drags every ancestor's width out with it
           instead of scrolling in place (the actual cause of the page-wide
           horizontal scroll this was meant to prevent). */
        .nav-row { flex-direction: column; align-items: stretch !important; padding: 12px; }
        .nav-row > div:first-child { width: 100%; min-width: 0; flex-wrap: nowrap; overflow-x: auto; padding-bottom: 4px; -webkit-overflow-scrolling: touch; }
        .nav-row h5 { margin-bottom: 8px; margin-right: 0 !important; }
        .nav-filters { flex-wrap: nowrap !important; min-width: 0; }
        .nav-filters .nav-link { white-space: nowrap; }
        .search-input-wrapper { width: 100%; margin-top: 10px; }

        /* A 7-tab row still needs horizontal scrolling to fit on a phone —
           swap it for the native dropdown built alongside it instead.
           !important: the row carries Bootstrap's d-flex utility, itself
           !important in this app's build, so a plain display:none loses. */
        .brand-row { display: none !important; }
        .brand-select-mobile { display: block; }

        /* "Fixed" here meant literally CSS position:fixed — a floating
           button parked over page content while scrolling. On a phone it
           becomes a normal button at the end of the page instead; the
           banner already carries its own Add Document entry point up top. */
        .vault-fab { position: static; width: 100%; justify-content: center; margin: 20px 0 0; box-shadow: none; }
        .container-xxl { padding-bottom: 20px; }

        /* Sidebar stacks above the product grid instead of sitting beside it —
           240px next to card content has no room to breathe on a phone.
           minmax(0, 1fr), not bare 1fr: a plain 1fr track still sizes itself
           to fit its content's minimum width, which is exactly the bug this
           rule exists to prevent (the sidebar's un-wrapped category chips
           are ~2300px wide; minmax(0, ...) is what actually caps the track). */
        .main-grid { grid-template-columns: minmax(0, 1fr); }
        .side-navigation { display: flex; width: 100%; min-width: 0; overflow-x: auto; gap: 6px; padding: 8px; -webkit-overflow-scrolling: touch; }
        .side-navigation hr { display: none; }
        .side-nav-item { flex: 0 0 auto; white-space: nowrap; margin-bottom: 0; }

        .brand-header { flex-wrap: wrap; gap: 12px; }
        .brand-header h2 { font-size: 1.2rem; }

        .action-row { gap: 6px; }
        .action-btn { padding: 8px 2px; }
        .action-btn span:first-child { font-size: 0.68rem; }

        .vault-fab { padding: 12px 18px; font-size: 0.78rem; }

        .vault-pagination { gap: 4px; }
        .page-btn { min-width: 30px; height: 30px; padding: 0 8px; }
    }

    @media (max-width: 480px) {
        .action-row { grid-template-columns: repeat(2, 1fr); row-gap: 6px; }
    }
</style>
@endsection

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('content')
<div class="container-xxl">
    @php
        /* Upload / creation controls. Admin and HR qualify by role, and so
           does anyone granted vault.upload individually under Roles &
           Permissions → User Special Permissions.

           This used to check the role alone. The routes behind these
           buttons have always accepted vault.upload, so the permission was
           real on the server but invisible in the UI: a manager granted it
           saw no Add Document / Bulk Upload button and reasonably concluded
           the grant had not worked. */
        $vaultCanManage = auth()->user()->hasRole(['admin','Admin','hr','HR'])
            || auth()->user()->can('vault.upload');
    @endphp

    {{-- One card, three flush panels: banner, document-type row, brand row. --}}
    <div class="vault-header-card">
        <div class="library-banner">
            <div class="banner-content">
                <h1>Intelligent Vault</h1>
                <p>Centralised technical archive for every brand.</p>
            </div>
            @if($vaultCanManage)
            <div class="banner-btns">
                <button class="btn btn-white bg-white text-dark fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">AI Bulk Upload</button>
                <button class="btn btn-outline-white border-white text-white rounded-pill px-4" onclick="openAddDocumentModal('{{ request('category') }}')">Add Document</button>
            </div>
            @endif
        </div>

        <div class="nav-row d-flex justify-content-between align-items-center p-3">
            <div class="d-flex align-items-center">
                <h5 class="fw-bold mb-0 me-4 text-dark">Intelligent Vault</h5>
                <div class="nav-filters d-flex gap-1">
                    <a href="{{ route('library.index') }}" class="nav-link {{ !request()->has('category') ? 'active' : '' }}">All Files</a>
                    <a href="{{ route('library.index', ['category' => 'SDS']) }}" class="nav-link {{ request('category') === 'SDS' ? 'active' : '' }}">SDS</a>
                    <a href="{{ route('library.index', ['category' => 'TDS']) }}" class="nav-link {{ request('category') === 'TDS' ? 'active' : '' }}">TDS</a>
                    <a href="{{ route('library.index', ['category' => 'Test Report']) }}" class="nav-link {{ request('category') === 'Test Report' ? 'active' : '' }}">🧪 Test Report</a>
                    <a href="{{ route('library.index', ['category' => 'Presentation']) }}" class="nav-link {{ request('category') === 'Presentation' ? 'active' : '' }}">Presentation</a>
                    <a href="{{ route('library.index', ['category' => 'Comparison Report']) }}" class="nav-link {{ request('category') === 'Comparison Report' ? 'active' : '' }}">📊 Comparison Report</a>
                    <a href="{{ route('library.index', ['category' => 'Video']) }}" class="nav-link {{ request('category') === 'Video' ? 'active' : '' }}">Video</a>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="search-input-wrapper">
                    <i class="bx bx-search text-muted"></i>
                    <input type="text" placeholder="Search technical assets..." id="librarySearch">
                </div>
            </div>
        </div>

        @if($tier !== 'video')
        {{-- Second bar, same treatment as the type row above it — brand
             selection as filter-tabs, not a separate card grid below the
             card. Phone gets a native <select> instead via CSS toggle: a
             7-tab row still needs horizontal scrolling to fit, a dropdown
             doesn't. --}}
        <div class="nav-row brand-row d-flex align-items-center p-3">
            <div class="nav-filters d-flex gap-1 flex-wrap">
                @foreach($brands as $b)
                    @php
                        $slug = \Illuminate\Support\Str::slug($b->name);
                        $isActive = $activeBrand && $activeBrand->id === $b->id;
                    @endphp
                    {{-- A real <a> can't wrap the dropdown's own <a> items
                         (invalid, nested-anchor HTML — browsers silently
                         split the outer tag), so this is a div navigated
                         via onclick instead. --}}
                    <div class="nav-link brand-tab {{ $isActive ? 'active' : '' }}"
                         onclick="if (!event.target.closest('.dropdown')) window.location.href = '{{ route('library.index', ['category' => $category, 'brand' => $slug]) }}';"
                         ondragover="handleDragOver(event)"
                         ondragleave="handleDragLeave(event)"
                         ondrop="handleBrandDrop(event, '{{ $b->name }}')">
                        {{ $b->name }}
                        @if($isActive)
                        <div class="dropdown d-inline-block ms-1">
                            <button class="brand-tab-dot" data-bs-toggle="dropdown" aria-expanded="false">⋮</button>
                            <div class="dropdown-menu dropdown-menu-custom shadow">
                                <a class="dropdown-item dropdown-item-custom" href="javascript:void(0)" onclick="editTaxonomy('brand', {{ $b->id }})">
                                    <i class="bx bx-edit fs-6"></i> Rename Brand
                                </a>
                                <div class="dropdown-divider opacity-10"></div>
                                <a class="dropdown-item dropdown-item-custom text-danger" href="javascript:void(0)" onclick="deleteTaxonomy({{ $b->id }})">
                                    <i class="bx bx-trash fs-6"></i> Delete Ecosystem
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>
                @endforeach
                @if($vaultCanManage ?? (auth()->user()->hasRole(['admin','Admin','hr','HR']) || auth()->user()->can('vault.upload')))
                <a href="javascript:void(0)" class="nav-link brand-tab-add" onclick="openAddModal('brand')">+ Add Brand</a>
                @endif
            </div>
        </div>

        {{-- Phone-only brand picker: a native <select>, swapped in for the
             tabs row above via CSS display toggle — one server-rendered
             brand list, shown as tabs or dropdown options, nothing to keep
             in sync client-side. --}}
        <div class="brand-select-mobile">
            <select onchange="if (this.value) window.location.href = this.value;">
                <option value="">Select Brand Ecosystem…</option>
                @foreach($brands as $b)
                    @php $slug = \Illuminate\Support\Str::slug($b->name); @endphp
                    <option value="{{ route('library.index', ['category' => $category, 'brand' => $slug]) }}" {{ ($activeBrand && $activeBrand->id === $b->id) ? 'selected' : '' }}>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif
    </div>

    {{-- Brand selection lives in the filter-tab row inside .vault-header-card
         above (desktop) / the dropdown next to it (phone) — not a separate
         card grid here. --}}

    @if($tier === 'video')
    {{-- ===== VIDEO VIEW — its own category taxonomy, no brand at all ===== --}}
    <div class="mt-2">
        <div class="brand-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-header-dot" style="background-color: #64748b;"></div>
                <h2>Video</h2>
                <div class="stats-badge">{{ $totalScopedDocs }} Files</div>
            </div>
            <div class="view-switcher">
                <button class="view-btn active" onclick="switchView('grid')" id="btn-grid-video"><i class="bx bx-grid-alt"></i> Grid View</button>
                <button class="view-btn" onclick="switchView('list')" id="btn-list-video"><i class="bx bx-list-ul"></i> List View</button>
            </div>
        </div>

        <div class="main-grid mt-4">
            <!-- Video Category Sidebar -->
            <div class="side-navigation shadow-sm">
                <a href="{{ route('library.index', ['category' => 'Video']) }}" class="side-nav-item @if($activeSubcat === 'all') active @endif">
                    <span>All Categories</span>
                    <span class="count">{{ $totalScopedDocs }}</span>
                </a>
                <hr class="my-2 opacity-25">
                @foreach($catNames as $vCat)
                    @php
                        $vCatSlug = \Illuminate\Support\Str::slug($vCat);
                        $vCount = $subCatCounts[$vCat] ?? 0;
                        $vTaxId = $videoTaxonomies->firstWhere('name', $vCat)?->id;
                    @endphp
                    <div class="side-nav-item d-flex align-items-center justify-content-between @if($activeSubcat === $vCatSlug) active @endif"
                         onclick="if (!event.target.closest('.dropdown')) window.location.href = '{{ route('library.index', ['category' => 'Video', 'subcat' => $vCatSlug]) }}';">
                        <span>{{ $vCat }}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="count">{{ $vCount }}</span>
                            @if($vTaxId)
                            <div class="dropdown">
                                <button class="action-dot" style="width: 20px; height: 20px; gap: 2px;" data-bs-toggle="dropdown">
                                    <span style="width: 3px; height: 3px;"></span>
                                    <span style="width: 3px; height: 3px;"></span>
                                    <span style="width: 3px; height: 3px;"></span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-custom shadow">
                                    <a class="dropdown-item dropdown-item-custom" href="javascript:void(0)" onclick="editTaxonomy('category', {{ $vTaxId }})">
                                        <i class="bx bx-edit"></i> Rename
                                    </a>
                                    <a class="dropdown-item dropdown-item-custom text-danger" href="javascript:void(0)" onclick="deleteTaxonomy({{ $vTaxId }})">
                                        <i class="bx bx-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if($vaultCanManage ?? (auth()->user()->hasRole(['admin','Admin','hr','HR']) || auth()->user()->can('vault.upload')))
                <hr class="my-2 opacity-25">
                <div class="side-nav-item text-primary" onclick="openAddModal('category', null)">
                    <span class="fw-bold"><i class="bx bx-plus me-1"></i> New Category</span>
                </div>
                @endif
            </div>

            <!-- Video cards — only this page's slice is rendered -->
            <div class="products-area">
                <div class="subcat-content">
                    @if(!$paginated || $paginated->total() == 0)
                        <div class="text-center p-5 bg-white rounded-4 border border-light shadow-sm">
                            <i class="bx bx-video-off fs-1 text-muted mb-3 d-block opacity-25"></i>
                            <h6 class="text-muted fw-bold">No videos {{ $activeSubcat === 'all' ? 'uploaded yet' : 'in this category' }}</h6>
                        </div>
                    @else
                        <div class="view-mode mode-grid">
                            <div class="row g-4">
                                @foreach($paginated as $productName => $files)
                                    @include('tenant.digital-library.product-card-item', ['files' => $files, 'brand' => '', 'category' => $files->first()->sub_category ?? ''])
                                @endforeach
                            </div>
                        </div>
                        <div class="view-mode mode-list" style="display:none;">
                            @foreach($paginated as $productName => $files)
                                @include('tenant.digital-library.list-item', ['files' => $files, 'brand' => '', 'category' => $files->first()->sub_category ?? ''])
                            @endforeach
                        </div>
                        @include('tenant.digital-library.pagination')
                    @endif
                </div>
            </div>
        </div>
    </div>
    @elseif($activeBrand)
    {{-- ===== BRAND-BASED VIEW — one brand at a time, server-paginated ===== --}}
    @php $slug = \Illuminate\Support\Str::slug($activeBrand->name); @endphp
    <div class="mt-2">
        <div class="brand-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-header-dot" style="background-color: {{ $activeBrand->color ?? '#ccc' }};"></div>
                <h2>{{ $activeBrand->name }}</h2>
                <div class="stats-badge">{{ $totalScopedDocs }} Technical Files</div>
            </div>
            <div class="view-switcher">
                <button class="view-btn active" onclick="switchView('grid')" id="btn-grid-{{ $slug }}"><i class="bx bx-grid-alt"></i> Grid View</button>
                <button class="view-btn" onclick="switchView('list')" id="btn-list-{{ $slug }}"><i class="bx bx-list-ul"></i> List View</button>
            </div>
        </div>

        <div class="main-grid mt-4 @if($tier === 'brand') no-sidebar @endif">
            @unless($tier === 'brand')
            <!-- Category Sidebar (Brand Specific) -->
            <div class="side-navigation shadow-sm">
                <a href="{{ route('library.index', ['category' => $category, 'brand' => $slug]) }}" class="side-nav-item @if($activeSubcat === 'all') active @endif">
                    <span>All Categories</span>
                    <span class="count">{{ $totalScopedDocs }}</span>
                </a>
                <hr class="my-2 opacity-25">
                @foreach($catNames as $mCat)
                    @php
                        $mCatSlug = \Illuminate\Support\Str::slug($mCat);
                        $prodCount = $subCatCounts[$mCat] ?? 0;
                        $taxId = $brandTaxonomies->firstWhere('name', $mCat)?->id;
                    @endphp
                    <div class="side-nav-item d-flex align-items-center justify-content-between @if($activeSubcat === $mCatSlug) active @endif"
                         onclick="if (!event.target.closest('.dropdown')) window.location.href = '{{ route('library.index', ['category' => $category, 'brand' => $slug, 'subcat' => $mCatSlug]) }}';"
                         ondragover="handleDragOver(event)"
                         ondragleave="handleDragLeave(event)"
                         ondrop="handleCategoryDrop(event, '{{ $activeBrand->name }}', '{{ $mCat }}')">
                        <span>{{ $mCat }}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="count">{{ $prodCount }}</span>
                            @if($taxId)
                            <div class="dropdown">
                                <button class="action-dot" style="width: 20px; height: 20px; gap: 2px;" data-bs-toggle="dropdown">
                                    <span style="width: 3px; height: 3px;"></span>
                                    <span style="width: 3px; height: 3px;"></span>
                                    <span style="width: 3px; height: 3px;"></span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-custom shadow">
                                    <a class="dropdown-item dropdown-item-custom" href="javascript:void(0)" onclick="editTaxonomy('category', {{ $taxId }})">
                                        <i class="bx bx-edit"></i> Rename
                                    </a>
                                    <a class="dropdown-item dropdown-item-custom text-danger" href="javascript:void(0)" onclick="deleteTaxonomy({{ $taxId }})">
                                        <i class="bx bx-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if($vaultCanManage ?? (auth()->user()->hasRole(['admin','Admin','hr','HR']) || auth()->user()->can('vault.upload')))
                <hr class="my-2 opacity-25">
                <div class="side-nav-item text-primary" onclick="openAddModal('category', {{ $activeBrand->id }})">
                    <span class="fw-bold"><i class="bx bx-plus me-1"></i> New Category</span>
                </div>
                @endif
            </div>
            @endunless

            <!-- Products View Area — only this page's slice is rendered -->
            <div class="products-area">
                <div class="subcat-content">
                    @if(!$paginated || $paginated->total() == 0)
                        <div class="text-center p-5 bg-white rounded-4 border border-light shadow-sm">
                            <i class="bx bx-package fs-1 text-muted mb-3 d-block opacity-25"></i>
                            <h6 class="text-muted fw-bold">No assets found for {{ $activeBrand->name }}</h6>
                        </div>
                    @else
                        <div class="view-mode mode-grid">
                            <div class="row g-4">
                                @foreach($paginated as $productName => $files)
                                    @include('tenant.digital-library.product-card-item', ['files' => $files, 'brand' => $activeBrand->name, 'category' => $files->first()->sub_category ?? ''])
                                @endforeach
                            </div>
                        </div>
                        <div class="view-mode mode-list" style="display:none;">
                            @foreach($paginated as $productName => $files)
                                @include('tenant.digital-library.list-item', ['files' => $files, 'brand' => $activeBrand->name, 'category' => $files->first()->sub_category ?? ''])
                            @endforeach
                        </div>
                        @include('tenant.digital-library.pagination')
                    @endif
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="text-center p-5 bg-white rounded-4 border border-light shadow-sm mt-2">
        <i class="bx bx-buildings fs-1 text-muted mb-3 d-block opacity-25"></i>
        <h6 class="text-muted fw-bold">No brand ecosystems yet</h6>
    </div>
    @endif
</div>

@if($vaultCanManage ?? (auth()->user()->hasRole(['admin','Admin','hr','HR']) || auth()->user()->can('vault.upload')))
<button type="button" class="vault-fab" onclick="openAddDocumentModal('{{ request('category') }}')">
    <i class="bx bx-plus fs-5"></i> Add Document
</button>
@endif

@include('tenant.digital-library.modals')
@endsection

@section('page-script')
<script>
    let currentView = 'grid';
    const libraryTaxonomy = @json($taxonomies);

    const pendingFiles = new Map();
    const auditState = new Map();

    function switchView(view) {
        currentView = view;
        // Select all view containers across all brands/subcategories
        document.querySelectorAll('.view-mode').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.mode-' + view).forEach(el => el.style.display = 'block');
        
        // Update button states globally
        document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('[id^="btn-' + view + '"]').forEach(btn => btn.classList.add('active'));
    }

    // Brand cards and category sidebar items are real links now (server
    // renders only the active brand/category, not all seven hidden with
    // CSS) — no client-side show/hide needed for switching between them.

    // Comprehensive Search
    document.getElementById('librarySearch').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        const items = document.querySelectorAll('.product-item');
        items.forEach(item => {
            const content = item.innerText.toLowerCase();
            item.style.display = content.includes(term) ? '' : 'none';
        });
    });

    // --- Drag & Drop Reassignment Logic ---
    function handleDragStart(e, title, brand, cat) {
        e.dataTransfer.setData('productTitle', title);
        e.dataTransfer.setData('currentBrand', brand);
        e.dataTransfer.setData('currentCat', cat);
        e.target.closest('.product-card').classList.add('dragging');
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.currentTarget.classList.add('drag-over');
    }

    function handleDragLeave(e) {
        e.currentTarget.classList.remove('drag-over');
    }

    async function handleBrandDrop(e, newBrand) {
        e.preventDefault();
        const title = e.dataTransfer.getData('productTitle');
        const currentBrand = e.dataTransfer.getData('currentBrand');
        const currentCat = e.dataTransfer.getData('currentCat');
        
        if (newBrand === currentBrand) return;
        
        await performReassignment(title, newBrand, currentCat);
    }

    async function handleCategoryDrop(e, newBrand, newCat) {
        e.preventDefault();
        const title = e.dataTransfer.getData('productTitle');
        const currentCat = e.dataTransfer.getData('currentCat');
        
        if (newCat === currentCat) return;
        
        await performReassignment(title, newBrand, newCat);
    }

    async function performReassignment(title, brand, cat) {
        try {
            const resp = await fetch('{{ route("library.reassign") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    current_title: title,
                    new_brand: brand,
                    new_sub_category: cat
                })
            });
            
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Move failed');
            
            location.reload();
        } catch (err) {
            alert(err.message);
        }
    }

    // --- AI Ingestion Workflow ---
    document.addEventListener('DOMContentLoaded', function() {
        const bulkInput = document.getElementById('bulkFileInput');
        if (bulkInput) bulkInput.addEventListener('change', (e) => handleFiles(e.target.files));
    });

    // Finalize All Assets listener
    document.getElementById('startBulkCommit')?.addEventListener('click', async function() {
        const buttons = document.querySelectorAll('[id^="commit-"]');
        for (const btn of buttons) {
            if (btn.offsetParent !== null && !btn.disabled) { 
                try {
                    await btn.click();
                    // Optional: add a small delay to prevent server hammering
                    await new Promise(r => setTimeout(r, 100));
                } catch (e) { console.error("Bulk step failed", e); }
            }
        }
    });

    function handleFiles(files) {
        const container = document.getElementById('bulkUploadList');
        if (!container) return;
        
        Array.from(files).forEach(async (file) => {
            const id = Math.random().toString(36).substr(2, 9);
            pendingFiles.set(id, file);
            const item = document.createElement('div');
            item.className = 'p-0 overflow-hidden rounded-4 mb-4 border border-light shadow-sm bg-white';
            item.id = `file-${id}`;
            item.innerHTML = `
                <div class="p-3 d-flex justify-content-between align-items-center" style="background: #fdfdfd; border-bottom: 1px solid #f0f0f0;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bx bx-file fs-4 text-primary"></i>
                        <div class="text-dark small fw-bold text-truncate" style="max-width: 250px;">${file.name}</div>
                    </div>
                    <div id="status-${id}" class="badge px-2 py-1" style="background: var(--hitech-primary-light); color: var(--hitech-primary); font-size: 0.65rem; font-weight: 800;">AI AUDITING...</div>
                </div>
                <div class="p-4 bg-white">
                    <div class="progress mb-3" style="height: 6px; background: #f0f2f5; border-radius: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="bar-${id}" style="width: 35%; border-radius: 10px; background-color: var(--hitech-primary);"></div>
                    </div>
                    <div id="summary-section-${id}">
                        <div id="summary-${id}" class="text-muted mb-3 italic" style="font-size: 0.75rem; line-height: 1.6; min-height: 40px;">
                            <span class="placeholder-glow w-100">
                                <span class="placeholder col-7 bg-light"></span>
                                <span class="placeholder col-4 bg-light"></span>
                                <span class="placeholder col-4 bg-light"></span>
                                <span class="placeholder col-6 bg-light"></span>
                            </span>
                        </div>
                        <div id="action-${id}" style="display:none;">
                            <hr class="my-4 opacity-10">
                            <button class="btn w-100 py-3 fw-bold rounded-pill shadow-lg transition-all text-white" id="commit-${id}" style="background: var(--hitech-primary); border: none;">
                                <i class="bx bx-check-shield me-2"></i> Confirm & Secure to Vault
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(item);
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('_token', '{{ csrf_token() }}');

            try {
                const response = await fetch('{{ route("library.analyze") }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });

                // Parse defensively. A gateway timeout or a login redirect hands
                // back an HTML page, and calling .json() on it produced the
                // useless "Unexpected token '<'" this used to show.
                const rawBody = await response.text();
                let data = null;
                try { data = rawBody ? JSON.parse(rawBody) : {}; } catch (_) { data = null; }

                if (data === null) {
                    throw new Error(response.status === 413
                        ? 'That file is too large to upload.'
                        : 'The AI audit did not finish in time. Save the file anyway — it will be classified in the background.');
                }
                if (!response.ok) throw new Error(data.error || 'AI Audit Interrupted');

                const statusEl = document.getElementById(`status-${id}`);
                const summaryEl = document.getElementById(`summary-${id}`);
                const actionBtnArea = document.getElementById(`action-${id}`);

                // The AI was busy: the file is still perfectly savable, it just
                // gets classified by vault:audit-pending later instead of now.
                statusEl.textContent = data.pending ? 'Queued' : data.category;
                statusEl.className = data.pending ? 'badge bg-warning text-dark' : 'badge bg-info';
                document.getElementById(`bar-${id}`).style.width = '100%';
                
                const brandId = Object.keys(libraryTaxonomy).find(bid => {
                    // This is a bit complex as we only have brand names in some places
                    return true; // Fallback or handle brand ID properly
                });

                summaryEl.innerHTML = `
                    <div class="row g-3 mb-4">
                        <div class="col-6 text-start">
                            <label class="form-label fw-bold small text-uppercase text-muted letter-spacing-1 mb-1">Brand Ecosystem</label>
                            <select id="brand-select-${id}" class="form-select border-light bg-light py-2 shadow-none" style="border-radius: 10px; font-size: 0.8rem; font-weight: 700;" onchange="updateCategoryDropdown('${id}')">
                                <option value="RUST-X" data-id="1" ${data.brand === 'RUST-X' ? 'selected' : ''}>RUST-X</option>
                                <option value="Dr.Bio" data-id="2" ${data.brand === 'Dr.Bio' ? 'selected' : ''}>Dr.Bio</option>
                                <option value="Fillezy" data-id="3" ${data.brand === 'Fillezy' ? 'selected' : ''}>Fillezy</option>
                                <option value="KIF" data-id="4" ${data.brand === 'KIF' ? 'selected' : ''}>KIF</option>
                                <option value="ZOrbit" data-id="5" ${data.brand === 'ZOrbit' ? 'selected' : ''}>ZOrbit</option>
                                <option value="Tuffpaulin" data-id="6" ${data.brand === 'Tuffpaulin' ? 'selected' : ''}>Tuffpaulin</option>
                                <option value="HITECH" data-id="7" ${data.brand === 'HITECH' ? 'selected' : ''}>HITECH</option>
                            </select>
                        </div>
                        <div class="col-6 text-start">
                            <label class="form-label fw-bold small text-uppercase text-muted letter-spacing-1 mb-1">Technical Category</label>
                            <select id="cat-input-${id}" class="form-select border-light bg-light py-2 shadow-none" style="border-radius: 10px; font-size: 0.8rem; font-weight: 700;">
                                <option value="${data.sub_category}" selected>${data.sub_category}</option>
                            </select>
                        </div>
                    </div>
                    <div class="p-3 rounded-3 text-dark border border-light mb-3 text-start" style="font-size: 0.75rem; line-height: 1.6; background: #fbfcfd;">
                        <span class="fw-bold text-primary">AI Crux:</span> ${data.summary}
                    </div>
                    ${data.pending ? `
                    <div class="p-2 rounded-3 mb-3 text-start" style="font-size: 0.72rem; background:#fff7ed; color:#b45309; border:1px solid #fed7aa;">
                        <i class="bx bx-time me-1"></i>${data.notice || 'The AI is busy. Save the file — it will be classified automatically in the background.'}
                    </div>` : ''}
                `;
                updateCategoryDropdown(id, data.sub_category);
                
                actionBtnArea.style.display = 'block';
                // Show the "Finalize All" button if there are files
                const bulkCommitBtn = document.getElementById('startBulkCommit');
                if (bulkCommitBtn) bulkCommitBtn.style.display = 'block';

                const commitBtn = document.getElementById(`commit-${id}`);
                if (commitBtn) {
                    commitBtn.onclick = async function() {
                        try {
                            const brandEl = document.getElementById(`brand-select-${id}`);
                            const catEl = document.getElementById(`cat-input-${id}`);
                            
                            if (!brandEl || !catEl) {
                                console.warn(`UI elements missing for file ${id}. Using suggested data.`);
                                await finalizeIngestion(id, data, file);
                                return;
                            }
                            
                            const finalBrand = brandEl.value;
                            const finalCat = catEl.value;
                            commitBtn.disabled = true;
                            commitBtn.innerHTML = '<i class="bx bx-loader-alt spin me-2"></i> Vaulting...';
                            
                            await finalizeIngestion(id, { ...data, brand: finalBrand, sub_category: finalCat }, file);
                        } catch (err) {
                            console.error("Manual commit error", err);
                        }
                    };
                }
            } catch (err) {
                document.getElementById(`status-${id}`).textContent = 'Error';
                document.getElementById(`status-${id}`).className = 'badge bg-danger';
                document.getElementById(`summary-${id}`).innerHTML = `<span class="text-danger small">${err.message}</span>`;
            }
        });
    }

    async function finalizeIngestion(id, data, fileArg, overwrite = false) {
        if (data) auditState.set(id, data);
        const finalData = data || auditState.get(id);
        
        const file = fileArg || pendingFiles.get(id);
        const formData = new FormData();
        formData.append('file', file);
        formData.append('brand', finalData.brand);
        formData.append('sub_category', finalData.sub_category);
        formData.append('category', finalData.category);
        formData.append('name', finalData.name);
        formData.append('summary', finalData.summary);
        if (overwrite) formData.append('overwrite', '1');
        formData.append('_token', '{{ csrf_token() }}');

        const statusArea = document.getElementById(`status-${id}`);
        statusArea.textContent = 'Archiving...';

        try {
            const resp = await fetch('{{ route("library.store") }}', { 
                method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            const dataResult = await resp.json();
            if (!resp.ok) {
                throw new Error(dataResult.message || dataResult.error || 'Vaulting Failed');
            }
            
            statusArea.textContent = 'SECURED';
            statusArea.className = 'badge bg-success';
            
            // Remove the card after a short delay
            setTimeout(() => {
                const card = document.getElementById(`file-${id}`);
                if (card) {
                    card.style.opacity = '0.5';
                    card.querySelector(`#summary-section-${id}`).innerHTML = `
                        <div class="text-center py-3">
                            <i class="bx bx-check-circle fs-2 text-success mb-2"></i>
                            <div class="fw-bold text-success">Asset Secured to Vault</div>
                            <div class="text-muted small">${finalData.name}</div>
                        </div>
                    `;
                }
            }, 500);
        } catch (err) {
            const isDuplicate = err.message.includes('DUPLICATE_FOUND') || err.message.includes('already exists');
            statusArea.textContent = isDuplicate ? 'DUPLICATE' : 'ERROR';
            statusArea.className = 'badge bg-danger';
            
            // Re-enable commit button if it exists
            const commitBtn = document.getElementById(`commit-${id}`);
            if (commitBtn) {
                commitBtn.disabled = false;
                commitBtn.innerHTML = '<i class="bx bx-check-shield me-2"></i> Confirm & Secure to Vault';
            }

            const sumEl = document.getElementById(`summary-${id}`);
            if (sumEl) {
                if (isDuplicate) {
                    sumEl.innerHTML = `
                        <div class="alert alert-warning py-3 px-3 small border-0 mb-3" style="font-size: 0.75rem; border-radius: 12px; background: rgba(255, 152, 0, 0.1); color: #e65100;">
                            <i class="bx bx-error me-2 fs-5"></i> <strong>Duplicate Detected:</strong> Asset already exists in the vault.
                        </div>
                        <button class="btn btn-warning w-100 py-2 fw-bold rounded-pill shadow-sm" onclick="finalizeIngestion('${id}', null, null, true)">
                            <i class="bx bx-transfer me-1"></i> Replace Existing File
                        </button>
                    `;
                    const actionArea = document.getElementById(`action-${id}`);
                    if (actionArea) actionArea.style.display = 'none';
                } else {
                    sumEl.innerHTML = `<div class="alert alert-danger py-2 px-3 small border-0 mb-0" style="font-size: 0.7rem;">
                        <i class="bx bx-error-circle me-1"></i> ${err.message}
                    </div>`;
                }
            }
        }
    }

    function updateCategoryDropdown(fileId, selectedCat = null) {
        const brandSelect = document.getElementById(`brand-select-${fileId}`);
        const catSelect = document.getElementById(`cat-input-${fileId}`);
        if (!brandSelect || !catSelect) return;
        
        const brandId = brandSelect.options[brandSelect.selectedIndex].getAttribute('data-id');
        
        catSelect.innerHTML = '';
        const cats = libraryTaxonomy[brandId] || [];
        
        cats.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.name;
            opt.textContent = c.name;
            if (selectedCat && c.name === selectedCat) opt.selected = true;
            catSelect.appendChild(opt);
        });

        // If AI suggested one not in list, add it as temporary
        if (selectedCat && !cats.find(c => c.name === selectedCat)) {
            const opt = document.createElement('option');
            opt.value = selectedCat;
            opt.textContent = selectedCat + ' (AI Suggestion)';
            opt.selected = true;
            catSelect.appendChild(opt);
        }
    }
</script>
@endsection
