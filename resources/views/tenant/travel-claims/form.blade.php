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
                <a href="{{ route('travel-claims.index') }}" class="btn btn-outline-light rounded-pill px-4">Back to Claims</a>
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
                        <input type="month" name="claim_month" class="form-control" value="{{ isset($claim) ? $claim->claim_month : '' }}" onchange="calculateTotals()" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Company</label>
                        <select name="company" class="form-select" required>
                            <option value="Hi Tech International" {{ (isset($claim) && $claim->company == 'Hi Tech International') ? 'selected' : '' }}>Hi Tech International</option>
                            <option value="KEEP IT FRESH LLP" {{ (isset($claim) && $claim->company == 'KEEP IT FRESH LLP') ? 'selected' : '' }}>KEEP IT FRESH LLP</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sales/Collection Target Achieved (₹)</label>
                        <input type="number" step="0.01" name="sales_collection" class="form-control" value="{{ isset($claim) ? $claim->sales_collection_target : '' }}" placeholder="Optional">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <h5 class="mb-0 fw-bold">Daily Travel Expenses</h5>
            <button type="button" class="btn btn-hitech btn-sm shadow-sm text-white" style="background-color: #0d9488;" onclick="addExpenseRow()"><i class="bx bx-plus me-1"></i> Add Expense</button>
        </div>

        <div id="expenseRows">
            <!-- Rows inserted by JS -->
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
    let expenseIndex = 0;
    let advanceIndex = 0;

    function addExpenseRow() {
        const html = `
            <div class="card shadow-sm border-0 mb-3 expense-card" id="expenseRow${expenseIndex}">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2">
                    <h6 class="mb-0 fw-bold text-dark">Expense Entry</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-icon rounded-circle" onclick="$('#expenseRow${expenseIndex}').remove(); calculateTotals();" title="Delete Entry"><i class="bx bx-trash"></i></button>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Date</label>
                            <input type="date" name="items[${expenseIndex}][date]" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Travel Mode</label>
                            <select name="items[${expenseIndex}][mode_of_travel]" class="form-select mode-select" onchange="calculateRow(${expenseIndex})" required>
                                <option value="">Select Mode</option>
                                <option value="Bike">Bike (₹4/km)</option>
                                <option value="Car">Car (₹9.5/km)</option>
                                <option value="Train">Train</option>
                                <option value="Bus">Bus</option>
                                <option value="Flight">Flight</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">From Location</label>
                            <input type="text" name="items[${expenseIndex}][from_location]" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">To Location</label>
                            <input type="text" name="items[${expenseIndex}][to_location]" class="form-control">
                        </div>

                        <!-- Odometer inputs only relevant for bike/car -->
                        <div class="col-md-2 meter-group">
                            <label class="form-label small text-muted">Start Mtr</label>
                            <input type="number" step="0.01" name="items[${expenseIndex}][start_meter]" class="form-control start-meter" oninput="calculateRow(${expenseIndex})">
                        </div>
                        <div class="col-md-2 meter-group">
                            <label class="form-label small text-muted">End Mtr</label>
                            <input type="number" step="0.01" name="items[${expenseIndex}][end_meter]" class="form-control end-meter" oninput="calculateRow(${expenseIndex})">
                        </div>
                        <div class="col-md-2 meter-group">
                            <label class="form-label small text-muted">Total KM</label>
                            <input type="number" step="0.01" name="items[${expenseIndex}][distance_km]" class="form-control distance" oninput="calculateRow(${expenseIndex})" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">
                                Odometer Proof 
                                <i class="bx bx-info-circle text-danger ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Issues/Rules: Max 1MB (JPG/PNG/PDF). If missing for Bike/Car, 30% conveyance penalty is applied."></i>
                            </label>
                            <input type="file" name="items[${expenseIndex}][photo]" class="form-control photo" onchange="calculateRow(${expenseIndex})" accept="image/jpeg,image/png,image/jpg,application/pdf">
                        </div>
                        <div class="col-md-2 text-end">
                            <label class="form-label small text-primary fw-bold">Conveyance (₹)</label>
                            <input type="text" class="form-control conveyance text-end bg-light fw-bold text-primary" readonly value="0.00">
                        </div>

                        <!-- Other Allowances -->
                        <div class="col-md-12 mt-3 mb-1"><hr class="my-0"></div>
                        
                        <div class="col-md-2 d-flex align-items-end mb-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="items[${expenseIndex}][is_outstation]" value="1" id="outstation${expenseIndex}" onchange="toggleOutstation(${expenseIndex}, this)">
                                <label class="form-check-label small" for="outstation${expenseIndex}">Outstation > 5 hrs</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Food (₹)</label>
                            <input type="number" step="0.01" name="items[${expenseIndex}][food_allowance]" class="form-control food text-end" value="0.00" oninput="calculateTotals()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Lodging (₹)</label>
                            <input type="number" step="0.01" name="items[${expenseIndex}][lodging_amount]" class="form-control lodging text-end" value="0.00" oninput="calculateTotals()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Courier (₹)</label>
                            <input type="number" step="0.01" name="items[${expenseIndex}][courier_amount]" class="form-control courier text-end" value="0.00" oninput="calculateTotals()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Other (₹)</label>
                            <input type="number" step="0.01" name="items[${expenseIndex}][other_amount]" class="form-control other text-end" value="0.00" oninput="calculateTotals()">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label small text-muted">Remarks / Party Visited</label>
                            <input type="text" name="items[${expenseIndex}][remarks]" class="form-control" placeholder="Optional notes">
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('#expenseRows').append(html);
        expenseIndex++;
        if(typeof initTooltips === 'function') initTooltips();
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

    function calculateRow(idx) {
        let row = $('#expenseRow' + idx);
        let mode = row.find('.mode-select').val();
        let start = parseFloat(row.find('.start-meter').val()) || 0;
        let end = parseFloat(row.find('.end-meter').val()) || 0;
        let dist = 0;
        
        if (['Bike', 'Car'].includes(mode)) {
            if(end > start) {
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
        
        if(['Bike', 'Car'].includes(mode) && !photoVal && !hasExistingPhoto && conveyance > 0) {
            conveyance = conveyance * 0.70; // 30% penalty
        }

        row.find('.conveyance').val(conveyance.toFixed(2));
        calculateTotals();
    }

    function toggleOutstation(idx, checkbox) {
        let row = $('#expenseRow' + idx);
        if(checkbox.checked) {
            row.find('.food').val('300.00').prop('readonly', true);
        } else {
            row.find('.food').val('0.00').prop('readonly', false);
        }
        calculateTotals();
    }

    function calculateTotals() {
        let tConv = 0, tFood = 0, tLodg = 0, tCour = 0, tOth = 0;
        
        $('.expense-card').each(function() {
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
        let claimMonth = $('input[name="claim_month"]').val();
        if(claimMonth) {
            let parts = claimMonth.split('-');
            let year = parseInt(parts[0]);
            let month = parseInt(parts[1]); // 1-12
            // Deadline is 7th of the NEXT month. E.g. for May (month=5), deadline is June 7 (month=6). Date object is 0-indexed month.
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

    // Initialization
    $(document).ready(function() {
        // Initialize Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize tooltips for dynamically added elements later
        window.initTooltips = function() {
            var newTooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]:not([data-bs-original-title])'));
            newTooltipTriggerList.map(function (tooltipTriggerEl) {
              return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        };

        @if(isset($claim) && $claim->items->count() > 0)
            let items = {!! json_encode($claim->items) !!};
            items.forEach((item, index) => {
                addExpenseRow();
                let row = $('#expenseRow' + index);
                row.find('input[name="items['+index+'][date]"]').val(item.date.split('T')[0]);
                row.find('select[name="items['+index+'][mode_of_travel]"]').val(item.mode_of_travel);
                row.find('input[name="items['+index+'][from_location]"]').val(item.from_location);
                row.find('input[name="items['+index+'][to_location]"]').val(item.to_location);
                row.find('input[name="items['+index+'][start_meter]"]').val(item.start_meter);
                row.find('input[name="items['+index+'][end_meter]"]').val(item.end_meter);
                row.find('input[name="items['+index+'][distance_km]"]').val(item.distance_km);
                
                if (item.is_outstation) {
                    row.find('input[name="items['+index+'][is_outstation]"]').prop('checked', true);
                    row.find('.food').val('300.00').prop('readonly', true);
                } else {
                    row.find('input[name="items['+index+'][food_allowance]"]').val(item.food_allowance);
                }
                
                row.find('input[name="items['+index+'][lodging_amount]"]').val(item.lodging_amount);
                row.find('input[name="items['+index+'][courier_amount]"]').val(item.courier_amount);
                row.find('input[name="items['+index+'][other_amount]"]').val(item.other_amount);
                row.find('input[name="items['+index+'][remarks]"]').val(item.remarks);
                
                if (item.photo_path) {
                    // Hidden input to preserve old photo
                    row.find('.card-body').append('<input type="hidden" name="items['+index+'][existing_photo]" value="'+item.photo_path+'">');
                    row.find('.photo').after('<div class="mt-1"><a href="/storage/'+item.photo_path+'" target="_blank" class="small text-primary">View Current Photo</a></div>');
                }
                
                calculateRow(index);
            });
        @else
            addExpenseRow();
        @endif

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
    });
</script>
@endsection
