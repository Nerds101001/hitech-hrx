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

@section('navbar-actions')
    <div class="d-flex gap-2 align-items-center flex-wrap">
        @include('tenant.partials.manager-view-switch', ['mvRoute' => 'sales-visits.pipeline.index', 'mvMemberParam' => 'sp'])
        <form action="{{ route('sales-visits.pipeline.index') }}" method="GET" class="d-flex gap-2 mb-0">
            @php $startYear = 2023; $endYear = (int)date('Y') + 1; @endphp
            <select name="fy" class="form-select form-select-sm border-0 fw-bold"
                    style="background-color: rgba(15, 118, 110, 0.1); color: #0f766e;" onchange="this.form.submit()">
                @for($i = $startYear; $i <= $endYear; $i++)
                    <option value="{{ $i }}-{{ $i+1 }}"
                        {{ $selectedFy == ($i.'-'.($i+1)) ? 'selected' : '' }}>
                        FY {{ $i }}-{{ substr($i+1,-2) }}
                    </option>
                @endfor
            </select>
        </form>
        @if($isCcare || $isAdmin || $isNewBiz)
            <a href="{{ route('sales-visits.pipeline.import') }}" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold shadow-sm d-flex align-items-center" title="Import Sales Report">
                <i class="bx bx-upload me-1"></i> Import Sales Report
            </a>
        @endif
        {{-- Targets are Admin/HR only — CCare and New Biz may view, never set. --}}
        @if($isAdmin)
            <a href="{{ route('sales-visits.kingo-bingo.fill-targets') }}" class="btn btn-sm btn-success rounded-pill fw-bold shadow-sm d-flex align-items-center">
                <i class="bx bx-edit me-1"></i> Fill Targets
            </a>
            <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-bold shadow-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#targetsModal">
                <i class="bx bx-target-lock me-1"></i> Assign Targets
            </button>
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
    </style>
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" action="{{ route('sales-visits.pipeline.index') }}" class="sales-filter d-flex flex-wrap align-items-center gap-2">
                <input type="hidden" name="fy" value="{{ $selectedFy }}">
                @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="dir" value="{{ request('dir') }}">@endif
                <input type="text" class="form-control" name="party" value="{{ $fParty }}"
                       placeholder="Search customer…" style="width:200px;">
                <select class="form-select" name="type" style="width:130px;" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="Distributor" {{ $fType === 'Distributor' ? 'selected' : '' }}>Distributor</option>
                    <option value="Dealer" {{ $fType === 'Dealer' ? 'selected' : '' }}>Dealer</option>
                    <option value="Customer" {{ $fType === 'Customer' ? 'selected' : '' }}>Customer</option>
                </select>
                <select class="form-select" name="cat" style="width:130px;" onchange="this.form.submit()">
                    <option value="">All Category</option>
                    <option value="New Biz" {{ request('cat') === 'New Biz' ? 'selected' : '' }}>New Biz</option>
                    <option value="CCare" {{ request('cat') === 'CCare' ? 'selected' : '' }}>CCare</option>
                    <option value="None" {{ request('cat') === 'None' ? 'selected' : '' }}>No Category</option>
                </select>
                {{-- Full directory filters for every role: rows stay scoped, so an
                     out-of-scope pick simply shows only what the user may see. --}}
                <select class="form-select" name="sp" style="width:180px;" onchange="this.form.submit()">
                    <option value="">All Salespersons ({{ $spFilterCounts->sum() }})</option>
                    @foreach($salespersonFilterUsers as $au)
                        <option value="{{ $au->id }}" {{ (string)$fSp === (string)$au->id ? 'selected' : '' }}>{{ $au->name }} ({{ $spFilterCounts[$au->id] ?? 0 }})</option>
                    @endforeach
                </select>
                <select class="form-select" name="ccare" style="width:170px;" onchange="this.form.submit()">
                    <option value="">All CCare ({{ $ccareFilterCounts->sum() }})</option>
                    @foreach($ccareFilterUsers as $au)
                        <option value="{{ $au->id }}" {{ (string)$fCcare === (string)$au->id ? 'selected' : '' }}>{{ $au->name }} ({{ $ccareFilterCounts[$au->id] ?? 0 }})</option>
                    @endforeach
                </select>
                <select class="form-select" name="brand" style="width:150px;" onchange="this.form.submit()">
                    <option value="">All Brands ({{ $brandCounts->sum() }})</option>
                    @foreach($brandCounts as $bName => $bCount)
                        <option value="{{ $bName }}" {{ (string)($fBrand ?? '') === (string)$bName ? 'selected' : '' }}>{{ $bName }} ({{ $bCount }})</option>
                    @endforeach
                </select>
                <select class="form-select" name="nb" style="width:170px;" onchange="this.form.submit()">
                    <option value="">All New Biz ({{ $nbFilterCounts->sum() }})</option>
                    @foreach($newBizFilterUsers as $au)
                        <option value="{{ $au->id }}" {{ (string)($fNb ?? '') === (string)$au->id ? 'selected' : '' }}>{{ $au->name }} ({{ $nbFilterCounts[$au->id] ?? 0 }})</option>
                    @endforeach
                </select>
                <select class="form-select" name="per" style="width:105px;" onchange="this.form.submit()" title="Rows per page">
                    @foreach([50,100,200,500,1000] as $pp)
                        <option value="{{ $pp }}" {{ (string)request('per', 50) === (string)$pp ? 'selected' : '' }}>{{ $pp }} rows</option>
                    @endforeach
                    <option value="all" {{ request('per') === 'all' ? 'selected' : '' }}>All rows</option>
                </select>
                <button type="submit" class="btn btn-primary"><i class="bx bx-search"></i> Search</button>
                <a href="{{ route('sales-visits.pipeline.index', ['fy' => $selectedFy]) }}" class="btn btn-outline-secondary">
                    <i class="bx bx-x"></i> Clear
                </a>
                <div class="d-flex align-items-center gap-2 ms-auto text-muted text-nowrap" style="font-size:0.8rem">
                    Showing <strong>{{ $pipelines->firstItem() ?? 0 }}–{{ $pipelines->lastItem() ?? 0 }}</strong>
                    of <strong>{{ $pipelines->total() }}</strong> customers
                </div>
            </form>
        </div>
    </div>

    {{-- ─── TABLE ──────────────────────────────────────────────────────── --}}
    <div class="table-responsive" id="pipelineTableWrap" style="overflow-x:auto;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
        <table class="table table-bordered table-sm align-middle m-0"
               id="pipelineTable" style="white-space:nowrap;font-size:0.85rem;">
            <thead class="position-sticky top-0 text-white"
                   style="z-index:40;background-color:#0f766e;">
                        {{-- Top Header Row --}}
                        <tr>
                            <th rowspan="2" class="text-center align-middle col-sno">S.No.</th>
                            <th rowspan="2" class="align-middle col-party" style="cursor:pointer;" onclick="sortPipeline('party', this)" title="Click to sort">Party Name<span class="sort-caret"></span></th>
                            <th rowspan="2" class="align-middle col-status" style="cursor:pointer;" onclick="sortPipeline('type', this)" title="Click to sort">Type<span class="sort-caret"></span></th>
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
                            <th rowspan="2" class="align-middle block-end" style="min-width:130px;">Other Reps</th>

                            @foreach($months as $m)
                                @php
                                    $isThisMonth = ($m['key'] === $currentMonthKey);
                                    // Past: 1 col (Sale only). Current: 4 (Forecast + Sale + Pending + Remarks).
                                    $colspan     = $isThisMonth ? 4 : 1;
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
                                @php $isThisMonth = ($m['key'] === $currentMonthKey); @endphp
                                @if($isThisMonth)
                                    <th class="text-end align-middle" style="min-width:52px;max-width:52px;cursor:pointer;" onclick="sortPipeline('forecast', this)" title="Click to sort">
                                        Fcst<span class="sort-caret"></span><br><small class="fw-normal opacity-75" style="font-size:0.6rem">₹L</small>
                                    </th>
                                @endif
                                <th class="text-end align-middle {{ !$isThisMonth ? 'block-end' : '' }}"
                                    style="min-width:55px;max-width:55px;cursor:pointer;"
                                    onclick="sortPipeline('{{ $isThisMonth ? 'sale' : 'month:'.$m['key'] }}', this)" title="Click to sort">
                                    Sale<span class="sort-caret"></span><br><small class="fw-normal opacity-75" style="font-size:0.6rem">₹L</small>
                                </th>
                                @if($isThisMonth)
                                    <th class="text-end align-middle" style="min-width:52px;max-width:52px;cursor:pointer;" onclick="sortPipeline('pending', this)" title="Click to sort">
                                        Pend<span class="sort-caret"></span><br><small class="fw-normal opacity-75" style="font-size:0.6rem">₹L</small>
                                    </th>
                                    <th class="align-middle block-end" style="min-width:95px;max-width:95px;">Remarks</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody id="pipelineBody">
                        {{-- Monthly totals row --}}
                        <tr id="monthTotalsRow" style="background:#f0fdf4;font-size:0.72rem;font-weight:700;position:sticky;top:68px;z-index:25;">
                            <td class="text-center col-sno" style="background:#d1fae5!important;color:#065f46;">∑</td>
                            <td class="col-party" style="background:#d1fae5!important;color:#065f46;">Total</td>
                            <td class="col-status" style="background:#d1fae5!important;"></td>
                            <td class="col-category" style="background:#d1fae5!important;"></td>
                            <td class="col-repname" style="background:#d1fae5!important;"></td>
                            <td class="col-potential text-end font-monospace" style="background:#d1fae5!important;color:#065f46;" id="totalPotential">{{ number_format($totalPotential ?? 0, 2) }}</td>
                            @unless($isSalesperson)
                                <td class="col-salesperson" style="background:#d1fae5!important;"></td>
                            @endunless
                            <td style="background:#d1fae5!important;"></td>
                            <td class="block-end" style="background:#d1fae5!important;"></td>
                            @foreach($months as $m)
                                @php $isThisMonth = ($m['key'] === $currentMonthKey); @endphp
                                @if($isThisMonth)
                                    <td class="text-end" id="mtotal-forecast-{{ $m['key'] }}"
                                        style="background:#d1fae5!important;color:#1e40af;">{{ number_format($monthlyForecast[$m['key']] ?? 0, 2) }}</td>
                                @endif
                                <td class="text-end {{ !$isThisMonth ? 'block-end' : '' }}" id="mtotal-{{ $m['key'] }}"
                                    style="background:#d1fae5!important;color:#065f46;">
                                    {{ number_format($monthlyActuals[$m['key']] ?? 0, 2) }}
                                </td>
                                @if($isThisMonth)
                                    <td class="text-end" id="mtotal-pending-{{ $m['key'] }}"
                                        style="background:#d1fae5!important;color:#9a3412;font-weight:700;">
                                        {{ number_format($totalPendingCurrentMonth ?? 0, 2) }}
                                    </td>
                                    <td class="block-end" style="background:#d1fae5!important;"></td>
                                @endif
                            @endforeach
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
                                </td>

                                <td class="col-status p-0 align-middle">
                                    @php
                                        // "Customer" and "Direct Customer" are treated as one type.
                                        $typeNorm = strtolower(trim($row->type ?? ''));
                                        $isCustomer   = in_array($typeNorm, ['customer', 'direct customer']);
                                        $isDistributor = $typeNorm === 'distributor';
                                        $isDealer      = $typeNorm === 'dealer';
                                    @endphp
                                    <select class="excel-select row-field status-select text-truncate"
                                            data-field="type">
                                        <option value="">-Select-</option>
                                        <option value="Distributor" {{ $isDistributor ? 'selected' : '' }}>Distributor</option>
                                        <option value="Dealer"      {{ $isDealer ? 'selected' : '' }}>Dealer</option>
                                        <option value="Customer"    {{ $isCustomer ? 'selected' : '' }}>Customer</option>
                                        @if($row->type && !$isCustomer && !$isDistributor && !$isDealer)
                                            <option value="{{ $row->type }}" selected>{{ $row->type }}</option>
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
                                        <option value="HITECH"      {{ $row->product === 'HITECH'      ? 'selected' : '' }}>HITECH</option>
                                        @if($row->product && !in_array($row->product, ['Rust-X', 'Dr.Bio', 'Fillezy', 'KIF', 'Zorbit', 'Tuffpaulin', 'HITECH']))
                                            <option value="{{ $row->product }}" selected>{{ $row->product }}</option>
                                        @endif
                                    </select>
                                </td>

                                {{-- Other Reps — extra people who also see this customer --}}
                                <td class="align-middle block-end extra-cell" data-id="{{ $row->id }}"
                                    style="min-width:130px;cursor:pointer;" title="Other representatives — click to add">
                                    @foreach($row->extraReps as $er)
                                        <span class="badge bg-label-secondary extra-tag" data-uid="{{ $er->user_id }}" style="font-size:0.62rem;">
                                            {{ $er->user->name ?? ('#'.$er->user_id) }} <i class="bx bx-x remove-extra" style="cursor:pointer;"></i>
                                        </span>
                                    @endforeach
                                </td>

                                @foreach($months as $m)
                                    @php
                                        $mData       = $row->months->firstWhere('month_year', $m['key']);
                                        $isPast      = $m['key'] < $currentMonthKey;
                                        $isThisMonth = ($m['key'] === $currentMonthKey);
                                        // Past months are editable too — users need to correct
                                        // imported / historical figures.
                                        $roAttr      = '';
                                    @endphp
                                    @if($isThisMonth)
                                        <td class="p-0 bg-forecast-light" style="max-width:52px;">
                                            <input type="number" step="0.01"
                                                   class="excel-input text-end month-field font-monospace"
                                                   data-month="{{ $m['key'] }}" data-field="forecast_amount"
                                                   value="{{ $mData && $mData->forecast_amount ? $mData->forecast_amount : '' }}">
                                        </td>
                                    @endif
                                    <td class="p-0 bg-sale-light {{ !$isThisMonth ? 'block-end' : '' }}" style="max-width:55px;">
                                        <input type="number" step="0.01"
                                               class="excel-input text-end month-field font-monospace"
                                               data-month="{{ $m['key'] }}" data-field="sale_amount"
                                               value="{{ $mData && $mData->sale_amount ? $mData->sale_amount : '' }}"
                                               {!! $roAttr !!}>
                                    </td>
                                    @if($isThisMonth)
                                        <td class="p-0 bg-pending-light" style="max-width:52px;">
                                            <input type="number" step="0.01"
                                                   class="excel-input text-end month-field font-monospace"
                                                   data-month="{{ $m['key'] }}" data-field="pending_amount"
                                                   value="{{ $mData && $mData->pending_amount ? $mData->pending_amount : '' }}">
                                        </td>
                                        <td class="p-0 block-end" style="max-width:95px;">
                                            <input type="text" class="excel-input month-field"
                                                   data-month="{{ $m['key'] }}" data-field="remarks"
                                                   value="{{ $mData ? $mData->remarks : '' }}">
                                        </td>
                                    @endif
                                @endforeach
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
                                            <option value="HITECH">HITECH</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">Potential ₹L</label>
                                        <input type="number" id="new_potential" step="0.01" class="form-control form-control-sm text-end" style="width:110px;" placeholder="0.00">
                                    </div>
                                    {{-- Month sale figures (+ current-month forecast/pending) --}}
                                    @foreach($months as $m)
                                        @php $isThisMonth = ($m['key'] === $currentMonthKey); @endphp
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

    {{-- Add New Entry trigger --}}
    <div class="d-flex justify-content-center py-2 mt-1">
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
            <div class="modal-header" style="background:linear-gradient(90deg,#0d9488,#0f766e);">
                <h5 class="modal-title text-white fw-bold mb-0">This Month — Target Achievement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                {{-- Legend stays static; ONLY the table below scrolls, so the
                     title, legend and column header are always pinned. --}}
                <div class="d-flex gap-3 px-3 py-2 small text-muted border-bottom flex-wrap">
                    <span><span class="d-inline-block" style="width:12px;height:12px;background:#16a34a;border-radius:2px;"></span> Achieved</span>
                    <span><span class="d-inline-block" style="width:12px;height:12px;background:#fde047;border-radius:2px;"></span> ≥75%</span>
                    <span><span class="d-inline-block" style="width:12px;height:12px;background:#fb923c;border-radius:2px;"></span> 50–75%</span>
                    <span><span class="d-inline-block" style="width:12px;height:12px;background:#dc2626;border-radius:2px;"></span> &lt;50%</span>
                    <span class="ms-auto">All figures ₹ Lakh</span>
                </div>
                <div style="max-height:62vh;overflow-y:auto;">
                <table class="table table-bordered table-sm mb-0 align-middle" style="font-size:0.82rem;border-collapse:separate;border-spacing:0;">
                    {{-- Sticky header needs its own stacking context + solid bg,
                         otherwise the coloured rows paint OVER it while scrolling. --}}
                    <thead class="table-light">
                        <tr>
                            <th style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;">Salesperson</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;">Actual Sale</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;">Pending</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;">Bingo</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;">Kingo</th>
                            <th class="text-end" style="position:sticky;top:0;z-index:10;background:#f8f9fa;box-shadow:inset 0 -1px 0 #dee2e6;">Super Kingo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($targetAchievers ?? [] as $r)
                            <tr>
                                <td class="fw-semibold">{{ $r['name'] }}</td>
                                <td class="text-end font-monospace fw-bold">{{ number_format($r['actual'], 2) }}</td>
                                <td class="text-end font-monospace" style="color:#9a3412;">{{ ($r['pending'] ?? 0) > 0 ? number_format($r['pending'], 2) : '—' }}</td>
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
                            <tr><td colspan="6" class="text-center text-muted py-4">No targets set for this month.</td></tr>
                        @endforelse
                    </tbody>
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

    .block-end { border-right: 2px solid #64748b !important; }
    .bg-forecast-light { background-color: #eff6ff !important; }
    .bg-forecast-light input { color: #1e40af !important; font-weight: 600; }
    .bg-sale-light { background-color: #ecfdf5 !important; }
    .bg-sale-light input { color: #065f46 !important; font-weight: 600; }
    .bg-pending-light { background-color: #fff7ed !important; }
    .bg-pending-light input { color: #9a3412 !important; font-weight: 600; }
    /* Scoped to the main report table only — this rule used to leak into the
       achievement modal and wipe its coloured cells on hover (white on white). */
    #pipelineTable tbody tr:hover td { background-color: transparent !important; }
    .converted-row td { background-color: #fdf4ff !important; border-bottom: 2px solid #e879f9 !important; }
    .converted-row .col-party::after { content:' (Converted)'; color:#d946ef; font-size:0.7rem; font-weight:bold; }
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
<template id="tplExtraSelect">
    <select class="excel-select" style="width:100%;min-width:120px;">
        <option value="">— Add person —</option>
        <optgroup label="Salespersons">
            @foreach($salespersons as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </optgroup>
        <optgroup label="CCare">
            @foreach($ccareUsers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </optgroup>
        <optgroup label="New Biz">
            @foreach($newBizUsers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </optgroup>
    </select>
</template>

{{-- ─── SCRIPTS ─────────────────────────────────────────────────────── --}}
<script>
document.addEventListener('DOMContentLoaded', function() {

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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
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


    // ── Inline edit: month-field ──────────────────────────────────────
    document.querySelectorAll('.month-field').forEach(field => {
        field.addEventListener('change', function() {
            if (this.hasAttribute('readonly')) {
                alert('Past entries cannot be edited.');
                return;
            }
            const tr       = this.closest('tr');
            const id       = tr.dataset.id;
            const month    = this.dataset.month;
            const fieldKey = this.dataset.field;
            const val      = this.value;

            fetch(`{{ route('sales-visits.pipeline.month.update') }}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ sales_pipeline_id: id, month_year: month, field: fieldKey, value: val })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    this.style.backgroundColor = '#e8f5e9';
                    setTimeout(() => { this.style.backgroundColor = 'transparent'; }, 500);
                }
            });
        });
    });
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
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
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
                    c.innerHTML = name; c.title = name + ' (click to change)';
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
                c.innerHTML = name; c.title = name + ' (click to change)';
                c.style.backgroundColor = '#e8f5e9'; setTimeout(() => c.style.backgroundColor = '', 600);
            });
        });
    }));

    // ── Other Reps: add / remove additional representatives ────────────────
    document.querySelectorAll('.extra-cell').forEach(cell => cell.addEventListener('click', function(e) {
        if (e.target.closest('.remove-extra') || this.querySelector('select')) return;
        const tpl = document.getElementById('tplExtraSelect');
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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ user_id: uid })
            }).then(r => r.json()).then(d => {
                sel.remove();
                if (d.success && !cell.querySelector(`.extra-tag[data-uid="${uid}"]`)) {
                    const s = document.createElement('span');
                    s.className = 'badge bg-label-secondary extra-tag';
                    s.dataset.uid = uid; s.style.fontSize = '0.62rem';
                    s.innerHTML = name + ' <i class="bx bx-x remove-extra" style="cursor:pointer;"></i>';
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
        fetch(`{{ url('sales-visits/sales-report') }}/${cell.dataset.id}/extra-rep/${tag.dataset.uid}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).then(r => r.json()).then(d => { if (d.success) tag.remove(); });
    });

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
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Could not save. Please try again.');
            if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="bx bx-save me-1"></i>Save'; }
        }
    })
    .catch(() => {
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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
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
@endsection
