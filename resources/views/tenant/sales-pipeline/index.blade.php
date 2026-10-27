@extends('layouts/layoutMaster')

@section('content')
@php
    $user = auth()->user();
    $isAdmin = $user->hasRole(['admin', 'Admin']);
    $isSalesperson = !$isAdmin && !$isCcare && !$isNewBiz;
    $currentMonthKey = \Carbon\Carbon::now()->format('Y-m');
    $currentFyActualTotal = array_sum($monthlyActuals ?? []);
    $fyParts = explode('-', $defaultFy ?? '');
    $currentFyLabel = (count($fyParts) === 2)
        ? substr($fyParts[0], -2) . '-' . substr($fyParts[1], -2)
        : ($defaultFy ?? 'FY');
@endphp
@section('title', 'Sales Report')

{{-- SweetAlert2 + Select2 aren't bundled globally — the Setu Sync preview /
     review modals need them (Swal for popups, Select2 for the review picker),
     so this page loads them itself. --}}
@section('vendor-style')
    @vite([
      'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
      'resources/assets/vendor/libs/select2/select2.scss'
    ])
@endsection

@section('vendor-script')
    @vite([
      'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
      'resources/assets/vendor/libs/select2/select2.js'
    ])
@endsection

@section('page-script')
    @vite([
      'resources/assets/js/app/setu-period-picker.js',
      'resources/assets/js/app/setu-review-common.js',
      'resources/assets/js/app/crm-mapping-review-common.js',
      'resources/assets/js/app/customer-setu-mapping.js',
      'resources/assets/js/app/customer-tally-mapping.js'
    ])
@endsection

@section('navbar-actions')
    {{-- Two clusters, not one flat row: "what am I looking at" (whose data,
         which year) stays visible, while every action collapses into a single
         menu. A manager previously saw up to eight controls side by side, which
         wrapped onto a second line and pushed into the page title. --}}
    <div class="d-flex gap-2 align-items-center flex-wrap sp-toolbar">

        {{-- ── View context ────────────────────────────────────────────── --}}
        @include('tenant.partials.manager-view-switch', ['mvRoute' => 'sales-visits.pipeline.index', 'mvMemberParam' => 'sp'])

        <form action="{{ route('sales-visits.pipeline.index') }}" method="GET" class="mb-0">
            {{-- Changing the year kept only ?fy, so it also dropped the Team
                 context and every active filter. Carry them through. --}}
            @foreach(['team','party','type','cat','sp','ccare','brand','nb','per','sort','dir'] as $keep)
                @if(request()->filled($keep))
                    <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">
                @endif
            @endforeach
            @php $startYear = 2023; $endYear = (int)date('Y') + 1; @endphp
            <select name="fy" class="form-select form-select-sm border-0 fw-bold"
                    style="background-color: rgba(15, 118, 110, 0.1); color: #0f766e; width:auto;" onchange="this.form.submit()">
                @for($i = $startYear; $i <= $endYear; $i++)
                    <option value="{{ $i }}-{{ $i+1 }}"
                        {{ $selectedFy == ($i.'-'.($i+1)) ? 'selected' : '' }}>
                        FY {{ $i }}-{{ substr($i+1,-2) }}
                    </option>
                @endfor
            </select>
        </form>

        {{-- ── Actions ─────────────────────────────────────────────────── --}}
        @php
            $spCanImport = $isCcare || $isAdmin || $isNewBiz;
            $spCanClaim  = \App\Support\SalesScope::canClaimCustomer($user);
            $spHasSetu   = (bool) $syncTargetId;
            $spHasAction = $spCanImport || $spCanClaim || $spHasSetu || $isAdmin;
        @endphp
        @if($spHasAction)
            {{-- auto-close="outside" so the Setu period selects inside the menu
                 can be used without the menu closing on the first click. --}}
            <div class="dropdown">
                <button class="btn btn-sm btn-primary rounded-pill fw-bold shadow-sm dropdown-toggle d-flex align-items-center"
                        type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bx bx-slider-alt me-1"></i> Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-2" style="min-width:270px;">

                    @if($spCanClaim)
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2"
                                    data-bs-toggle="modal" data-bs-target="#claimSearchModal">
                                <i class="bx bx-search-alt text-primary"></i> Find &amp; Claim Customer
                            </button>
                        </li>
                    @endif

                    @if($spCanImport)
                        <li>
                            <a href="{{ route('sales-visits.pipeline.import') }}" class="dropdown-item d-flex align-items-center gap-2">
                                <i class="bx bx-upload text-secondary"></i> Import Sales Report
                            </a>
                        </li>
                    @endif

                    @if($spHasSetu)
                        <li><hr class="dropdown-divider"></li>
                        @if($syncMapped)
                            <li class="px-3 pb-2">
                                @include('tenant.partials.setu-period-picker')
                            </li>
                            <li>
                                <button type="button" id="btnSyncSetu" data-target-id="{{ $syncTargetId }}"
                                        class="dropdown-item d-flex align-items-center gap-2">
                                    <i class="bx bx-sync text-primary"></i> Sync {{ $syncTargetLabel }} Sales Report
                                </button>
                            </li>
                        @else
                            <li>
                                <span class="dropdown-item disabled d-flex align-items-center gap-2"
                                      title="Not linked to Setu yet — contact admin">
                                    <i class="bx bx-sync"></i> Sync {{ $syncTargetLabel }} Sales Report
                                </span>
                            </li>
                        @endif
                    @endif

                    @if($syncTargetId)
                        <li>
                            <button type="button" id="btnSyncCrmMapping" data-target-id="{{ $syncTargetId }}"
                                    class="dropdown-item d-flex align-items-center gap-2">
                                <i class="bx bx-link text-primary"></i> Link {{ $syncTargetLabel }} customers to CRM
                            </button>
                        </li>
                    @endif
                    <li>
                        <button type="button" id="btnBulkConfirmSetu"
                                class="dropdown-item d-flex align-items-center gap-2">
                            <i class="bx bx-check-shield text-success"></i> Confirm all exact Setu matches
                        </button>
                    </li>

                    {{-- Targets are Admin/HR only — CCare and New Biz may view, never set. --}}
                    @if($isAdmin)
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a href="{{ route('sales-visits.kingo-bingo.fill-targets') }}" class="dropdown-item d-flex align-items-center gap-2">
                                <i class="bx bx-edit text-success"></i> Fill Targets
                            </a>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2"
                                    data-bs-toggle="modal" data-bs-target="#targetsModal">
                                <i class="bx bx-target-lock text-success"></i> Assign Targets
                            </button>
                        </li>
                    @endif
                </ul>
            </div>
        @endif
    </div>
@endsection

<div class="container-fluid px-4 py-4">

    {{-- ─── COMPACT STATS STRIP ────────────────────────────────────────── --}}
    {{-- Note: yoy_* and sale/pending amounts are stored in LAKH units; only potential is in rupees --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        <div class="stat-chip"><span class="stat-label">YoY 22-23</span><span class="stat-value" id="cardYoy22_23">₹{{ number_format($totals['22_23'] ?? 0, 2) }}<em>L</em></span></div>
        <div class="stat-chip"><span class="stat-label">YoY 23-24</span><span class="stat-value" id="cardYoy23_24">₹{{ number_format($totals['23_24'] ?? 0, 2) }}<em>L</em></span></div>
        <div class="stat-chip"><span class="stat-label">YoY 24-25</span><span class="stat-value" id="cardYoy24_25">₹{{ number_format($totals['24_25'] ?? 0, 2) }}<em>L</em></span></div>
        <div class="stat-chip"><span class="stat-label">YoY 25-26</span><span class="stat-value" id="cardYoy25_26">₹{{ number_format($totals['25_26'] ?? 0, 2) }}<em>L</em></span></div>
        <div class="stat-chip" style="border-left-color:#10b981;"><span class="stat-label">YoY {{ $currentFyLabel }}</span><span class="stat-value" id="fyTotalCard">₹{{ number_format($currentFyActualTotal, 2) }}<em>L</em></span></div>
        <div class="stat-chip" style="border-left-color:#22c55e;min-width:210px;cursor:pointer;" onclick="openTargetModal()" title="Click to see who achieved their targets this month">
            <span class="stat-label">This Month — Achieved <i class="bx bx-chevron-right"></i></span>
            <span class="stat-value">
                <span class="badge bg-info text-dark" style="font-size:0.7rem;" title="Bingo achievers">B {{ $bAchieved ?? 0 }}</span>
                <span class="badge bg-warning text-dark" style="font-size:0.7rem;" title="Kingo achievers">K {{ $kAchieved ?? 0 }}</span>
                <span class="badge bg-success" style="font-size:0.7rem;" title="Super Kingo achievers">SK {{ $skAchieved ?? 0 }}</span>
            </span>
        </div>
        {{-- This month's actual sale. The YoY chips are whole financial years and
             "Achieved" counts people, so nothing here showed what has actually
             been sold this month. Comes from $monthlyActuals, which is built from
             the same filtered query as Customers and Pending, so it moves with
             the filters like they do. --}}
        <div class="stat-chip" style="border-left-color:#0d9488;">
            <span class="stat-label">This Month — Sales</span>
            <span class="stat-value" style="color:#0f766e;" id="monthSalesCard">₹{{ number_format($monthlyActuals[$currentMonthKey] ?? 0, 2) }}<em>L</em></span>
        </div>
        <div class="stat-chip" style="border-left-color:#3b82f6;"><span class="stat-label">Customers</span><span class="stat-value text-primary" id="totalCustomersCard">{{ $totalNewClients }}</span></div>
        <div class="stat-chip" style="border-left-color:#f97316;"><span class="stat-label">Pending</span><span class="stat-value" id="pendingCard" style="color:#ea580c;">₹{{ number_format($totalPendingCurrentMonth ?? 0, 2) }}<em>L</em></span></div>
    </div>

    {{-- ─── FILTER BAR (server-side; submits a GET request) ──────────────── --}}
    <style>
        .sales-filter .form-select,
        .sales-filter .form-control,
        .sales-filter .btn { height:36px; border-radius:9px; font-size:0.8rem; }
        /* Search box styled identically to the filter selects */
        .sales-filter .form-select,
        .sales-filter .form-control { border:1px solid #d9dee3; background-color:#fff; box-shadow:none; }
        .sales-filter .btn { display:inline-flex; align-items:center; gap:4px; }
        /* All the filters on one line (desktop): each takes a share of the
           width instead of a fixed one, so they fit beside Search and Clear.
           Tight side padding keeps the words readable; the full selection
           shows on hover. Below 1200px they wrap as before. */
        .sales-filter .form-select { padding-left:.6rem !important; padding-right:1.6rem !important; background-position:right .45rem center !important; }
        .sales-filter .form-control { padding-left:.75rem !important; padding-right:.75rem !important; }
        @media (min-width: 1200px) {
            .sales-filter { flex-wrap:nowrap !important; }
            .sales-filter > .form-select, .sales-filter > .form-control { flex:1 1 0; min-width:0; width:auto !important; }
            .sales-filter > [name="party"] { flex-grow:1.4; }
            .sales-filter > [name="type"], .sales-filter > [name="brand"] { flex-grow:.9; }
            .sales-filter > [name="sp"] { flex-grow:1.15; }
            .sales-filter > [name="both"] { flex-grow:1.25; }
            .sales-filter > [name="per"] { flex-grow:.85; }
            .sales-filter > .btn { flex:0 0 auto; }
        }

        /* Below 1200px every field reverts to its hardcoded style="width:
           Npx" (200/130/190/180/150/180/200/105px) and just wraps at that
           fixed size instead of stacking -- on phone that's still 9 fields
           individually too narrow to use comfortably. Force full-width. */
        @media (max-width: 480px) {
            .sales-filter > .form-select,
            .sales-filter > .form-control,
            .sales-filter > select,
            .sales-filter > input {
                width: 100% !important;
                flex: 1 1 100%;
            }
        }

/* A negative sale is a credit note, not a typo - show it red so it reads as
   money going the other way, and name it on hover. */
.excel-input.is-credit-note, .is-credit-note { color: #dc3545 !important; font-weight: 600; }
</style>
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" action="{{ route('sales-visits.pipeline.index') }}" class="sales-filter d-flex flex-wrap align-items-center gap-2">
                <input type="hidden" name="fy" value="{{ $selectedFy }}">
                {{-- Keep the Team context across a filter. Without this the form
                     submitted without ?team=1, the controller fell back to the
                     manager's own rows, and applying any filter silently threw
                     you back to "My Data" with an empty table. --}}
                @if(request()->boolean('team'))<input type="hidden" name="team" value="1">@endif
                @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="dir" value="{{ request('dir') }}">@endif
                <input type="text" class="form-control" name="party" value="{{ $fParty }}"
                       placeholder="Search customer…" style="width:200px;">
                <select class="form-select" name="type" style="width:130px;" onchange="this.form.submit()">
                    <option value="">Type</option>
                    <option value="Distributor" {{ $fType === 'Distributor' ? 'selected' : '' }}>Distributor</option>
                    <option value="Dealer" {{ $fType === 'Dealer' ? 'selected' : '' }}>Dealer</option>
                    <option value="Customer" {{ $fType === 'Customer' ? 'selected' : '' }}>Customer</option>
                    <option value="Packer" {{ $fType === 'Packer' ? 'selected' : '' }}>Packer</option>
                </select>
                <select class="form-select" name="cat" style="width:130px;" onchange="this.form.submit()">
                    <option value="">Category</option>
                    <option value="New Biz" {{ request('cat') === 'New Biz' ? 'selected' : '' }}>New Biz</option>
                    <option value="CCare" {{ request('cat') === 'CCare' ? 'selected' : '' }}>CCare</option>
                    <option value="None" {{ request('cat') === 'None' ? 'selected' : '' }}>No Category</option>
                </select>
                {{-- Full directory filters for every role: rows stay scoped, so an
                     out-of-scope pick simply shows only what the user may see. --}}
                {{-- "No Salesperson" isolates the independent customers a CCare
                     rep holds directly. Shown only when such rows exist here. --}}
                <select class="form-select" name="sp" style="width:190px;" onchange="this.form.submit()">
                    <option value="">Salesperson</option>
                    @if($spNoneCount)
                        <option value="none" {{ (string)$fSp === 'none' ? 'selected' : '' }}>&mdash; No Salesperson &mdash; ({{ $spNoneCount }})</option>
                    @endif
                    @foreach($salespersonFilterUsers as $au)
                        <option value="{{ $au->id }}" {{ (string)$fSp === (string)$au->id ? 'selected' : '' }}>{{ $au->name }} ({{ $spFilterCounts[$au->id] ?? 0 }})</option>
                    @endforeach
                </select>
                <select class="form-select" name="ccare" style="width:180px;" onchange="this.form.submit()">
                    <option value="">CCare</option>
                    @if($ccareNoneCount)
                        <option value="none" {{ (string)$fCcare === 'none' ? 'selected' : '' }}>&mdash; No CCare &mdash; ({{ $ccareNoneCount }})</option>
                    @endif
                    @foreach($ccareFilterUsers as $au)
                        <option value="{{ $au->id }}" {{ (string)$fCcare === (string)$au->id ? 'selected' : '' }}>{{ $au->name }} ({{ $ccareFilterCounts[$au->id] ?? 0 }})</option>
                    @endforeach
                </select>
                <select class="form-select" name="brand" style="width:150px;" onchange="this.form.submit()">
                    <option value="">Brand</option>
                    @foreach($brandCounts as $bName => $bCount)
                        <option value="{{ $bName }}" {{ (string)($fBrand ?? '') === (string)$bName ? 'selected' : '' }}>{{ $bName }} ({{ $bCount }})</option>
                    @endforeach
                </select>
                <select class="form-select" name="nb" style="width:180px;" onchange="this.form.submit()">
                    <option value="">New Biz</option>
                    @if($nbNoneCount)
                        <option value="none" {{ (string)($fNb ?? '') === 'none' ? 'selected' : '' }}>&mdash; No New Biz &mdash; ({{ $nbNoneCount }})</option>
                    @endif
                    @foreach($newBizFilterUsers as $au)
                        <option value="{{ $au->id }}" {{ (string)($fNb ?? '') === (string)$au->id ? 'selected' : '' }}>{{ $au->name }} ({{ $nbFilterCounts[$au->id] ?? 0 }})</option>
                    @endforeach
                </select>
                {{-- One person, either role. The CCare and New Biz dropdowns above
                     AND together, so someone who holds customers under both -
                     Poonam has 226 as CCare and 75 as New Biz - could not be seen
                     whole from either one. --}}
                <select class="form-select" name="both" style="width:200px;" onchange="this.form.submit()"
                        title="One person, counted whether they are the CCare or the New Biz rep">
                    <option value="">CCare / New Biz</option>
                    @foreach($bothFilterUsers as $au)
                        <option value="{{ $au->id }}" {{ (string)($fBoth ?? '') === (string)$au->id ? 'selected' : '' }}>
                            {{ $au->name }} ({{ $bothFilterCounts[$au->id] ?? 0 }})
                        </option>
                    @endforeach
                </select>
                <select class="form-select" name="per" style="width:105px;" onchange="this.form.submit()" title="Rows per page">
                    @foreach([50,100,200,500,1000] as $pp)
                        <option value="{{ $pp }}" {{ (string)request('per', 50) === (string)$pp ? 'selected' : '' }}>{{ $pp }} rows</option>
                    @endforeach
                    <option value="all" {{ request('per') === 'all' ? 'selected' : '' }}>All rows</option>
                </select>
                {{-- Admin/HR only: appears when the selected Salesperson/CCare/New Biz
                     is themselves a manager with direct reports, so their whole team's
                     data can be included, not just their own. See
                     SalesPipelineController::applyUnrestrictedPersonOrTeamFilter(). --}}
                @if($selectedPersonHasTeam ?? false)
                    @if($showTeamActive ?? false)
                        <a href="{{ route('sales-visits.pipeline.index', collect(request()->except(['page', 'team']))->all()) }}"
                           class="btn btn-success" title="Showing this person's whole team — click to show just them">
                            <i class="bx bx-group"></i> Team Sales: ON
                        </a>
                    @else
                        <a href="{{ route('sales-visits.pipeline.index', array_merge(request()->except('page'), ['team' => 1])) }}"
                           class="btn btn-outline-primary" title="Show sales for this person's whole team, not just them">
                            <i class="bx bx-group"></i> Show Team Sales
                        </a>
                    @endif
                @endif
                <button type="submit" class="btn btn-primary" title="Search"><i class="bx bx-search"></i><span class="d-none d-xxl-inline"> Search</span></button>
                <a href="{{ route('sales-visits.pipeline.index', ['fy' => $selectedFy]) }}" class="btn btn-outline-secondary" title="Clear filters">
                    <i class="bx bx-x"></i><span class="d-none d-xxl-inline"> Clear</span>
                </a>
            </form>
            <script>
                // A share of the row can cut a long name short: show the full choice on hover.
                document.querySelectorAll('.sales-filter select').forEach(function (s) {
                    var base = s.getAttribute('title') || '';
                    var tip = function () { var o = s.options[s.selectedIndex]; s.title = (o && o.value !== '') ? o.text.trim() : base; };
                    tip(); s.addEventListener('change', tip);
                });
            </script>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-1">
        {{-- Choose which of the leading columns to show; S.No and Party Name always stay. --}}
        <div class="dropdown">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle py-0" id="srColsBtn"
                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="font-size:0.78rem;">
                <i class="bx bx-columns me-1"></i>Columns
            </button>
            <div class="dropdown-menu p-2" id="srColsMenu" style="min-width:220px;font-size:0.8rem;"></div>
        </div>
        <div class="text-muted" style="font-size:0.78rem">
            Showing <strong>{{ $pipelines->firstItem() ?? 0 }}–{{ $pipelines->lastItem() ?? 0 }}</strong>
            of <strong>{{ $pipelines->total() }}</strong> customers
        </div>
    </div>

    {{-- ─── TABLE ──────────────────────────────────────────────────────── --}}
    <div id="pipelineTopScroll" style="overflow-x:auto;overflow-y:hidden;height:14px;margin-bottom:2px;position:sticky;top:0;z-index:40;background:#fff;">
        <div id="pipelineTopScrollInner" style="height:1px;"></div>
    </div>
    <div class="table-responsive" id="pipelineTableWrap" style="overflow:auto;max-height:max(340px, calc(100vh - 250px));border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
        <table class="table table-bordered table-sm align-middle m-0"
               id="pipelineTable" style="white-space:nowrap;font-size:0.85rem;">
            <thead class="position-sticky top-0 text-white"
                   style="z-index:40;background-color:#0f766e;">
                        {{-- Top Header Row --}}
                        <tr>
                            <th rowspan="2" class="text-center align-middle col-sno">S.No.</th>
                            <th rowspan="2" class="align-middle col-party" style="cursor:pointer;" onclick="sortPipeline('party', this)" title="Click to sort">Party Name<span class="sort-caret"></span></th>
                            <th rowspan="2" class="align-middle col-status" style="cursor:pointer;" onclick="sortPipeline('type', this)" title="Click to sort">Type<span class="sort-caret"></span></th>
                            <th rowspan="2" class="align-middle col-order-interval" style="min-width:110px;cursor:pointer;" onclick="sortPipeline('order_interval', this)" title="Click to sort">Order Interval<span class="sort-caret"></span></th>
                            <th rowspan="2" class="align-middle col-category" style="min-width:90px;cursor:pointer;" onclick="sortPipeline('category', this)" title="Click to sort">Category<span class="sort-caret"></span></th>
                            <th rowspan="2" class="align-middle col-repname" style="min-width:130px;cursor:pointer;" onclick="sortPipeline('rep', this)" title="Click to sort">Representative<span class="sort-caret"></span></th>
                            <th rowspan="2" class="text-end align-middle col-potential" style="cursor:pointer;" onclick="sortPipeline('potential', this)" title="Click to sort">
                                Potential<span class="sort-caret"></span><br><small class="fw-normal opacity-75" style="font-size:0.65rem">₹ Lakh/Month</small>
                            </th>
                            {{-- Salesperson column: hidden for a salesperson login (they only see
                                 their own rows, so it would just repeat the Representative column). --}}
                            @unless($isSalesperson)
                                <th rowspan="2" class="align-middle col-salesperson" style="cursor:pointer;" onclick="sortPipeline('salesperson', this)" title="Click to sort">Salesperson<span class="sort-caret"></span></th>
                            @endunless
                            <th rowspan="2" class="align-middle" style="min-width:150px;cursor:pointer;" onclick="sortPipeline('brand', this)" title="Click to sort">Brand<span class="sort-caret"></span></th>
                            <th rowspan="2" class="align-middle " style="min-width:130px;">New BIZ2</th>
                            <th rowspan="2" class="align-middle block-end" style="min-width:130px;">Service Sales</th>

                            @foreach($months as $m)
                                @php
                                    $isThisMonth = ($m['key'] === $currentMonthKey);
                                    // Pending is shown for the immediately previous month too, so the
                                    // outstanding July figures stay visible next to August.
                                    $prevMonthKey = \Carbon\Carbon::createFromFormat('Y-m-d', $currentMonthKey.'-01')->subMonth()->format('Y-m');
                                    $showPend    = $isThisMonth || ($m['key'] === $prevMonthKey);
                                    // Past: 1 col (Sale). Prev month: 2 (Sale + Pend). Current: 4.
                                    $colspan     = $isThisMonth ? 4 : ($showPend ? 3 : 1);
                                    // Salesperson login: badge the month if their own sale beat their target.
                                    $mActual = $monthlyActuals[$m['key']] ?? 0;
                                    $mKingo  = $monthlyTargets[$m['key']]['kingo'] ?? 0;
                                    $mBingo  = $monthlyTargets[$m['key']]['bingo'] ?? 0;
                                @endphp
                                <th colspan="{{ $colspan }}" class="text-center align-middle border-bottom border-white"
                                    style="background-color:#0d9488;">
                                    {{ $m['display'] }}
                                    @if($isSalesperson && $mKingo > 0 && $mActual >= $mKingo)
                                        <span class="badge bg-warning text-dark ms-1 rounded-circle border border-white" style="padding:0.2em 0.35em" title="Kingo Achieved!">K</span>
                                    @endif
                                    @if($isSalesperson && $mBingo > 0 && $mActual >= $mBingo)
                                        <span class="badge bg-info text-dark ms-1 rounded-circle border border-white" style="padding:0.2em 0.35em" title="Bingo Achieved!">B</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                        {{-- Sub Header Row --}}
                        <tr>
                            @foreach($months as $m)
                                @php
                                    $isThisMonth = ($m['key'] === $currentMonthKey);
                                    $prevMonthKey = \Carbon\Carbon::createFromFormat('Y-m-d', $currentMonthKey.'-01')->subMonth()->format('Y-m');
                                    $showPend = $isThisMonth || ($m['key'] === $prevMonthKey);
                                @endphp
                                @if($showPend)
                                    <th class="text-end align-middle" style="min-width:52px;max-width:52px;{{ $isThisMonth ? 'cursor:pointer;' : '' }}"
                                        @if($isThisMonth) onclick="sortPipeline('forecast', this)" title="Click to sort" @endif>
                                        Fcst<span class="sort-caret"></span><br><small class="fw-normal opacity-75" style="font-size:0.6rem">₹L</small>
                                    </th>
                                @endif
                                <th class="text-end align-middle {{ !$isThisMonth ? 'block-end' : '' }}"
                                    style="min-width:55px;max-width:55px;cursor:pointer;"
                                    onclick="sortPipeline('{{ $isThisMonth ? 'sale' : 'month:'.$m['key'] }}', this)" title="Click to sort">
                                    Sale<span class="sort-caret"></span><br><small class="fw-normal opacity-75" style="font-size:0.6rem">₹L</small>
                                </th>
                                @if($showPend)
                                    <th class="text-end align-middle {{ !$isThisMonth ? 'block-end' : '' }}" style="min-width:52px;max-width:52px;">
                                        Pend<br><small class="fw-normal opacity-75" style="font-size:0.6rem">₹L</small>
                                    </th>
                                @endif
                                @if($isThisMonth)
                                    <th class="align-middle block-end" style="min-width:95px;max-width:95px;">Remarks</th>
                                @endif
                            @endforeach
                            @if($canDeleteCustomer)
                                <th class="text-center align-middle" style="min-width:48px;max-width:48px;">Del</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="pipelineBody">
                        {{-- Monthly totals row --}}
                        <tr id="monthTotalsRow" style="background:#f0fdf4;font-size:0.72rem;font-weight:700;position:sticky;top:68px;z-index:25;">
                            <td class="text-center col-sno" style="background:#d1fae5!important;color:#065f46;">∑</td>
                            <td class="col-party" style="background:#d1fae5!important;color:#065f46;">Total</td>
                            <td class="col-status" style="background:#d1fae5!important;"></td>
                            <td class="col-order-interval" style="background:#d1fae5!important;"></td>
                            <td class="col-category" style="background:#d1fae5!important;"></td>
                            <td class="col-repname" style="background:#d1fae5!important;"></td>
                            <td class="col-potential text-end font-monospace" style="background:#d1fae5!important;color:#065f46;" id="totalPotential">{{ number_format($totalPotential ?? 0, 2) }}</td>
                            @unless($isSalesperson)
                                <td class="col-salesperson" style="background:#d1fae5!important;"></td>
                            @endunless
                            <td style="background:#d1fae5!important;"></td>
                            <td style="background:#d1fae5!important;"></td>
                            <td class="block-end" style="background:#d1fae5!important;"></td>
                            @foreach($months as $m)
                                @php
                                    $isThisMonth = ($m['key'] === $currentMonthKey);
                                    $prevMonthKey = \Carbon\Carbon::createFromFormat('Y-m-d', $currentMonthKey.'-01')->subMonth()->format('Y-m');
                                    $showPend = $isThisMonth || ($m['key'] === $prevMonthKey);
                                @endphp
                                @if($showPend)
                                    <td class="text-end" id="mtotal-forecast-{{ $m['key'] }}"
                                        style="background:#d1fae5!important;color:#1e40af;">{{ number_format($monthlyForecast[$m['key']] ?? 0, 2) }}</td>
                                @endif
                                <td class="text-end {{ !$isThisMonth ? 'block-end' : '' }}" id="mtotal-{{ $m['key'] }}"
                                    style="background:#d1fae5!important;color:#065f46;">
                                    {{ number_format($monthlyActuals[$m['key']] ?? 0, 2) }}
                                </td>
                                @if($showPend)
                                    <td class="text-end {{ !$isThisMonth ? 'block-end' : '' }}" id="mtotal-pending-{{ $m['key'] }}"
                                        style="background:#d1fae5!important;color:#9a3412;font-weight:700;">
                                        {{ number_format($isThisMonth ? ($totalPendingCurrentMonth ?? 0) : ($monthlyPending[$m['key']] ?? 0), 2) }}
                                    </td>
                                @endif
                                @if($isThisMonth)
                                    <td class="block-end" style="background:#d1fae5!important;"></td>
                                @endif
                            @endforeach
                            @if($canDeleteCustomer)
                                <td style="background:#d1fae5!important;"></td>
                            @endif
                        </tr>

                        @forelse($pipelines as $index => $row)
                            @php
                                // Determine what to show in the "rep" column
                                if ($isSalesperson) {
                                    $reps = [];
                                    if ($row->ccare->name ?? null) { $reps[] = $row->ccare->name . ' (CC)'; }
                                    if ($row->newBiz->name ?? null) { $reps[] = $row->newBiz->name . ' (NB)'; }
                                    $repDisplay = !empty($reps) ? implode(' / ', $reps) : '-';
                                } elseif ($isCcare || $isNewBiz) {
                                    $repDisplay = $row->salesperson->name ?? '-';
                                } else {
                                    $repDisplay = ''; // not used for admin (3 separate cols)
                                }
                                // data-rep attribute for JS filter
                                $filterRepStr = $isAdmin
                                    ? implode(' | ', array_filter([
                                        $row->salesperson->name ?? '',
                                        $row->newBiz->name      ?? '',
                                        $row->ccare->name       ?? '',
                                      ]))
                                    : $repDisplay;
                                $rowCurrentMonthPending = $row->months->firstWhere('month_year', $currentMonthKey)?->pending_amount ?? 0;
                                $rowCurrentMonthForecast = $row->months->firstWhere('month_year', $currentMonthKey)?->forecast_amount ?? 0;
                                $rowMonthSales = $row->months->pluck('sale_amount', 'month_year')->toArray();
                            @endphp
                            <tr data-id="{{ $row->id }}"
                                data-type="{{ $row->type }}"
                                data-party="{{ strtolower($row->party_name) }}"
                                data-rep="{{ strtolower($filterRepStr) }}"
                                data-yoy22="{{ $row->yoy_22_23 ?? 0 }}"
                                data-yoy23="{{ $row->yoy_23_24 ?? 0 }}"
                                data-yoy24="{{ $row->yoy_24_25 ?? 0 }}"
                                data-yoy25="{{ $row->yoy_25_26 ?? 0 }}"
                                data-pending="{{ $rowCurrentMonthPending }}"
                                data-forecast="{{ $rowCurrentMonthForecast }}"
                                data-potential="{{ $row->total_business_potential ?? 0 }}"
                                data-salesperson="{{ strtolower($row->salesperson->name ?? '') }}"
                                data-months='@json($rowMonthSales)'
                                class="{{ $row->converted_from_newbiz ? 'converted-row' : '' }}">

                                <td class="text-center col-sno">{{ $index + 1 }}</td>
                                <td class="col-party text-dark p-0" title="{{ $row->party_name }}">
                                    <input type="text" class="excel-input row-field fw-semibold" data-field="party_name"
                                           value="{{ $row->party_name }}" style="font-size:0.72rem;">
                                    @if($row->customer)
                                        @php
                                            $setuBadge = match($row->customer->setu_match_status) {
                                                'verified' => ['bg-label-success', 'bx-check-shield', 'Verified'],
                                                'suggested' => ['bg-label-warning', 'bx-search-alt', 'Match Found'],
                                                'similar' => ['bg-label-info', 'bx-git-compare', 'Similar Match'],
                                                default => ['bg-label-danger', 'bx-x-circle', 'Not Found'],
                                            };
                                        @endphp
                                        <span class="badge {{ $setuBadge[0] }} js-setu-badge d-inline-flex align-items-center gap-1"
                                              style="cursor:pointer; font-size:0.62rem; margin:0 0 2px 4px;"
                                              data-customer-id="{{ $row->customer->id }}"
                                              title="Click to verify against Setu">
                                            <i class="bx {{ $setuBadge[1] }}"></i> {{ $setuBadge[2] }}
                                        </span>
                                        @php
                                            $tallyBadge = match($row->customer->tally_match_status) {
                                                'verified' => ['bg-label-success', 'bx-check-shield', 'Tally: Verified'],
                                                'suggested' => ['bg-label-warning', 'bx-search-alt', 'Tally: Match Found'],
                                                'similar' => ['bg-label-info', 'bx-git-compare', 'Tally: Similar Match'],
                                                default => ['bg-label-danger', 'bx-x-circle', 'Tally: Not Found'],
                                            };
                                        @endphp
                                        <span class="badge {{ $tallyBadge[0] }} js-tally-badge d-inline-flex align-items-center gap-1"
                                              style="cursor:pointer; font-size:0.62rem; margin:0 0 2px 4px;"
                                              data-customer-id="{{ $row->customer->id }}"
                                              title="Click to verify against Tally — once mapped, this customer's sales auto-update from Tally">
                                            <i class="bx {{ $tallyBadge[1] }}"></i> {{ $tallyBadge[2] }}
                                        </span>
                                    @endif
                                </td>

                                <td class="col-status p-0 align-middle">
                                    @php
                                        // "Customer" and "Direct Customer" are treated as one type.
                                        $typeNorm = strtolower(trim($row->type ?? ''));
                                        $isCustomer   = in_array($typeNorm, ['customer', 'direct customer']);
                                        $isDistributor = $typeNorm === 'distributor';
                                        $isDealer      = $typeNorm === 'dealer';
                                        $isPacker      = $typeNorm === 'packer';
                                    @endphp
                                    <select class="excel-select row-field status-select text-truncate"
                                            data-field="type">
                                        <option value="">-Select-</option>
                                        <option value="Distributor" {{ $isDistributor ? 'selected' : '' }}>Distributor</option>
                                        <option value="Dealer"      {{ $isDealer ? 'selected' : '' }}>Dealer</option>
                                        <option value="Customer"    {{ $isCustomer ? 'selected' : '' }}>Customer</option>
                                        <option value="Packer"      {{ $isPacker ? 'selected' : '' }}>Packer</option>
                                        @if($row->type && !$isCustomer && !$isDistributor && !$isDealer && !$isPacker)
                                            <option value="{{ $row->type }}" selected>{{ $row->type }}</option>
                                        @endif
                                    </select>
                                </td>

                                {{-- Order Interval — how often this customer places an order.
                                     Left blank ("-Select-") by default; not required. --}}
                                <td class="col-order-interval p-0 align-middle">
                                    <select class="excel-select row-field status-select text-truncate" data-field="order_interval">
                                        <option value="">-Select-</option>
                                        <option value="Monthly"        {{ $row->order_interval === 'Monthly' ? 'selected' : '' }}>Monthly</option>
                                        <option value="Quarterly"      {{ $row->order_interval === 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                                        <option value="Semi-Annually"  {{ $row->order_interval === 'Semi-Annually' ? 'selected' : '' }}>Semi-Annually</option>
                                        <option value="Annually"       {{ $row->order_interval === 'Annually' ? 'selected' : '' }}>Annually</option>
                                        @if($row->order_interval && !in_array($row->order_interval, ['Monthly', 'Quarterly', 'Semi-Annually', 'Annually']))
                                            <option value="{{ $row->order_interval }}" selected>{{ $row->order_interval }}</option>
                                        @endif
                                    </select>
                                </td>

                                @php
                                    $catDisplay = '-';
                                    $catPerson  = '-';
                                    if ($row->newBiz->name ?? null) {
                                        $catDisplay = 'New Biz';
                                        $catPerson  = $row->newBiz->name;
                                    } elseif ($row->ccare->name ?? null) {
                                        $catDisplay = 'CCare';
                                        $catPerson  = $row->ccare->name;
                                    }
                                @endphp

                                {{-- Category column — editable by every role on this page --}}
                                <td class="col-category align-middle text-center">
                                    <select class="excel-select row-field status-select text-truncate" data-field="category" style="width:85px;">
                                        <option value="">-Select-</option>
                                        <option value="New Biz" {{ $catDisplay === 'New Biz' ? 'selected' : '' }}>New Biz</option>
                                        <option value="CCare" {{ $catDisplay === 'CCare' ? 'selected' : '' }}>CCare</option>
                                    </select>
                                    @if($row->ccare_from)
                                        @php $movedFrom = \Carbon\Carbon::parse($row->ccare_from . '-01'); @endphp
                                        <div class="cat-moved"
                                             title="New Biz{{ $row->formerNewBiz ? ' (' . $row->formerNewBiz->name . ')' : '' }} until {{ $movedFrom->copy()->subMonth()->format('M Y') }}; CCare from {{ $movedFrom->format('M Y') }}">
                                            New Biz till {{ $movedFrom->copy()->subMonth()->format('M y') }}
                                        </div>
                                    @endif
                                </td>

                                {{-- Representative — click to pick any CCare/New Biz agent --}}
                                <td class="col-repname text-truncate rep-cell" title="{{ $catPerson }} (click to change)"
                                    style="cursor:pointer;"
                                    data-cc="{{ $row->ccare_id }}" data-nb="{{ $row->new_biz_id }}">{{ $catPerson }}</td>

                                <td class="col-potential p-0">
                                    <input type="number" step="0.01" class="excel-input row-field text-end font-monospace fw-bold"
                                           data-field="total_business_potential"
                                           value="{{ ($row->total_business_potential ?? 0) + 0 }}">
                                </td>

                                @unless($isSalesperson)
                                    {{-- Salesperson — click to reassign --}}
                                    <td class="col-salesperson text-truncate sp-cell" title="{{ $row->salesperson->name ?? '-' }} (click to change)"
                                        style="cursor:pointer;" data-sp="{{ $row->salesperson_id }}">{{ $row->salesperson->name ?? '-' }}</td>
                                @endunless

                                <td class="p-0 align-middle" style="max-width:150px;">
                                    <select class="excel-select row-field status-select text-truncate"
                                            data-field="product">
                                        <option value="">-Select-</option>
                                        <option value="Rust-X"      {{ $row->product === 'Rust-X'      ? 'selected' : '' }}>Rust-X</option>
                                        <option value="Dr.Bio"      {{ $row->product === 'Dr.Bio'      ? 'selected' : '' }}>Dr.Bio</option>
                                        <option value="Fillezy"     {{ $row->product === 'Fillezy'     ? 'selected' : '' }}>Fillezy</option>
                                        <option value="KIF"         {{ $row->product === 'KIF'         ? 'selected' : '' }}>KIF</option>
                                        <option value="Zorbit"      {{ $row->product === 'Zorbit'      ? 'selected' : '' }}>Zorbit</option>
                                        <option value="Tuffpaulin"  {{ $row->product === 'Tuffpaulin'  ? 'selected' : '' }}>Tuffpaulin</option>
                                        <option value="Neutrasafe"  {{ $row->product === 'Neutrasafe'  ? 'selected' : '' }}>Neutrasafe</option>
                                        {{-- Any value not in the list above (including the legacy "HITECH")
                                             is preserved and shown as the selected option, so removing a
                                             brand never silently rewrites existing rows. --}}
                                        @if($row->product && !in_array($row->product, ['Rust-X', 'Dr.Bio', 'Fillezy', 'KIF', 'Zorbit', 'Tuffpaulin', 'Neutrasafe']))
                                            <option value="{{ $row->product }}" selected>{{ $row->product }}</option>
                                        @endif
                                    </select>
                                </td>

                                {{-- Other Reps — extra people who also see this customer --}}
                                <td class="align-middle extra-cell" data-id="{{ $row->id }}" data-type="other"
                                    style="min-width:130px;cursor:pointer;" title="New BIZ2 — CCare / New Biz people; click to add">
                                    @foreach($row->extraReps as $er)
                                        <span class="badge bg-label-secondary extra-tag" data-uid="{{ $er->user_id }}" style="font-size:0.62rem;">
                                            {{ $er->user->name ?? ('#'.$er->user_id) }} <i class="bx bx-x remove-extra" style="cursor:pointer;"></i>
                                        </span>
                                    @endforeach
                                </td>

                                {{-- Service Sales — salespersons attached to this customer --}}
                                <td class="align-middle block-end extra-cell" data-id="{{ $row->id }}" data-type="service"
                                    style="min-width:130px;cursor:pointer;" title="Service Sales — click to add a salesperson">
                                    @foreach($row->serviceReps as $sr)
                                        <span class="badge bg-label-info extra-tag" data-uid="{{ $sr->user_id }}" style="font-size:0.62rem;">
                                            {{ $sr->user->name ?? ('#'.$sr->user_id) }} <i class="bx bx-x remove-extra" style="cursor:pointer;"></i>
                                        </span>
                                    @endforeach
                                </td>

                                @foreach($months as $m)
                                    @php
                                        $mData       = $row->months->firstWhere('month_year', $m['key']);
                                        $isPast      = $m['key'] < $currentMonthKey;
                                        $isThisMonth = ($m['key'] === $currentMonthKey);
                                        $prevMonthKey = \Carbon\Carbon::createFromFormat('Y-m-d', $currentMonthKey.'-01')->subMonth()->format('Y-m');
                                        $showPend    = $isThisMonth || ($m['key'] === $prevMonthKey);
                                        // A month is open to non-Admin/HR roles only while it is
                                        // the current month, or last month through the 5th of this
                                        // one (grace period for month-end reconciliation). Once it
                                        // closes, only Admin/HR can still edit it — mirrors
                                        // isMonthLockedForUser() server-side.
                                        // A one-off reopen (SalesPipelineController::isReopened) lifts the
                                        // lock for everyone until it expires by itself. A narrower
                                        // one (isReopenedForUser) lifts it for a single named user only.
                                        $monthLocked = !$isAdminOrHr
                                            && !\App\Http\Controllers\tenant\SalesPipelineController::isReopened($m['key'])
                                            && !\App\Http\Controllers\tenant\SalesPipelineController::isReopenedForUser(auth()->user(), $m['key'])
                                            && !($isThisMonth || ($m['key'] === $prevMonthKey && \Carbon\Carbon::now()->day <= 5));
                                        // Sale / Pending / Remarks stay the book editors' job
                                        // (CCare / New Biz / manager / admin).
                                        $roAttr      = ($canEditBook && !$monthLocked) ? '' : 'readonly';
                                        // A plain salesperson (canEditOwnRow but not canEditBook)
                                        // may fill in ONLY the Forecast figure, on any customer row
                                        // they can see. It used to also require the row to be
                                        // unlocked and personally theirs, but the Sales Report lists
                                        // only LOCKED rows, so the permission could never apply there
                                        // and every cell rendered readonly.
                                        $isMyOpenRow = $canEditOwnRow && !$canEditBook;
                                        $fcstRoAttr  = (($canEditBook || $isMyOpenRow) && !$monthLocked) ? '' : 'readonly';
                                        // A customer that moved New Biz -> CCare: its months before
                                        // the move are New Biz months, and a month outside the
                                        // category / person in view is left out of the totals row.
                                        $nbEra   = $row->ccare_from && $m['key'] < $row->ccare_from;
                                        $counted = (!$monthCategory || ($monthCategory === 'New Biz') === $row->isNewBizMonth($m['key']))
                                            && collect($creditPeople)->every(fn ($pid) => $row->creditsMonth($pid, $m['key']));
                                        $eraCls  = ($nbEra ? ' nb-era' : '') . ($counted ? '' : ' month-out');
                                        $eraTip  = !$row->ccare_from ? null
                                            : ($nbEra ? 'New Biz month' : 'CCare month') . ($counted ? '' : ' (not counted in this view)');
                                    @endphp
                                    @if($showPend)
                                        <td class="p-0 bg-forecast-light{{ $eraCls }}" style="max-width:52px;" @if($eraTip) title="{{ $eraTip }}" @endif>
                                            <input type="number" step="0.01"
                                                   class="excel-input text-end month-field font-monospace"
                                                   data-month="{{ $m['key'] }}" data-field="forecast_amount"
                                                   value="{{ $mData && $mData->forecast_amount ? $mData->forecast_amount : '' }}"
                                                   {!! $fcstRoAttr !!}>
                                        </td>
                                    @endif
                                    <td class="p-0 bg-sale-light {{ !$isThisMonth ? 'block-end' : '' }}{{ $eraCls }}" style="max-width:55px;" @if($eraTip) title="{{ $eraTip }}" @endif>
                                        @php $isCredit = $mData && (float) $mData->sale_amount < 0; @endphp
                                        <input type="number" step="0.01"
                                               class="excel-input text-end month-field font-monospace{{ $isCredit ? ' is-credit-note' : '' }}"
                                               data-month="{{ $m['key'] }}" data-field="sale_amount"
                                               value="{{ $mData && $mData->sale_amount ? $mData->sale_amount : '' }}"
                                               @if($isCredit) title="Credit Note" @endif
                                               {!! $roAttr !!}>
                                    </td>
                                    @if($showPend)
                                        <td class="p-0 bg-pending-light {{ !$isThisMonth ? 'block-end' : '' }}{{ $eraCls }}" style="max-width:52px;" @if($eraTip) title="{{ $eraTip }}" @endif>
                                            <input type="number" step="0.01"
                                                   class="excel-input text-end month-field font-monospace"
                                                   data-month="{{ $m['key'] }}" data-field="pending_amount"
                                                   value="{{ $mData && $mData->pending_amount ? $mData->pending_amount : '' }}"
                                                   {!! $roAttr !!}>
                                        </td>
                                    @endif
                                    @if($isThisMonth)
                                        <td class="p-0 block-end" style="max-width:95px;">
                                            <input type="text" class="excel-input month-field"
                                                   data-month="{{ $m['key'] }}" data-field="remarks"
                                                   value="{{ $mData ? $mData->remarks : '' }}"
                                                   {!! $roAttr !!}>
                                        </td>
                                    @endif
                                @endforeach
                                @if($canDeleteCustomer)
                                    <td class="text-center align-middle">
                                        <form action="{{ route('sales-visits.pipeline.delete-request.store', $row->id) }}" method="POST"
                                              class="js-delete-customer" data-party="{{ $row->party_name }}"
                                              style="display:inline-block;">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-icon btn-text-danger"
                                                    style="padding:0;background:none;border:none;" title="Request deletion of this customer">
                                                <i class="bx bx-trash text-danger" style="font-size:1.05rem;"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr id="emptyRow">
                                <td colspan="100" class="text-center py-4 text-muted">
                                    No pipeline data found. Click "+ Add New Entry" below to start.
                                </td>
                            </tr>
                        @endforelse

                        {{-- ── Add New Entry — full-width form, works for every role ── --}}
                        <tr id="addNewRow" style="display:none;background:#f0fdf4;">
                            <td colspan="100" class="p-3">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div style="min-width:180px;">
                                        <label class="form-label small fw-bold mb-1 text-success">Customer Name *</label>
                                        <input type="text" id="new_party_name" class="form-control form-control-sm" placeholder="Customer Name">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">Type</label>
                                        <select id="new_type" class="form-select form-select-sm" style="min-width:120px;">
                                            <option value="">-Type-</option>
                                            <option value="Customer">Customer</option>
                                            <option value="Distributor">Distributor</option>
                                            <option value="Dealer">Dealer</option>
                                            <option value="Packer">Packer</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">Salesperson</label>
                                        <select id="new_salesperson_id" class="form-select form-select-sm" style="min-width:150px;">
                                            <option value="">-Salesperson-</option>
                                            @foreach($salespersons as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">New Biz</label>
                                        <select id="new_new_biz_id" class="form-select form-select-sm" style="min-width:140px;">
                                            <option value="">-New Biz-</option>
                                            @foreach($newBizUsers as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">CCare</label>
                                        <select id="new_ccare_id" class="form-select form-select-sm" style="min-width:140px;">
                                            <option value="">-CCare-</option>
                                            @foreach(($ccareUsers ?? $assignedUsers) as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">Brand</label>
                                        <select id="new_product" class="form-select form-select-sm" style="min-width:120px;">
                                            <option value="">-Brand-</option>
                                            <option value="Rust-X">Rust-X</option>
                                            <option value="Dr.Bio">Dr.Bio</option>
                                            <option value="Fillezy">Fillezy</option>
                                            <option value="KIF">KIF</option>
                                            <option value="Zorbit">Zorbit</option>
                                            <option value="Tuffpaulin">Tuffpaulin</option>
                                            <option value="Neutrasafe">Neutrasafe</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">Potential ₹L</label>
                                        <input type="number" id="new_potential" step="0.01" class="form-control form-control-sm text-end" style="width:110px;" placeholder="0.00">
                                    </div>
                                    {{-- Month sale figures (+ current-month forecast/pending) --}}
                                    @foreach($months as $m)
                                        @php
                                    $isThisMonth = ($m['key'] === $currentMonthKey);
                                    $prevMonthKey = \Carbon\Carbon::createFromFormat('Y-m-d', $currentMonthKey.'-01')->subMonth()->format('Y-m');
                                    $showPend = $isThisMonth || ($m['key'] === $prevMonthKey);
                                @endphp
                                        <div>
                                            <label class="form-label small fw-bold mb-1" style="color:#065f46;">{{ $m['display'] }} Sale</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm text-end new-month" data-month="{{ $m['key'] }}" data-field="sale_amount" style="width:80px;" placeholder="₹L">
                                        </div>
                                        @if($isThisMonth)
                                        <div>
                                            <label class="form-label small fw-bold mb-1" style="color:#1e40af;">{{ $m['display'] }} Fcst</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm text-end new-month" data-month="{{ $m['key'] }}" data-field="forecast_amount" style="width:80px;" placeholder="₹L">
                                        </div>
                                        <div>
                                            <label class="form-label small fw-bold mb-1" style="color:#9a3412;">{{ $m['display'] }} Pend</label>
                                            <input type="number" step="0.01" class="form-control form-control-sm text-end new-month" data-month="{{ $m['key'] }}" data-field="pending_amount" style="width:80px;" placeholder="₹L">
                                        </div>
                                        @endif
                                    @endforeach
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-success px-3" onclick="saveNewPipeline()">
                                            <i class="bx bx-save me-1"></i>Save
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="cancelNewPipeline()">
                                            <i class="bx bx-x me-1"></i>Cancel
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
        </table>
    </div>

    {{-- ─── PAGINATION (server-side, 50 per page) ────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
        <span class="text-muted small">
            Showing {{ $pipelines->firstItem() ?? 0 }}–{{ $pipelines->lastItem() ?? 0 }} of {{ $pipelines->total() }}
        </span>
        <div>{{ $pipelines->onEachSide(1)->links() }}</div>
    </div>

    {{-- Add New Entry trigger — hidden for read-only roles (plain salespeople). --}}
    <div class="d-flex justify-content-center py-2 mt-1 @unless($canEditBook) d-none @endunless">
        <button class="btn btn-sm btn-outline-success rounded-pill px-4" id="addRowTriggerBtn"
                onclick="showAddNewRow()" style="border-color:#0f766e;color:#0f766e;">
            <i class="bx bx-plus-circle me-1"></i> Add New Entry
        </button>
    </div>
</div>

{{-- ─── THIS-MONTH TARGET ACHIEVEMENT MODAL ──────────────────────────── --}}
@php
    // Colour a target cell by how close the actual sale is to that target.
    $tgtCell = function ($actual, $target) {
        if ($target <= 0) return ['cls' => 'text-muted', 'style' => ''];                       // no target
        $p = $actual / $target;
        if ($p >= 1)    return ['cls' => 'text-white fw-bold', 'style' => 'background:#16a34a;']; // achieved → green
        if ($p >= 0.75) return ['cls' => 'text-dark fw-bold',  'style' => 'background:#fde047;']; // ≥75% → yellow
        if ($p >= 0.50) return ['cls' => 'text-white',         'style' => 'background:#fb923c;']; // 50–75% → orange
        return ['cls' => 'text-white', 'style' => 'background:#dc2626;'];                          // <50% → red
    };
@endphp
<div class="modal fade" id="targetAchieveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            @php
                // Newest month first — people look at the current month, then step back.
                $tgtMonthKeys = array_reverse($achieveMonthKeys ?? [$currentMonthKey]);
                $tgtMonthLabel = collect($months)->keyBy('key')->map(fn($m) => $m['display']);
            @endphp
            <div class="modal-header d-flex flex-wrap gap-2 align-items-center" style="background:linear-gradient(90deg,#0d9488,#0f766e);">
                <h5 class="modal-title text-white fw-bold mb-0">Target Achievement</h5>

                {{-- Month picker. Every elapsed month of the FY is already rendered
                     below, so switching is instant — no extra request. --}}
                <select id="tgtMonthPicker" class="form-select form-select-sm ms-2 fw-bold border-0"
                        style="width:auto; background-color:rgba(255,255,255,.92); color:#0f766e;">
                    @foreach($tgtMonthKeys as $mk)
                        <option value="{{ $mk }}" {{ $mk === $currentMonthKey ? 'selected' : '' }}>
                            {{ $tgtMonthLabel[$mk] ?? $mk }}{{ $mk === $currentMonthKey ? ' (this month)' : '' }}
                        </option>
                    @endforeach
                </select>

                {{-- Department filter — client-side only, same instant-switch
                     design as the month picker; every category is already
                     rendered, this just shows/hides rows. --}}
                <select id="tgtDeptPicker" class="form-select form-select-sm fw-bold border-0"
                        style="width:auto; background-color:rgba(255,255,255,.92); color:#0f766e;">
                    <option value="">All Departments</option>
                    <option value="Salesperson">Salesperson</option>
                    <option value="Export">Export</option>
                    <option value="CCare">CCare</option>
                    <option value="New Biz">New Biz</option>
                    <option value="CCare & New Biz">CCare &amp; New Biz</option>
                </select>

                <a id="tgtExportBtn" class="btn btn-sm btn-light fw-bold d-flex align-items-center"
                   href="{{ route('sales-visits.pipeline.targets.export', ['month' => $currentMonthKey, 'fy' => $selectedFy]) }}">
                    <i class="bx bx-download me-1"></i> Excel
                </a>

                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                {{-- Legend stays static; ONLY the table below scrolls, so the
                     title, legend and column header are always pinned. --}}
                <div class="d-flex gap-3 px-3 py-2 small text-muted border-bottom flex-wrap">
                    <span><span class="d-inline-block" style="width:12px;height:12px;background:#16a34a;border-radius:2px;"></span> Achieved</span>
                    <span><span class="d-inline-block" style="width:12px;height:12px;background:#fde047;border-radius:2px;"></span> ≥75%</span>
                    <span><span class="d-inline-block" style="width:12px;height:12px;background:#fb923c;border-radius:2px;"></span> 50–75%</span>
                    <span><span class="d-inline-block" style="width:12px;height:12px;background:#dc2626;border-radius:2px;"></span> &lt;50%</span>
                    <span><span class="d-inline-block" style="width:12px;height:12px;background:#fef2f2;border:1px solid #dc2626;border-radius:2px;"></span> Tally ≠ Actual Sale</span>
                    <span class="ms-auto">All figures ₹ Lakh</span>
                </div>
                <style>.tgt-table td,.tgt-table th{padding:.28rem .35rem !important;white-space:nowrap;}.tgt-table td:first-child,.tgt-table th:first-child{white-space:normal;}.tgt-table tr.tgt-mm td:nth-child(-n+5){background:#fef2f2 !important;}.tgt-table tr.tgt-mm td:first-child{border-left:3px solid #dc2626 !important;color:#991b1b;}.tgt-table tr.tgt-mm .tgt-actual{color:#b91c1c !important;}.tgt-table tr.tgt-mm .tgt-tally{color:#b91c1c !important;font-weight:700;}.tgt-table tr.tgt-mm .tgt-tally-cell{box-shadow:inset 0 0 0 2px #dc2626;}</style>
                <div style="max-height:62vh;overflow-y:auto;">
                <table class="table table-bordered table-sm mb-0 align-middle tgt-table" style="font-size:0.76rem;border-collapse:separate;border-spacing:0;">
                    {{-- Sticky header needs its own stacking context + solid bg,
                         otherwise the coloured rows paint OVER it while scrolling. --}}
                    <thead class="table-light">
                        <tr>
                            <th style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;width:auto;">Salesperson</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;width:74px;min-width:74px;padding:.3rem .35rem;">Fcst</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;width:74px;min-width:74px;padding:.3rem .35rem;">Actual<br>Sale</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;width:74px;min-width:74px;padding:.3rem .35rem;">Pending</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#eef7f6;box-shadow:inset 0 -1px 0 #dee2e6;width:86px;min-width:86px;padding:.3rem .35rem;" title="Entered by CCare / New Biz">Tally<br>Sale</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;width:74px;min-width:74px;padding:.3rem .35rem;">Bingo</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;width:74px;min-width:74px;padding:.3rem .35rem;">Kingo</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;width:74px;min-width:74px;padding:.3rem .35rem;">Super<br>Kingo</th>
                        </tr>
                    </thead>
                    {{-- One tbody per elapsed month; the picker shows exactly one.
                         Rendered server-side so the colour thresholds stay in PHP
                         rather than being duplicated in JS. --}}
                    @foreach($tgtMonthKeys as $mk)
                        @php $rowsForMonth = ($targetAchieversByMonth[$mk] ?? ($mk === $currentMonthKey ? ($targetAchievers ?? []) : [])); @endphp
                        <tbody class="tgt-month-body" data-month="{{ $mk }}" @if($mk !== $currentMonthKey) style="display:none;" @endif>
                            @forelse($rowsForMonth as $r)
                                {{-- Tally Sale is typed in by CCare / New Biz, so it is the one
                                     figure on this row that nobody derived from the book. When it
                                     disagrees with Actual Sale the row is flagged red, because the
                                     two are meant to reconcile and a gap means one of them is
                                     wrong. Blank is not a mismatch: nobody has filled it in yet,
                                     which reads differently from a recorded 0. --}}
                                @php
                                    $tgtLocked = \App\Http\Controllers\tenant\SalesPipelineController::isMonthLockedForUser(auth()->user(), $mk);
                                    $tallyVal  = $r['tally'] ?? null;
                                    // Half a paisa of tolerance, so a rounding tail does not read
                                    // as a discrepancy.
                                    $tallyDiff = $tallyVal === null ? null : round((float) $tallyVal - (float) $r['actual'], 2);
                                    $tallyMm   = $tallyDiff !== null && abs($tallyDiff) > 0.005;
                                @endphp
                                <tr class="tgt-row {{ $tallyMm ? 'tgt-mm' : '' }}" data-category="{{ $r['category'] ?? 'Salesperson' }}"
                                    @if($tallyMm) title="Tally {{ number_format($tallyVal, 2) }} vs Actual {{ number_format($r['actual'], 2) }} — off by {{ number_format(abs($tallyDiff), 2) }}" @endif>
                                    <td class="fw-semibold">
                                        @if($tallyMm)<i class="bx bx-error-circle text-danger me-1" style="vertical-align:-2px;"></i>@endif{{ $r['name'] }}
                                    </td>
                                    <td class="text-end font-monospace" style="color:#1d4ed8;">{{ ($r['forecast'] ?? 0) > 0 ? number_format($r['forecast'], 2) : '—' }}</td>
                                    <td class="text-end font-monospace fw-bold tgt-actual">{{ number_format($r['actual'], 2) }}</td>
                                    <td class="text-end font-monospace" style="color:#9a3412;">{{ ($r['pending'] ?? 0) > 0 ? number_format($r['pending'], 2) : '—' }}</td>
                                    {{-- Editable only while the month is open, under the same rule
                                         the Sale/Pending figures use, so this cannot drift from the
                                         rest of the book. --}}
                                    <td class="text-end p-0 tgt-tally-cell" style="background:#f4fbfa;">
                                        @if($canEditBook && !$tgtLocked)
                                            <input type="number" step="0.01" min="0"
                                                   class="form-control form-control-sm text-end font-monospace border-0 tgt-tally"
                                                   style="background:transparent;font-size:0.76rem;padding:.28rem .35rem;box-shadow:none;"
                                                   value="{{ $tallyVal !== null ? number_format($tallyVal, 2, '.', '') : '' }}"
                                                   data-sp="{{ $r['id'] }}" data-month="{{ $mk }}"
                                                   data-actual="{{ number_format((float) $r['actual'], 2, '.', '') }}"
                                                   placeholder="—" title="Tally sale for {{ $r['name'] }}">
                                        @else
                                            <span class="d-block text-end font-monospace px-1"
                                                  style="font-size:0.76rem;{{ $tallyVal === null ? 'color:#9ca3af;' : '' }}"
                                                  title="{{ $tgtLocked ? 'This month is closed' : 'You cannot edit the sales book' }}">
                                                {{ $tallyVal !== null ? number_format($tallyVal, 2) : '—' }}
                                            </span>
                                        @endif
                                    </td>
                                    @foreach(['bingo','kingo','super'] as $tier)
                                        @php $c = $tgtCell($r['actual'], $r[$tier]); @endphp
                                        <td class="text-end font-monospace {{ $c['cls'] }}" style="{{ $c['style'] }}">
                                            @if($r[$tier] > 0)
                                                {{ number_format($r[$tier], 2) }}
                                                @if($r['actual'] >= $r[$tier])<i class="bx bx-check"></i>@endif
                                            @else — @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">No targets set for {{ $tgtMonthLabel[$mk] ?? $mk }}.</td></tr>
                            @endforelse
                        </tbody>
                    @endforeach
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ─── TARGETS MODAL (Admin/HR only) ───────────────────────────────── --}}
@if($isAdmin)
<div class="modal fade" id="targetsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#0f766e;color:white;">
                <div>
                    <h5 class="modal-title mb-0">Assign KINGO &amp; BINGO Targets</h5>
                    <small class="opacity-75">Monthly sale targets are set individually per salesperson</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <label class="form-label fw-bold">Select Month</label>
                    <select id="targetMonthSelect" class="form-select" style="border-color:#0f766e;">
                        <option value="">-- Choose Month --</option>
                        @foreach($months as $m)
                            <option value="{{ $m['key'] }}">{{ $m['display'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="targetsTableContainer" style="display:none;">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bx bx-info-circle me-1"></i>
                        Targets below apply to the selected month. Each row is saved individually when you change a value.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm text-center align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-start ps-3">Salesperson</th>
                                    <th>Kingo Target (₹)</th>
                                    <th>Bingo Target (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salespersons as $u)
                                    <tr>
                                        <td class="fw-bold text-start ps-3">{{ $u->name }}</td>
                                        <td>
                                            <input type="number" step="0.01"
                                                   class="form-control text-end target-input"
                                                   data-sp="{{ $u->id }}" data-field="kingo_target"
                                                   placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01"
                                                   class="form-control text-end target-input"
                                                   data-sp="{{ $u->id }}" data-field="bingo_target"
                                                   placeholder="0.00">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Setu sync preview + review modals (shared with the Setu Sales Sync page). --}}
@if($syncTargetId && $syncMapped)
    @include('tenant.partials.setu-preview-modal')
    @include('tenant.partials.setu-review-modal')
@endif

{{-- CRM mapping preview + review modals — link Sales Report customers to their CRM.API account. --}}
@if($syncTargetId)
    @include('tenant.partials.crm-mapping-preview-modal')
    @include('tenant.partials.crm-mapping-review-modal')
@endif

@if(\App\Support\SalesScope::canClaimCustomer($user))
    @include('tenant.partials.customer-claim-modal')
@endif

{{-- Per-customer Setu verification badge/popup — click any badge in the Party
     Name column to confirm or search-and-map. --}}
@include('tenant.partials.customer-setu-mapping-modal')
@include('tenant.partials.customer-tally-mapping-modal')

{{-- ─── STYLES ──────────────────────────────────────────────────────── --}}
<style>
    /* Compact stats strip */
    .stat-chip {
        display: flex; flex-direction: column; justify-content: center;
        background: #fff; border-left: 4px solid #0f766e;
        border-radius: 6px; padding: 0.45rem 0.9rem;
        box-shadow: 0 1px 4px rgba(0,0,0,.10);
        min-width: 130px;
    }
    .stat-label {
        font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: #64748b; line-height: 1.2; margin-bottom: 2px;
    }
    .stat-value {
        font-size: 1rem; font-weight: 700; color: #0f172a; line-height: 1.2;
    }
    .stat-value em { font-style: normal; font-size: 0.7rem; color: #94a3b8; margin-left: 1px; }

    .excel-input, .excel-select {
        width: 100%; height: 100%; min-height: 28px;
        border: none !important; outline: none !important;
        background: transparent !important;
        padding: 0.2rem 0.5rem; font-size: 0.75rem;
        border-radius: 0 !important; box-shadow: none !important; color: #333;
    }
    .excel-select {
        appearance: none; -webkit-appearance: none;
        background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.4-12.8z%22%2F%3E%3C%2Fsvg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right .5rem top 50% !important;
        background-size: .65em auto !important;
        padding-right: 1.5rem; cursor: pointer;
    }
    .excel-input:focus, .excel-select:focus {
        background-color: #fff !important;
        box-shadow: inset 0 0 0 2px #0d9488 !important;
    }
    .status-select { font-weight: 700; font-size: 0.75rem; }

    #pipelineTable { background-color: #fff; }
    #pipelineTable th, #pipelineTable td {
        padding: 0.35rem 0.5rem !important; font-size: 0.75rem;
        vertical-align: middle; border-color: #e2e8f0;
    }
    #pipelineTable td.p-0 { padding: 0 !important; }
    #pipelineTable thead th {
        background-color: #0f766e !important; color: #fff !important;
        border-color: #0b534d !important;
        white-space: normal !important; line-height: 1.2;
        text-align: center !important; vertical-align: middle !important;
    }
    #pipelineTable thead th.col-sno,
    #pipelineTable thead th.col-party {
        position: sticky !important; z-index: 30 !important; top: 0 !important;
    }
    #pipelineTable tbody td.col-sno,
    #pipelineTable tbody td.col-party {
        background-color: #fff !important; z-index: 20;
    }
    .col-sno        { position:sticky; left:0;     min-width:40px;  max-width:40px; }
    .col-party      { position:sticky; left:40px;  min-width:150px; max-width:150px; overflow:hidden; white-space:normal !important; vertical-align:middle; }
    .col-party .party-name {
        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
        font-size:0.7rem; line-height:1.15; font-weight:600; word-break:break-word;
    }
    /* Only S.No + Party Name are frozen (row identity). The other leading
       columns scroll normally — they used to be sticky with stale left offsets
       (potential at 300px, salesperson at 395px) that no longer matched the
       real layout once Category/Representative were added, so they painted on
       top of the scrolling cells. */
    .col-status     { min-width:110px; max-width:110px; }
    .col-potential  { min-width:95px;  max-width:95px; }
    .col-salesperson{ min-width:120px; max-width:120px; overflow:hidden; }
    .col-category   { min-width:90px;  max-width:100px; }
    .col-repname    { min-width:130px; max-width:150px; overflow:hidden; text-overflow:ellipsis; }

    /* Excel-style column resize handle, appended to every <th> by JS. */
    #pipelineTable .col-resize-handle {
        position: absolute; top: 0; right: 0; bottom: 0; width: 6px;
        cursor: col-resize; z-index: 50; user-select: none;
    }
    #pipelineTable .col-resize-handle:hover,
    #pipelineTable .col-resize-handle:active {
        background: rgba(255,255,255,0.35);
    }

    .block-end { border-right: 2px solid #64748b !important; }
    .bg-forecast-light { background-color: #eff6ff !important; }
    .bg-forecast-light input { color: #1e40af !important; font-weight: 600; }
    /* Hide number input spinners */
    #pipelineTable input[type=number]::-webkit-outer-spin-button,
    #pipelineTable input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    #pipelineTable input[type=number] { -moz-appearance: textfield; appearance: textfield; }
    .bg-sale-light { background-color: #ecfdf5 !important; }
    .bg-sale-light input { color: #065f46 !important; font-weight: 600; }
    .bg-pending-light { background-color: #fff7ed !important; }
    .bg-pending-light input { color: #9a3412 !important; font-weight: 600; }
    /* Scoped to the main report table only — this rule used to leak into the
       achievement modal and wipe its coloured cells on hover (white on white). */
    #pipelineTable tbody tr:hover td { background-color: transparent !important; }
    /* ...except the two frozen columns: transparent there let the cells
       scrolled underneath show through on hover (Salesperson and Brand drawn
       over the Party Name). They need a solid fill to cover what scrolls under. */
    #pipelineTable tbody tr:hover td.col-sno,
    #pipelineTable tbody tr:hover td.col-party { background-color: #f6faf9 !important; }
    /* Columns switched off in the Columns menu */
    #pipelineTable .sr-col-hidden { display: none !important; }
    .converted-row td { background-color: #fdf4ff !important; border-bottom: 2px solid #e879f9 !important; }
    .converted-row .col-party::after { content:' (Converted)'; color:#d946ef; font-size:0.7rem; font-weight:bold; }
    /* A customer that moved New Biz -> CCare: "New Biz till ..." under its
       Category, a violet underline on its New Biz months, and greyed figures
       in months the current Category / person filter leaves out of the totals. */
    .cat-moved { font-size: 0.58rem; color: #a21caf; font-weight: 600; line-height: 1.1; white-space: nowrap; }
    td.nb-era { box-shadow: inset 0 -2px 0 #e879f9; }
    td.month-out input { color: #9ca3af !important; font-style: italic; }
    .pipeline-row-hidden { display: none !important; }
</style>

{{-- Shared dropdown templates for click-to-edit cells (rendered ONCE — the
     rows carry plain text, so 50 rows don't each embed an 80-option select) --}}
<template id="tplRepSelect">
    <select class="excel-select" style="width:100%;min-width:120px;">
        <option value="">— No Rep —</option>
        <optgroup label="CCare">
            @foreach($ccareUsers as $u)<option value="cc:{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </optgroup>
        <optgroup label="New Biz">
            @foreach($newBizUsers as $u)<option value="nb:{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </optgroup>
    </select>
</template>
<template id="tplSpSelect">
    <select class="excel-select" style="width:100%;min-width:120px;">
        <option value="">— Select —</option>
        @foreach($salespersons as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
    </select>
</template>
{{-- Other Reps: CCare / New Biz people only. Salespersons were removed here —
     they belong in the Service Sales column instead. --}}
<template id="tplExtraSelect">
    <select class="excel-select" style="width:100%;min-width:120px;">
        <option value="">— Add person —</option>
        @if(($bothUsers ?? collect())->count())
        <optgroup label="CCare &amp; New Biz">
            @foreach($bothUsers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </optgroup>
        @endif
        <optgroup label="CCare">
            @foreach(($ccareOnly ?? $ccareUsers) as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </optgroup>
        <optgroup label="New Biz">
            @foreach(($newBizOnly ?? $newBizUsers) as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </optgroup>
    </select>
</template>

{{-- Service Sales: salespersons only. --}}
<template id="tplServiceSelect">
    <select class="excel-select" style="width:100%;min-width:120px;">
        <option value="">— Add salesperson —</option>
        @foreach($salespersons as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
    </select>
</template>

{{-- ─── SCRIPTS ─────────────────────────────────────────────────────── --}}
<script>
// Excel-style drag-to-resize columns. Width persists per-column in
// localStorage (keyed by table id + column index) so it survives reloads.
function makeTableColumnsResizable(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    var storagePrefix = 'colw_' + tableId + '_';

    table.querySelectorAll('thead th').forEach(function (th, idx) {
        var saved = localStorage.getItem(storagePrefix + idx);
        if (saved) {
            th.style.width = saved + 'px';
            th.style.minWidth = saved + 'px';
            th.style.maxWidth = saved + 'px';
        }

        if (th.querySelector('.col-resize-handle')) return;
        if (getComputedStyle(th).position === 'static') th.style.position = 'relative';

        var handle = document.createElement('span');
        handle.className = 'col-resize-handle';
        th.appendChild(handle);

        // Headers here are often clickable (sort) -- a resize drag must
        // never bubble up and trigger that.
        handle.addEventListener('click', function (e) { e.stopPropagation(); });
        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var startX = e.pageX;
            var startWidth = th.offsetWidth;
            document.body.style.userSelect = 'none';
            document.body.style.cursor = 'col-resize';

            function onMove(e2) {
                var newWidth = Math.max(40, startWidth + (e2.pageX - startX));
                th.style.width = newWidth + 'px';
                th.style.minWidth = newWidth + 'px';
                th.style.maxWidth = newWidth + 'px';
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.body.style.userSelect = '';
                document.body.style.cursor = '';
                localStorage.setItem(storagePrefix + idx, th.offsetWidth);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    makeTableColumnsResizable('pipelineTable');

    // A negative sale is a credit note: red, and named on hover. Applied on
    // load and after each inline edit so a freshly typed -1.5 turns red at once.
    // Always defined (not gated on canEditBook) -- it's read-only styling,
    // and the month-field listener below (also always attached) calls it.
    function markCreditNotes(scope) {
        (scope || document).querySelectorAll('.month-field[data-field="sale_amount"]').forEach(el => {
            const isCredit = parseFloat(el.value) < 0;
            el.classList.toggle('is-credit-note', isCredit);
            if (isCredit) el.setAttribute('title', 'Credit Note'); else el.removeAttribute('title');
        });
        document.querySelectorAll('[id^="mtotal-"]').forEach(td => {
            const isCredit = parseFloat(String(td.textContent).replace(/,/g, '')) < 0;
            td.classList.toggle('is-credit-note', isCredit);
            if (isCredit) td.setAttribute('title', 'Credit Note');
        });
    }
    document.addEventListener('DOMContentLoaded', () => markCreditNotes());

    {{-- Inline editing on month cells (Sale / Fcst / Pending / Remarks) is
         attached for everyone -- the `readonly` attribute rendered per cell
         above (canEditBook, or a plain salesperson's own open-row Forecast
         cell, and never a closed month) is the real gate, re-checked by
         monthUpdate() server-side. Row-field / rep / salesperson / extra-rep
         editing below stays canEditBook-only: those change who owns the
         book, not just a figure on it. --}}
    // ── Inline edit: month-field ──────────────────────────────────────
    document.querySelectorAll('.month-field').forEach(field => {
        field.addEventListener('change', function() {
            if (this.hasAttribute('readonly')) {
                alert('This field cannot be edited — either the month is closed or this figure is not yours to update.');
                return;
            }
            const tr       = this.closest('tr');
            const id       = tr.dataset.id;
            const month    = this.dataset.month;
            const fieldKey = this.dataset.field;
            const val      = this.value;
            markCreditNotes(tr);

            fetch(`{{ route('sales-visits.pipeline.month.update') }}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ sales_pipeline_id: id, month_year: month, field: fieldKey, value: val })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    this.style.backgroundColor = '#e8f5e9';
                    setTimeout(() => { this.style.backgroundColor = 'transparent'; }, 500);
                    // 5th month with sales: the customer has just moved to CCare.
                    if (data.moved_to_ccare) {
                        const cat = tr.querySelector('select[data-field="category"]');
                        if (cat) cat.value = 'CCare';
                        const rep = tr.querySelector('.rep-cell');
                        if (rep) {
                            rep.dataset.cc = data.ccare_id || '';
                            rep.dataset.nb = '';
                            if (data.rep_name) { rep.textContent = data.rep_name; rep.title = data.rep_name + ' (click to change)'; }
                        }
                        alert('This customer now has sales in 5 months, so it has moved to CCare from ' + data.ccare_from + '. Earlier months stay under New Biz.');
                    }
                } else if (data.message) {
                    alert(data.message);
                }
            });
        });
    });

    {{-- Everything below changes who owns the book (row type/category, rep
         assignment, extra reps) or needs the spreadsheet-nav helper — still
         canEditBook-only. Plain salespeople stop at the Forecast cell above. --}}
    @if($canEditBook)
    // ── Inline edit: row-field (type, etc.) ───────────────────────────
    document.querySelectorAll('.row-field').forEach(field => {
        field.addEventListener('change', function() {
            if (this.hasAttribute('readonly')) return;
            const tr  = this.closest('tr');
            const id  = tr.dataset.id;
            const key = this.dataset.field;
            const val = this.value;

            fetch(`{{ url('sales-visits/sales-report') }}/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ [key]: val })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    this.style.backgroundColor = '#e8f5e9';
                    setTimeout(() => { this.style.backgroundColor = 'transparent'; }, 500);
                    // Update data-type attribute for filter
                    if (key === 'type') tr.dataset.type = val;
                    // When category changes, update the Representative name cell instantly
                    if (key === 'category' && data.rep_name !== undefined) {
                        const repCell = tr.querySelector('td.col-repname');
                        if (repCell) {
                            repCell.textContent = data.rep_name || '-';
                            repCell.title       = data.rep_name || '-';
                            repCell.style.backgroundColor = '#e8f5e9';
                            setTimeout(() => { repCell.style.backgroundColor = 'transparent'; }, 800);
                        }
                    }
                }
            });
        });
    });

    // ── Grid keyboard nav ─────────────────────────────────────────────
    // On a number input the arrow keys SPIN THE VALUE. The spinner buttons
    // were hidden in CSS, which stopped the clicks but not the keys, so a
    // stray Up/Down silently edited a sales figure. Arrows now move between
    // cells like a spreadsheet, and never change what is in them.
    (function excelGridNav() {
        const EDITABLE = 'input.excel-input:not([type=hidden]):not([disabled])';

        function move(from, dir) {
            const td = from.closest('td'), tr = from.closest('tr');
            if (!td || !tr) return false;
            const col = Array.prototype.indexOf.call(tr.children, td);
            let row = dir > 0 ? tr.nextElementSibling : tr.previousElementSibling;
            while (row) {
                const cell = row.children[col];
                const target = cell && cell.querySelector(EDITABLE);
                if (target && target.offsetParent !== null) {
                    target.focus();
                    if (typeof target.select === 'function') target.select();
                    return true;
                }
                row = dir > 0 ? row.nextElementSibling : row.previousElementSibling;
            }
            return false;
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown' && e.key !== 'Enter') return;
            const el = e.target;
            if (!el || !el.classList || !el.classList.contains('excel-input')) return;
            if (e.altKey || e.ctrlKey || e.metaKey) return;
            // Always stop the default first: on type=number that IS the spin.
            e.preventDefault();
            move(el, e.key === 'ArrowUp' ? -1 : 1);
        });

        // Same bug by another route: scrolling the page with the cursor over a
        // focused number cell scrolls its value instead.
        document.addEventListener('wheel', function (e) {
            const el = document.activeElement;
            if (el && el.classList && el.classList.contains('excel-input') &&
                el.type === 'number' && el === e.target) {
                el.blur();
            }
        }, { passive: true });
    })();

    // ── Click-to-edit: Representative & Salesperson cells (shared templates,
    //    so the page doesn't carry a dropdown per row) ──────────────────────
    function swapEdit(cell, tplId, currentVal, onChange) {
        if (cell.querySelector('select')) return;
        const tpl = document.getElementById(tplId);
        if (!tpl) return;
        const sel = tpl.content.firstElementChild.cloneNode(true);
        sel.value = currentVal;
        const old = cell.innerHTML;
        cell.innerHTML = ''; cell.appendChild(sel); sel.focus();
        let done = false;
        sel.addEventListener('change', function() { done = true; onChange(cell, sel); });
        sel.addEventListener('blur', function() { if (!done) cell.innerHTML = old; });
    }
    const putRow = (id, payload) => fetch(`{{ url('sales-visits/sales-report') }}/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payload)
    }).then(r => r.json());
    document.querySelectorAll('.rep-cell').forEach(cell => cell.addEventListener('click', function() {
        const cur = this.dataset.nb ? ('nb:' + this.dataset.nb) : (this.dataset.cc ? ('cc:' + this.dataset.cc) : '');
        swapEdit(this, 'tplRepSelect', cur, (c, sel) => {
            const v = sel.value, kind = v ? v.split(':')[0] : '', uid = v ? v.split(':')[1] : '';
            const name = v ? sel.options[sel.selectedIndex].text : '-';
            const tr = c.closest('tr');
            putRow(tr.dataset.id, { ccare_id: kind === 'cc' ? uid : null, new_biz_id: kind === 'nb' ? uid : null })
                .then(() => {
                    c.dataset.cc = kind === 'cc' ? uid : '';
                    c.dataset.nb = kind === 'nb' ? uid : '';
                    c.textContent = name; c.title = name + ' (click to change)'; // a name is text, not markup
                    c.style.backgroundColor = '#e8f5e9'; setTimeout(() => c.style.backgroundColor = '', 600);
                    const cat = tr.querySelector('select[data-field="category"]');
                    if (cat) cat.value = kind === 'nb' ? 'New Biz' : (kind === 'cc' ? 'CCare' : '');
                });
        });
    }));
    document.querySelectorAll('.sp-cell').forEach(cell => cell.addEventListener('click', function() {
        swapEdit(this, 'tplSpSelect', this.dataset.sp || '', (c, sel) => {
            const uid = sel.value;
            const name = uid ? sel.options[sel.selectedIndex].text : '-';
            const tr = c.closest('tr');
            putRow(tr.dataset.id, { salesperson_id: uid || null }).then(() => {
                c.dataset.sp = uid;
                c.textContent = name; c.title = name + ' (click to change)'; // a name is text, not markup
                c.style.backgroundColor = '#e8f5e9'; setTimeout(() => c.style.backgroundColor = '', 600);
            });
        });
    }));

    // ── Other Reps: add / remove additional representatives ────────────────
    document.querySelectorAll('.extra-cell').forEach(cell => cell.addEventListener('click', function(e) {
        if (e.target.closest('.remove-extra') || this.querySelector('select')) return;
        // Other Reps uses the CCare/New Biz picker; Service Sales uses the
        // salesperson-only one. data-type on the cell decides which.
        const cellType = this.dataset.type || 'other';
        const tpl = document.getElementById(cellType === 'service' ? 'tplServiceSelect' : 'tplExtraSelect');
        if (!tpl) return;
        const sel = tpl.content.firstElementChild.cloneNode(true);
        this.appendChild(sel); sel.focus();
        let done = false;
        sel.addEventListener('change', () => {
            done = true;
            const uid = sel.value;
            if (!uid) { sel.remove(); return; }
            const name = sel.options[sel.selectedIndex].text;
            const cell = sel.closest('.extra-cell');
            fetch(`{{ url('sales-visits/sales-report') }}/${cell.dataset.id}/extra-rep`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ user_id: uid, type: cell.dataset.type || 'other' })
            }).then(r => r.json()).then(d => {
                sel.remove();
                if (d.success && !cell.querySelector(`.extra-tag[data-uid="${uid}"]`)) {
                    const s = document.createElement('span');
                    s.className = 'badge extra-tag ' + ((cell.dataset.type === 'service') ? 'bg-label-info' : 'bg-label-secondary');
                    s.dataset.uid = uid; s.style.fontSize = '0.62rem';
                    // The name as text, then the remove icon - a name is never parsed as markup.
                    s.textContent = name + ' ';
                    const x = document.createElement('i');
                    x.className = 'bx bx-x remove-extra'; x.style.cursor = 'pointer';
                    s.appendChild(x);
                    cell.appendChild(s);
                    cell.style.backgroundColor = '#e8f5e9'; setTimeout(() => cell.style.backgroundColor = '', 600);
                }
            });
        });
        sel.addEventListener('blur', () => { if (!done) sel.remove(); });
    }));
    document.addEventListener('click', function(e) {
        const x = e.target.closest('.remove-extra');
        if (!x) return;
        const tag = x.closest('.extra-tag'), cell = x.closest('.extra-cell');
        if (!tag || !cell) return;
        const t = cell.dataset.type || 'other';
        fetch(`{{ url('sales-visits/sales-report') }}/${cell.dataset.id}/extra-rep/${tag.dataset.uid}?type=${t}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).then(r => r.json()).then(d => { if (d.success) tag.remove(); });
    });

    @endif

    // ── Setu Sync — opens the shared preview modal (customer × month-wise
    // changes), then the review modal for similar-name decisions. Driven by
    // window.SetuSync (setu-review-common.js). A full page reload after apply
    // so the corrected figures show immediately.
    const btnSyncSetu = document.getElementById('btnSyncSetu');
    if (btnSyncSetu) {
        btnSyncSetu.addEventListener('click', function () {
            const btn = this;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> Checking…';
            window.SetuSync.run(btn.dataset.targetId, {
                onDone: function () { setTimeout(() => location.reload(), 1600); }
            }).then(function (d) {
                btn.disabled = false; btn.innerHTML = original;
                const c = d.counts;
                if (!c.blankFills && !c.overwrites && !c.renames && !c.newCustomers && !c.reviewItems) {
                    window.showSuccessSwal(`${d.salesperson} is already in sync with Setu (${d.period}).`);
                }
            }).catch(function (e) {
                btn.disabled = false; btn.innerHTML = original;
                window.showErrorSwal((e && e.message) || 'Could not sync with Setu.');
            });
        });
    }

    const btnSyncCrmMapping = document.getElementById('btnSyncCrmMapping');
    if (btnSyncCrmMapping) {
        btnSyncCrmMapping.addEventListener('click', function () {
            const btn = this;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> Checking…';
            window.CrmMappingSync.run(btn.dataset.targetId, {
                onDone: function () { setTimeout(() => location.reload(), 1600); }
            }).then(function (d) {
                btn.disabled = false; btn.innerHTML = original;
                const c = d.counts;
                if (!c.autoLinked && !c.reviewItems) {
                    window.showSuccessSwal(`${d.salesperson}'s Sales Report is already linked to the CRM where a match exists.`);
                }
            }).catch(function (e) {
                btn.disabled = false; btn.innerHTML = original;
                window.showErrorSwal((e && e.message) || 'Could not check the CRM mapping.');
            });
        });
    }

    // Filtering / pagination / totals are server-side now — nothing to init here.
});

// ── Filters ───────────────────────────────────────────────────────────
// --- Column sorting (Potential, current-month Fcst/Sale/Pend, and any month's Sale) ---
// SERVER-SIDE: clicking a header reloads with ?sort=<key>&dir=<asc|desc>, so the
// whole dataset is sorted across every page (not just the 50 rendered rows).
const SORT_CURRENT_MONTH = @json($currentMonthKey);
const ANNUAL_MAP  = @json($annualMap);
const ANNUAL_GRAND = @json($annualGrand);
const KB_MAP = @json($kbByName ?? []);   // salesperson name -> {kingo, bingo} (current month)
const TEXT_SORT_KEYS = ['party', 'type', 'category', 'rep', 'salesperson', 'brand'];
function sortPipeline(key) {
    const p = new URLSearchParams(window.location.search);
    // Text columns: first click A->Z. Numeric columns: first click biggest-first.
    let dir = TEXT_SORT_KEYS.includes(key) ? 'asc' : 'desc';
    if (p.get('sort') === key) dir = (p.get('dir') === dir ? (dir === 'asc' ? 'desc' : 'asc') : dir);
    p.set('sort', key); p.set('dir', dir); p.delete('page');
    window.location.search = p.toString();
}
// Show the caret on the active sort column after reload.
document.addEventListener('DOMContentLoaded', function() {
    const p = new URLSearchParams(window.location.search);
    const s = p.get('sort');
    if (!s) return;
    const caret = p.get('dir') === 'asc' ? ' ▲' : ' ▼';
    document.querySelectorAll('th[onclick]').forEach(th => {
        const m = (th.getAttribute('onclick') || '').match(/sortPipeline\('([^']+)'/);
        if (m && m[1] === s) { const c = th.querySelector('.sort-caret'); if (c) c.textContent = caret; }
    });
});

// Filtering, pagination and the stat/month-totals are now computed SERVER-SIDE:
// the controller renders 50 rows per page and pre-computes every card and the
// totals row over the full filtered set. These stubs keep any old inline callers
// safe.
function applyFilters() {}
function renderPage() {}
function changePerPage() {}
function pagePrev() {}
function pageNext() {}
function updateDynamicStats() {}
function clearFilters() {
    window.location.href = @json(route('sales-visits.pipeline.index')) + '?fy=' + encodeURIComponent(@json($selectedFy));
}

// ── Inline add row ────────────────────────────────────────────────────
function openTargetModal() {
    new bootstrap.Modal(document.getElementById('targetAchieveModal')).show();
}

// Target Achievement — month picker. Every elapsed month is already rendered as
// its own <tbody>, so switching is a show/hide, not a request. The Excel link
// follows the selection so you always export what you are looking at.
document.addEventListener('DOMContentLoaded', function () {
    const picker = document.getElementById('tgtMonthPicker');
    if (!picker) return;
    const exportBtn = document.getElementById('tgtExportBtn');
    const deptPicker = document.getElementById('tgtDeptPicker');

    function applyRowFilter() {
        const dept = deptPicker ? deptPicker.value : '';
        document.querySelectorAll('#targetAchieveModal .tgt-month-body:not([style*="display: none"]) .tgt-row')
            .forEach(function (row) {
                row.style.display = (!dept || row.dataset.category === dept) ? '' : 'none';
            });
    }

    picker.addEventListener('change', function () {
        const month = this.value;
        document.querySelectorAll('#targetAchieveModal .tgt-month-body').forEach(function (tb) {
            tb.style.display = (tb.dataset.month === month) ? '' : 'none';
        });
        if (exportBtn) {
            const u = new URL(exportBtn.href, window.location.origin);
            u.searchParams.set('month', month);
            exportBtn.href = u.toString();
        }
        applyRowFilter();
    });

    if (deptPicker) {
        deptPicker.addEventListener('change', applyRowFilter);
    }

    // Flags the row when the typed Tally Sale disagrees with Actual Sale. Same
    // rule and same half-paisa tolerance the server-rendered pass uses, so a row
    // does not change colour just because it was edited rather than reloaded.
    function flagMismatch(cell) {
        const row = cell.closest('tr');
        if (!row) return;
        const actual = parseFloat(cell.dataset.actual || '0');
        const typed  = cell.value === '' ? null : parseFloat(cell.value);
        const off    = typed !== null && !isNaN(typed) && Math.abs(typed - actual) > 0.005;
        row.classList.toggle('tgt-mm', off);
        const icon = row.querySelector('td:first-child .bx-error-circle');
        if (off && !icon) {
            const i = document.createElement('i');
            i.className = 'bx bx-error-circle text-danger me-1';
            i.style.verticalAlign = '-2px';
            row.querySelector('td:first-child').prepend(i);
        } else if (!off && icon) {
            icon.remove();
        }
        row.title = off
            ? 'Tally ' + typed.toFixed(2) + ' vs Actual ' + actual.toFixed(2) +
              ' — off by ' + Math.abs(typed - actual).toFixed(2)
            : '';
    }

    // Tally Sale cells. Saved on blur rather than per keystroke, and only when
    // the value actually changed, so tabbing across the table does not fire a
    // request per cell. The server re-checks who may write and whether the month
    // is still open; this only reports what it says.
    document.querySelectorAll('#targetAchieveModal .tgt-tally').forEach(function (input) {
        input.dataset.was = input.value;

        input.addEventListener('blur', function () {
            if (this.value === this.dataset.was) return;

            const cell = this;
            cell.disabled = true;

            fetch('{{ route('sales-visits.pipeline.tally-sale.update') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({
                    salesperson_id: cell.dataset.sp,
                    month_year: cell.dataset.month,
                    value: cell.value === '' ? null : cell.value
                })
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                cell.disabled = false;
                if (data.success) {
                    cell.dataset.was = cell.value;
                    flagMismatch(cell);
                    cell.style.backgroundColor = '#e8f5e9';
                    setTimeout(function () { cell.style.backgroundColor = 'transparent'; }, 600);
                } else {
                    // Put back what was there, so the screen never shows a figure
                    // the server did not accept.
                    cell.value = cell.dataset.was;
                    cell.style.backgroundColor = '#fdecea';
                    setTimeout(function () { cell.style.backgroundColor = 'transparent'; }, 1200);
                    if (data.message) { alert(data.message); }
                }
              })
              .catch(function () {
                cell.disabled = false;
                cell.value = cell.dataset.was;
                alert('Could not save that figure. Try again.');
              });
        });
    });
});

function showAddNewRow() {
    document.getElementById('addNewRow').style.display       = '';
    document.getElementById('addRowTriggerBtn').style.display = 'none';
    document.getElementById('new_party_name').focus();
}

function cancelNewPipeline() {
    document.getElementById('addNewRow').style.display       = 'none';
    document.getElementById('addRowTriggerBtn').style.display = '';
    // Clear inputs
    ['new_party_name','new_type','new_potential','new_product',
     'new_salesperson_id','new_new_biz_id','new_ccare_id']
        .forEach(id => { const el = document.getElementById(id); if(el) el.value = ''; });
    document.querySelectorAll('#addNewRow .new-month').forEach(el => el.value = '');
}

function saveNewPipeline() {
    const partyName = document.getElementById('new_party_name')?.value?.trim();
    if (!partyName) {
        alert('Customer Name is required.');
        document.getElementById('new_party_name')?.focus();
        return;
    }

    // Collect the month figures into { 'YYYY-MM': { sale_amount, forecast_amount, pending_amount } }
    const months = {};
    document.querySelectorAll('#addNewRow .new-month').forEach(el => {
        const v = el.value;
        if (v === '' || v === null) return;
        const mk = el.dataset.month, f = el.dataset.field;
        (months[mk] = months[mk] || {})[f] = v;
    });

    const payload = {
        party_name:               partyName,
        type:                     document.getElementById('new_type')?.value        || '',
        total_business_potential: document.getElementById('new_potential')?.value   || 0,
        product:                  document.getElementById('new_product')?.value     || '',
        salesperson_id:           document.getElementById('new_salesperson_id')?.value || '',
        new_biz_id:               document.getElementById('new_new_biz_id')?.value  || '',
        ccare_id:                 document.getElementById('new_ccare_id')?.value    || '',
        months:                   months,
        is_locked:                true,   // Sales Report rows are locked
    };

    const saveBtn = document.querySelector('#addNewRow .btn-success');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }

    fetch('{{ route("sales-visits.pipeline.store") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  '{{ csrf_token() }}'
        },
        body: JSON.stringify(payload)
    })
    .then(r => {
        // A session that has expired since the page loaded (or a page left
        // open a long time) fails the CSRF check and comes back as valid
        // JSON with no `success` key -- identical to a real save failure as
        // far as the check below is concerned, but "try again" cannot work
        // here: the same stale token just fails the same way every retry.
        // Reloading is the only fix, so do it instead of repeating a dead end.
        if (r.status === 419) {
            alert('Your session has expired. The page will reload so you can try again.');
            window.location.reload();
            return Promise.reject(new Error('session expired'));
        }
        return r.json();
    })
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Could not save. Please try again.');
            if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="bx bx-save me-1"></i>Save'; }
        }
    })
    .catch((err) => {
        if (err && err.message === 'session expired') return; // already handled above
        alert('Network error. Please try again.');
        if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="bx bx-save me-1"></i>Save'; }
    });
}
</script>

<script>
// ── Targets modal ─────────────────────────────────────────────────────
const allTargets = @json($salespersonTargets ?? []);

document.getElementById('targetMonthSelect')?.addEventListener('change', function() {
    const month     = this.value;
    const container = document.getElementById('targetsTableContainer');
    if (!month) { container.style.display = 'none'; return; }
    container.style.display = 'block';
    document.querySelectorAll('.target-input').forEach(input => {
        const spId  = input.dataset.sp;
        const field = input.dataset.field;
        const t     = allTargets.find(t => t.salesperson_id == spId && t.month_year == month);
        input.value = t ? (t[field] ?? '') : '';
    });
});

document.querySelectorAll('.target-input').forEach(input => {
    input.addEventListener('change', function() {
        const month = document.getElementById('targetMonthSelect').value;
        if (!month) return;
        const spId  = this.dataset.sp;
        const kingo = document.querySelector(`.target-input[data-sp="${spId}"][data-field="kingo_target"]`).value;
        const bingo = document.querySelector(`.target-input[data-sp="${spId}"][data-field="bingo_target"]`).value;
        this.style.backgroundColor = '#fef08a';

        fetch("{{ route('sales-visits.targets.update') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ salesperson_id: spId, month_year: month, kingo_target: kingo, bingo_target: bingo })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.style.backgroundColor = '#bbf7d0';
                setTimeout(() => { this.style.backgroundColor = ''; }, 1000);
                let existing = allTargets.find(t => t.salesperson_id == spId && t.month_year == month);
                if (existing) { existing.kingo_target = kingo; existing.bingo_target = bingo; }
                else allTargets.push(data.target);
            }
        })
        .catch(() => { this.style.backgroundColor = '#fecaca'; });
    });
});
</script>
{{-- Deletion is approval-gated: the requester picks a reason and may add
     remarks, and the row is only removed once Admin/HR approve it. --}}
<script>
document.addEventListener('submit', function (e) {
    const form = e.target.closest('.js-delete-customer');
    if (!form || form.dataset.confirmed === '1') return;
    e.preventDefault();

    const party = form.dataset.party || 'this customer';
    const send = (reason, remarks) => {
        form.dataset.confirmed = '1';
        const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v || ''; form.appendChild(i); };
        add('reason', reason); add('remarks', remarks);
        form.submit();
    };

    if (typeof Swal === 'undefined') {
        const r = prompt('Reason to delete ' + party + '?\n\nType one of: duplicate, legal_case, closed');
        if (!r) return;
        send(r.trim().toLowerCase(), prompt('Remarks (optional)') || '');
        return;
    }

    Swal.fire({
        title: 'Request deletion',
        html:
            '<div style="text-align:left;font-size:.88rem;color:#566a7f;">'
          + '  <div style="font-weight:700;color:#2f3944;margin-bottom:10px;">' + party.replace(/</g,'&lt;') + '</div>'
          + '  <label style="font-weight:700;font-size:.76rem;text-transform:uppercase;letter-spacing:.4px;color:#7b8794;">Reason <span style="color:#c0392b;">*</span></label>'
          + '  <select id="delReason" class="form-select" style="margin:4px 0 12px;">'
          + '    <option value="">— Select a reason —</option>'
          + '    <option value="duplicate">Duplicate</option>'
          + '    <option value="legal_case">Legal case</option>'
          + '    <option value="closed">Closed</option>'
          + '  </select>'
          + '  <label style="font-weight:700;font-size:.76rem;text-transform:uppercase;letter-spacing:.4px;color:#7b8794;">Remarks</label>'
          + '  <textarea id="delRemarks" class="form-control" rows="3" style="margin-top:4px;" placeholder="Anything the approver should know (optional)"></textarea>'
          + '  <div style="margin-top:12px;padding:8px 10px;background:#fff6f6;border-radius:8px;color:#c0392b;font-weight:600;font-size:.8rem;">'
          + '    This is sent to Admin/HR for approval. The customer is not deleted until it is approved.'
          + '  </div>'
          + '</div>',
        showCancelButton: true,
        reverseButtons: true,
        buttonsStyling: false,
        confirmButtonText: 'Send for approval',
        cancelButtonText: 'Cancel',
        customClass: { popup: 'hrx-swal', confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        preConfirm: () => {
            const reason = document.getElementById('delReason').value;
            if (!reason) { Swal.showValidationMessage('Please choose a reason'); return false; }
            return { reason: reason, remarks: document.getElementById('delRemarks').value };
        }
    }).then((r) => { if (r.isConfirmed && r.value) send(r.value.reason, r.value.remarks); });
});
</script>
<style>
    .hrx-swal { border-radius: 14px !important; }
    .hrx-swal .swal2-title { font-size: 1.05rem !important; font-weight: 800 !important; color: #2f3944 !important; }
</style>


<style>
    /* Freeze the first two columns so the customer stays identifiable while
       scrolling right through the month columns. */
    #pipelineTableWrap table th.col-sno,
    #pipelineTableWrap table td.col-sno {
        position: sticky; left: 0; z-index: 12; background: #fff;
    }
    #pipelineTableWrap table th.col-party,
    #pipelineTableWrap table td.col-party {
        position: sticky; left: 44px; z-index: 12; background: #fff;
        box-shadow: 2px 0 4px -2px rgba(0,0,0,.18);
    }
    /* header cells sit above body cells, and the totals row above both */
    #pipelineTableWrap thead th.col-sno,
    #pipelineTableWrap thead th.col-party { z-index: 30; background: #0f6e6e; }
    #monthTotalsRow td.col-sno,
    #monthTotalsRow td.col-party { z-index: 26; }
    /* keep the striping/highlight readable on the frozen cells */
    #pipelineTableWrap tbody tr:hover td.col-sno,
    #pipelineTableWrap tbody tr:hover td.col-party { background: #f6faf9; }

    #pipelineTopScroll::-webkit-scrollbar { height: 10px; }
    #pipelineTopScroll::-webkit-scrollbar-thumb { background: #c6cdd3; border-radius: 6px; }
    #pipelineTopScroll::-webkit-scrollbar-thumb:hover { background: #aeb7be; }
</style>
<script>
(function () {
    const wrap = document.getElementById('pipelineTableWrap');
    const top  = document.getElementById('pipelineTopScroll');
    const inner = document.getElementById('pipelineTopScrollInner');
    if (!wrap || !top || !inner) return;

    const sizeIt = () => {
        const t = wrap.querySelector('table');
        if (t) inner.style.width = t.scrollWidth + 'px';
        top.style.display = (wrap.scrollWidth > wrap.clientWidth) ? 'block' : 'none';
    };

    // Mirror either scroller into the other. Assigning .onscroll (rather than
    // addEventListener) keeps this idempotent if the block is ever included
    // twice, and the guard skips the echo when the values already agree.
    top.onscroll = function () {
        if (Math.abs(wrap.scrollLeft - top.scrollLeft) > 1) wrap.scrollLeft = top.scrollLeft;
    };
    wrap.onscroll = function () {
        if (Math.abs(top.scrollLeft - wrap.scrollLeft) > 1) top.scrollLeft = wrap.scrollLeft;
    };

    sizeIt();
    window.addEventListener('resize', sizeIt);
    window.addEventListener('load', sizeIt);
    // Watch the table as well as its box: the table can widen after first
    // paint (column widths, fonts, the Columns menu) without the box itself
    // changing size, which left the top scrollbar hidden on some views.
    if (window.ResizeObserver) {
        const ro = new ResizeObserver(sizeIt);
        ro.observe(wrap);
        const t = wrap.querySelector('table');
        if (t) ro.observe(t);
    }
})();
</script>
<script>
// Columns menu: hide/show the leading columns (everything before the month
// groups except S.No and Party Name). Remembered per browser.
(function () {
    const tbl  = document.getElementById('pipelineTable');
    const menu = document.getElementById('srColsMenu');
    if (!tbl || !tbl.tHead || !menu) return;

    const head = tbl.tHead.rows[0];
    // The leading columns are the header cells spanning both header rows.
    const lead = [];
    for (const c of head.cells) { if (c.rowSpan < 2) break; lead.push(c); }
    const label = c => c.textContent.replace(/\s+/g, ' ').replace(/₹.*$/, '').trim();   // "Potential ₹ Lakh/Month" -> "Potential"
    const KEY = 'hrx.salesReport.hiddenCols';
    let hidden = [];
    try { hidden = JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (e) { hidden = []; }

    const apply = () => {
        const off = new Set(lead.map((c, i) => (i > 1 && hidden.includes(label(c))) ? i : -1).filter(i => i > 1));
        const rows = [head, ...tbl.querySelectorAll('#monthTotalsRow, tbody tr')];
        rows.forEach(row => {
            // only real customer/total rows, which start with the S.No cell
            if (!row.cells[0] || !row.cells[0].classList.contains('col-sno') || row.cells.length < lead.length) return;
            for (let i = 0; i < lead.length; i++) row.cells[i].classList.toggle('sr-col-hidden', off.has(i));
        });
        const n = off.size;
        document.getElementById('srColsBtn').innerHTML = '<i class="bx bx-columns me-1"></i>Columns' + (n ? ' (' + n + ' hidden)' : '');
        window.dispatchEvent(new Event('resize'));   // re-measure the top scrollbar
    };
    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(hidden)); } catch (e) {} };

    lead.forEach((c, i) => {
        if (i < 2) return;   // S.No and Party Name always show
        const name = label(c);
        const id = 'srCol' + i;
        const row = document.createElement('div');
        row.className = 'form-check mb-1';
        row.innerHTML = '<input class="form-check-input" type="checkbox" id="' + id + '">'
            + '<label class="form-check-label" for="' + id + '"></label>';
        row.querySelector('label').textContent = name;
        const box = row.querySelector('input');
        box.checked = !hidden.includes(name);
        box.addEventListener('change', () => {
            hidden = box.checked ? hidden.filter(h => h !== name) : [...new Set([...hidden, name])];
            save(); apply();
        });
        menu.appendChild(row);
    });
    const all = document.createElement('button');
    all.type = 'button';
    all.className = 'btn btn-sm btn-link p-0 mt-1';
    all.textContent = 'Show all columns';
    all.addEventListener('click', () => {
        hidden = []; save();
        menu.querySelectorAll('input[type=checkbox]').forEach(b => { b.checked = true; });
        apply();
    });
    menu.appendChild(all);

    apply();
})();
</script>


<style>
    /* --- frozen header -------------------------------------------------
       The grid scrolls inside #pipelineTableWrap, so both header rows and the
       totals row stay visible while the customer rows move underneath. Offsets
       are set from JS (below) because the two header rows are not a fixed
       height across zoom levels / font settings. */
    #pipelineTableWrap thead th {
        position: sticky;
        z-index: 22;
        background: #0f6e6e;   /* header keeps its teal so rows don't show through */
    }
    #pipelineTableWrap thead tr:first-child th  { top: 0; }
    #pipelineTableWrap #monthTotalsRow td {
        position: sticky;
        z-index: 18;
        background: #d1fae5 !important;
    }
    /* the two frozen columns must sit above the scrolling cells in BOTH axes */
    #pipelineTableWrap thead th.col-sno,
    #pipelineTableWrap thead th.col-party { z-index: 34; }
    #pipelineTableWrap #monthTotalsRow td.col-sno,
    #pipelineTableWrap #monthTotalsRow td.col-party { z-index: 28; }
</style>
<script>
(function () {
    const wrap = document.getElementById('pipelineTableWrap');
    if (!wrap) return;
    const tbl = wrap.querySelector('table');
    if (!tbl || !tbl.tHead) return;

    // Stack row 2 under row 1, and the totals row under both — measured rather
    // than hard-coded so it survives zoom and font changes.
    const place = () => {
        const rows = tbl.tHead.rows;
        const h1 = rows[0] ? rows[0].getBoundingClientRect().height : 0;
        if (rows[1]) {
            [...rows[1].cells].forEach(c => { c.style.top = h1 + 'px'; });
        }
        const headH = h1 + (rows[1] ? rows[1].getBoundingClientRect().height : 0);
        const totals = document.getElementById('monthTotalsRow');
        if (totals) [...totals.cells].forEach(c => { c.style.top = headH + 'px'; });
    };

    place();
    window.addEventListener('resize', place);
    if (window.ResizeObserver) new ResizeObserver(place).observe(tbl);
})();
</script>

@if(\App\Support\SalesScope::canClaimCustomer($user))
<script>
(function () {
    const CURRENT_USER_ID = {{ $user->id }};
    const modalEl = document.getElementById('claimSearchModal');
    if (!modalEl) return;

    const input   = document.getElementById('claimSearchInput');
    const hint    = document.getElementById('claimSearchHint');
    const results = document.getElementById('claimSearchResults');

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    const ROLE_LABELS = { salesperson: 'Salesperson', ccare: 'CCare', new_biz: 'New Biz', new_biz_2: 'New Biz 2' };

    function roleRow(id, role, person) {
        return `
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                <div><span class="text-muted small">${ROLE_LABELS[role]}:</span> <strong>${person ? esc(person.name) : 'Unassigned'}</strong></div>
                <button type="button" class="btn btn-xs btn-outline-primary claim-role-btn" data-id="${id}" data-role="${role}" data-party="__PARTY__">Assign to me</button>
            </div>`;
    }

    function renderCard(r) {
        const serviceNames = (r.service_reps || []).map(p => esc(p.name)).join(', ') || 'None';
        const partyEsc = esc(r.party_name);

        return `
        <div class="card border-0 shadow-sm mb-2">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold">${partyEsc}</div>
                        <div class="small text-muted">${esc(r.type || '')}${r.address ? ' · ' + esc(r.address) : ''}</div>
                    </div>
                    <button type="button" class="btn btn-xs btn-link claim-history-btn" data-id="${r.id}">History</button>
                </div>
                <div class="mt-2">
                    ${roleRow(r.id, 'salesperson', r.salesperson).replace('__PARTY__', partyEsc)}
                    ${roleRow(r.id, 'ccare', r.ccare).replace('__PARTY__', partyEsc)}
                    ${roleRow(r.id, 'new_biz', r.new_biz).replace('__PARTY__', partyEsc)}
                    ${roleRow(r.id, 'new_biz_2', r.new_biz_2).replace('__PARTY__', partyEsc)}
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <div><span class="text-muted small">Service Sales:</span> <strong>${serviceNames}</strong></div>
                        <button type="button" class="btn btn-xs btn-outline-secondary claim-service-btn" data-id="${r.id}" data-party="${partyEsc}">Add me</button>
                    </div>
                </div>
                <div class="claim-history-panel small text-muted mt-2" style="display:none;"></div>
            </div>
        </div>`;
    }

    let debounceTimer = null;
    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = input.value.trim();
        if (q.length < 3) {
            results.innerHTML = '';
            hint.textContent = q.length ? 'Type at least 3 letters…' : '';
            return;
        }
        hint.textContent = 'Searching…';
        debounceTimer = setTimeout(function () {
            fetch(`{{ url('sales-visits/sales-report/search-customer') }}?q=${encodeURIComponent(q)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) { hint.textContent = d.message || 'Search failed.'; return; }
                results.innerHTML = d.results.map(renderCard).join('');
                hint.textContent = d.count === 0
                    ? 'No customers found.'
                    : (d.truncated ? `Showing first ${d.count} matches — refine your search.` : `${d.count} match(es).`);
            })
            .catch(() => { hint.textContent = 'Search failed.'; });
        }, 300);
    });

    function finishAndReload(message) {
        window.showSuccessSwal(message);
        setTimeout(() => window.location.reload(), 1500);
    }

    results.addEventListener('click', function (e) {
        const roleBtn = e.target.closest('.claim-role-btn');
        const serviceBtn = e.target.closest('.claim-service-btn');
        const historyBtn = e.target.closest('.claim-history-btn');

        if (roleBtn) {
            const id = roleBtn.dataset.id, role = roleBtn.dataset.role, party = roleBtn.dataset.party;
            if (!confirm(`Assign yourself as ${ROLE_LABELS[role]} for "${party}"? This will replace the current ${ROLE_LABELS[role]}.`)) return;
            roleBtn.disabled = true;
            fetch(`{{ url('sales-visits/sales-report') }}/${id}/claim`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ role: role }),
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) { window.showErrorSwal(d.message || 'Could not claim customer.'); roleBtn.disabled = false; return; }
                finishAndReload(d.message);
            })
            .catch(() => { window.showErrorSwal('Could not claim customer.'); roleBtn.disabled = false; });
            return;
        }

        if (serviceBtn) {
            const id = serviceBtn.dataset.id;
            serviceBtn.disabled = true;
            fetch(`{{ url('sales-visits/sales-report') }}/${id}/extra-rep`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ user_id: CURRENT_USER_ID, type: 'service' }),
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) { window.showErrorSwal(d.message || 'Could not add you as Service Sales.'); serviceBtn.disabled = false; return; }
                finishAndReload(`You've been added as Service Sales for ${serviceBtn.dataset.party}.`);
            })
            .catch(() => { window.showErrorSwal('Could not add you as Service Sales.'); serviceBtn.disabled = false; });
            return;
        }

        if (historyBtn) {
            const id = historyBtn.dataset.id;
            const panel = historyBtn.closest('.card-body').querySelector('.claim-history-panel');
            if (panel.style.display !== 'none') { panel.style.display = 'none'; return; }
            panel.style.display = 'block';
            panel.textContent = 'Loading…';
            fetch(`{{ url('sales-visits/sales-report') }}/${id}/transfer-history`)
                .then(r => r.json())
                .then(d => {
                    if (!d.success || !d.history.length) { panel.textContent = 'No history yet.'; return; }
                    panel.innerHTML = d.history.map(h => `
                        <div class="py-1 border-top">
                            <strong>${esc(h.role)}</strong>: ${esc(h.from)} → ${esc(h.to)}
                            <span class="text-muted">by ${esc(h.changed_by)} on ${esc(h.changed_at)}</span>
                            ${h.reason ? `<div class="fst-italic">${esc(h.reason)}</div>` : ''}
                        </div>`).join('');
                })
                .catch(() => { panel.textContent = 'Could not load history.'; });
        }
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        input.value = '';
        results.innerHTML = '';
        hint.textContent = '';
    });
})();
</script>
@endif

@endsection
