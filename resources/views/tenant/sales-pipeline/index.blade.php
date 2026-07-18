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
    <div class="d-flex gap-2">
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
        @if($isAdmin || $isCcare)
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
        <div class="stat-chip" style="border-left-color:#22c55e;min-width:200px;"><span class="stat-label">This Month Target</span><span class="stat-value"><span class="badge bg-warning text-dark" style="font-size:0.65rem;">K ₹{{ number_format(($monthlyTargets[$currentMonthKey]['kingo'] ?? 0) / 100000, 2) }}L</span> <span class="badge bg-info text-dark" style="font-size:0.65rem;">B ₹{{ number_format(($monthlyTargets[$currentMonthKey]['bingo'] ?? 0) / 100000, 2) }}L</span></span></div>
        <div class="stat-chip" style="border-left-color:#3b82f6;"><span class="stat-label">Customers</span><span class="stat-value text-primary" id="totalCustomersCard">{{ $pipelines->count() }}</span></div>
        <div class="stat-chip" style="border-left-color:#f97316;"><span class="stat-label">Pending</span><span class="stat-value" id="pendingCard" style="color:#ea580c;">₹{{ number_format($totalPendingCurrentMonth ?? 0, 2) }}<em>L</em></span></div>
    </div>

    {{-- ─── FILTER BAR ─────────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="fw-bold text-muted" style="font-size:0.8rem">FILTER:</span>
                <select class="form-select form-select-sm" id="filterType" style="width:130px;"
                        onchange="applyFilters()">
                    <option value="">All Types</option>
                    <option value="Distributor">Distributor</option>
                    <option value="Dealer">Dealer</option>
                    <option value="Customer">Customer</option>
                </select>
                <input type="text" class="form-control form-control-sm" id="filterParty"
                       placeholder="Search customer…" style="width:200px;" oninput="applyFilters()">
                <select class="form-select form-select-sm" id="filterRep" style="width:210px;"
                        onchange="applyFilters()">
                    <option value="">
                        @if($isCcare || $isNewBiz) All Salespersons
                        @elseif($isSalesperson) All Representatives
                        @else All Reps @endif
                    </option>
                    @foreach($repFilterUsers as $au)
                        <option value="{{ strtolower($au->name) }}">{{ $au->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                    <i class="bx bx-x"></i> Clear
                </button>
                <div class="d-flex align-items-center gap-2 ms-auto" style="font-size:0.8rem">
                    <span class="text-muted">Rows/page:</span>
                    <select class="form-select form-select-sm" id="perPageSelect" style="width:72px;" onchange="changePerPage(this.value)">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="text-muted text-nowrap">
                        <strong id="pageInfo">—</strong> of <strong id="filterCount">{{ $pipelines->count() }}</strong>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="pagePrev" onclick="pagePrev()" title="Previous page">‹</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="pageNext" onclick="pageNext()" title="Next page">›</button>
                </div>
            </div>
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
                            <th rowspan="2" class="align-middle col-party">Party Name</th>
                            <th rowspan="2" class="align-middle col-status">Type</th>
                            <th rowspan="2" class="align-middle col-category" style="min-width:90px;">Category</th>
                            <th rowspan="2" class="align-middle col-repname" style="min-width:130px;">Representative</th>
                            <th rowspan="2" class="text-end align-middle col-potential" style="cursor:pointer;" onclick="sortPipeline('potential', this)" title="Click to sort">
                                Potential<span class="sort-caret"></span><br><small class="fw-normal opacity-75" style="font-size:0.65rem">₹ Lakh/Month</small>
                            </th>
                            @if($isAdmin)
                                <th rowspan="2" class="align-middle col-salesperson">Salesperson</th>
                            @elseif($isSalesperson)
                                <th rowspan="2" class="align-middle col-salesperson"
                                    title="Shows CCare name if type=CCare, New Biz name if type=New Biz">Representative</th>
                            @else
                                <th rowspan="2" class="align-middle col-salesperson">Salesperson</th>
                            @endif
                            <th rowspan="2" class="align-middle block-end" style="min-width:150px;">Brand</th>

                            @foreach($months as $m)
                                @php
                                    $actual      = $monthlyActuals[$m['key']] ?? 0;
                                    $tKingo      = $monthlyTargets[$m['key']]['kingo'] ?? 0;
                                    $tBingo      = $monthlyTargets[$m['key']]['bingo'] ?? 0;
                                    $achKingo    = $tKingo > 0 && $actual >= $tKingo;
                                    $achBingo    = $tBingo > 0 && $actual >= $tBingo;
                                    $isThisMonth = ($m['key'] === $currentMonthKey);
                                    // Past: 1 col (Sale only). Current: 4 (Forecast + Sale + Pending + Remarks).
                                    $colspan     = $isThisMonth ? 4 : 1;
                                @endphp
                                <th colspan="{{ $colspan }}" class="text-center align-middle border-bottom border-white"
                                    style="background-color:#0d9488;">
                                    {{ $m['display'] }}
                                    @if($achKingo)
                                        <span class="badge bg-warning text-dark ms-1 rounded-circle border border-white"
                                              style="padding:0.2em 0.35em" title="Kingo Achieved!">K</span>
                                    @endif
                                    @if($achBingo)
                                        <span class="badge bg-info text-dark ms-1 rounded-circle border border-white"
                                              style="padding:0.2em 0.35em" title="Bingo Achieved!">B</span>
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
                            <td class="col-potential text-end font-monospace" style="background:#d1fae5!important;color:#065f46;" id="totalPotential">—</td>
                            @if($isAdmin)
                                <td class="col-salesperson" style="background:#d1fae5!important;"></td>
                            @elseif($isSalesperson)
                                <td class="col-salesperson" style="background:#d1fae5!important;"></td>
                            @else
                                <td class="col-salesperson" style="background:#d1fae5!important;"></td>
                            @endif
                            <td class="block-end" style="background:#d1fae5!important;"></td>
                            @foreach($months as $m)
                                @php $isThisMonth = ($m['key'] === $currentMonthKey); @endphp
                                @if($isThisMonth)
                                    <td class="text-end" id="mtotal-forecast-{{ $m['key'] }}"
                                        style="background:#d1fae5!important;color:#1e40af;">—</td>
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
                                <td class="col-party fw-semibold text-dark text-truncate"
                                    title="{{ $row->party_name }}">{{ $row->party_name }}</td>

                                <td class="col-status p-0 align-middle">
                                    <select class="excel-select row-field status-select text-truncate"
                                            data-field="type">
                                        <option value="">-Select-</option>
                                        <option value="Distributor" {{ $row->type === 'Distributor' ? 'selected' : '' }}>Distributor</option>
                                        <option value="Dealer"      {{ $row->type === 'Dealer'      ? 'selected' : '' }}>Dealer</option>
                                        <option value="Customer"    {{ $row->type === 'Customer'    ? 'selected' : '' }}>Customer</option>
                                        @if($row->type && !in_array($row->type, ['Distributor', 'Dealer', 'Customer']))
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

                                {{-- Category column --}}
                                <td class="col-category align-middle text-center">
                                    @if($isAdmin)
                                        <select class="excel-select row-field status-select text-truncate" data-field="category" style="width:85px;">
                                            <option value="">-Select-</option>
                                            <option value="New Biz" {{ $catDisplay === 'New Biz' ? 'selected' : '' }}>New Biz</option>
                                            <option value="CCare" {{ $catDisplay === 'CCare' ? 'selected' : '' }}>CCare</option>
                                        </select>
                                    @elseif($catDisplay === 'New Biz')
                                        <span class="badge bg-primary fw-normal" style="font-size:0.75rem;">New Biz</span>
                                    @elseif($catDisplay === 'CCare')
                                        <span class="badge bg-info text-dark fw-normal" style="font-size:0.75rem;">CCare</span>
                                    @else
                                        -
                                    @endif
                                </td>

                                {{-- Representative name column --}}
                                <td class="col-repname text-truncate" title="{{ $catPerson }}">{{ $catPerson }}</td>

                                <td class="col-potential text-end font-monospace fw-bold text-dark">
                                    {{ number_format($row->total_business_potential ?? 0, 2) }}
                                </td>

                                @if($isAdmin)
                                    <td class="col-salesperson text-truncate" title="{{ $row->salesperson->name ?? '-' }}">{{ $row->salesperson->name ?? '-' }}</td>
                                @elseif($isSalesperson)
                                    <td class="col-salesperson text-truncate" title="{{ $repDisplay }}">{{ $repDisplay }}</td>
                                @else
                                    <td class="col-salesperson text-truncate" title="{{ $row->salesperson->name ?? '-' }}">{{ $row->salesperson->name ?? '-' }}</td>
                                @endif

                                <td class="p-0 align-middle block-end" style="max-width:150px;">
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

                                @foreach($months as $m)
                                    @php
                                        $mData       = $row->months->firstWhere('month_year', $m['key']);
                                        $isPast      = $m['key'] < $currentMonthKey;
                                        $isThisMonth = ($m['key'] === $currentMonthKey);
                                        $roAttr      = $isPast ? 'readonly title="Past entries cannot be edited"' : '';
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

                        {{-- ── Inline Add New Row (hidden) ── --}}
                        <tr id="addNewRow" style="display:none;background:#f0fdf4;">
                            <td class="text-center text-success fw-bold">+</td>
                            <td class="p-1">
                                <input type="text" id="new_party_name" class="form-control form-control-sm"
                                       placeholder="Customer Name *">
                            </td>
                            <td class="p-1">
                                <select id="new_type" class="form-select form-select-sm">
                                    <option value="">-Type-</option>
                                    <option value="Distributor">Distributor</option>
                                    <option value="Dealer">Dealer</option>
                                    <option value="Customer">Customer</option>
                                </select>
                            </td>
                            <td class="p-1">
                                <input type="number" id="new_potential" step="0.01"
                                       class="form-control form-control-sm text-end" placeholder="Potential ₹">
                            </td>
                            @if($isAdmin)
                                <td class="p-1">
                                    <select id="new_salesperson_id" class="form-select form-select-sm">
                                        <option value="">-SP-</option>
                                        @foreach($salespersons as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-1">
                                    <select id="new_new_biz_id" class="form-select form-select-sm">
                                        <option value="">-NB-</option>
                                        @foreach($newBizUsers as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-1">
                                    <select id="new_ccare_id" class="form-select form-select-sm">
                                        <option value="">-CC-</option>
                                        @foreach($assignedUsers as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            @else
                                <td class="p-2 text-muted small align-middle">Auto-assigned</td>
                            @endif
                            <td class="p-1">
                                <select id="new_product" class="form-select form-select-sm">
                                    <option value="">-Brand-</option>
                                    <option value="Rust-X">Rust-X</option>
                                    <option value="Dr.Bio">Dr.Bio</option>
                                    <option value="Fillezy">Fillezy</option>
                                    <option value="KIF">KIF</option>
                                    <option value="Zorbit">Zorbit</option>
                                    <option value="Tuffpaulin">Tuffpaulin</option>
                                    <option value="HITECH">HITECH</option>
                                </select>
                            </td>
                            {{-- Empty cells for month columns --}}
                            @foreach($months as $m)
                                @php $isThisMonth = ($m['key'] === $currentMonthKey); @endphp
                                @if($isThisMonth)
                                    <td class="p-0 bg-forecast-light"></td>
                                @endif
                                <td class="p-0 bg-sale-light {{ !$isThisMonth ? 'block-end' : '' }}"></td>
                                @if($isThisMonth)
                                    <td class="p-0 bg-pending-light"></td>
                                    <td class="p-0 block-end"></td>
                                @endif
                            @endforeach
                        </tr>
                        {{-- Save / Cancel for inline add --}}
                        <tr id="addNewRowActions" style="display:none;background:#f0fdf4;">
                            <td colspan="100" class="p-2 text-center">
                                <button class="btn btn-sm btn-success px-4 me-2" onclick="saveNewPipeline()">
                                    <i class="bx bx-save me-1"></i>Save
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="cancelNewPipeline()">
                                    <i class="bx bx-x me-1"></i>Cancel
                                </button>
                            </td>
                        </tr>
                    </tbody>
        </table>
    </div>

    {{-- Add New Entry trigger --}}
    <div class="d-flex justify-content-center py-2 mt-1">
        <button class="btn btn-sm btn-outline-success rounded-pill px-4" id="addRowTriggerBtn"
                onclick="showAddNewRow()" style="border-color:#0f766e;color:#0f766e;">
            <i class="bx bx-plus-circle me-1"></i> Add New Entry
        </button>
    </div>
</div>

{{-- ─── TARGETS MODAL ────────────────────────────────────────────────── --}}
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
    #pipelineTable thead th.col-party,
    #pipelineTable thead th.col-status,
    #pipelineTable thead th.col-potential,
    #pipelineTable thead th.col-salesperson,
    #pipelineTable thead th.col-ccare {
        position: sticky !important; z-index: 30 !important; top: 0 !important;
    }
    #pipelineTable tbody td.col-sno,
    #pipelineTable tbody td.col-party,
    #pipelineTable tbody td.col-status,
    #pipelineTable tbody td.col-potential,
    #pipelineTable tbody td.col-salesperson,
    #pipelineTable tbody td.col-ccare {
        background-color: #fff !important; z-index: 20;
    }
    .col-sno        { position:sticky; left:0;     min-width:40px;  max-width:40px; }
    .col-party      { position:sticky; left:40px;  min-width:150px; max-width:150px; overflow:hidden; }
    .col-status     { position:sticky; left:190px; min-width:110px; max-width:110px; }
    .col-potential  { position:sticky; left:300px; min-width:95px;  max-width:95px; }
    .col-salesperson{ position:sticky; left:395px; min-width:120px; max-width:120px; overflow:hidden; }
    .col-newbiz     { position:sticky; left:515px; min-width:120px; max-width:120px; overflow:hidden; }
    .col-ccare      { position:sticky; left:635px; min-width:120px; max-width:120px; overflow:hidden; }

    .block-end { border-right: 2px solid #64748b !important; }
    .bg-forecast-light { background-color: #eff6ff !important; }
    .bg-forecast-light input { color: #1e40af !important; font-weight: 600; }
    .bg-sale-light { background-color: #ecfdf5 !important; }
    .bg-sale-light input { color: #065f46 !important; font-weight: 600; }
    .bg-pending-light { background-color: #fff7ed !important; }
    .bg-pending-light input { color: #9a3412 !important; font-weight: 600; }
    tr:hover td { background-color: transparent !important; }
    .converted-row td { background-color: #fdf4ff !important; border-bottom: 2px solid #e879f9 !important; }
    .converted-row .col-party::after { content:' (Converted)'; color:#d946ef; font-size:0.7rem; font-weight:bold; }
    .pipeline-row-hidden { display: none !important; }
</style>

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
    // Trigger initial filter and stats calculation on page load
    applyFilters();
});

// ── Filters ───────────────────────────────────────────────────────────
// --- Column sorting (Potential, current-month Fcst/Sale/Pend, and any month's Sale) ---
const SORT_CURRENT_MONTH = @json($currentMonthKey);
const ANNUAL_MAP  = @json($annualMap);
const ANNUAL_GRAND = @json($annualGrand);
let sortState = { key: null, dir: 1 };
function sortPipeline(key, el) {
    sortState.dir = (sortState.key === key) ? -sortState.dir : 1;
    sortState.key = key;
    const body = document.getElementById('pipelineBody');
    const totalsRow = document.getElementById('monthTotalsRow');
    const rows = Array.from(body.querySelectorAll('tr[data-id]'));
    const monthVal = (tr, mk) => { try { return parseFloat((JSON.parse(tr.dataset.months || '{}'))[mk] || 0); } catch (e) { return 0; } };
    const val = (tr) => {
        if (key === 'potential') return parseFloat(tr.dataset.potential || 0);
        if (key === 'pending')   return parseFloat(tr.dataset.pending || 0);
        if (key === 'forecast')  return parseFloat(tr.dataset.forecast || 0);
        if (key === 'sale')      return monthVal(tr, SORT_CURRENT_MONTH);
        if (key.indexOf('month:') === 0) return monthVal(tr, key.slice(6)); // any month's sale
        return 0;
    };
    rows.sort((a, b) => (val(a) - val(b)) * sortState.dir);
    rows.forEach(r => body.appendChild(r));                 // re-append in new order
    if (totalsRow) body.insertBefore(totalsRow, body.firstChild); // keep totals on top
    document.querySelectorAll('.sort-caret').forEach(c => c.textContent = '');
    if (el) { const c = el.querySelector('.sort-caret'); if (c) c.textContent = sortState.dir > 0 ? ' ▲' : ' ▼'; }
    if (typeof renderPage === 'function') renderPage(); // re-apply pagination to sorted order
}

// --- Pagination state (client-side; works together with the filters) ---
let currentPage = 1;
let perPage = 10;

function applyFilters() {
    const typeVal  = document.getElementById('filterType').value.toLowerCase();
    const partyVal = document.getElementById('filterParty').value.toLowerCase().trim();
    const repVal   = document.getElementById('filterRep').value.toLowerCase().trim();

    document.querySelectorAll('#pipelineBody tr[data-id]').forEach(tr => {
        const matchType  = !typeVal  || (tr.dataset.type  || '').toLowerCase() === typeVal;
        const matchParty = !partyVal || (tr.dataset.party || '').includes(partyVal);
        const matchRep   = !repVal   || (tr.dataset.rep   || '').includes(repVal);
        tr.dataset.filtermatch = (matchType && matchParty && matchRep) ? '1' : '0';
    });
    currentPage = 1;
    renderPage();          // slice to the current page
    updateDynamicStats();  // totals from ALL filtered rows (not just the page)
}

// Reveal only the current page's slice of filter-matched rows.
function renderPage() {
    const matched = Array.from(document.querySelectorAll('#pipelineBody tr[data-id]'))
                         .filter(tr => tr.dataset.filtermatch !== '0');
    const totalPages = Math.max(1, Math.ceil(matched.length / perPage));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    const start = (currentPage - 1) * perPage, end = start + perPage;

    document.querySelectorAll('#pipelineBody tr[data-id]').forEach(tr => tr.classList.add('pipeline-row-hidden'));
    matched.forEach((tr, i) => { if (i >= start && i < end) tr.classList.remove('pipeline-row-hidden'); });

    const fc = document.getElementById('filterCount'); if (fc) fc.textContent = matched.length;
    const info = document.getElementById('pageInfo');
    if (info) info.textContent = matched.length ? ((start + 1) + '–' + Math.min(end, matched.length)) : '0';
    const prev = document.getElementById('pagePrev'), next = document.getElementById('pageNext');
    if (prev) prev.disabled = currentPage <= 1;
    if (next) next.disabled = currentPage >= totalPages;
}

function changePerPage(v) { perPage = parseInt(v) || 10; currentPage = 1; renderPage(); }
function pagePrev() { if (currentPage > 1) { currentPage--; renderPage(); } }
function pageNext() { currentPage++; renderPage(); }

function updateDynamicStats() {
    let pending = 0, potential = 0, forecast = 0;
    const monthTotals = {};
    let count = 0;
    const salespersonsSeen = new Set();

    document.querySelectorAll('#pipelineBody tr[data-id]').forEach(tr => {
        if (tr.dataset.filtermatch === '0') return; // every filtered row, across all pages
        pending   += parseFloat(tr.dataset.pending || 0);
        forecast  += parseFloat(tr.dataset.forecast || 0);
        potential += parseFloat(tr.dataset.potential || 0);
        count++;
        if (tr.dataset.salesperson) salespersonsSeen.add(tr.dataset.salesperson);
        try {
            const months = JSON.parse(tr.dataset.months || '{}');
            for (const [mk, val] of Object.entries(months)) {
                monthTotals[mk] = (monthTotals[mk] || 0) + parseFloat(val || 0);
            }
        } catch(e) {}
    });

    // YoY comes from per-salesperson annual figures (not pipeline rows), so a
    // salesperson with no pipeline rows still shows their annual on filter.
    const repVal = (document.getElementById('filterRep').value || '').toLowerCase().trim();
    let yoy = [0, 0, 0, 0];
    if (!repVal) {
        yoy = [ANNUAL_GRAND['22_23'], ANNUAL_GRAND['23_24'], ANNUAL_GRAND['24_25'], ANNUAL_GRAND['25_26']];
    } else if (ANNUAL_MAP[repVal]) {
        yoy = ANNUAL_MAP[repVal].slice();
    } else {
        // A CCare / New Biz rep: sum the annuals of the salespersons under them
        salespersonsSeen.forEach(sp => { const a = ANNUAL_MAP[sp]; if (a) { yoy[0]+=a[0]; yoy[1]+=a[1]; yoy[2]+=a[2]; yoy[3]+=a[3]; } });
    }

    const fyTotal = Object.values(monthTotals).reduce((a, b) => a + b, 0);
    function fmtL(v) { return '₹' + parseFloat(v).toFixed(2) + '<em>L</em>'; }
    document.getElementById('cardYoy22_23').innerHTML = fmtL(yoy[0]);
    document.getElementById('cardYoy23_24').innerHTML = fmtL(yoy[1]);
    document.getElementById('cardYoy24_25').innerHTML = fmtL(yoy[2]);
    document.getElementById('cardYoy25_26').innerHTML = fmtL(yoy[3]);
    document.getElementById('fyTotalCard').innerHTML  = fmtL(fyTotal);
    document.getElementById('pendingCard').innerHTML  = fmtL(pending);
    document.getElementById('totalCustomersCard').textContent = count;
    const potEl = document.getElementById('totalPotential');
    if (potEl) potEl.textContent = potential.toFixed(2);

    // Past-month sale totals
    for (const [mk, total] of Object.entries(monthTotals)) {
        const cell = document.getElementById('mtotal-' + mk);
        if (cell) cell.textContent = (total > 0 || mk === SORT_CURRENT_MONTH) ? total.toFixed(2) : '—';
    }
    // Current month Forecast / Sale / Pending totals (always show the sum, even 0.00)
    const fcEl = document.getElementById('mtotal-forecast-' + SORT_CURRENT_MONTH);
    if (fcEl) fcEl.textContent = forecast.toFixed(2);
    const saleEl = document.getElementById('mtotal-' + SORT_CURRENT_MONTH);
    if (saleEl) saleEl.textContent = (monthTotals[SORT_CURRENT_MONTH] || 0).toFixed(2);
    const pendEl = document.getElementById('mtotal-pending-' + SORT_CURRENT_MONTH);
    if (pendEl) pendEl.textContent = pending.toFixed(2);
}

function clearFilters() {
    document.getElementById('filterType').value  = '';
    document.getElementById('filterParty').value = '';
    document.getElementById('filterRep').value   = '';
    applyFilters();
}

// ── Inline add row ────────────────────────────────────────────────────
function showAddNewRow() {
    document.getElementById('addNewRow').style.display       = '';
    document.getElementById('addNewRowActions').style.display = '';
    document.getElementById('addRowTriggerBtn').style.display = 'none';
    document.getElementById('new_party_name').focus();
}

function cancelNewPipeline() {
    document.getElementById('addNewRow').style.display       = 'none';
    document.getElementById('addNewRowActions').style.display = 'none';
    document.getElementById('addRowTriggerBtn').style.display = '';
    // Clear inputs
    ['new_party_name','new_type','new_potential','new_product',
     'new_salesperson_id','new_new_biz_id','new_ccare_id']
        .forEach(id => { const el = document.getElementById(id); if(el) el.value = ''; });
}

function saveNewPipeline() {
    const partyName = document.getElementById('new_party_name')?.value?.trim();
    if (!partyName) {
        alert('Customer Name is required.');
        document.getElementById('new_party_name')?.focus();
        return;
    }

    const payload = {
        party_name:               partyName,
        type:                     document.getElementById('new_type')?.value        || '',
        total_business_potential: document.getElementById('new_potential')?.value   || 0,
        product:                  document.getElementById('new_product')?.value     || '',
        salesperson_id:           document.getElementById('new_salesperson_id')?.value || '',
        new_biz_id:               document.getElementById('new_new_biz_id')?.value  || '',
        ccare_id:                 document.getElementById('new_ccare_id')?.value    || '',
    };

    const saveBtn = document.querySelector('#addNewRowActions .btn-success');
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
