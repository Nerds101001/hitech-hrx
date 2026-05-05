@extends('layouts/layoutMaster')

@section('title', 'Digital Library')

@section('page-style')
<style>
    :root {
        --hitech-primary: #00897b;
        --hitech-dark: #0d3b33;
        --hitech-bg: #f8fafb;
    }

    body { background-color: var(--hitech-bg); font-family: 'Public Sans', sans-serif; }
    
    /* Navigation Bar */
    .nav-filters .nav-link { padding: 6px 18px; border-radius: 8px; color: #666; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; border: 1px solid transparent; }
    .nav-filters .nav-link:hover { background: #f0f2f5; }
    .nav-filters .nav-link.active { background: var(--hitech-primary); color: white !important; }

    .search-input-wrapper { background: #f0f2f5; border-radius: 20px; padding: 8px 16px; display: flex; align-items: center; width: 250px; border: 1px solid #eef2f4; }
    .search-input-wrapper input { border: none; background: transparent; font-size: 0.85rem; padding-left: 10px; width: 100%; outline: none; }

    /* Hero Banner */
    .library-banner { background: var(--hitech-dark); border-radius: 16px; padding: 40px 50px; color: white; margin-bottom: 2rem; position: relative; overflow: hidden; }
    .banner-content h1 { font-size: 2.4rem; font-weight: 800; margin-bottom: 8px; }
    .banner-content p { opacity: 0.8; font-size: 1rem; margin-bottom: 0; font-weight: 500; }
    .banner-btns { position: absolute; right: 50px; top: 50%; transform: translateY(-50%); display: flex; gap: 15px; }

    /* Brand Selection - Streamlined */
    .brand-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-bottom: 2rem; }
    .brand-card { background: white; border: 1px solid #eef2f4; border-radius: 12px; padding: 12px 10px; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); position: relative; text-align: center; }
    .brand-card:hover { transform: translateY(-2px); border-color: #ddd; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
    .brand-card.active { border: 2px solid #ff7043; background: #fffdfc; box-shadow: 0 4px 12px rgba(255, 112, 67, 0.1); }
    .brand-card.drag-over { background: #fff3e0; border: 2px dashed #ff7043; transform: scale(1.05); }
    
    .action-dropdown { position: absolute; top: 6px; right: 6px; z-index: 50; }
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
    
    .brand-dot { width: 8px; height: 8px; border-radius: 50%; margin: 0 auto 8px auto; }
    .brand-card h6 { font-weight: 800; margin-bottom: 2px; color: #333; font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .brand-card p { display: none; } /* Hide description for ultra-compact view */
    .brand-card .doc-count { font-size: 0.65rem; color: #aaa; font-weight: 700; letter-spacing: 0.2px; }

    /* Matrix Layout */
    .main-grid { display: grid; grid-template-columns: 240px 1fr; gap: 25px; }
    .brand-header { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eef2f4; }
    .brand-header-dot { width: 14px; height: 14px; border-radius: 50%; }
    .brand-header h2 { font-weight: 800; margin: 0; font-size: 1.6rem; color: #333; }
    .stats-badge { background: #e0f2f1; color: var(--hitech-primary); font-size: 0.7rem; padding: 5px 14px; border-radius: 20px; font-weight: 800; }

    .side-navigation { background: white; border-radius: 16px; padding: 10px; border: 1px solid #eef2f4; align-self: start; }
    .side-nav-item { padding: 12px 16px; border-radius: 12px; margin-bottom: 4px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: #777; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; }
    .side-nav-item:hover { background: #f8fafb; color: #333; }
    .side-nav-item.active { background: #e0f2f1; color: var(--hitech-primary); }
    .side-nav-item.drag-over { background: #b2dfdb; border-left: 4px solid var(--hitech-primary); }
    .side-nav-item .count { background: #f0f2f5; padding: 2px 8px; border-radius: 8px; font-size: 0.65rem; color: #999; }
    .side-nav-item.active .count { background: #b2dfdb; color: var(--hitech-primary); }

    /* View Toggle */
    .view-switcher { display: flex; background: #f0f2f5; padding: 4px; border-radius: 10px; gap: 4px; }
    .view-btn { border: none; background: transparent; color: #999; padding: 6px 14px; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 0.75rem; font-weight: 800; display: flex; align-items: center; gap: 6px; }
    .view-btn.active { background: white; color: var(--hitech-primary); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }

    /* Cards */
    .product-card { background: white; border: 1px solid #eef2f4; border-radius: 18px; height: 100%; display: flex; flex-direction: column; overflow: hidden; transition: all 0.3s; position: relative; cursor: grab; }
    .product-card.dragging { opacity: 0.5; border: 2px dashed #ccc; }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.08); border-color: #d1d9e0; }
    .card-main-content { padding: 25px 20px; flex-grow: 1; }
    .product-header { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 18px; }
    .product-icon-box { width: 44px; height: 44px; background: #fff8f8; border: 1px solid #ffebee; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    .product-title { font-weight: 800; color: #333; font-size: 1rem; margin-bottom: 4px; line-height: 1.3; }
    .product-subtitle { font-size: 0.75rem; color: #aaa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .card-top-badge { position: absolute; right: 15px; top: 15px; font-size: 0.65rem; font-weight: 900; color: var(--hitech-primary); background: #e0f2f1; padding: 4px 12px; border-radius: 6px; z-index: 1; letter-spacing: 1px; }
    .card-summary { font-size: 0.8rem; color: #666; line-height: 1.6; margin: 15px 0; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; font-style: italic; min-height: 80px; }
    .action-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 20px; }
    .action-btn { border: 1px solid #f0f2f5; border-radius: 10px; padding: 12px 4px; text-align: center; text-decoration: none; transition: all 0.2s; display: flex; flex-direction: column; align-items: center; gap: 4px; }
    .action-btn:hover:not(.disabled) { background: #f8fafb; border-color: #ddd; transform: scale(1.02); box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
    .action-btn span:first-child { font-size: 0.75rem; font-weight: 900; color: #444; }
    .action-btn span:last-child { font-size: 0.65rem; color: #bbb; font-weight: 700; }
    .action-btn.disabled { opacity: 0.3; cursor: not-allowed; background: #fafafa; border-style: dashed; }
    .card-footer { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; border-top: 1px solid #f8fafb; }
    .file-size { font-size: 0.75rem; color: #bbb; font-weight: 700; }
    .view-details { color: var(--hitech-primary); font-weight: 800; font-size: 0.8rem; text-decoration: none; display: flex; align-items: center; gap: 6px; }

    /* List View mode */
    .list-view-item { background: white; border: 1px solid #eef2f4; border-radius: 14px; padding: 15px 25px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s; }
    .list-view-item:hover { border-color: var(--hitech-primary); transform: translateX(5px); }
    .list-item-main { display: flex; align-items: center; gap: 20px; }
    .list-item-title { font-weight: 800; font-size: 0.95rem; color: #333; }
    .list-item-meta { font-size: 0.75rem; color: #aaa; font-weight: 600; }
    .list-item-actions { display: flex; gap: 12px; }
    .action-pill { padding: 5px 15px; border-radius: 20px; background: #f5f7f9; font-size: 0.65rem; font-weight: 800; color: #666; text-decoration: none; border: 1px solid transparent; transition: all 0.2s; }
    .action-pill:hover:not(.disabled) { border-color: var(--hitech-primary); color: var(--hitech-primary); background: #e0f2f1; }
    .action-pill.disabled { opacity: 0.3; cursor: not-allowed; }
</style>
@endsection

@section('content')
<div class="container-xxl">
    <!-- Hero Banner -->
    <div class="library-banner">
        <div class="banner-content">
            <h1>AI Digital Library</h1>
            <p>Secure documents & intelligent assistant powered by Nerds Bot.</p>
        </div>
        <div class="banner-btns">
            <button class="btn btn-white bg-white text-dark fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">AI Bulk Upload</button>
            <button class="btn btn-outline-white border-white text-white rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#uploadModal">Add Document</button>
        </div>
    </div>

    <!-- User Requested Navigation Bar Above Brands -->
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-4 shadow-sm border border-light">
        <div class="d-flex align-items-center">
            <h5 class="fw-bold mb-0 me-4 text-dark">Intelligent Vault</h5>
            <div class="nav-filters d-flex gap-1">
                <a href="{{ route('library.index') }}" class="nav-link {{ !request()->has('category') ? 'active' : '' }}">All Files</a>
                <a href="{{ route('library.index', ['category' => 'SDS']) }}" class="nav-link {{ request('category') === 'SDS' ? 'active' : '' }}">SDS</a>
                <a href="{{ route('library.index', ['category' => 'TDS']) }}" class="nav-link {{ request('category') === 'TDS' ? 'active' : '' }}">TDS</a>
                <a href="{{ route('library.index', ['category' => 'MOM']) }}" class="nav-link {{ request('category') === 'MOM' ? 'active' : '' }}">MOM</a>
                <a href="{{ route('library.index', ['category' => 'LEARN']) }}" class="nav-link {{ request('category') === 'LEARN' ? 'active' : '' }}">Learn @ Hitech</a>
                <a href="{{ route('library.index', ['category' => 'Video']) }}" class="nav-link {{ request('category') === 'Video' ? 'active' : '' }}">Videos</a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="search-input-wrapper">
                <i class="ti ti-search text-muted"></i>
                <input type="text" placeholder="Search technical assets..." id="librarySearch">
            </div>
        </div>
    </div>

    <!-- Select Brand Row -->
    <div class="section-label">Select Brand Ecosystem</div>
    <div class="brand-grid">
        @foreach($brands as $b)
            @php
                $slug = \Illuminate\Support\Str::slug($b->name);
                $brandFiles = $groupedFiles[strtolower($b->name)] ?? collect([]);
                $count = 0;
                foreach($brandFiles as $sc => $p) foreach($p as $f) $count += count($f);
            @endphp
            <div class="brand-card @if($loop->first) active @endif" 
                 onclick="showBrand('{{ $slug }}')" 
                 data-brand-card="{{ $slug }}"
                 ondragover="handleDragOver(event)"
                 ondragleave="handleDragLeave(event)"
                 ondrop="handleBrandDrop(event, '{{ $b->name }}')">
                <div class="action-dropdown dropdown">
                    <button class="action-dot" data-bs-toggle="dropdown" aria-expanded="false">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-custom shadow">
                        <a class="dropdown-item dropdown-item-custom" href="javascript:void(0)" onclick="editTaxonomy('brand', {{ $b->id }})">
                            <i class="ti ti-edit fs-6"></i> Rename Brand
                        </a>
                        <div class="dropdown-divider opacity-10"></div>
                        <a class="dropdown-item dropdown-item-custom text-danger" href="javascript:void(0)" onclick="deleteTaxonomy({{ $b->id }})">
                            <i class="ti ti-trash fs-6"></i> Delete Ecosystem
                        </a>
                    </div>
                </div>
                <div class="brand-dot" style="background-color: {{ $b->color ?? '#ccc' }};"></div>
                <h6>{{ $b->name }}</h6>
                <div class="doc-count">{{ $count }} documents</div>
            </div>
        @endforeach
        
        <!-- Add Brand Trigger -->
        <div class="brand-card add-trigger border-dashed" onclick="openAddModal('brand')">
            <div class="brand-dot bg-light d-flex align-items-center justify-content-center">
                <i class="ti ti-plus fs-6 text-muted"></i>
            </div>
            <h6>Add Brand</h6>
            <div class="doc-count">New Ecosystem</div>
        </div>
    </div>

    <!-- Main Content Matrix -->
    @foreach($brands as $b)
        @php
            $slug = \Illuminate\Support\Str::slug($b->name);
            $brandFiles = $groupedFiles[strtolower($b->name)] ?? collect([]);
            $totalBrandDocs = 0;
            foreach($brandFiles as $sc => $p) foreach($p as $f) $totalBrandDocs += count($f);
            
            // Dynamic categories from DB for this brand
            $brandTaxonomies = $taxonomies[$b->id] ?? collect([]);
            $brandCategories = $brandTaxonomies->pluck('name')->merge($brandFiles->keys())->unique()->values();
        @endphp
        <div id="brand-container-{{ $slug }}" class="brand-container" style="@if(!$loop->first) display:none; @endif">
            <div class="brand-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-header-dot" style="background-color: {{ $b->color ?? '#ccc' }};"></div>
                    <h2>{{ $b->name }}</h2>
                    <div class="stats-badge">{{ $totalBrandDocs }} Technical Files</div>
                </div>
                <div class="view-switcher">
                    <button class="view-btn active" onclick="switchView('grid')" id="btn-grid-{{ $slug }}"><i class="ti ti-layout-grid"></i> Grid View</button>
                    <button class="view-btn" onclick="switchView('list')" id="btn-list-{{ $slug }}"><i class="ti ti-list"></i> List View</button>
                </div>
            </div>

            <div class="main-grid mt-4">
                <!-- Category Sidebar (Brand Specific) -->
                <div class="side-navigation shadow-sm">
                    <div class="side-nav-item active" 
                         onclick="showSubCat('{{ $slug }}', 'all')"
                         data-cat-btn="{{ $slug }}-all">
                        <span>All Categories</span>
                        <span class="count">{{ $totalBrandDocs }}</span>
                    </div>
                    <hr class="my-2 opacity-25">
                    @foreach($brandCategories as $mCat)
                        @php 
                            $mCatSlug = \Illuminate\Support\Str::slug($mCat);
                            $hasDocs = isset($brandFiles[$mCat]);
                            $prodCount = $hasDocs ? count($brandFiles[$mCat]) : 0;
                            $taxId = $brandTaxonomies->firstWhere('name', $mCat)?->id;
                        @endphp
                        <div class="side-nav-item d-flex align-items-center justify-content-between" 
                             onclick="showSubCat('{{ $slug }}', '{{ $mCatSlug }}')"
                             data-cat-btn="{{ $slug }}-{{ $mCatSlug }}"
                             ondragover="handleDragOver(event)"
                             ondragleave="handleDragLeave(event)"
                             ondrop="handleCategoryDrop(event, '{{ $b->name }}', '{{ $mCat }}')">
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
                                            <i class="ti ti-edit"></i> Rename
                                        </a>
                                        <a class="dropdown-item dropdown-item-custom text-danger" href="javascript:void(0)" onclick="deleteTaxonomy({{ $taxId }})">
                                            <i class="ti ti-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    
                    <hr class="my-2 opacity-25">
                    <div class="side-nav-item text-primary" onclick="openAddModal('category', {{ $b->id }})">
                        <span class="fw-bold"><i class="ti ti-plus me-1"></i> New Category</span>
                    </div>
                </div>

                <!-- Products View Area -->
                <div class="products-area">
                    <!-- All Categories View for this Brand -->
                    <div id="content-{{ $slug }}-all" class="subcat-content">
                        @if($totalBrandDocs == 0)
                            <div class="text-center p-5 bg-white rounded-4 border border-light shadow-sm">
                                <i class="ti ti-box-off fs-1 text-muted mb-3 d-block opacity-25"></i>
                                <h6 class="text-muted fw-bold">No assets found for {{ $b->name }}</h6>
                            </div>
                        @else
                            <div class="view-mode mode-grid">
                                <div class="row g-4">
                                    @foreach($brandFiles as $catName => $products)
                                        @foreach($products as $productName => $files)
                                            @include('tenant.digital-library.product-card-item', ['files' => $files, 'brand' => $b->name, 'category' => $catName])
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>
                            <div class="view-mode mode-list" style="display:none;">
                                @foreach($brandFiles as $catName => $products)
                                    @foreach($products as $productName => $files)
                                        @include('tenant.digital-library.list-item', ['files' => $files, 'brand' => $b->name, 'category' => $catName])
                                    @endforeach
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @foreach($brandCategories as $mCat)
                        @php 
                            $mCatSlug = \Illuminate\Support\Str::slug($mCat);
                            $products = $brandData[$mCat] ?? [];
                        @endphp
                        <div id="content-{{ $slug }}-{{ $mCatSlug }}" class="subcat-content" style="display:none;">
                            @if(count($products) == 0)
                                <div class="text-center p-5 bg-white rounded-4 border border-light shadow-sm">
                                    <i class="ti ti-folder-off fs-1 text-muted mb-3 d-block opacity-25"></i>
                                    <h6 class="text-muted fw-bold">No assets in {{ $mCat }}</h6>
                                    <p class="text-muted small">Archiving technical data for {{ $b['name'] }} ecosystem.</p>
                                </div>
                            @else
                                <!-- Grid View -->
                                <div class="view-mode mode-grid">
                                    <div class="row g-4">
                                        @foreach($products as $productName => $files)
                                            @include('tenant.digital-library.product-card-item', ['files' => $files, 'brand' => $b['name'], 'category' => $mCat])
                                        @endforeach
                                    </div>
                                </div>

                                <!-- List View -->
                                <div class="view-mode mode-list" style="display:none;">
                                    @foreach($products as $productName => $files)
                                        @include('tenant.digital-library.list-item', ['files' => $files, 'brand' => $b['name'], 'category' => $mCat])
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>

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

    function showBrand(brandSlug) {
        if (event.target.closest('.dropdown')) return;
        
        // Hide all brand containers
        document.querySelectorAll('.brand-container').forEach(el => el.style.display = 'none');
        const target = document.getElementById('brand-container-' + brandSlug);
        if (target) target.style.display = 'block';
        
        // Update brand card active state
        document.querySelectorAll('.brand-card').forEach(el => el.classList.remove('active'));
        const card = document.querySelector(`[data-brand-card="${brandSlug}"]`);
        if (card) card.classList.add('active');
        
        // Auto-select first sub-category of this brand
        const firstCatBtn = document.querySelector('#brand-container-' + brandSlug + ' .side-nav-item');
        if (firstCatBtn) firstCatBtn.click();
        
        // Persist current view mode
        switchView(currentView);
    }

    function showSubCat(brandSlug, subCatSlug) {
        if (event.target.closest('.dropdown')) return;

        const containers = document.querySelectorAll('#brand-container-' + brandSlug + ' .subcat-content');
        containers.forEach(el => el.style.display = 'none');
        
        const target = document.getElementById('content-' + brandSlug + '-' + subCatSlug);
        if (target) target.style.display = 'block';
        
        const btns = document.querySelectorAll('#brand-container-' + brandSlug + ' .side-nav-item');
        btns.forEach(el => el.classList.remove('active'));
        
        const btn = document.querySelector(`[data-cat-btn="${brandSlug}-${subCatSlug}"]`);
        if (btn) btn.classList.add('active');
        
        switchView(currentView);
    }

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
                        <i class="ti ti-file-text fs-4 text-primary"></i>
                        <div class="text-dark small fw-bold text-truncate" style="max-width: 250px;">${file.name}</div>
                    </div>
                    <div id="status-${id}" class="badge px-2 py-1" style="background: rgba(0, 137, 123, 0.1); color: #00897b; font-size: 0.65rem; font-weight: 800;">AI AUDITING...</div>
                </div>
                <div class="p-4 bg-white">
                    <div class="progress mb-3" style="height: 6px; background: #f0f2f5; border-radius: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="bar-${id}" style="width: 35%; border-radius: 10px; background-color: #00897b;"></div>
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
                            <button class="btn w-100 py-3 fw-bold rounded-pill shadow-lg transition-all text-white" id="commit-${id}" style="background: #00897b; border: none;">
                                <i class="ti ti-shield-check me-2"></i> Confirm & Secure to Vault
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
                    headers: { 'X-Requested-With': 'XMLHttpRequest' } 
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'AI Audit Interrupted');

                const statusEl = document.getElementById(`status-${id}`);
                const summaryEl = document.getElementById(`summary-${id}`);
                const actionBtnArea = document.getElementById(`action-${id}`);

                statusEl.textContent = data.category;
                statusEl.className = 'badge bg-info';
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
                            commitBtn.innerHTML = '<i class="ti ti-loader-2 spin me-2"></i> Vaulting...';
                            
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
                            <i class="ti ti-circle-check fs-2 text-success mb-2"></i>
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
                commitBtn.innerHTML = '<i class="ti ti-shield-check me-2"></i> Confirm & Secure to Vault';
            }

            const sumEl = document.getElementById(`summary-${id}`);
            if (sumEl) {
                if (isDuplicate) {
                    sumEl.innerHTML = `
                        <div class="alert alert-warning py-3 px-3 small border-0 mb-3" style="font-size: 0.75rem; border-radius: 12px; background: rgba(255, 152, 0, 0.1); color: #e65100;">
                            <i class="ti ti-alert-triangle me-2 fs-5"></i> <strong>Duplicate Detected:</strong> Asset already exists in the vault.
                        </div>
                        <button class="btn btn-warning w-100 py-2 fw-bold rounded-pill shadow-sm" onclick="finalizeIngestion('${id}', null, null, true)">
                            <i class="ti ti-replace me-1"></i> Replace Existing File
                        </button>
                    `;
                    const actionArea = document.getElementById(`action-${id}`);
                    if (actionArea) actionArea.style.display = 'none';
                } else {
                    sumEl.innerHTML = `<div class="alert alert-danger py-2 px-3 small border-0 mb-0" style="font-size: 0.7rem;">
                        <i class="ti ti-alert-circle me-1"></i> ${err.message}
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
