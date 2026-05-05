@php use Illuminate\Support\Str; @endphp
@extends('layouts/layoutMaster')

@section('title', 'My Leaves')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')



<div class="container-xxl flex-grow-1 container-p-y">
    
    {{-- HERO SECTION --}}
    <div class="leaves-hero animate__animated animate__fadeIn">
        <div class="leaves-hero-text">
            <div class="greeting">Leave Management</div>
            <div class="sub-text">Plan your time off and track request statuses.</div>
        </div>
        <div>
            <button type="button" class="btn btn-hitech" data-bs-toggle="modal" data-bs-target="#hitechApplyLeaveModal">
                <i class="bx bx-plus-circle me-2"></i> Apply for Leave
            </button>
        </div>
    </div>

    {{-- STATS SECTION --}}
    @php
        // Define exclusion codes for the unified pool
        $excludedCodes = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL'];

        // Find poolable paid leave balances (summing them up)
        $poolableBalances = $leaveBalances->filter(function($b) use ($excludedCodes) {
            return $b->leaveType && $b->leaveType->is_paid && !in_array(strtoupper($b->leaveType->code), $excludedCodes);
        });
        
        $totalLeaves = $poolableBalances->sum('balance');
        $usedLeaves = $poolableBalances->sum('used');
        $remainingPL = $totalLeaves - $usedLeaves;
        
        $carryForward = $poolableBalances->sum('carry_forward_last_year');
        $accrued = $poolableBalances->sum('accrued_this_year');

        // Find short leave balance
        $shortLeaveBalance = $leaveBalances->filter(function($b) {
            return $b->leaveType && $b->leaveType->is_short_leave;
        })->first();
        $remainingShort = $shortLeaveBalance ? ($shortLeaveBalance->balance - $shortLeaveBalance->used) : 0;

        // Fallback for existing data
        if ($totalLeaves > 0 && $carryForward == 0 && $accrued == 0) {
            $accrued = $totalLeaves;
        }
    @endphp
    <div class="row g-4 mb-6">
        <div class="col-sm-6 col-lg-2 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-teal"><i class="bx bx-calendar"></i></div>
                <div class="stat-label">Pool Leave</div>
                <div class="stat-value">{{ number_format($remainingPL, 1) }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2 animate__animated animate__fadeInUp" style="animation-delay: 0.08s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-blue"><i class="bx bx-time-five"></i></div>
                <div class="stat-label">Short Leave</div>
                <div class="stat-value">{{ number_format($remainingShort, 1) }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2 animate__animated animate__fadeInUp" style="animation-delay: 0.12s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-red"><i class="bx bx-log-out-circle"></i></div>
                <div class="stat-label">Leave Taken</div>
                <div class="stat-value text-danger">{{ number_format($usedLeaves, 1) }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-secondary"><i class="bx bx-list-ul"></i></div>
                <div class="stat-label">Total Requests</div>
                <div class="stat-value">{{ $leaves->where('is_adjustment', false)->count() }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-amber"><i class="bx bx-time"></i></div>
                <div class="stat-label">Pending</div>
                <div class="stat-value">{{ $leaves->where('is_adjustment', false)->where('status', 'pending')->count() }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2 animate__animated animate__fadeInUp" style="animation-delay: 0.25s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-dark"><i class="bx bx-x-circle"></i></div>
                <div class="stat-label">Rejected</div>
                <div class="stat-value">{{ $leaves->where('is_adjustment', false)->where('status', 'rejected')->count() }}</div>
            </div>
        </div>
    </div>

    {{-- DETAILED BREAKDOWN SECTION --}}
    <div class="card mb-6 hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.22s; border: none; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
        <div class="card-body p-4">
            <div class="d-flex align-items-center mb-4">
                <div class="hitech-icon-wrap me-3" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                    <i class="bx bx-info-circle fs-4"></i>
                </div>
                <h6 class="mb-0 fw-bold" style="color: #1E293B;">Detailed Balance Breakdown (Pool Leave)</h6>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="p-3 rounded-4 border bg-white shadow-sm h-100">
                        <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Carry Forward (Last Year)</div>
                        <div class="h4 mb-0 fw-bold text-dark">{{ number_format($carryForward, 1) }}</div>
                        <div class="smallest text-muted mt-1">Brought from prev. fiscal year</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-4 border bg-white shadow-sm h-100">
                        <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Accrued (This Year)</div>
                        <div class="h4 mb-0 fw-bold text-primary">{{ number_format($accrued, 1) }}</div>
                        <div class="smallest text-muted mt-1">Accumulated in current cycle</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-4 border bg-white shadow-sm h-100">
                        <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Net Available</div>
                        <div class="h4 mb-0 fw-bold text-success">{{ number_format($remainingPL, 1) }}</div>
                        <div class="smallest text-muted mt-1">After deducting {{ number_format($usedLeaves, 1) }} used days</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TABLE SECTION --}}
    <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.25s">
        <div class="hitech-card-header">
            <h5 class="title">My Leave History</h5>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bx bx-filter-alt me-1"></i> Filter
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('user.leaves.index') }}">All Requests</a></li>
                    <li><a class="dropdown-item" href="{{ route('user.leaves.index') }}?status=approved">Approved</a></li>
                    <li><a class="dropdown-item" href="{{ route('user.leaves.index') }}?status=pending">Pending</a></li>
                </ul>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Duration (From - To)</th>
                            <th>Total Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leaves as $item)
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-label-primary rounded p-1 me-2">
                                        <i class="bx {{ $item->is_adjustment ? 'bx-plus-circle' : 'bx-calendar-event' }}"></i>
                                    </div>
                                    <span class="fw-bold text-dark">{{ $item->leave_type }}</span>
                                    @php
                                        $itemFromDate = $item->from_date ? \Carbon\Carbon::parse($item->from_date) : null;
                                        $isBackdated = !$item->is_adjustment && $itemFromDate && $itemFromDate->lt($item->created_at->startOfDay());
                                    @endphp
                                    @if($isBackdated)
                                        <span class="badge bg-label-warning ms-2" style="font-size: 0.6rem;">BACK DATED</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-semibold">
                                    @if($item->from_date)
                                        {{ \Carbon\Carbon::parse($item->from_date)->format('d M') }} - {{ \Carbon\Carbon::parse($item->to_date)->format('d M, Y') }}
                                    @else
                                        <span class="text-muted small">N/A (Adjustment)</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if(!$item->is_adjustment)
                                    @php
                                        $from = \Carbon\Carbon::parse($item->from_date);
                                        $to = \Carbon\Carbon::parse($item->to_date);
                                        $days = $from->diffInDays($to) + 1;
                                    @endphp
                                    <span class="badge bg-label-info">{{ $days }} {{ \Illuminate\Support\Str::plural('Day', $days) }}</span>
                                @else
                                    <span class="badge {{ $item->amount > 0 ? 'bg-label-success' : 'bg-label-danger' }}">
                                        {{ $item->amount > 0 ? '+' : '' }}{{ number_format($item->amount, 1) }} Days
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted" title="{{ $item->notes }}">{{ \Illuminate\Support\Str::limit($item->notes, 40) }}</span>
                            </td>
                            <td>
                                @php
                                    $statusColor = 'secondary';
                                    $statusValue = $item->status instanceof \UnitEnum ? $item->status->value : $item->status;
                                    if($statusValue == 'approved' || $statusValue == 'Processed') $statusColor = 'success';
                                    elseif($statusValue == 'rejected') $statusColor = 'danger';
                                    elseif($statusValue == 'pending') $statusColor = 'warning';
                                    elseif($statusValue == 'system') $statusColor = 'primary';
                                @endphp
                                <span class="badge badge-hitech bg-label-{{ $statusColor }}">
                                    <i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>
                                    {{ ucfirst($statusValue) }}
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">{{ $item->created_at->format('d M, H:i') }}</small>
                            </td>
                        </tr>
                        @endforeach
                        @if($leaves->isEmpty())
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bx bx-info-circle fs-2 d-block mb-2 opacity-50"></i>
                                No leave records found.
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Apply Leave Modal -->
<div class="modal fade" id="hitechApplyLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-calendar-plus fs-3"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech" id="hitechApplyLeaveModalTitle">New Leave Request</h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <form action="{{ route('user.leaves.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body modal-body-hitech">
                    <div class="alert alert-soft-info d-flex align-items-center mb-4" role="alert">
                        <i class="bx bx-info-circle me-3 fs-3"></i>
                        <div class="small">
                            Maternity/Paternity leaves appear based on your <strong>Gender</strong> and <strong>Marital Status</strong> in your profile settings.
                        </div>
                    </div>
                    <div class="row g-4">
                        <div class="col-12">
                            {{-- Default Balance Summary --}}
                            <div class="row g-3 mb-5">
                                <div class="col-6">
                                    <div class="p-3 rounded-4 border bg-white shadow-sm text-center border-success" style="background: rgba(18, 116, 100, 0.05) !important;">
                                        <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.65rem;">Pool Balance</div>
                                        <div class="h5 mb-0 fw-bold text-success">{{ number_format($remainingPL, 1) }} <span class="small fw-normal">Days</span></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 rounded-4 border bg-white shadow-sm text-center border-primary" style="background: rgba(13, 110, 253, 0.05) !important;">
                                        <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.65rem;">Short Leave</div>
                                        <div class="h5 mb-0 fw-bold text-primary">{{ number_format($remainingShort, 1) }} <span class="small fw-normal">Left</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="leave_type_id" class="form-label-hitech">
                                    Leave Type 
                                    <span id="selected_type_balance" class="badge bg-success ms-2 d-none"></span>
                                </label>
                            <select id="leave_type_id" name="leave_type_id" class="form-select form-select-hitech" required>
                                <option value="">Choose leave type...</option>
                                @foreach($leaveTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Policy Entitlement Info Card (Dynamic) -->
                        <div class="col-12 d-none" id="policyEntitlementCard">
                            <div class="p-3 rounded-4 bg-glass-teal border border-teal flex-column animate__animated animate__fadeIn">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bx bx-info-circle text-teal me-2 fs-5"></i>
                                    <span class="fw-bold text-dark small text-uppercase letter-spacing-05">Policy Entitlement</span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="bg-white bg-opacity-50 p-2 rounded-3 text-center border">
                                            <div class="small fw-semibold text-muted">WFH Pool</div>
                                            <div class="h5 mb-0 text-dark fw-bold" id="policy_wfh_days">0</div>
                                            <div class="small text-muted">Days</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-white bg-opacity-50 p-2 rounded-3 text-center border">
                                            <div class="small fw-semibold text-muted">OFF Pool</div>
                                            <div class="h5 mb-0 text-dark fw-bold" id="policy_off_days">0</div>
                                            <div class="small text-muted">Days</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2 text-center">
                                    <small class="text-muted italic">This breakdown is based on the selected policy constraints.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Backdated Warning (Dynamic) -->
                        <div class="col-12 d-none" id="backdatedWarning">
                            <div class="p-3 rounded-4 bg-label-warning border border-warning border-opacity-25 mb-4 animate__animated animate__fadeIn">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bx bx-error-circle text-warning me-2 fs-5"></i>
                                    <span class="fw-bold text-dark small text-uppercase">Back-dated Leave Notice</span>
                                </div>
                                <div class="small text-dark opacity-75">
                                    You are applying for a past date. Please ensure your reason/remarks are detailed and accurate.
                                </div>
                            </div>
                        </div>

                        <!-- Conflict and Impact Info (Dynamic) -->
                        <div class="col-12 d-none" id="leaveImpactSection">
                            <div class="p-4 rounded-4 border animate__animated animate__fadeIn" id="impactContainer" style="background: #F8FAFC;">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="bx bx-analyse text-primary me-2 fs-4"></i>
                                    <span class="fw-bold text-dark small text-uppercase">Leave Impact Analysis</span>
                                </div>

                                <!-- Conflicts -->
                                <div id="conflictAlert" class="d-none mb-4">
                                    <div class="d-flex align-items-start p-3 bg-label-danger rounded-3 border border-danger border-opacity-25">
                                        <i class="bx bx-error-circle me-3 fs-3 mt-1"></i>
                                        <div>
                                            <div class="fw-bold text-danger mb-1">Team Availability Conflict</div>
                                            <div class="small text-dark opacity-75" id="conflictText"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Impact Stats -->
                                <div class="row g-3">
                                    <div class="col-4 text-center border-end">
                                        <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Paid Utilized</div>
                                        <div class="h4 mb-0 fw-bold text-success" id="impact_paid">0</div>
                                    </div>
                                    <div class="col-4 text-center border-end">
                                        <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Unpaid (LWP)</div>
                                        <div class="h4 mb-0 fw-bold text-danger" id="impact_unpaid">0</div>
                                    </div>
                                    <div class="col-4 text-center">
                                        <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Balance After</div>
                                        <div class="h4 mb-0 fw-bold text-primary" id="impact_remaining">0</div>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-top text-center">
                                    <small class="text-muted italic">System automatically calculates paid vs unpaid based on your current PL balance.</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="from_date" class="form-label-hitech">Start Date</label>
                                    <input type="date" id="from_date" name="from_date" class="form-control form-control-hitech" required>
                                </div>
                                <div class="col-6">
                                    <label for="to_date" class="form-label-hitech">End Date</label>
                                    <input type="date" id="to_date" name="to_date" class="form-control form-control-hitech" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="user_notes" class="form-label-hitech">Reason for Leave</label>
                            <textarea id="user_notes" name="user_notes" class="form-control form-control-hitech" rows="4" placeholder="Explain your reason briefly..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label for="document" class="form-label-hitech">Attachment / Proof <span id="proof_required_star" class="text-danger d-none">*</span></label>
                            <input type="file" id="document" name="document" class="form-control form-control-hitech">
                            <small class="text-muted" id="proof_msg">Please attach proof (Medical Certificate, etc.) for Maternity/Paternity or sick leaves.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-hitech px-4">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const leaveTypes = @json($leaveTypes);
    const typeSelect = document.getElementById('leave_type_id');
    const fromDateInput = document.getElementById('from_date');
    const toDateInput = document.getElementById('to_date');
    const proofStar = document.getElementById('proof_required_star');
    const proofMsg = document.getElementById('proof_msg');
    const docInput = document.getElementById('document');
    
    // Impact Section Elements
    const impactSection = document.getElementById('leaveImpactSection');
    const conflictAlert = document.getElementById('conflictAlert');
    const conflictText = document.getElementById('conflictText');
    const impactPaid = document.getElementById('impact_paid');
    const impactUnpaid = document.getElementById('impact_unpaid');
    const impactRemaining = document.getElementById('impact_remaining');

    // Policy Elements
    const policyCard = document.getElementById('policyEntitlementCard');
    const wfhLabel = document.getElementById('policy_wfh_days');
    const offLabel = document.getElementById('policy_off_days');

    async function checkLeaveImpact() {
        const typeId = typeSelect.value;
        const fromDate = fromDateInput.value;
        const toDate = toDateInput.value;

        if (typeId && fromDate && toDate) {
            try {
                const response = await fetch('{{ route("user.leaves.check_impact") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        leave_type_id: typeId,
                        from_date: fromDate,
                        to_date: toDate
                    })
                });
                if (response.ok) {
                    const data = await response.json();
                    const leaveTypesArr = Array.isArray(leaveTypes) ? leaveTypes : Object.values(leaveTypes);
                    const selectedType = leaveTypesArr.find(t => t.id == typeId);
                    const isShortLeave = selectedType && selectedType.code === 'SHL';
                    
                    impactSection.classList.remove('d-none');
                    
                    // Update Impact Stats
                    impactPaid.innerText = data.impact.paid_utilized.toFixed(1);
                    impactUnpaid.innerText = data.impact.unpaid_utilized.toFixed(1);
                    impactRemaining.innerText = data.impact.remaining_balance.toFixed(1);

                    // Update Conflicts
                    if (data.conflicts.length > 0) {
                        conflictAlert.classList.remove('d-none');
                        const conflictList = data.conflicts.map(c => `<strong>${c.user_name}</strong> is on leave (${c.from} - ${c.to})`).join('<br>');
                        conflictText.innerHTML = `Note: The following team members are already on leave during this period:<br>${conflictList}`;
                    } else {
                        conflictAlert.classList.add('d-none');
                    }
                }
            } catch (error) {
                console.error('Impact check failed:', error);
            }
        } else {
            impactSection.classList.add('d-none');
        }
    }

    function updateLeaveDetails() {
        const typeId = typeSelect.value;
        const leaveTypesArr = Array.isArray(leaveTypes) ? leaveTypes : Object.values(leaveTypes);
        const selectedType = leaveTypesArr.find(t => t.id == typeId);
        const fromDate = fromDateInput.value;

        if (selectedType) {
            const isMAT = selectedType.code === 'MAT';
            const isPAT = selectedType.code === 'PAT';
            const isSpecial = isMAT || isPAT;

            // 1. Proof Logic
            if (selectedType.is_proof_required || isSpecial) {
                proofStar.classList.remove('d-none');
                proofMsg.innerHTML = '<strong class="text-danger">Evidence is mandatory for this leave type.</strong>';
                docInput.setAttribute('required', 'required');
            } else {
                proofStar.classList.add('d-none');
                proofMsg.innerHTML = 'Optional attachment (Medical certificate, etc.)';
                docInput.removeAttribute('required');
            }

            // 2. Policy Entitlement Display
            if (selectedType.is_split_entitlement) {
                policyCard.classList.remove('d-none');
                wfhLabel.innerText = selectedType.wfh_days_entitlement || 0;
                offLabel.innerText = selectedType.off_days_entitlement || 0;
            } else {
                policyCard.classList.add('d-none');
            }
            
            // 3. Show Balance
            const userBalances = @json($leaveBalances);
            const userBalancesArr = Array.isArray(userBalances) ? userBalances : Object.values(userBalances);
            const excludedCodes = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL'];
            const isPoolable = selectedType.is_paid && !excludedCodes.includes(selectedType.code.toUpperCase());
            const isSHL = selectedType.code.toUpperCase() === 'SHL';
            
            let availBalance = 0;
            if (isPoolable) {
                // Sum all poolable balances
                availBalance = userBalancesArr.filter(b => {
                    const bt = leaveTypesArr.find(t => t.id == b.leave_type_id);
                    return bt && bt.is_paid && !excludedCodes.includes(bt.code.toUpperCase());
                }).reduce((sum, b) => sum + (parseFloat(b.balance) - parseFloat(b.used)), 0);
            } else {
                const balanceData = userBalancesArr.find(b => b.leave_type_id == typeId);
                availBalance = balanceData ? (parseFloat(balanceData.balance) - parseFloat(balanceData.used)) : 0;
            }

            const balFormatted = availBalance.toFixed(1);
            
            const balEl = document.getElementById('selected_type_balance');
            if(balEl) {
                // Only show badge if it's NOT poolable or SHL (since those are in the top summary)
                if (!isPoolable && !isSHL) {
                    balEl.innerText = 'Category Balance: ' + balFormatted;
                    balEl.classList.remove('d-none');
                } else {
                    balEl.classList.add('d-none');
                }
            }

            // 3. Auto-Fill End Date Logic (If Start Date exists)
            if (fromDate && (isMAT || isPAT)) {
                let start = new Date(fromDate);
                const days = isMAT ? 90 : 5;
                start.setDate(start.getDate() + (days - 1));
                const yyyy = start.getFullYear();
                const mm = String(start.getMonth() + 1).padStart(2, '0');
                const dd = String(start.getDate()).padStart(2, '0');
                toDateInput.value = `${yyyy}-${mm}-${dd}`;
            }
            
            // Trigger Impact Check
            checkLeaveImpact();
        } else {
            const balEl = document.getElementById('selected_type_balance');
            if(balEl) balEl.classList.add('d-none');
            
            proofStar.classList.add('d-none');
            docInput.removeAttribute('required');
            policyCard.classList.add('d-none');
            impactSection.classList.add('d-none');
        }
    }

    // 3. Past Date Validation & Range Restriction (Allowed up to 7 days back)
    const todayDate = new Date();
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(todayDate.getDate() - 7);
    const minDate = sevenDaysAgo.toISOString().split('T')[0];
    
    fromDateInput.setAttribute('min', minDate);
    toDateInput.setAttribute('min', minDate);

    const backdatedWarning = document.getElementById('backdatedWarning');

    function checkBackdatedStatus() {
        if (fromDateInput.value) {
            const selectedDate = new Date(fromDateInput.value);
            const today = new Date();
            today.setHours(0,0,0,0);
            selectedDate.setHours(0,0,0,0);

            if (selectedDate < today) {
                backdatedWarning.classList.remove('d-none');
            } else {
                backdatedWarning.classList.add('d-none');
            }
        } else {
            backdatedWarning.classList.add('d-none');
        }
    }

    fromDateInput.addEventListener('change', function() {
        if (this.value) {
            toDateInput.setAttribute('min', this.value);
            if (toDateInput.value && toDateInput.value < this.value) {
                toDateInput.value = this.value;
            }
        }
        checkBackdatedStatus();
        updateLeaveDetails();
    });

    toDateInput.addEventListener('change', updateLeaveDetails);

    if (typeSelect) typeSelect.addEventListener('change', updateLeaveDetails);

    // Initial state
    if (typeSelect && typeSelect.value) {
        updateLeaveDetails();
    }
});
</script>
@endsection
@endsection
