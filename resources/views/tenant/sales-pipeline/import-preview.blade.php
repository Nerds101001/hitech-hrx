@extends('layouts/layoutMaster')

@section('title', 'Preview & Confirm Import')

@section('page-style')
<style>
.btn-hitech { background: linear-gradient(135deg, #127464, #1b6ca8); color: white !important; border: none; }
.btn-hitech:hover { box-shadow: 0 4px 12px rgba(18,116,100,.3); transform: translateY(-1px); }
table.prev-table { border-collapse: separate; border-spacing: 0; font-size: 11px; }
table.prev-table th { background: #0f766e; color: #fff; font-weight: 600; text-align: center; padding: 5px 6px; border: 1px solid #0b5e56; white-space: nowrap; }
table.prev-table th.left { text-align: left; }
table.prev-table td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; white-space: nowrap; }
table.prev-table tr:hover td { background: #f0fdf4; }
.sale-cell { text-align: right; font-variant-numeric: tabular-nums; color: #065f46; font-weight: 600; }
.zero-cell { color: #d1d5db; text-align: right; }
.party-col { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    <form action="{{ route('sales-visits.pipeline.import.process') }}" method="POST">
        @csrf
        <input type="hidden" name="temp_path" value="{{ $path }}">
        <input type="hidden" name="salesperson_id" value="{{ $salesperson->id }}">
        <input type="hidden" name="ccare_id" value="{{ $ccareId }}">
        <input type="hidden" name="new_biz_id" value="{{ $newBizId }}">
        <input type="hidden" name="type_col" value="{{ $typeColIdx }}">
        <input type="hidden" name="product_col" value="{{ $productColIdx }}">
        <input type="hidden" name="party_col" value="{{ $partyColIdx }}">
        <input type="hidden" name="potential_col" value="{{ $potentialColIdx }}">
        <input type="hidden" name="data_start_row" value="{{ $dataStartRowIdx }}">
        <input type="hidden" name="month_mappings" value="{{ json_encode($monthMappings) }}">

        {{-- Header strip --}}
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0">Preview & Confirm Import</h5>
                <span class="text-muted small">Importing for: <strong>{{ $salesperson->getFullName() }}</strong></span>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="badge bg-success fs-6 px-3 py-2">
                    <i class="bx bx-check me-1"></i>{{ $totalMonthsDetected }} months detected
                </span>
                <span class="badge bg-primary fs-6 px-3 py-2">
                    {{ count($previewData) }} of {{ count($previewData) >= 50 ? '50+ ' : '' }} parties previewed
                </span>
                <a href="{{ route('sales-visits.pipeline.import') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Cancel</a>
                <button type="submit" class="btn btn-hitech rounded-pill px-4 shadow-sm">
                    <i class="bx bx-check-circle me-1"></i> Confirm & Import
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="card border-0 shadow-sm" style="border-radius:12px; overflow:hidden;">
            <div class="table-responsive">
                <table class="prev-table w-100">
                    <thead>
                        <tr>
                            <th class="left" style="min-width:140px;max-width:160px;">Party Name</th>
                            <th style="width:60px;">Type</th>
                            <th style="width:60px;">Brand</th>
                            <th style="width:70px;">Potential<br><span style="font-weight:400;font-size:10px;">₹ Lakh</span></th>
                            @foreach($previewMonthKeys as $mk)
                                @php
                                    [$yr, $mo] = explode('-', $mk);
                                    $label = \Carbon\Carbon::createFromDate($yr, $mo, 1)->format('M y');
                                @endphp
                                <th style="width:58px;font-size:10px;">{{ $label }}<br><span style="font-weight:400;font-size:9px;">₹L</span></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($previewData as $i => $row)
                        <tr>
                            <td class="fw-semibold left party-col" title="{{ $row['party_name'] }}">
                                {{ $row['party_name'] }}
                                <input type="hidden" name="potentials[{{ $row['row_num'] }}]" value="{{ $row['potential'] }}">
                            </td>
                            <td class="text-center">
                                @php
                                    $t = strtoupper($row['type'] ?? '');
                                    $badgeColor = 'secondary';
                                    if ($t === 'DISTRIBUTOR') { $badgeColor = 'success'; }
                                    elseif ($t === 'DEALER') { $badgeColor = 'primary'; }
                                    elseif ($t === 'CUSTOMER') { $badgeColor = 'info'; }
                                    elseif ($t === 'CCARE') { $badgeColor = 'warning'; }
                                    elseif ($t === 'NEWBIZ' || $t === 'NEW BIZ') { $badgeColor = 'danger'; }
                                @endphp
                                <span class="badge bg-label-{{ $badgeColor }} text-uppercase" style="font-size:10px;">{{ $row['type'] ?: '—' }}</span>
                            </td>
                            <td class="text-center" style="font-size:11px; font-weight:600;">
                                {{ $row['product'] ?: '—' }}
                            </td>
                            <td class="text-end fw-semibold">{{ number_format($row['potential'], 2) }}<small class="text-muted ms-1">L</small></td>
                            @foreach($previewMonthKeys as $mk)
                                @php $val = $row['months'][$mk] ?? null; @endphp
                                <td class="{{ ($val && $val > 0) ? 'sale-cell' : 'zero-cell' }}">
                                    {{ ($val && $val > 0) ? number_format($val, 2) : '—' }}
                                </td>
                            @endforeach
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ 3 + count($previewMonthKeys) }}" class="text-center text-muted py-5">
                                No party data detected. Check column mapping.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light text-muted small py-2 px-3">
                Showing first {{ count($previewData) }} parties · {{ $totalMonthsDetected }} months total will be imported · All {{ count($monthMappings) }} months of sale/pending data will be saved
            </div>
        </div>

        <div class="text-end mt-3 mb-5">
            <a href="{{ route('sales-visits.pipeline.import') }}" class="btn btn-outline-secondary rounded-pill me-2 px-4">Cancel</a>
            <button type="submit" class="btn btn-hitech rounded-pill px-5 shadow-sm">
                <i class="bx bx-check-circle me-1"></i> Confirm & Import
            </button>
        </div>

    </form>
</div>
@endsection
