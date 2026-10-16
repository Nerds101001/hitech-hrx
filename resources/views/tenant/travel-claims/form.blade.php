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

    @if(isset($claim) && $claim->status === 'objection')
        @php $flaggedItems = $claim->items->where('is_flagged', true); @endphp
        <div class="alert alert-danger d-flex align-items-start" style="border-left:4px solid #dc3545;">
            <i class="bx bx-error-circle fs-4 me-2"></i>
            <div>
                <div class="fw-bold mb-1">Objection raised — please correct and resubmit</div>
                <div>{{ $claim->objection_notes }}</div>
                @if($flaggedItems->count())
                    <div class="mt-2 small">
                        <strong>{{ $flaggedItems->count() }} row(s) flagged</strong> and highlighted in red below:
                        {{ $flaggedItems->map(fn($i) => $i->date->format('d M'))->implode(', ') }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <style>
        tr.flagged-row > td { background-color: #fdecea !important; }
        tr.flagged-row > td:first-child { box-shadow: inset 3px 0 0 0 #dc3545; }
        tr.flagged-row input.form-control, tr.flagged-row select.form-select { border-color: #f1a9a0; }

        /* The grid is packed with number boxes right next to each other, and
           the browser's built-in up/down spinner sits exactly where a cursor
           rests while typing the next row — one stray click or a wheel-scroll
           over the field bumps the amount instead of just moving the page.
           Hidden here rather than globally, so number inputs elsewhere in the
           app keep their spinner. */
        #claimTableScroll input[type="number"]::-webkit-outer-spin-button,
        #claimTableScroll input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #claimTableScroll input[type="number"] {
            -moz-appearance: textfield;
        }

        /* The horizontal scrollbar sits above the grid and stays put while the
           page moves, so you never have to travel to the bottom of a 31-row
           table to scroll sideways. */
        #claimTopScroll {
            position: sticky;
            top: 0;
            z-index: 7;
            background: #fff;
        }

        /* The grid scrolls inside its own box rather than with the page. This
           is what lets the header freeze: `overflow-x` on the wrapper makes it
           a scroll container on BOTH axes, so a sticky thead pins to the
           wrapper's top edge — which only stays on screen if the wrapper has a
           bounded height of its own. */
        #claimTableScroll {
            max-height: calc(100vh - 260px);
            min-height: 320px;
            overflow: auto;
        }

        #claimTableScroll thead th {
            position: sticky;
            top: 0;
            z-index: 4;
            /* Opaque: the rows scroll underneath it. */
            background-color: #f2f3f4;
            box-shadow: inset 0 -1px 0 #d9dee3;
        }
    </style>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Submission & attachment guidelines --}}
    <div class="alert border-0 shadow-sm mb-4" style="background:#f0f9ff; border-left:4px solid #0ea5e9 !important;">
        <h6 class="fw-bold mb-2" style="color:#075985;"><i class="bx bx-info-circle me-1"></i> Before you submit — please read</h6>
        <ul class="mb-0 small" style="color:#0c4a6e; line-height:1.7;">
            <li><strong>Last date of submission:</strong> the <strong>7th of the following month</strong>. Claims submitted after the 7th incur a <strong>10% late-submission penalty</strong> on the net payable amount.</li>
            <li><strong>Bill attachments are now uploaded once for the whole month</strong> at the bottom of this form — <em>not</em> per day. Combine all of a category's bills into a single file (PDF or image).</li>
            <li>For every category where you have claimed an amount, the matching consolidated bill file is <strong>mandatory</strong> — e.g. if your Food total is above ₹0 you must attach the combined Food bills. <strong>The form will not submit</strong> until each claimed category has its bill attached.</li>
            <li><strong>An odometer photo is only needed if you travelled by bike or car</strong> (personal or official). <strong>One photo is enough</strong> — if a single image shows both the month start and month end readings, upload it in either box. If your month is only food, courier, cab, bus, train or flight, leave them blank and you can still submit. On bike/car days, fill the <strong>start and end meter</strong> readings in the grid; no photo is asked for per day.</li>
        </ul>
    </div>

    {{-- Drafts already in progress. Shown on a NEW claim only: without it a
         second visit quietly started another draft for the same month. --}}
    @if(!isset($claim) && ($openDrafts ?? collect())->count())
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center mb-2">
                <i class="bx bx-time-five fs-4 me-2"></i>
                <strong>You already have {{ $openDrafts->count() }} saved draft{{ $openDrafts->count() > 1 ? 's' : '' }}.</strong>
            </div>
            <p class="small mb-2">Continue one of these instead of starting again — filling the same month twice creates a duplicate claim.</p>
            <div class="list-group">
                @foreach($openDrafts as $d)
                    <a href="{{ route('travel-claims.edit', $d->id) }}"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>
                            <strong>{{ $d->claim_month }}</strong>
                            <span class="text-muted">— {{ $d->company }}</span>
                            <span class="badge bg-label-secondary ms-2">{{ $d->items_count }} row(s)</span>
                        </span>
                        <span class="small text-muted">
                            last saved {{ $d->updated_at->diffForHumans() }}
                            <i class="bx bx-chevron-right"></i>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <form id="claimForm" action="{{ isset($claim) ? route('travel-claims.update', $claim->id) : route('travel-claims.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        {{-- Ties files uploaded before this claim exists to the claim once it is
             saved. Kept across a failed submit so nothing has to be re-picked. --}}
        <input type="hidden" name="upload_token" id="uploadToken" value="{{ old('upload_token', \Illuminate\Support\Str::uuid()) }}">
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
            
                <div class="row g-3 mt-1" id="odometerProofRow">
                    <div class="col-12">
                        <div class="small mb-0" id="odometerRequirementNote"></div>
                    </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {{-- No asterisk on either box: one photo covering both
                             readings satisfies the rule, so marking each field
                             individually required would be wrong. --}}
                        <label class="form-label fw-semibold text-secondary small mb-1">Month Start Odometer Proof <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" title="Photo of the odometer at the start of the month. If one image shows both the start and end readings, upload it in either box."></i></label>
                        {{-- data-has-file tells the warning below that a proof is
                             already stored, so an edited claim does not keep
                             demanding a photo it already has. --}}
                        <input type="file" name="month_start_odometer_proof" class="form-control odo-proof"
                               data-has-file="{{ (isset($claim) && $claim->month_start_odometer_proof) ? '1' : '0' }}"
                               accept="image/jpeg,image/png,image/jpg,application/pdf">
                        @if(isset($claim) && $claim->month_start_odometer_proof)
                            <div class="mt-2 text-primary small">
                                <i class="bi bi-check-circle-fill"></i> Uploaded: <a href="{{ asset('storage/' . $claim->month_start_odometer_proof) }}" target="_blank">View File</a>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label fw-semibold text-secondary small mb-1">Month End Odometer Proof <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" title="Photo of the odometer at the end of the month. Optional if a single image already shows both readings."></i></label>
                        <input type="file" name="month_end_odometer_proof" class="form-control odo-proof"
                               data-has-file="{{ (isset($claim) && $claim->month_end_odometer_proof) ? '1' : '0' }}"
                               accept="image/jpeg,image/png,image/jpg,application/pdf">
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
            <table class="table table-bordered table-hover table-sm mb-0 align-middle text-nowrap" style="min-width: 2400px; font-size: 0.85rem;">
                <thead class="bg-light">
                    <tr class="text-center align-middle" style="line-height: 1.2;">
                        <th style="min-width: 75px;">Date</th>
                        <th style="min-width: 110px;">Mode</th>
                        <th style="min-width: 80px;">Currency</th>
                        <th style="min-width: 140px;">Customer Name</th>
                        <th style="min-width: 140px;">From Address<br><small class="fw-normal text-muted">(Source)</small></th>
                        <th style="min-width: 140px;">To Address<br><small class="fw-normal text-muted">(Destination)</small></th>
                        <th style="min-width: 80px;">Meter<br>Start</th>
                        <th style="min-width: 80px;">Meter<br>End</th>
                        <th style="min-width: 60px;">Total<br>KM</th>
                        <th style="min-width: 110px;">Conveyance/<br>Amount (₹)</th>
                        <th style="min-width: 95px;">Outstation<br>>5hrs</th>
                        <th style="min-width: 100px;">Food<br>(₹)</th>
                        <th style="min-width: 100px;">Toll/Parking<br>(₹)</th>
                        <th style="min-width: 110px;">Customer<br>Expense (₹)</th>
                        <th style="min-width: 120px;">Courier (Transport/<br>Freight) etc (₹)</th>
                        <th style="min-width: 90px;">Lodging<br>(₹)</th>
                        <th style="min-width: 120px;">Other (Photocopy/<br>Misc) etc (₹)</th>
                        <th style="min-width: 100px;">Day Total<br>(₹)</th>
                        <th style="min-width: 120px;">Remarks</th>
                    </tr>
                </thead>
                <tbody id="expenseRowsGrid">
                    <!-- Rows inserted by JS -->
                </tbody>
                <tfoot>
                    <tr class="text-center align-middle fw-bold" style="background:#f1f3f5; font-size: 0.8rem;">
                        <td colspan="8" class="text-end pe-2">Column Totals</td>
                        <td id="footDistance" class="text-end p-1">0.00</td>
                        <td id="footConveyance" class="text-end p-1">0.00</td>
                        <td></td>
                        <td id="footFood" class="text-end p-1">0.00</td>
                        <td id="footToll" class="text-end p-1">0.00</td>
                        <td id="footSpecialAppr" class="text-end p-1">0.00</td>
                        <td id="footCourier" class="text-end p-1">0.00</td>
                        <td id="footLodging" class="text-end p-1">0.00</td>
                        <td id="footOther" class="text-end p-1">0.00</td>
                        <td id="footDayTotal" class="text-end p-1">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Consolidated monthly bill uploads (one file each, replacing per-day proofs) --}}
        <div class="card shadow-sm border-0 mb-4 mt-3">
            <div class="card-header bg-white border-bottom pt-4 pb-3">
                <h5 class="card-title mb-1">Monthly Bill Attachments</h5>
                <p class="text-muted small mb-0">Attach the bills for each category — <strong>up to {{ \App\Models\TravelClaimAttachment::MAX_PER_CATEGORY }} files per box</strong>, no need to merge them into one. Files upload as soon as you pick them. Any category you have claimed an amount for needs at least one bill, or the claim cannot be submitted.</p>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @php
                        $consolidated = [
                            ['name' => 'food_bill_proof',         'label' => 'Food Bills',              'hint' => 'All food/meal bills for the month'],
                            ['name' => 'conveyance_petrol_proof', 'label' => 'Conveyance / Petrol',     'hint' => 'All petrol slips & conveyance bills'],
                            ['name' => 'toll_parking_proof',      'label' => 'Toll / Parking',          'hint' => 'All toll & parking receipts'],
                            ['name' => 'customer_courier_proof',  'label' => 'Customer Expense / Courier', 'hint' => 'Customer expense & courier/transport bills'],
                            ['name' => 'hotel_bill_proof',        'label' => 'Hotel Bills',             'hint' => 'All hotel/accommodation bills for the month'],
                        ];
                    @endphp
                    @foreach($consolidated as $c)
                        @php $catFiles = ($attachmentsByCategory ?? collect())->get($c['name'], collect()); @endphp
                        <div class="col-md-6">
                            <div class="form-group attachment-box" data-category="{{ $c['name'] }}">
                                <label class="form-label fw-semibold text-secondary small mb-1">{{ $c['label'] }} Bill
                                    <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" title="{{ $c['hint'] }}"></i>
                                    <span class="badge bg-label-secondary ms-1 attach-count">{{ $catFiles->count() }}/{{ \App\Models\TravelClaimAttachment::MAX_PER_CATEGORY }}</span>
                                </label>
                                <input type="file" class="form-control attach-input" multiple
                                       accept="image/jpeg,image/png,image/jpg,application/pdf">
                                <div class="form-text small">Pick several at once — each uploads immediately.</div>

                                <div class="attach-progress progress mt-2" style="height:4px; display:none;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
                                </div>
                                <div class="attach-error text-danger small mt-1" style="display:none;"></div>

                                <ul class="list-group list-group-flush mt-2 attach-list">
                                    @foreach($catFiles as $f)
                                        <li class="list-group-item d-flex align-items-center justify-content-between px-0 py-1" data-id="{{ $f->id }}">
                                            <a href="{{ $f->url }}" target="_blank" class="text-truncate small me-2" style="max-width: 75%;">
                                                <i class="bi {{ $f->is_image ? 'bi-image' : 'bi-file-earmark-pdf' }}"></i> {{ $f->original_name }}
                                            </a>
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 attach-remove" title="Remove">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>

                                @if(isset($claim) && $claim->{$c['name']})
                                    <div class="mt-2 text-primary small">
                                        <i class="bi bi-check-circle-fill"></i> Previously uploaded:
                                        <a href="{{ asset('storage/' . $claim->{$c['name']}) }}" target="_blank">View File</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
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
                <div class="d-flex justify-content-end align-items-center gap-2">
                    {{-- Autosave status. Stays quiet until something is actually
                         saved, so it never nags on an untouched form. --}}
                    <span id="autosaveStatus" class="small text-muted me-auto"></span>
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
    
    function escAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                        .replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // In edit mode, store existing items so we can prefill the grid
    let existingItemsData = {};
    @if(isset($claim) && $claim->items->count() > 0)
        {{-- SECURITY (H-2/S8): @json applies JSON_HEX_* so a claim-item string
             containing </script> or a backtick cannot break out of this block. --}}
        let itemsArr = @json($claim->items);
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
            <tr class="expense-row${ex.is_flagged ? ' flagged-row' : ''}" id="row_${d}">
                <td class="align-middle ${ex.is_flagged ? 'bg-transparent' : 'bg-light'} fw-bold text-center" style="font-size: 0.75rem;">
                    ${displayDate}
                    ${ex.is_flagged ? `<div class="text-danger fw-bold" style="font-size:.65rem;" title="${escAttr(ex.flag_reason || 'Flagged by verifier')}"><i class="bx bx-flag"></i> FLAGGED</div>` : ''}
                    <input type="hidden" name="items[${d}][date]" value="${dateString}">
                </td>
                <td class="p-1">
                    <select name="items[${d}][mode_of_travel]" class="form-select form-select-sm mode-select p-1" style="font-size: 0.8rem;" onchange="calculateRow(${d})">
                        <option value="">--</option>
                        <option value="Personal Bike" ${(ex.mode_of_travel == 'Personal Bike' || ex.mode_of_travel == 'Bike') ? 'selected' : ''}>Personal Bike</option>
                        <option value="Personal Car" ${(ex.mode_of_travel == 'Personal Car' || ex.mode_of_travel == 'Car') ? 'selected' : ''}>Personal Car</option>
                        <option value="Electric Car" ${ex.mode_of_travel == 'Electric Car' ? 'selected' : ''}>Electric Car</option>
                        <option value="Official Bike" ${ex.mode_of_travel == 'Official Bike' ? 'selected' : ''}>Official Bike</option>
                        <option value="Official Car" ${ex.mode_of_travel == 'Official Car' ? 'selected' : ''}>Official Car</option>
                        <option value="Cab / Taxi" ${ex.mode_of_travel == 'Cab / Taxi' ? 'selected' : ''}>Cab / Taxi</option>
                        <option value="Bus" ${ex.mode_of_travel == 'Bus' ? 'selected' : ''}>Bus</option>
                        <option value="Train" ${ex.mode_of_travel == 'Train' ? 'selected' : ''}>Train</option>
                        <option value="Flight" ${ex.mode_of_travel == 'Flight' ? 'selected' : ''}>Flight</option>
                    </select>
                </td>
                <td class="p-1">
                    <select name="items[${d}][currency]" class="form-select form-select-sm p-1" style="font-size: 0.8rem;">
                        <option value="INR" ${(!ex.currency || ex.currency == 'INR') ? 'selected' : ''}>INR</option>
                        <option value="USD" ${ex.currency == 'USD' ? 'selected' : ''}>USD</option>
                        <option value="EUR" ${ex.currency == 'EUR' ? 'selected' : ''}>EUR</option>
                        <option value="AED" ${ex.currency == 'AED' ? 'selected' : ''}>AED</option>
                    </select>
                </td>
                <td class="p-1"><input type="text" name="items[${d}][party_visited]" class="form-control form-control-sm p-1 customer-name" style="font-size: 0.8rem;" placeholder="Customer..." value="${ex.party_visited || ''}"></td>
                <td class="p-1"><input type="text" name="items[${d}][from_location]" class="form-control form-control-sm p-1 from-location" style="font-size: 0.8rem;" placeholder="From..." value="${ex.from_location || ''}"></td>
                <td class="p-1"><input type="text" name="items[${d}][to_location]" class="form-control form-control-sm p-1 to-location" style="font-size: 0.8rem;" placeholder="To..." value="${ex.to_location || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][start_meter]" class="form-control form-control-sm start-meter p-1 text-center" style="font-size: 0.8rem;" oninput="calculateRow(${d})" value="${ex.start_meter || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][end_meter]" class="form-control form-control-sm end-meter p-1 text-center" style="font-size: 0.8rem;" oninput="calculateRow(${d})" value="${ex.end_meter || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][distance_km]" class="form-control form-control-sm distance bg-light p-1 text-center" style="font-size: 0.8rem;" readonly value="${ex.distance_km || ''}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][conveyance_amount]" class="form-control form-control-sm conveyance text-primary fw-bold text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.conveyance_amount || 0).toFixed(2)}"></td>
                <td class="text-center align-middle p-1">
                    <input class="form-check-input" type="checkbox" name="items[${d}][is_outstation]" value="1" onchange="toggleOutstation(${d}, this)" ${ex.is_outstation ? 'checked' : ''}>
                </td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][food_allowance]" class="form-control form-control-sm food text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.food_allowance || 0).toFixed(2)}" ${ex.is_outstation ? 'readonly' : ''}></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][toll_amount]" class="form-control form-control-sm toll text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.toll_amount || 0).toFixed(2)}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][special_approval_amount]" class="form-control form-control-sm special-appr text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.special_approval_amount || 0).toFixed(2)}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][courier_amount]" class="form-control form-control-sm courier text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.courier_amount || 0).toFixed(2)}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][lodging_amount]" class="form-control form-control-sm lodging text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.lodging_amount || 0).toFixed(2)}"></td>
                <td class="p-1"><input type="number" step="0.01" name="items[${d}][other_amount]" class="form-control form-control-sm other text-end p-1" style="font-size: 0.8rem;" oninput="calculateTotals()" value="${parseFloat(ex.other_amount || 0).toFixed(2)}"></td>
                <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm day-total bg-light text-end fw-bold p-1" style="font-size: 0.8rem;" readonly tabindex="-1" value="0.00"></td>
                <td class="p-1"><input type="text" name="items[${d}][remarks]" class="form-control form-control-sm p-1" style="font-size: 0.8rem;" placeholder="..." value="${ex.remarks || ''}"></td>
            </tr>`;
            grid.append(html);
            
            // Ensure logic runs initially if there is existing data
            if (Object.keys(ex).length > 0) {
                calculateRow(d);
            }
        }
        updateOdometerRequirement();
        calculateTotals();
    }

    // Must match TravelClaimController::PER_KM_RATES and ::METERED_MODES —
    // the server recalculates on save and the two have to agree.
    const PER_KM_RATES = {
        'Personal Bike': 4.00, 'Bike': 4.00,
        'Personal Car':  9.50, 'Car':  9.50,
        'Electric Car':  3.00
    };
    const METERED_MODES = [
        'Personal Bike', 'Bike', 'Personal Car', 'Car', 'Official Bike', 'Official Car', 'Electric Car'
    ];
    function isMeteredMode(mode) { return METERED_MODES.includes(mode); }

    // True once any row is on a bike or car — the month's odometer photos are
    // only required in that case.
    function monthHasVehicleRow() {
        let found = false;
        $('.expense-row').each(function() {
            if (isMeteredMode($(this).find('.mode-select').val())) { found = true; return false; }
        });
        return found;
    }

    // Reflects the server rule on screen so nobody hunts for a photo they do
    // not owe, and nobody is surprised at submit time.
    // A proof counts as present when it is already stored on the claim OR the
    // user has just picked a file. Without the stored case, reopening a claim
    // that already has both photos kept showing the red "required" warning.
    function odoProofPresent(name) {
        const el = document.querySelector('input[name="' + name + '"]');
        if (!el) return false;
        return (el.files && el.files.length > 0) || el.dataset.hasFile === '1';
    }

    function updateOdometerRequirement() {
        let needed = monthHasVehicleRow();

        if (!needed) {
            $('#odometerRequirementNote').html(
                '<span class="text-muted">No bike or car travel entered — odometer photos are optional for this claim.</span>');
            return;
        }

        // One photo is enough: many people capture the opening and closing
        // readings in a single image, so we ask for proof, not for two files.
        const haveAny = odoProofPresent('month_start_odometer_proof')
                     || odoProofPresent('month_end_odometer_proof');

        $('#odometerRequirementNote').html(haveAny
            ? '<span class="text-success fw-semibold"><i class="bx bx-check-circle"></i> Odometer photo attached — this claim can be submitted.</span>'
            : '<span class="text-danger fw-semibold"><i class="bx bx-error-circle"></i> This claim includes bike/car travel, so an odometer photo is required. '
              + 'One image showing both the month start and month end readings is fine.</span>');
    }

    // NOTE: the binding for this lives inside DOMContentLoaded below. It must
    // not run at top level — jQuery is not loaded yet at that point, and a
    // "$ is not defined" here kills the whole script, so the day grid never
    // renders and a saved draft looks empty.

    function calculateRow(idx) {
        let row = $('#row_' + idx);
        let mode = row.find('.mode-select').val();
        let start = parseFloat(row.find('.start-meter').val()) || 0;
        let end = parseFloat(row.find('.end-meter').val()) || 0;
        let dist = 0;

        let rate = PER_KM_RATES[mode];      // undefined => paid at actual fare
        let isPerKm = rate !== undefined;

        if (isMeteredMode(mode)) {
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
            // Cab, bus, train and flight have no odometer — the user types the
            // fare they paid straight into the conveyance column.
            row.find('.distance').val('');
            row.find('.start-meter, .end-meter').val('').prop('readonly', true).addClass('bg-light');
        }

        if (isPerKm) {
            row.find('.conveyance').val((dist * rate).toFixed(2)).prop('readonly', true).addClass('bg-light');
        } else {
            row.find('.conveyance').prop('readonly', false).removeClass('bg-light');
        }
        updateOdometerRequirement();
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
        let tConv = 0, tFood = 0, tLodg = 0, tCour = 0, tOth = 0, tToll = 0, tSpecialAppr = 0, tDist = 0, tDayTotal = 0;

        $('.expense-row').each(function() {
            let row = $(this);
            let conv = parseFloat(row.find('.conveyance').val()) || 0;
            let food = parseFloat(row.find('.food').val()) || 0;
            let lodg = parseFloat(row.find('.lodging').val()) || 0;
            let cour = parseFloat(row.find('.courier').val()) || 0;
            let oth  = parseFloat(row.find('.other').val()) || 0;
            let toll = parseFloat(row.find('.toll').val()) || 0;
            let special = parseFloat(row.find('.special-appr').val()) || 0;

            tConv += conv;
            tFood += food;
            tLodg += lodg;
            tCour += cour;
            tOth  += oth;
            tToll += toll;
            tSpecialAppr += special;
            tDist += parseFloat(row.find('.distance').val()) || 0;

            // Everything that day, added up in its own row so a wrong entry
            // shows up against the day it belongs to rather than only in the
            // month-end grand total.
            let dayTotal = conv + food + lodg + cour + oth + toll + special;
            row.find('.day-total').val(dayTotal.toFixed(2));
            tDayTotal += dayTotal;
        });

        // Column totals footer, right under the grid, so each amount column
        // (and the km driven) can be checked at a glance instead of adding
        // 30-odd rows by eye.
        $('#footDistance').text(tDist.toFixed(2));
        $('#footConveyance').text(tConv.toFixed(2));
        $('#footFood').text(tFood.toFixed(2));
        $('#footToll').text(tToll.toFixed(2));
        $('#footSpecialAppr').text(tSpecialAppr.toFixed(2));
        $('#footCourier').text(tCour.toFixed(2));
        $('#footLodging').text(tLodg.toFixed(2));
        $('#footOther').text(tOth.toFixed(2));
        $('#footDayTotal').text(tDayTotal.toFixed(2));

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
            let effectiveSubmitTime = FIRST_SUBMITTED_AT ? new Date(FIRST_SUBMITTED_AT) : new Date();

            if (effectiveSubmitTime > deadline && net > 0) {
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

    // ── Monthly bill attachments ──────────────────────────────────────────
    // Files upload the moment they are picked, so a slow connection is paid
    // for once while the user carries on filling the grid, and an abandoned
    // form does not lose them. Nothing here is posted with the form; the
    // hidden upload token is what links these files to the saved claim.
    const ATTACH_MAX        = {{ \App\Models\TravelClaimAttachment::MAX_PER_CATEGORY }};
    const ATTACH_UPLOAD_URL = "{{ route('travel-claims.attachments.store') }}";
    const ATTACH_DELETE_URL = "{{ route('travel-claims.attachments.destroy', ['id' => '__ID__']) }}";
    const ATTACH_CLAIM_ID   = @json($claim->id ?? null);

    // Mirrors TravelClaimController::store()'s own rule: the 10% is fixed by
    // the FIRST time this claim was actually submitted, never by now(). This
    // page used to preview against now() unconditionally, so reopening an
    // on-time claim to fix an objection after the 7th showed the penalty
    // warning and a discounted Net Payable on screen for a save that would
    // not actually be penalised - null here means "not submitted yet",
    // which previews against today exactly like a brand-new claim should.
    const FIRST_SUBMITTED_AT = @json(isset($claim) && $claim->first_submitted_at ? $claim->first_submitted_at->toIso8601String() : null);

    function attachCsrf() {
        return $('input[name="_token"]').first().val() || '';
    }

    function attachSetError(box, msg) {
        const el = box.find('.attach-error');
        if (!msg) { el.hide().text(''); return; }
        el.text(msg).show();
    }

    function attachRefreshCount(box) {
        const n = box.find('.attach-list li').length;
        box.find('.attach-count').text(n + '/' + ATTACH_MAX);
        return n;
    }

    function attachRenderRow(box, file) {
        const icon = file.is_image ? 'bi-image' : 'bi-file-earmark-pdf';
        const li = $('<li>')
            .addClass('list-group-item d-flex align-items-center justify-content-between px-0 py-1')
            .attr('data-id', file.id);
        $('<a>').attr({ href: file.url, target: '_blank' })
            .addClass('text-truncate small me-2').css('max-width', '75%')
            .html('<i class="bi ' + icon + '"></i> ')
            .append(document.createTextNode(file.name))
            .appendTo(li);
        $('<button type="button" class="btn btn-sm btn-link text-danger p-0 attach-remove" title="Remove"><i class="bx bx-trash"></i></button>')
            .appendTo(li);
        box.find('.attach-list').append(li);
        attachRefreshCount(box);
    }

    // The host's firewall rejects a multipart filename containing an apostrophe
    // with a bare 403 — before Laravel runs, so no error of ours is ever shown.
    // "Bill-July'26.pdf" is blocked; the same file as "Bill-July_26.pdf" is not.
    // Upload under a sanitised name and carry the real one alongside, so what
    // the user sees and downloads is unchanged.
    function attachSafeName(name) {
        const dot  = name.lastIndexOf('.');
        const ext  = dot > 0 ? name.slice(dot).replace(/[^A-Za-z0-9.]/g, '') : '';
        const base = (dot > 0 ? name.slice(0, dot) : name).replace(/[^A-Za-z0-9 _-]/g, '_');
        return (base.trim() || 'file') + ext;
    }

    async function attachUploadOne(box, file) {
        const data = new FormData();
        data.append('file', file, attachSafeName(file.name));
        data.append('original_name', file.name);
        data.append('category', box.data('category'));
        data.append('upload_token', $('#uploadToken').val());
        if (ATTACH_CLAIM_ID) data.append('claim_id', ATTACH_CLAIM_ID);

        const res = await fetch(ATTACH_UPLOAD_URL, {
            method: 'POST',
            body: data,
            headers: { 'X-CSRF-TOKEN': attachCsrf(), 'Accept': 'application/json' },
            credentials: 'same-origin'
        });

        if (!res.ok) {
            let msg = 'Upload failed (' + res.status + ').';
            try {
                const err = await res.json();
                msg = err.message || (err.errors && Object.values(err.errors)[0][0]) || msg;
            } catch (e) { /* non-JSON error page — keep the status message */ }
            throw new Error(file.name + ': ' + msg);
        }
        return await res.json();
    }

    // Uploaded one at a time on purpose: 30 parallel POSTs of 10 MB each is a
    // good way to have the server drop half of them.
    async function attachUploadQueue(box, files) {
        attachSetError(box, '');
        const room = ATTACH_MAX - attachRefreshCount(box);
        if (room <= 0) {
            attachSetError(box, 'This box already holds the maximum of ' + ATTACH_MAX + ' files.');
            return;
        }
        if (files.length > room) {
            attachSetError(box, 'Only ' + room + ' more file(s) fit here — the rest were not uploaded.');
            files = files.slice(0, room);
        }

        box.find('.attach-progress').show();
        try {
            for (const file of files) {
                try {
                    attachRenderRow(box, await attachUploadOne(box, file));
                } catch (e) {
                    attachSetError(box, e.message);
                }
            }
        } finally {
            box.find('.attach-progress').hide();
        }
    }

    async function attachRemove(box, li) {
        const id = li.attr('data-id');
        try {
            const res = await fetch(ATTACH_DELETE_URL.replace('__ID__', id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': attachCsrf(), 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            if (!res.ok) throw new Error('Could not remove that file.');
            li.remove();
            attachRefreshCount(box);
            attachSetError(box, '');
        } catch (e) {
            attachSetError(box, e.message);
        }
    }

    function initAttachmentBoxes() {
        $('.attachment-box').each(function() {
            const box = $(this);
            box.find('.attach-input').on('change', function() {
                const files = Array.from(this.files || []);
                this.value = '';   // so re-picking the same file fires change again
                if (files.length) attachUploadQueue(box, files);
            });
            box.on('click', '.attach-remove', function() {
                attachRemove(box, $(this).closest('li'));
            });
            attachRefreshCount(box);
        });
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
        // Picking a photo clears the odometer warning straight away.
        $(document).on('change', '.odo-proof', updateOdometerRequirement);

        // Hiding the spin buttons (CSS above) stops a stray click from
        // bumping the value, but a focused number field still eats the
        // mouse wheel and changes its own value instead of scrolling the
        // page — exactly what happens when you scroll down past a field you
        // just finished typing into. Blurring on wheel hands the scroll back
        // to the page without touching what was typed.
        $(document).on('wheel', '#claimTableScroll input[type="number"]', function() {
            $(this).blur();
        });

        updateOdometerRequirement();
        initAttachmentBoxes();

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
                // A row carrying only a customer name or only meter readings is
                // still a row the user filled in — it used to be disabled here
                // and thrown away without a word.
                let party = row.find('input[name*="[party_visited]"]').val();
                let startMeter = row.find('.start-meter').val();
                let endMeter = row.find('.end-meter').val();
                let food = parseFloat(row.find('.food').val()) || 0;
                let lodging = parseFloat(row.find('.lodging').val()) || 0;
                let courier = parseFloat(row.find('.courier').val()) || 0;
                let other = parseFloat(row.find('.other').val()) || 0;
                let toll = parseFloat(row.find('.toll').val()) || 0;
                let specialAppr = parseFloat(row.find('.special-appr').val()) || 0;
                let conveyanceAmount = parseFloat(row.find('.conveyance').val()) || 0;

                // Bills are now uploaded once at the bottom (not per day), so a row
                // counts as empty purely on its own text/amount fields.
                // If everything is completely empty or zero, disable all inputs in this row
                if (!mode && !fromLoc && !toLoc && !remarks && !party && !startMeter && !endMeter && food === 0 && lodging === 0 && courier === 0 && other === 0 && toll === 0 && specialAppr === 0 && conveyanceAmount === 0) {
                    row.find('input, select').prop('disabled', true);
                }
            });
        });
    });
</script>

<script>
/* ── Autosave ──────────────────────────────────────────────────────────────
   Saves the form as a DRAFT in the background so a half-filled claim is never
   lost to a timeout, a stray refresh or a closed tab. "Save Draft" and "Submit
   Claim" keep working exactly as before — this only removes the need to
   remember to press Draft.

   Deliberately does NOT send file inputs: bills already upload the moment they
   are picked (travel-claims.attachments), and the server falls back to the
   stored path / the existing_* hidden fields whenever a file is absent, so
   omitting them re-posts nothing and loses nothing. */
(function () {
    const form   = document.getElementById('claimForm');
    const status = document.getElementById('autosaveStatus');
    if (!form || !status) return;

    const STORE_URL  = @json(route('travel-claims.store'));
    const UPDATE_TPL = @json(route('travel-claims.update', ['id' => '__ID__']));

    let claimId   = @json(isset($claim) ? $claim->id : null);
    let dirty     = false;
    let inFlight  = false;
    let timer     = null;

    const IDLE_MS  = 2500;      // save this long after typing stops
    const SWEEP_MS = 60000;     // and at least once a minute while dirty

    function show(html, cls) {
        status.className = 'small me-auto ' + (cls || 'text-muted');
        status.innerHTML = html;
    }

    /* claim_month and company are required even for a draft, so there is
       nothing the server can store until they are set. Saving before that
       would just 422 in a loop. */
    function ready() {
        const m = form.querySelector('[name="claim_month"]');
        const c = form.querySelector('[name="company"]');
        return m && c && m.value && c.value;
    }

    function save() {
        if (inFlight || !dirty || !ready()) return;
        inFlight = true;
        show('<i class="bx bx-loader-alt bx-spin"></i> Saving…');

        const fd = new FormData();
        for (const el of form.elements) {
            if (!el.name || el.disabled) continue;
            if (el.type === 'file') continue;                       // see note above
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
            if (el.name === 'action') continue;                     // never "submit"
            fd.append(el.name, el.value);
        }
        fd.append('action', 'draft');
        fd.append('autosave', '1');

        fetch(claimId ? UPDATE_TPL.replace('__ID__', claimId) : STORE_URL, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => Promise.reject(j)))
        .then(j => {
            if (!j.ok) return Promise.reject(j);
            dirty = false;
            /* First save created the claim. Point later saves at it and fix the
               URL, so a refresh reopens the draft instead of a blank form. */
            if (!claimId && j.id) {
                claimId = j.id;
                history.replaceState(null, '', UPDATE_TPL.replace('__ID__', claimId).replace('/update', '/edit'));
            }
            let msg = '<i class="bx bx-check-circle text-success"></i> Draft saved ' + j.saved_at;
            if (j.skipped) msg += ' — ' + j.skipped + ' row(s) without a date were not saved';
            show(msg, 'text-muted');
        })
        .catch(err => {
            // A validation complaint is normal mid-typing; say so without alarm.
            const first = err && err.errors ? Object.values(err.errors)[0][0] : null;
            show('<i class="bx bx-error-circle text-warning"></i> Not saved yet'
                 + (first ? ' — ' + first : '') + '. Your typing is safe; it will retry.',
                 'text-muted');
        })
        .finally(() => { inFlight = false; });
    }

    function touched() {
        dirty = true;
        clearTimeout(timer);
        timer = setTimeout(save, IDLE_MS);
    }

    form.addEventListener('input',  touched);
    form.addEventListener('change', touched);
    setInterval(save, SWEEP_MS);

    // A real Draft/Submit click must not race the background save.
    form.addEventListener('submit', () => { clearTimeout(timer); dirty = false; });

    // Last chance if they close the tab mid-edit.
    window.addEventListener('beforeunload', function (e) {
        if (dirty && ready()) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>

@endsection
