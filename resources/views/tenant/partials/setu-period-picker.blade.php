{{--
    Shared Setu sync period picker — one instance per page (not one per
    row/table entry; a page with a table of syncable rows should render this
    ONCE above the table and have every row's Sync action read this single
    control, mirroring this app's existing "pick a month up top, act on rows
    below" pattern from the Sales Report page's own Targets modal).

    Deliberately named setu_fy / setu_period / etc, never bare "fy" — the
    Sales Report page already has its own unrelated ?fy= filter controlling
    which year of the HRX book the grid displays (format "2026-2027", no
    "FY-" prefix); this picker must never be confused with that control.

    Reads: App\Services\SetuReportService::availableFiscalYears() for the
    year options. Writes: nothing server-side — window.getSetuPeriod()
    (resources/assets/js/app/setu-period-picker.js) is how calling JS reads
    the current selection when building a sync request.
--}}
@php
    $setuFyOptions = app(\App\Services\SetuReportService::class)->availableFiscalYears();
@endphp
<div class="setu-period-picker d-inline-flex align-items-center gap-1 flex-wrap">
    <span class="small text-muted fw-semibold me-1">Sync period:</span>
    <select id="setu_fy" class="form-select form-select-sm" style="width:auto;">
        @foreach($setuFyOptions as $i => $setuFy)
            <option value="{{ $setuFy }}" {{ $i === 0 ? 'selected' : '' }}>{{ str_replace('FY-', 'FY ', $setuFy) }}</option>
        @endforeach
    </select>
    <select id="setu_period" class="form-select form-select-sm" style="width:auto;">
        <option value="whole" selected>Whole Year</option>
        <option value="Q1">Q1 (Apr-Jun)</option>
        <option value="Q2">Q2 (Jul-Sep)</option>
        <option value="Q3">Q3 (Oct-Dec)</option>
        <option value="Q4">Q4 (Jan-Mar)</option>
        <option value="custom">Custom months&hellip;</option>
    </select>
    <span id="setu_custom_months" class="align-items-center gap-1" style="display:none;">
        <select id="setu_from_month" class="form-select form-select-sm" style="width:auto;"></select>
        <span class="small text-muted">to</span>
        <select id="setu_to_month" class="form-select form-select-sm" style="width:auto;"></select>
    </span>
</div>
