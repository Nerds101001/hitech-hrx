{{--
    Tally sync period picker — mirrors tenant.partials.setu-period-picker
    exactly, pointed at TallyReportService::availableFiscalYears() and using
    the `tally_*` element ids / window.getTallyPeriod() so it never collides
    with the Setu picker's ids or the Sales Report page's own unrelated ?fy=
    filter.
--}}
@php
    $tallyFyOptions = app(\App\Services\TallyReportService::class)->availableFiscalYears();
@endphp
<div class="tally-period-picker d-inline-flex align-items-center gap-1 flex-wrap">
    <span class="small text-muted fw-semibold me-1">Sync period:</span>
    <select id="tally_fy" class="form-select form-select-sm" style="width:auto;">
        @foreach($tallyFyOptions as $i => $tallyFy)
            <option value="{{ $tallyFy }}" {{ $i === 0 ? 'selected' : '' }}>{{ str_replace('FY-', 'FY ', $tallyFy) }}</option>
        @endforeach
    </select>
    <select id="tally_period" class="form-select form-select-sm" style="width:auto;">
        <option value="whole" selected>Whole Year</option>
        <option value="Q1">Q1 (Apr-Jun)</option>
        <option value="Q2">Q2 (Jul-Sep)</option>
        <option value="Q3">Q3 (Oct-Dec)</option>
        <option value="Q4">Q4 (Jan-Mar)</option>
        <option value="custom">Custom months&hellip;</option>
    </select>
    <span id="tally_custom_months" class="align-items-center gap-1" style="display:none;">
        <select id="tally_from_month" class="form-select form-select-sm" style="width:auto;"></select>
        <span class="small text-muted">to</span>
        <select id="tally_to_month" class="form-select form-select-sm" style="width:auto;"></select>
    </span>
</div>
