@extends('layouts.layoutMaster')

@section('title', 'New Travel Claim')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="container-fluid animate__animated animate__fadeIn">
    {{-- Hero Header --}}
    <div class="hitech-page-hero mb-6">
        <div class="hitech-page-hero-text d-flex justify-content-between align-items-center w-100">
            <div>
                <h4 class="greeting text-white mb-1">{{ isset($claim) ? 'Edit Travel Claim' : 'New Travel Claim' }}</h4>
                <p class="sub-text text-white-50 mb-0">{{ isset($claim) ? 'Resubmit your rejected or draft claim.' : 'Submit your monthly expenses for reimbursement.' }}</p>
            </div>
            <div>
                <a href="{{ route('travel-claims.index') }}" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm" style="background-color: #ffffff !important; color: #333333 !important;">Back to Claims</a>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ isset($claim) ? route('travel-claims.update', $claim->id) : route('travel-claims.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom pt-4 pb-3">
                <h5 class="card-title mb-0">Claim Header</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">
                            Claim Month
                            <i class="bx bx-info-circle text-danger ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Submissions after the 7th of the following month will incur a 10% penalty on the net payable amount."></i>
                        </label>
                        <input type="month" name="claim_month" id="claimMonthInput" class="form-control" value="{{ isset($claim) ? $claim->claim_month : '' }}" onchange="generateMonthGrid(); calculateTotals()" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Company</label>
                        <select name="company" class="form-select" required>
                            <option value="Hi Tech International" {{ (isset($claim) && $claim->company == 'Hi Tech International') ? 'selected' : '' }}>Hi Tech International</option>
                            <option value="KEEP IT FRESH LLP" {{ (isset($claim) && $claim->company == 'KEEP IT FRESH LLP') ? 'selected' : '' }}>KEEP IT FRESH LLP</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <h5 class="mb-0 fw-bold">Daily Travel Expenses</h5>
            <span class="text-muted small">Fill out your expenses for the month. Blank rows will be ignored automatically.</span>
        </div>

        <div class="table-responsive bg-white rounded shadow-sm border mb-3">
            <table class="table table-bordered table-hover table-sm mb-0 align-middle text-nowrap" style="min-width: 1400px; font-size: 0.85rem;">
                <thead class="bg-light">
                    <tr class="text-center align-middle" style="line-height: 1.2;">
                        <th style="min-width: 75px;">Date</th>
                        <th style="min-width: 90px;">Mode</th>
                        <th style="min-width: 120px;">Customer Name</th>
                        <th style="min-width: 80px;">Meter<br>Start</th>
                        <th style="min-width: 80px;">Meter<br>End</th>
                        <th style="min-width: 60px;">Total<br>KM</th>
                        <th style="min-width: 130px;">Odometer<br>Proof</th>
                        <th style="min-width: 80px;">Conv.<br>(₹)</th>
                        <th style="min-width: 60px;">Outst.<br>>5hrs</th>
                        <th style="min-width: 75px;">Food<br>(₹)</th>
                        <th style="min-width: 75px;">Lodging<br>(₹)</th>
                        <th style="min-width: 75px;">Courier<br>(₹)</th>
                        <th style="min-width: 75px;">Other<br>(₹)</th>
                        <th style="min-width: 120px;">Remarks</th>
                    </tr>
                </thead>
                <tbody id="expenseRowsGrid">
                    <!-- Rows inserted by JS -->
                </tbody>
            </table>
        </div>

        <div class="card shadow-sm border-0 mb-4 bg-white mt-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-muted text-uppercase fw-bold">Total Expenses</h6>
                    <h4 id="grandTotal" class="mb-0 text-primary fw-bold">₹0.00</h4>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <h5 class="mb-0 fw-bold">Advances Taken</h5>
            <button type="button" class="btn btn-secondary btn-sm shadow-sm" onclick="addAdvanceRow()"><i class="bx bx-plus me-1"></i> Add Advance</button>
        </div>

        <div id="advanceRows">
            <!-- Advances inserted by JS -->
        </div>
        
        <div class="card shadow-sm border mb-4 mt-4" style="background: transparent;">
            <div class="card-body text-end p-4">
                <h6 class="text-danger mb-2">Total Advances: <span id="totalAdvances">₹0.00</span></h6>
                <p id="latePenaltyWarning" class="text-danger small mb-1 fw-bold" style="display:none;"><i class="bx bx-error-circle"></i> 10% Late Submission Penalty Applied to Net Payable!</p>
                <h3 class="mb-3">Net Payable: <span id="netPayableText" class="text-success fw-bold">₹0.00</span></h3>
                <p class="text-muted small mb-4">By submitting, you certify these expenses were incurred for official business in compliance with the company Travel Policy.</p>
                <button type="submit" class="btn btn-success btn-lg px-5 shadow"><i class="bx bx-check-circle me-1"></i> Submit Claim</button>
            </div>
        </div>
    </form>
</div>

<script>
    let advanceIndex = 0;
    
    // In edit mode, store existing items so we can prefill the grid
    let existingItemsData = {};
    @if(isset($claim) && $claim->items->count() > 0)
        let itemsArr = {!! json_encode($claim->items) !!};
        itemsArr.forEach(item => {
            let d = item.date.split('T')[0];
            existingItemsData[d] = item;
        });
    @endif

    function generateMonthGrid() {
        let monthStr = $('#claimMonthInput').val();
        let grid = $('#expenseRowsGrid');
        grid.empty();
        
        if (!monthStr) return;
        
        let parts = monthStr.split('-');
        let year = parseInt(parts[0]);
        let month = parseInt(parts[1]) - 1; // JS months are 0-11
        
        let startDate = new Date(year, month, 1);
        let endDate = new Date(year, month + 1, 0); // Last day of month
        
        const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        for (let d = 1; d <= endDate.getDate(); d++) {
            let currentDate = new Date(year, month, d);
            let dateString = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            let dayName = daysOfWeek[currentDate.getDay()];
            let displayDate = String(d).padStart(2, '0') + ' ' + dayName;
            
            // Check if there is existing data for this date
            let ex = existingItemsData[dateString] || {};
            
            // Generate table row
            let html = `
            <tr class="expense-row" id="row_${d}">
                <td class="align-middle bg-light fw-bold text-center" style="font-size: 0.75rem;">
                    ${displayDate}
                    <input type="hidden" name="items[${d}][date]" value="${dateString}">
                </td>
                <td class="p-1">
                    <select name="items[${d}][mode_of_travel]" class="form-select form-select-sm mode-select p-1" style="font-size: 0.8rem;" onchange="calculateRow(${d})">
                        <option value="">--</option>
                        <option value="Bike" ${ex.mode_of_travel == 'Bike' ? 'selected' : ''}>Bike</option>
                        <option value="Car" ${ex.mode_of_travel == 'Car' ? 'selected' : ''}>Car</option>
                        <option value="Train" ${ex.mode_of_travel == 'Train' ? 'selected' : ''}>Train</option>
                        <option value="Bus" ${ex.mode_of_travel == 'Bus' ? 'selected' : ''}>Bus</option>
                        <option value="Flight" ${ex.mode_of_travel == 'Flight' ? 'selected' : ''}>Flight</option>
                    </select>
                </td>
                <td class="p-1"><input type="text" name="items[${d}][to_location]" class="form-control form-control-sm p-1" style="font-size: 0.8rem;" value="${ex.to_location || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][start_meter]" class="form-control form-control-sm start-meter p-1 text-center" style="font-size: 0.8rem;" oninput="calculateRow(${d})" value="${ex.start_meter || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][end_meter]" class="form-control form-control-sm end-meter p-1 text-center" style="font-size: 0.8rem;" oninput="calculateRow(${d})" value="${ex.end_meter || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][distance_km]" class="form-control form-control-sm distance bg-light p-1 text-center" style="font-size: 0.8rem;" readonly value="${ex.distance_km || ''}"></td>
                <td class="p-1">
                    <input type="file" name="items[${d}][photo]" class="form-control form-control-sm photo p-1" style="font-size: 0.75rem;" onchange="calculateRow(${d})" accept="image/jpeg,image/png,image/jpg,application/pdf">
                    ${ex.photo_path ? `<input type="hidden" name="items[${d}][existing_photo]" value="${ex.photo_path}"><a href="/storage/${ex.photo_path}" target="_blank" class="small d-block mt-1">View File</a>` : ''}
                </td>
                <td class="p-1"><input type="text" class="form-control form-control-sm conveyance bg-light text-primary fw-bold text-end p-1" style="font-size: 0.8rem;" readonly value="${(ex.conveyance_amount || 0).toFixed(2)}"></td>
                <td class="text-center align-middle p-1">
                    <input class="form-check-input" type="checkbox" name="items[${d}][is_outstation]" value="1" onchange="toggleOutstation(${d}, this)" ${ex.is_outstation ? 'checked' : ''}>
                </td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][food_allowance]" class="form-control form-control-sm food text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${(ex.food_allowance || 0).toFixed(2)}" ${ex.is_outstation ? 'readonly' : ''}></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][lodging_amount]" class="form-control form-control-sm lodging text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${(ex.lodging_amount || 0).toFixed(2)}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][courier_amount]" class="form-control form-control-sm courier text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${(ex.courier_amount || 0).toFixed(2)}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][other_amount]" class="form-control form-control-sm other text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${(ex.other_amount || 0).toFixed(2)}"></td>
                <td class="p-1"><input type="text" name="items[${d}][remarks]" class="form-control form-control-sm p-1" style="font-size: 0.8rem;" placeholder="..." value="${ex.remarks || ''}"></td>
            </tr>`;
            grid.append(html);
            
            // Ensure logic runs initially if there is existing data
            if (Object.keys(ex).length > 0) {
                calculateRow(d);
            }
        }
        calculateTotals();
    }

    function calculateRow(idx) {
        let row = $('#row_' + idx);
        let mode = row.find('.mode-select').val();
        let start = parseFloat(row.find('.start-meter').val()) || 0;
        let end = parseFloat(row.find('.end-meter').val()) || 0;
        let dist = parseFloat(row.find('.distance').val()) || 0;
        
        if (['Bike', 'Car'].includes(mode)) {
            if (end > start && end !== 0) {
                dist = end - start;
                row.find('.distance').val(dist.toFixed(2));
            } else {
                dist = parseFloat(row.find('.distance').val()) || 0;
            }
        } else {
            row.find('.distance').val('');
        }

        let rate = 0;
        if(mode === 'Bike') rate = 4.00;
        if(mode === 'Car') rate = 9.50;

        let conveyance = dist * rate;
        let photoVal = row.find('.photo').val();
        let hasExistingPhoto = row.find('input[name*="[existing_photo]"]').length > 0;
        
        if (['Bike', 'Car'].includes(mode) && !photoVal && !hasExistingPhoto && conveyance > 0) {
            conveyance = conveyance * 0.70; // 30% penalty
        }

        row.find('.conveyance').val(conveyance.toFixed(2));
        calculateTotals();
    }

    function toggleOutstation(idx, checkbox) {
        let row = $('#row_' + idx);
        if(checkbox.checked) {
            row.find('.food').val('300.00').prop('readonly', true);
        } else {
            row.find('.food').val('0.00').prop('readonly', false);
        }
        calculateTotals();
    }

    function calculateTotals() {
        let tConv = 0, tFood = 0, tLodg = 0, tCour = 0, tOth = 0;
        
        $('.expense-row').each(function() {
            tConv += parseFloat($(this).find('.conveyance').val()) || 0;
            tFood += parseFloat($(this).find('.food').val()) || 0;
            tLodg += parseFloat($(this).find('.lodging').val()) || 0;
            tCour += parseFloat($(this).find('.courier').val()) || 0;
            tOth  += parseFloat($(this).find('.other').val()) || 0;
        });

        let grandTotal = tConv + tFood + tLodg + tCour + tOth;
        $('#grandTotal').text('₹' + grandTotal.toFixed(2));

        let tAdv = 0;
        $('.advance-card').each(function() {
            tAdv += parseFloat($(this).find('.advance-amount').val()) || 0;
        });
        
        let net = grandTotal - tAdv;
        
        // Late Submission Penalty Logic
        let claimMonth = $('#claimMonthInput').val();
        if(claimMonth) {
            let parts = claimMonth.split('-');
            let year = parseInt(parts[0]);
            let month = parseInt(parts[1]); // 1-12
            let deadline = new Date(year, month, 7, 23, 59, 59);
            let now = new Date();
            
            if (now > deadline && net > 0) {
                net = net * 0.90; // 10% penalty
                $('#latePenaltyWarning').show();
            } else {
                $('#latePenaltyWarning').hide();
            }
        } else {
            $('#latePenaltyWarning').hide();
        }

        $('#totalAdvances').text('₹' + tAdv.toFixed(2));
        $('#netPayableText').text('₹' + net.toFixed(2));
    }

    function addAdvanceRow() {
        const html = `
            <div class="card shadow-sm border-0 mb-2 advance-card" id="advanceRow${advanceIndex}">
                <div class="card-body p-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Date</label>
                            <input type="date" name="advances[${advanceIndex}][date]" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Mode</label>
                            <select name="advances[${advanceIndex}][mode]" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Transfer">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Reference / Cheque No.</label>
                            <input type="text" name="advances[${advanceIndex}][cheque_number]" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-danger fw-bold">Amount (₹)</label>
                            <input type="number" step="0.01" name="advances[${advanceIndex}][amount]" class="form-control advance-amount text-end fw-bold text-danger" value="0.00" oninput="calculateTotals()" required>
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="btn btn-outline-danger btn-icon rounded-circle" onclick="$('#advanceRow${advanceIndex}').remove(); calculateTotals();" title="Remove Advance"><i class="bx bx-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('#advanceRows').append(html);
        advanceIndex++;
    }

    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Setup advances if editing
        @if(isset($claim) && $claim->advances->count() > 0)
            let advances = {!! json_encode($claim->advances) !!};
            advances.forEach((adv, index) => {
                addAdvanceRow();
                let row = $('#advanceRow' + index);
                row.find('input[name="advances['+index+'][date]"]').val(adv.date.split('T')[0]);
                row.find('select[name="advances['+index+'][mode]"]').val(adv.mode);
                row.find('input[name="advances['+index+'][cheque_number]"]').val(adv.cheque_number);
                row.find('input[name="advances['+index+'][amount]"]').val(adv.amount);
            });
            calculateTotals();
        @endif

        // Trigger grid generation on load if month is already set (e.g. edit mode or validation redirect)
        if ($('#claimMonthInput').val()) {
            generateMonthGrid();
        }
        
        // Form submit intercept to disable totally blank rows so they don't bloat the POST payload
        $('form').on('submit', function() {
            $('.expense-row').each(function() {
                let row = $(this);
                let mode = row.find('.mode-select').val();
                let toLoc = row.find('input[name*="[to_location]"]').val();
                let remarks = row.find('input[name*="[remarks]"]').val();
                let food = parseFloat(row.find('.food').val()) || 0;
                let lodging = parseFloat(row.find('.lodging').val()) || 0;
                let courier = parseFloat(row.find('.courier').val()) || 0;
                let other = parseFloat(row.find('.other').val()) || 0;
                
                // If everything is completely empty or zero, disable all inputs in this row
                if (!mode && !toLoc && !remarks && food === 0 && lodging === 0 && courier === 0 && other === 0) {
                    row.find('input, select').prop('disabled', true);
                }
            });
        });
    });
</script>
@endsection
