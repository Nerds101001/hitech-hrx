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

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

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
            
                <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label fw-semibold text-secondary small mb-1">Month Start Odometer Proof <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" title="Upload a photo of your vehicle's odometer at the start of the month"></i></label>
                        <input type="file" name="month_start_odometer_proof" class="form-control" accept="image/jpeg,image/png,image/jpg,application/pdf">
                        @if(isset($claim) && $claim->month_start_odometer_proof)
                            <div class="mt-2 text-primary small">
                                <i class="bi bi-check-circle-fill"></i> Uploaded: <a href="{{ asset('storage/' . $claim->month_start_odometer_proof) }}" target="_blank">View File</a>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label fw-semibold text-secondary small mb-1">Month End Odometer Proof <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" title="Upload a photo of your vehicle's odometer at the end of the month"></i></label>
                        <input type="file" name="month_end_odometer_proof" class="form-control" accept="image/jpeg,image/png,image/jpg,application/pdf">
                        @if(isset($claim) && $claim->month_end_odometer_proof)
                            <div class="mt-2 text-primary small">
                                <i class="bi bi-check-circle-fill"></i> Uploaded: <a href="{{ asset('storage/' . $claim->month_end_odometer_proof) }}" target="_blank">View File</a>
                            </div>
                        @endif
                    </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <h5 class="mb-0 fw-bold">Daily Travel Expenses</h5>
            <span class="text-muted small">Fill out your expenses for the month. Blank rows will be ignored automatically.</span>
        </div>

        {{-- Top horizontal scrollbar, kept in sync with the table below --}}
        <div id="claimTopScroll" style="overflow-x: auto; overflow-y: hidden; height: 16px;" class="mb-1">
            <div id="claimTopScrollInner" style="height: 1px;"></div>
        </div>
        <div class="table-responsive bg-white rounded shadow-sm border mb-3" id="claimTableScroll">
            <table class="table table-bordered table-hover table-sm mb-0 align-middle text-nowrap" style="min-width: 3600px; font-size: 0.85rem;">
                <thead class="bg-light">
                    <tr class="text-center align-middle" style="line-height: 1.2;">
                        <th style="min-width: 75px;">Date</th>
                        <th style="min-width: 110px;">Mode</th>
                        <th style="min-width: 140px;">Customer Name</th>
                        <th style="min-width: 140px;">From Address<br><small class="fw-normal text-muted">(Source)</small></th>
                        <th style="min-width: 140px;">To Address<br><small class="fw-normal text-muted">(Destination)</small></th>
                        <th style="min-width: 80px;">Meter<br>Start</th>
                        <th style="min-width: 80px;">Meter<br>End</th>
                        <th style="min-width: 60px;">Total<br>KM</th>
                        <th style="min-width: 110px;">Conveyance/<br>Amount (₹)</th>
                        <th style="min-width: 160px;">Conveyance/<br>Bill Proof</th>
                        <th style="min-width: 95px;">Outstation<br>>5hrs</th>
                        <th style="min-width: 100px;">Food<br>(₹)</th>
                        <th style="min-width: 160px;">Food Bill<br>Proof</th>
                        <th style="min-width: 100px;">Toll/Parking<br>(₹)</th>
                        <th style="min-width: 160px;">Toll/Parking<br>Proof</th>
                        <th style="min-width: 110px;">Customer<br>Expense (₹)</th>
                        <th style="min-width: 160px;">Customer Expense Proof</th>
                        <th style="min-width: 120px;">Courier (Transport/<br>Freight) etc (₹)</th>
                        <th style="min-width: 160px;">Courier / Transport<br>Proof</th>
                        <th style="min-width: 90px;">Lodging<br>(₹)</th>
                        <th style="min-width: 120px;">Other (Photocopy/<br>Misc) etc (₹)</th>
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

        <div class="card shadow-sm border mb-4 mt-4" style="background: transparent;">
            <div class="card-body text-end p-4">
                <p id="latePenaltyWarning" class="text-danger small mb-1 fw-bold" style="display:none;"><i class="bx bx-error-circle"></i> 10% Late Submission Penalty Applied to Net Payable!</p>
                <h3 class="mb-3">Net Payable: <span id="netPayableText" class="text-success fw-bold">₹0.00</span></h3>
                <p class="text-muted small mb-4">By submitting, you certify these expenses were incurred for official business in compliance with the company Travel Policy.</p>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" name="action" value="draft" formnovalidate class="btn btn-outline-secondary btn-lg px-4 shadow-sm"><i class="bx bx-save me-1"></i> Save Draft</button>
                    <button type="submit" name="action" value="submit" class="btn btn-success btn-lg px-5 shadow"><i class="bx bx-check-circle me-1"></i> Submit Claim</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('page-script')
<script>
    // Global Error Logger for UI Troubleshooting
    window.addEventListener('error', function(e) {
        let errorDiv = document.createElement('div');
        errorDiv.style.position = 'fixed';
        errorDiv.style.top = '0';
        errorDiv.style.left = '0';
        errorDiv.style.width = '100%';
        errorDiv.style.backgroundColor = '#ea5455';
        errorDiv.style.color = 'white';
        errorDiv.style.padding = '15px';
        errorDiv.style.zIndex = '99999';
        errorDiv.style.fontFamily = 'monospace';
        errorDiv.style.fontSize = '13px';
        errorDiv.style.lineHeight = '1.4';
        errorDiv.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        errorDiv.innerHTML = '<h5 style="color: white; margin-bottom: 5px;">⚠️ JavaScript Error Detected:</h5>' +
            '<div><strong>Message:</strong> ' + e.message + '</div>' +
            '<div><strong>File:</strong> ' + e.filename + ' (Line: ' + e.lineno + ', Col: ' + e.colno + ')</div>' +
            '<pre style="margin-top: 10px; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 4px; color: #ffcccc; overflow-x: auto; max-height: 200px;">' + (e.error ? e.error.stack : '') + '</pre>';
        document.body.appendChild(errorDiv);
    });

    let advanceIndex = 0;
    
    // In edit mode, store existing items so we can prefill the grid
    let existingItemsData = {};
    @if(isset($claim) && $claim->items->count() > 0)
        let itemsArr = {!! json_encode($claim->items) !!};
        itemsArr.forEach(item => {
            if (!item || !item.date) return;
            let dateStr = item.date;
            let dateString;
            if (typeof dateStr === 'string' && dateStr.includes('T') && dateStr.endsWith('Z')) {
                // Handle ISO format by adjusting the UTC timestamp to Indian Standard Time (+5:30)
                let dateObj = new Date(dateStr);
                let localTime = dateObj.getTime() + (5.5 * 60 * 60 * 1000);
                let localDate = new Date(localTime);
                let y = localDate.getUTCFullYear();
                let m = String(localDate.getUTCMonth() + 1).padStart(2, '0');
                let d = String(localDate.getUTCDate()).padStart(2, '0');
                dateString = `${y}-${m}-${d}`;
            } else if (typeof dateStr === 'string') {
                // If it is already in Y-m-d format (e.g. 2026-06-01), extract directly
                dateString = dateStr.substring(0, 10);
            } else {
                return;
            }
            existingItemsData[dateString] = item;
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
                        <option value="Personal Bike" ${(ex.mode_of_travel == 'Personal Bike' || ex.mode_of_travel == 'Bike') ? 'selected' : ''}>Personal Bike</option>
                        <option value="Personal Car" ${(ex.mode_of_travel == 'Personal Car' || ex.mode_of_travel == 'Car') ? 'selected' : ''}>Personal Car</option>
                        <option value="Official Bike" ${ex.mode_of_travel == 'Official Bike' ? 'selected' : ''}>Official Bike</option>
                        <option value="Official Car" ${ex.mode_of_travel == 'Official Car' ? 'selected' : ''}>Official Car</option>
                        <option value="Train" ${ex.mode_of_travel == 'Train' ? 'selected' : ''}>Train</option>
                        <option value="Bus" ${ex.mode_of_travel == 'Bus' ? 'selected' : ''}>Bus</option>
                        <option value="Flight" ${ex.mode_of_travel == 'Flight' ? 'selected' : ''}>Flight</option>
                    </select>
                </td>
                <td class="p-1"><input type="text" name="items[${d}][party_visited]" class="form-control form-control-sm p-1 customer-name" style="font-size: 0.8rem;" placeholder="Customer..." value="${ex.party_visited || ''}"></td>
                <td class="p-1"><input type="text" name="items[${d}][from_location]" class="form-control form-control-sm p-1 from-location" style="font-size: 0.8rem;" placeholder="From..." value="${ex.from_location || ''}"></td>
                <td class="p-1"><input type="text" name="items[${d}][to_location]" class="form-control form-control-sm p-1 to-location" style="font-size: 0.8rem;" placeholder="To..." value="${ex.to_location || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][start_meter]" class="form-control form-control-sm start-meter p-1 text-center" style="font-size: 0.8rem;" oninput="calculateRow(${d})" value="${ex.start_meter || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][end_meter]" class="form-control form-control-sm end-meter p-1 text-center" style="font-size: 0.8rem;" oninput="calculateRow(${d})" value="${ex.end_meter || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][distance_km]" class="form-control form-control-sm distance bg-light p-1 text-center" style="font-size: 0.8rem;" readonly value="${ex.distance_km || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][conveyance_amount]" class="form-control form-control-sm conveyance text-primary fw-bold text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.conveyance_amount || 0).toFixed(2)}"></td>
                <td class="p-1">
                    <input type="file" name="items[${d}][petrol_slip]" class="form-control form-control-sm petrol-slip p-1" style="font-size: 0.75rem;" onchange="calculateRow(${d})" accept="image/jpeg,image/png,image/jpg,application/pdf">
                    ${ex.petrol_slip_proof ? `<input type="hidden" name="items[${d}][existing_petrol_slip]" value="${ex.petrol_slip_proof}"><a href="/storage/${ex.petrol_slip_proof}" target="_blank" class="small d-block mt-1">View File</a>` : ''}
                </td>
                <td class="text-center align-middle p-1">
                    <input class="form-check-input" type="checkbox" name="items[${d}][is_outstation]" value="1" onchange="toggleOutstation(${d}, this)" ${ex.is_outstation ? 'checked' : ''}>
                </td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][food_allowance]" class="form-control form-control-sm food text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.food_allowance || 0).toFixed(2)}" ${ex.is_outstation ? 'readonly' : ''}></td>
                <td class="p-1">
                    <input type="file" name="items[${d}][additional_food_proof]" class="form-control form-control-sm p-1" style="font-size: 0.75rem;" accept="image/jpeg,image/png,image/jpg,application/pdf">
                    ${ex.additional_food_proof ? `<input type="hidden" name="items[${d}][existing_additional_food_proof]" value="${ex.additional_food_proof}"><a href="/storage/${ex.additional_food_proof}" target="_blank" class="small d-block mt-1">View File</a>` : ''}
                </td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][toll_amount]" class="form-control form-control-sm toll text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.toll_amount || 0).toFixed(2)}"></td>
                <td class="p-1">
                    <input type="file" name="items[${d}][toll_proof]" class="form-control form-control-sm p-1" style="font-size: 0.75rem;" accept="image/jpeg,image/png,image/jpg,application/pdf">
                    ${ex.toll_proof ? `<input type="hidden" name="items[${d}][existing_toll_proof]" value="${ex.toll_proof}"><a href="/storage/${ex.toll_proof}" target="_blank" class="small d-block mt-1">View File</a>` : ''}
                </td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][special_approval_amount]" class="form-control form-control-sm special-appr text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.special_approval_amount || 0).toFixed(2)}"></td>
                <td class="p-1">
                    <input type="file" name="items[${d}][special_approval_proof]" class="form-control form-control-sm p-1" style="font-size: 0.75rem;" accept="image/jpeg,image/png,image/jpg,application/pdf">
                    ${ex.special_approval_proof ? `<input type="hidden" name="items[${d}][existing_special_approval_proof]" value="${ex.special_approval_proof}"><a href="/storage/${ex.special_approval_proof}" target="_blank" class="small d-block mt-1">View File</a>` : ''}
                </td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][courier_amount]" class="form-control form-control-sm courier text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.courier_amount || 0).toFixed(2)}"></td>
                <td class="p-1">
                    <input type="file" name="items[${d}][courier_proof]" class="form-control form-control-sm p-1" style="font-size: 0.75rem;" accept="image/jpeg,image/png,image/jpg,application/pdf">
                    ${ex.courier_proof ? `<input type="hidden" name="items[${d}][existing_courier_proof]" value="${ex.courier_proof}"><a href="/storage/${ex.courier_proof}" target="_blank" class="small d-block mt-1">View File</a>` : ''}
                </td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][lodging_amount]" class="form-control form-control-sm lodging text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.lodging_amount || 0).toFixed(2)}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][other_amount]" class="form-control form-control-sm other text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.other_amount || 0).toFixed(2)}"></td>
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
        let dist = 0;
        
        let isPersonal = ['Personal Bike', 'Personal Car', 'Bike', 'Car'].includes(mode);
        let isOfficial = ['Official Bike', 'Official Car'].includes(mode);

        if (isPersonal || isOfficial) {
            // Meter readings are recorded for both personal and office vehicles;
            // only the conveyance amount differs (auto rate vs actual expense)
            if (end > start && end !== 0) {
                dist = end - start;
                row.find('.distance').val(dist.toFixed(2));
            } else {
                dist = parseFloat(row.find('.distance').val()) || 0;
            }
            row.find('.start-meter, .end-meter').prop('readonly', false).removeClass('bg-light');
        } else {
            row.find('.distance').val('');
            row.find('.start-meter, .end-meter').val('').prop('readonly', true).addClass('bg-light');
        }

        let rate = 0;
        if(mode === 'Personal Bike' || mode === 'Bike') rate = 4.00;
        if(mode === 'Personal Car' || mode === 'Car') rate = 9.50;

        let conveyance = 0;
        if (isPersonal) {
            conveyance = dist * rate;
            row.find('.conveyance').val(conveyance.toFixed(2)).prop('readonly', true).addClass('bg-light');
        } else {
            row.find('.conveyance').prop('readonly', false).removeClass('bg-light');
        }
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
        let tConv = 0, tFood = 0, tLodg = 0, tCour = 0, tOth = 0, tToll = 0, tSpecialAppr = 0;
        
        $('.expense-row').each(function() {
            tConv += parseFloat($(this).find('.conveyance').val()) || 0;
            tFood += parseFloat($(this).find('.food').val()) || 0;
            tLodg += parseFloat($(this).find('.lodging').val()) || 0;
            tCour += parseFloat($(this).find('.courier').val()) || 0;
            tOth  += parseFloat($(this).find('.other').val()) || 0;
            tToll += parseFloat($(this).find('.toll').val()) || 0;
            tSpecialAppr += parseFloat($(this).find('.special-appr').val()) || 0;
        });

        let grandTotal = tConv + tFood + tLodg + tCour + tOth + tToll + tSpecialAppr;
        $('#grandTotal').text('₹' + grandTotal.toFixed(2));
        
        let net = grandTotal;
        
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

        $('#netPayableText').text('₹' + net.toFixed(2));
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips (safeguarded against missing bootstrap globals)
        try {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
              if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                  return new bootstrap.Tooltip(tooltipTriggerEl);
              }
            });
        } catch (e) {
            console.warn('Tooltip initialization failed:', e);
        }

        // Trigger grid generation on load if month is already set (e.g. edit mode or validation redirect)
        if ($('#claimMonthInput').val()) {
            generateMonthGrid();
        }

        // Keep the top scrollbar in sync with the wide expense table so users
        // can scroll horizontally without going to the bottom of the page
        var topScroll = document.getElementById('claimTopScroll');
        var tableScroll = document.getElementById('claimTableScroll');
        if (topScroll && tableScroll) {
            var syncTopScrollWidth = function() {
                document.getElementById('claimTopScrollInner').style.width = tableScroll.scrollWidth + 'px';
            };
            syncTopScrollWidth();
            window.addEventListener('resize', syncTopScrollWidth);
            setInterval(syncTopScrollWidth, 1500); // grid rows are injected dynamically

            var syncing = false;
            topScroll.addEventListener('scroll', function() {
                if (syncing) { syncing = false; return; }
                syncing = true;
                tableScroll.scrollLeft = topScroll.scrollLeft;
            });
            tableScroll.addEventListener('scroll', function() {
                if (syncing) { syncing = false; return; }
                syncing = true;
                topScroll.scrollLeft = tableScroll.scrollLeft;
            });
        }
        
        // Form submit intercept to disable totally blank rows so they don't bloat the POST payload
        $('form').on('submit', function() {
            $('.expense-row').each(function() {
                let row = $(this);
                let mode = row.find('.mode-select').val();
                let fromLoc = row.find('input[name*="[from_location]"]').val();
                let toLoc = row.find('input[name*="[to_location]"]').val();
                let remarks = row.find('input[name*="[remarks]"]').val();
                let food = parseFloat(row.find('.food').val()) || 0;
                let lodging = parseFloat(row.find('.lodging').val()) || 0;
                let courier = parseFloat(row.find('.courier').val()) || 0;
                let other = parseFloat(row.find('.other').val()) || 0;
                let toll = parseFloat(row.find('.toll').val()) || 0;
                let specialAppr = parseFloat(row.find('.special-appr').val()) || 0;
                let conveyanceAmount = parseFloat(row.find('.conveyance').val()) || 0;
                
                let hasPetrolSlip = row.find('.petrol-slip').val() || row.find('input[name*="[existing_petrol_slip]"]').length > 0;
                let hasFoodProof = row.find('input[name*="[additional_food_proof]"]').val() || row.find('input[name*="[existing_additional_food_proof]"]').length > 0;
                let hasTollProof = row.find('input[name*="[toll_proof]"]').val() || row.find('input[name*="[existing_toll_proof]"]').length > 0;
                let hasSpecialApprProof = row.find('input[name*="[special_approval_proof]"]').val() || row.find('input[name*="[existing_special_approval_proof]"]').length > 0;
                let hasCourierProof = row.find('input[name*="[courier_proof]"]').val() || row.find('input[name*="[existing_courier_proof]"]').length > 0;
                
                // If everything is completely empty or zero, disable all inputs in this row
                if (!mode && !fromLoc && !toLoc && !remarks && food === 0 && lodging === 0 && courier === 0 && other === 0 && toll === 0 && specialAppr === 0 && conveyanceAmount === 0 && !hasPetrolSlip && !hasTollProof && !hasSpecialApprProof && !hasCourierProof && !hasFoodProof) {
                    row.find('input, select').prop('disabled', true);
                }
            });
        });
    });
</script>
@endsection
