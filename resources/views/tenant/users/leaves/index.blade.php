@php use Illuminate\Support\Str; @endphp
@extends('layouts/layoutMaster')

@section('title', 'My Leaves')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('page-style')
<style>
.half-day-btn { user-select:none; transition: border .18s, background .18s, box-shadow .18s; }
.half-day-btn:hover { box-shadow: 0 2px 8px rgba(99,102,241,.15); }
</style>
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
        $excludedCodes = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL'];

        $poolableBalances = $leaveBalances->filter(function($b) use ($excludedCodes) {
            return $b->leaveType && $b->leaveType->is_paid && !in_array(strtoupper($b->leaveType->code), $excludedCodes);
        });

        $totalAllocated = $poolableBalances->sum('balance') + $poolableBalances->sum('used');
        $totalUsed      = $poolableBalances->sum('used');
        $available      = $poolableBalances->sum('balance');

        // Keep these for the modal
        $remainingPL    = $available;
        $usedLeaves     = $totalUsed;

        $carryForward   = $poolableBalances->sum('carry_forward_last_year');
        $accrued        = $poolableBalances->sum('accrued_this_year');

        $shortLeaveBalance = $leaveBalances->filter(function($b) {
            return $b->leaveType && $b->leaveType->is_short_leave;
        })->first();
        $remainingShort = $shortLeaveBalance ? ($shortLeaveBalance->balance - $shortLeaveBalance->used) : 0;

        if ($totalAllocated > 0 && $carryForward == 0 && $accrued == 0) {
            $accrued = $totalAllocated;
        }
    @endphp

    <div class="row g-4 mb-6">
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-teal"><i class="bx bx-calendar-check"></i></div>
                <div class="stat-label">Total Allocated</div>
                <div class="stat-value">{{ number_format($totalAllocated, 1) }}</div>
                <div class="stat-sub text-muted small">Days this cycle</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.08s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-red"><i class="bx bx-log-out-circle"></i></div>
                <div class="stat-label">Total Used</div>
                <div class="stat-value text-danger">{{ number_format($totalUsed, 1) }}</div>
                <div class="stat-sub text-muted small">Days consumed</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.12s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-blue"><i class="bx bx-wallet"></i></div>
                <div class="stat-label">Available Balance</div>
                <div class="stat-value text-success">{{ number_format($available, 1) }}</div>
                <div class="stat-sub text-muted small">Days remaining</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-amber"><i class="bx bx-time-five"></i></div>
                <div class="stat-label">Short Leave</div>
                <div class="stat-value">{{ number_format($remainingShort, 1) }}</div>
                <div class="stat-sub text-muted small">Hours remaining</div>
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
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leaves as $item)
                        @php
                            if (!$item->is_adjustment) {
                                $typeLabel = 'Leave Deducted';
                                $typeColor = 'danger';
                                $from = \Carbon\Carbon::parse($item->from_date);
                                $to   = \Carbon\Carbon::parse($item->to_date);
                                $days = $from->diffInDays($to) + 1;
                                $amountDisplay = '-' . number_format($days, 1);
                                $amountClass   = 'text-danger';
                                $reason = 'Leave Applied (' . $from->format('d M') . ' - ' . $to->format('d M, Y') . ')';
                                if ($item->notes) $reason .= ' | ' . \Illuminate\Support\Str::limit($item->notes, 30);
                                $isBackdated = $item->from_date && \Carbon\Carbon::parse($item->from_date)->lt($item->created_at->startOfDay());
                            } else {
                                $amt    = (float)$item->amount;
                                $notes  = $item->notes ?? '';
                                $isCarryFwd = stripos($notes, 'carry forward') !== false || $item->type === 'Carry Forward';
                                if ($isCarryFwd) {
                                    $typeLabel = 'Carry Forward';
                                    $typeColor = 'primary';
                                } elseif ($amt > 0) {
                                    $typeLabel = 'Leave Credited';
                                    $typeColor = 'success';
                                } else {
                                    $typeLabel = 'Leave Deducted';
                                    $typeColor = 'danger';
                                }
                                $amountDisplay = ($amt > 0 ? '+' : '') . number_format($amt, 1);
                                $amountClass   = $amt > 0 ? 'text-success' : 'text-danger';
                                $reason = $notes ?: 'System Adjustment';
                                $isBackdated = false;
                            }
                            $statusValue = $item->status instanceof \UnitEnum ? $item->status->value : ($item->status ?? 'Processed');
                            $statusColor = match($statusValue) {
                                'approved', 'Processed', 'system' => 'success',
                                'rejected' => 'danger',
                                'pending'  => 'warning',
                                default    => 'secondary'
                            };
                        @endphp
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-label-{{ $typeColor }}">{{ $typeLabel }}</span>
                                    @if($isBackdated ?? false)
                                        <span class="badge bg-label-warning" style="font-size: 0.6rem;">BACK DATED</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="fw-bold {{ $amountClass }}">{{ $amountDisplay }}</span>
                            </td>
                            <td class="py-3">
                                <span class="text-muted small" title="{{ $reason }}">{{ \Illuminate\Support\Str::limit($reason, 50) }}</span>
                            </td>
                            <td class="py-3">
                                <span class="badge badge-hitech bg-label-{{ $statusColor }}">
                                    <i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>
                                    {{ ucfirst($statusValue) }}
                                </span>
                            </td>
                            <td class="py-3">
                                <small class="text-muted">{{ $item->created_at->format('d M, Y') }}</small>
                            </td>
                        </tr>
                        @endforeach
                        @if($leaves->isEmpty())
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
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
                        {{-- Half Day Toggle — shown ABOVE dates so users notice it first --}}
                        <div class="col-12" id="half_day_toggle_container">
                            <label class="form-label-hitech d-block mb-2">Leave Duration</label>
                            <div class="d-flex gap-2">
                                <label id="btn-full-day" class="half-day-btn active" for="duration_full_day" style="flex:1;cursor:pointer;border:2px solid #e0e0e0;border-radius:10px;padding:10px 12px;text-align:center;transition:all .2s;background:#fff;">
                                    <input type="radio" name="_duration" id="duration_full_day" value="full" class="d-none" checked>
                                    <i class="bx bx-sun d-block fs-4 mb-1" style="color:#f59e0b;"></i>
                                    <div class="fw-semibold" style="font-size:0.8rem;">Full Day</div>
                                </label>
                                <label id="btn-half-day" class="half-day-btn" for="duration_half_day" style="flex:1;cursor:pointer;border:2px solid #e0e0e0;border-radius:10px;padding:10px 12px;text-align:center;transition:all .2s;background:#fff;">
                                    <input type="radio" name="_duration" id="duration_half_day" value="half" class="d-none">
                                    <i class="bx bx-moon d-block fs-4 mb-1" style="color:#6366f1;"></i>
                                    <div class="fw-semibold" style="font-size:0.8rem;">Half Day</div>
                                </label>
                            </div>
                            {{-- Hidden real checkbox that the backend reads --}}
                            <input type="hidden" name="is_half_day" id="is_half_day" value="0">
                        </div>

                        <div class="col-12 d-none" id="half_day_session_container">
                            <label for="half_day_session" class="form-label-hitech">Which Half?</label>
                            <div class="d-flex gap-2">
                                <label id="btn-first-half" class="half-day-btn active" for="sess_first_half" style="flex:1;cursor:pointer;border:2px solid #e0e0e0;border-radius:10px;padding:10px 12px;text-align:center;transition:all .2s;background:#fff;">
                                    <input type="radio" name="half_day_session" id="sess_first_half" value="first_half" class="d-none" checked>
                                    <i class="bx bx-alarm d-block fs-4 mb-1" style="color:#10b981;"></i>
                                    <div class="fw-semibold" style="font-size:0.8rem;">First Half</div>
                                    <div class="text-muted" style="font-size:0.7rem;">Morning</div>
                                </label>
                                <label id="btn-second-half" class="half-day-btn" for="sess_second_half" style="flex:1;cursor:pointer;border:2px solid #e0e0e0;border-radius:10px;padding:10px 12px;text-align:center;transition:all .2s;background:#fff;">
                                    <input type="radio" name="half_day_session" id="sess_second_half" value="second_half" class="d-none">
                                    <i class="bx bx-moon d-block fs-4 mb-1" style="color:#6366f1;"></i>
                                    <div class="fw-semibold" style="font-size:0.8rem;">Second Half</div>
                                    <div class="text-muted" style="font-size:0.7rem;">Afternoon</div>
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="row g-3">
                                <div class="col-6" id="from_date_container">
                                    <label for="from_date" class="form-label-hitech">Start Date</label>
                                    <input type="date" id="from_date" name="from_date" class="form-control form-control-hitech" required>
                                </div>
                                <div class="col-6" id="to_date_container">
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
    
    // Half Day Elements
    const isHalfDayInput = document.getElementById('is_half_day'); // hidden input
    const halfDayToggleContainer = document.getElementById('half_day_toggle_container');
    const halfDaySessionContainer = document.getElementById('half_day_session_container');
    const fromDateContainer = document.getElementById('from_date_container');
    const toDateContainer = document.getElementById('to_date_container');

    // Duration button helpers
    const btnFullDay  = document.getElementById('btn-full-day');
    const btnHalfDay  = document.getElementById('btn-half-day');
    const rdFullDay   = document.getElementById('duration_full_day');
    const rdHalfDay   = document.getElementById('duration_half_day');
    const btnFirstHalf  = document.getElementById('btn-first-half');
    const btnSecondHalf = document.getElementById('btn-second-half');
    const rdFirstHalf   = document.getElementById('sess_first_half');
    const rdSecondHalf  = document.getElementById('sess_second_half');

    function isHalfDaySelected() { return rdHalfDay && rdHalfDay.checked; }
    function getHalfDaySession()  { return rdSecondHalf && rdSecondHalf.checked ? 'second_half' : 'first_half'; }

    function updateDurationBtnStyles() {
        const half = isHalfDaySelected();
        if (btnFullDay)  { btnFullDay.style.border  = half ? '2px solid #e0e0e0' : '2px solid #6366f1'; btnFullDay.style.background  = half ? '#fff' : '#f5f3ff'; }
        if (btnHalfDay)  { btnHalfDay.style.border  = half ? '2px solid #6366f1' : '2px solid #e0e0e0'; btnHalfDay.style.background  = half ? '#f5f3ff' : '#fff'; }
    }

    function updateSessionBtnStyles() {
        const second = rdSecondHalf && rdSecondHalf.checked;
        if (btnFirstHalf)  { btnFirstHalf.style.border  = second ? '2px solid #e0e0e0' : '2px solid #10b981'; btnFirstHalf.style.background  = second ? '#fff' : '#f0fdf4'; }
        if (btnSecondHalf) { btnSecondHalf.style.border = second ? '2px solid #6366f1' : '2px solid #e0e0e0'; btnSecondHalf.style.background = second ? '#f5f3ff' : '#fff'; }
    }

    // Wire duration buttons
    [btnFullDay, btnHalfDay].forEach(btn => {
        if (btn) btn.addEventListener('click', function() {
            const isHalf = this === btnHalfDay;
            rdFullDay.checked = !isHalf;
            rdHalfDay.checked = isHalf;
            isHalfDayInput.value = isHalf ? '1' : '0';
            updateDurationBtnStyles();
            handleHalfDayToggle();
        });
    });

    // Wire session buttons
    [btnFirstHalf, btnSecondHalf].forEach(btn => {
        if (btn) btn.addEventListener('click', function() {
            const isSecond = this === btnSecondHalf;
            rdFirstHalf.checked  = !isSecond;
            rdSecondHalf.checked = isSecond;
            updateSessionBtnStyles();
            checkLeaveImpact();
        });
    });

    updateDurationBtnStyles();
    updateSessionBtnStyles();
    
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
                        to_date: toDate,
                        is_half_day: isHalfDaySelected() ? 1 : 0,
                        half_day_session: getHalfDaySession()
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

    function handleHalfDayToggle() {
        if (isHalfDaySelected()) {
            halfDaySessionContainer.classList.remove('d-none');
            toDateContainer.classList.add('d-none');
            fromDateContainer.className = 'col-12';
            toDateInput.value = fromDateInput.value;
        } else {
            halfDaySessionContainer.classList.add('d-none');
            toDateContainer.classList.remove('d-none');
            fromDateContainer.className = 'col-6';
        }
        checkLeaveImpact();
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
            const isSHL = selectedType.code.toUpperCase() === 'SHL';

            // Half day configuration
            if (isSHL) {
                halfDayToggleContainer.classList.add('d-none');
                // Reset to full day silently
                if (rdFullDay) { rdFullDay.checked = true; rdHalfDay.checked = false; }
                if (isHalfDayInput) isHalfDayInput.value = '0';
                updateDurationBtnStyles();
                handleHalfDayToggle();
            } else {
                halfDayToggleContainer.classList.remove('d-none');
            }

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
            if (isHalfDayCheckbox.checked) {
                toDateInput.value = this.value;
            } else {
                toDateInput.setAttribute('min', this.value);
                if (toDateInput.value && toDateInput.value < this.value) {
                    toDateInput.value = this.value;
                }
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
