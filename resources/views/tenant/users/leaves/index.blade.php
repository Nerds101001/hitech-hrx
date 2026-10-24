@php use Illuminate\Support\Str; @endphp
@extends('layouts/layoutMaster')

@section('title', 'My Leaves')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('page-style')
<style>
.hdpick-opt {
    flex: 1;
    cursor: pointer;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 10px 12px;
    text-align: center;
    transition: border .18s, background .18s, box-shadow .18s;
    background: #fff;
    user-select: none;
}
.hdpick-opt:hover { box-shadow: 0 2px 8px rgba(99,102,241,.15); }
.hdpick-active {
    border-color: #6366f1 !important;
    background: #f5f3ff !important;
}
</style>
@endsection

@section('content')



<div class="container-xxl flex-grow-1 container-p-y">
    
    {{-- PAGE HEADER --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h5 class="mb-0 fw-bold">Leave Management</h5>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#hitechApplyLeaveModal">
                <i class="bx bx-plus-circle me-1"></i> Apply for Leave
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#odApplyModal">
                <i class="bx bx-map-pin me-1"></i> Apply for Outdoor Duty
            </button>
        </div>
    </div>

    {{-- STATS SECTION --}}
    @php
        // WFH belongs in this list. It is paid, but never out of the ALP/PL
        // pool these three cards total - approval pays it directly and never
        // deducts a balance. Leaving it out counted any WFH balance into
        // Total Allocated and Available, so the cards disagreed with both the
        // deduction logic and the JS copy of this same list further down,
        // which already had it right.
        $excludedCodes = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL', 'WFH'];

        $poolableBalances = $leaveBalances->filter(function($b) use ($excludedCodes) {
            return $b->leaveType && $b->leaveType->is_paid && !in_array(strtoupper($b->leaveType->code), $excludedCodes);
        });

        // balance is a running total of everything ever credited; used is a
        // running total of everything ever consumed - a deduction only ever
        // increments used, it never decrements balance (see LeaveRequest's
        // approval hook: $balance->used += $decrement, balance itself is
        // untouched). So Allocated is balance alone, and Available is
        // balance minus used - the same shape the Short Leave card below
        // already gets right. This used to read Allocated as balance+used
        // (double-counting whatever had been used) and Available as balance
        // alone (ignoring used entirely) - invisible for as long as used
        // stayed at 0, which is exactly what the half-day-never-deducts bug
        // (fixed separately) made true for far more employees than it
        // should have been.
        $totalAllocated = $poolableBalances->sum('balance');
        $totalUsed      = $poolableBalances->sum('used');
        $available      = $totalAllocated - $totalUsed;

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
                            $req = $item->raw_request ?? null;
                            if (!$item->is_adjustment) {
                                $typeLabel = 'Leave Deducted';
                                $typeColor = 'danger';
                                $from = \Carbon\Carbon::parse($item->from_date);
                                $to   = \Carbon\Carbon::parse($item->to_date);
                                
                                // Short leave is detected from the request flag OR the leave type,
                                // so older requests saved before the flag existed still show correctly
                                $isShortReq = $req && ($req->is_short_leave || ($req->leaveType->is_short_leave ?? false));
                                // WFH is functionally Half Day Leave (same is_half_day flag), so it
                                // must be checked before the generic half-day branch below, or every
                                // WFH request would display as "Half Day" instead of "WFH".
                                $isWfhReq = $req && strtoupper($req->leaveType->code ?? '') === 'WFH';

                                $leaveTypeDetail = 'Full Day';
                                if ($req) {
                                    if ($isShortReq) {
                                        $leaveTypeDetail = 'Short Leave (' . ($req->duration_hours ?? 0) . ' hrs' . ($req->start_time ? ': ' . substr($req->start_time, 0, 5) . '-' . substr($req->end_time, 0, 5) : '') . ')';
                                    } elseif ($isWfhReq) {
                                        $sessionName = $req->half_day_session === 'second_half' ? '2nd Half' : '1st Half';
                                        $leaveTypeDetail = 'WFH (' . $sessionName . ')';
                                    } elseif ($req->is_half_day) {
                                        $sessionName = $req->half_day_session === 'second_half' ? '2nd Half' : '1st Half';
                                        $leaveTypeDetail = 'Half Day (' . $sessionName . ')';
                                    }
                                }

                                if ($isShortReq) {
                                    $amountDisplay = '-1.0 count';
                                } else {
                                    // Show what actually left the balance (paid_days,
                                    // already correctly split from lwp_days by the
                                    // service), not the full requested span — a
                                    // half-day or multi-day request that ended up
                                    // partly or fully unpaid was always shown here
                                    // as if the whole thing had been deducted,
                                    // regardless of is_lwp/lwp_days.
                                    $amountDisplay = '-' . number_format($item->paid_days ?? 0, 1);
                                }
                                // Days the balance could not cover. The service already
                                // splits these out; the table just never showed them.
                                $lwpDays = $isShortReq ? 0 : (float) ($item->lwp_days ?? 0);
                                $paidDaysShown = (float) ($item->paid_days ?? 0);
                                $amountClass   = 'text-danger';
                                $reason = 'Leave Applied (' . $from->format('d M') . ' - ' . $to->format('d M, Y') . ') - ' . $leaveTypeDetail;
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
                                // Every credit/adjustment row used to render as
                                // plain, unlabelled "Earned for August 2026" -
                                // indistinguishable from any other leave type's
                                // credit for the same month, which is exactly
                                // what made a genuine ALP accrual and an
                                // erroneous Short Leave accrual on the same day
                                // look like duplicate entries of each other.
                                $reason = $notes ?: 'System Adjustment';
                                if ($item->leave_type ?? null) {
                                    $reason = $item->leave_type . ' — ' . $reason;
                                }
                                // Reset per-row, or a leave row's unpaid days leak onto the
                                // credit/adjustment rows rendered after it.
                                $lwpDays = 0;
                                $paidDaysShown = 0;
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
                                    @if($req ?? false)
                                        @if($isShortReq)
                                            <span class="badge" style="font-size: 0.65rem; font-weight:700; background-color:#0dcaf0; color:#000 !important;">SHORT LEAVE</span>
                                        @elseif($isWfhReq)
                                            <span class="badge" style="font-size: 0.65rem; font-weight:700; background-color:#0f766e; color:#fff !important;">WFH &bull; {{ $req->half_day_session === 'second_half' ? '2ND HALF' : '1ST HALF' }}</span>
                                        @elseif($req->is_half_day)
                                            <span class="badge" style="font-size: 0.65rem; font-weight:700; background-color:#6610f2; color:#fff !important;">HALF DAY &bull; {{ $req->half_day_session === 'second_half' ? '2ND HALF' : '1ST HALF' }}</span>
                                        @else
                                            <span class="badge bg-label-secondary" style="font-size: 0.65rem; font-weight:600;">FULL DAY</span>
                                        @endif
                                    @endif
                                    @if($isBackdated ?? false)
                                        <span class="badge bg-label-warning" style="font-size: 0.6rem;">BACK DATED</span>
                                    @endif
                                    {{-- A request longer than the balance covers is split: some days
                                         come off the balance, the rest are leave without pay. Only the
                                         deducted part was ever shown, so the unpaid days simply vanished
                                         from the statement and the row read as if the whole leave had
                                         been paid for. --}}
                                    @if(($lwpDays ?? 0) > 0)
                                        <span class="badge" style="font-size: 0.6rem; font-weight:700; background-color:#a855f7; color:#fff !important;">
                                            NON-PAID {{ rtrim(rtrim(number_format($lwpDays, 1), '0'), '.') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="fw-bold {{ $amountClass }}">{{ $amountDisplay }}</span>
                                @if(($lwpDays ?? 0) > 0)
                                    <div class="small" style="color:#a855f7; font-weight:600;">
                                        {{ rtrim(rtrim(number_format($lwpDays, 1), '0'), '.') }} non-paid
                                    </div>
                                    @if(($paidDaysShown ?? 0) <= 0)
                                        <div class="text-muted" style="font-size:0.7rem;">nothing deducted</div>
                                    @endif
                                @endif
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

    {{-- OUTDOOR DUTY HISTORY --}}
    <div class="hitech-card animate__animated animate__fadeInUp mt-4" style="animation-delay: 0.35s">
        <div class="hitech-card-header">
            <h5 class="title"><i class="bx bx-map-pin me-2 text-primary"></i>My Outdoor Duty Requests</h5>
        </div>
        <div class="card-body p-0">
            <div id="odListWrap" class="px-3 py-2">
                <div class="text-center py-3 text-muted small">
                    <div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- OD Apply Modal -->
<div class="modal fade" id="odApplyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-map-pin me-2"></i>Apply for Outdoor Duty</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">From Date <span class="text-danger">*</span></label>
                        {{-- Same backdate rule as regular leave (up to 1 week back, or
                             further with an HR unlock window) - this used to be locked
                             to today only, with no backdating at all. --}}
                        <input type="date" id="od_date" class="form-control" min="{{ \App\Support\LeaveBackdate::earliestFor(auth()->id()) }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">To Date <span class="text-muted small">(optional)</span></label>
                        <input type="date" id="od_to_date" class="form-control" min="{{ \App\Support\LeaveBackdate::earliestFor(auth()->id()) }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Purpose <span class="text-danger">*</span></label>
                    <textarea id="od_purpose" class="form-control" rows="3" placeholder="Describe the purpose of outdoor duty..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Location <span class="text-muted small">(optional)</span></label>
                    <input type="text" id="od_location" class="form-control" placeholder="e.g. Client office, Site visit">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Expected Return Time <span class="text-muted small">(optional)</span></label>
                    <input type="time" id="od_return_time" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="odSubmitBtn">Submit Request</button>
            </div>
        </div>
    </div>
</div>

<!-- OD View Modal -->
<div class="modal fade" id="odViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Outdoor Duty Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="odViewBody">
                <div class="text-center py-3"><div class="spinner-border text-primary"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
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
                                    You are applying for a past date (allowed up to {{ \App\Support\LeaveBackdate::MAX_DAYS }} days back). Please ensure your reason/remarks are detailed and accurate.
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
                        {{-- Session picker: only visible when HDL (Half Day Leave) is selected --}}
                        <input type="hidden" name="is_half_day" id="is_half_day" value="0">

                        <div class="col-12 d-none" id="half_day_session_container">
                            <label class="form-label-hitech d-block mb-2">Which Half?</label>
                            <div class="d-flex gap-2">
                                <div class="hdpick-opt hdpick-active" id="hdpick-first" onclick="selectSession('first_half')">
                                    <i class="bx bx-sun d-block fs-4 mb-1" style="color:#10b981;"></i>
                                    <div class="fw-semibold" style="font-size:0.85rem;">First Half</div>
                                    <div class="text-muted" style="font-size:0.72rem;">Morning</div>
                                </div>
                                <div class="hdpick-opt" id="hdpick-second" onclick="selectSession('second_half')">
                                    <i class="bx bx-moon d-block fs-4 mb-1" style="color:#6366f1;"></i>
                                    <div class="fw-semibold" style="font-size:0.85rem;">Second Half</div>
                                    <div class="text-muted" style="font-size:0.72rem;">Afternoon</div>
                                </div>
                            </div>
                            <input type="hidden" name="half_day_session" id="half_day_session" value="first_half">
                            {{-- Attendance warning shown when other-half attendance is missing --}}
                            <div id="att_warning" class="d-none mt-2 p-2 rounded-3 border border-warning bg-label-warning" style="font-size:0.8rem;">
                                <i class="bx bx-error-circle me-1 text-warning"></i>
                                <span id="att_warning_text"></span>
                            </div>
                        </div>

                        <!-- Short Leave Session / Time Picker -->
                        <div class="col-12 d-none" id="short_leave_container">
                            <label class="form-label-hitech mb-2">Short Leave Details</label>
                            <div class="row g-3">
                                <div class="col-4">
                                    <label for="start_time" class="form-label-hitech" style="font-size:0.75rem;">Start Time</label>
                                    <input type="time" id="start_time" name="start_time" class="form-control form-control-hitech">
                                </div>
                                <div class="col-4">
                                    <label for="end_time" class="form-label-hitech" style="font-size:0.75rem;">End Time</label>
                                    <input type="time" id="end_time" name="end_time" class="form-control form-control-hitech">
                                </div>
                                <div class="col-4">
                                    <label for="duration_hours" class="form-label-hitech" style="font-size:0.75rem;">Duration (Hrs)</label>
                                    <input type="number" id="duration_hours" name="duration_hours" class="form-control form-control-hitech" step="0.5" readonly placeholder="Calculated">
                                </div>
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
    const halfDaySessionContainer = document.getElementById('half_day_session_container');
    const fromDateContainer = document.getElementById('from_date_container');
    const toDateContainer   = document.getElementById('to_date_container');

    function isHalfDaySelected() {
        const inp = document.getElementById('is_half_day');
        return inp && inp.value === '1';
    }
    function getHalfDaySession() {
        const inp = document.getElementById('half_day_session');
        return inp ? inp.value : 'first_half';
    }

    // Called from onclick on the session divs
    window.selectSession = function(val) {
        document.getElementById('half_day_session').value = val;
        document.getElementById('hdpick-first').classList.toggle('hdpick-active',  val === 'first_half');
        document.getElementById('hdpick-second').classList.toggle('hdpick-active', val === 'second_half');
        checkAttendance();
        checkLeaveImpact();
    };

    // Check attendance for the "other" half when HDL date/session changes
    async function checkAttendance() {
        const date    = fromDateInput.value;
        const session = getHalfDaySession();
        const warnEl  = document.getElementById('att_warning');
        const warnTxt = document.getElementById('att_warning_text');
        if (!date || !isHalfDaySelected() || !warnEl) return;
        try {
            const res = await fetch('{{ route("user.leaves.check_attendance") }}?date=' + date + '&session=' + session);
            if (!res.ok) return;
            const d = await res.json();
            if (d.warning) {
                warnTxt.textContent = d.warning;
                warnEl.classList.remove('d-none');
            } else {
                warnEl.classList.add('d-none');
            }
        } catch(e) { /* silent */ }
    }
    
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

    // In half-day / short-leave mode End Date is hidden and simply mirrors the
    // date. Its min must move with it: a min left over from an earlier full-day
    // pick (say 29 Sep) made a hidden 23 Sep invalid, and the browser then
    // refused to submit without showing anything - the form just sat there.
    function syncHiddenEndDate() {
        toDateInput.setAttribute('min', fromDateInput.value || fromDateInput.getAttribute('min') || '');
        toDateInput.value = fromDateInput.value;
    }

    function handleHalfDayToggle() {
        const typeId = typeSelect.value;
        const leaveTypesArr = Array.isArray(leaveTypes) ? leaveTypes : Object.values(leaveTypes);
        const selectedType = leaveTypesArr.find(t => t.id == typeId);
        const isSHL = selectedType && selectedType.code.toUpperCase() === 'SHL';

        const shortLeaveContainer = document.getElementById('short_leave_container');
        const startTimeEl = document.getElementById('start_time');
        const endTimeEl = document.getElementById('end_time');

        if (isSHL) {
            if (shortLeaveContainer) shortLeaveContainer.classList.remove('d-none');
            if (startTimeEl) startTimeEl.setAttribute('required', 'required');
            if (endTimeEl) endTimeEl.setAttribute('required', 'required');
            
            halfDaySessionContainer.classList.add('d-none');
            toDateContainer.classList.add('d-none');
            fromDateContainer.className = 'col-12';
            
            const fromLabel = document.querySelector("label[for='from_date']");
            if (fromLabel) fromLabel.innerText = 'Date';
            
            syncHiddenEndDate();
        } else if (isHalfDaySelected()) {
            if (shortLeaveContainer) shortLeaveContainer.classList.add('d-none');
            if (startTimeEl) startTimeEl.removeAttribute('required');
            if (endTimeEl) endTimeEl.removeAttribute('required');

            halfDaySessionContainer.classList.remove('d-none');
            toDateContainer.classList.add('d-none');
            fromDateContainer.className = 'col-12';
            
            const fromLabel = document.querySelector("label[for='from_date']");
            if (fromLabel) fromLabel.innerText = 'Date';
            
            syncHiddenEndDate();
            checkAttendance();
        } else {
            if (shortLeaveContainer) shortLeaveContainer.classList.add('d-none');
            if (startTimeEl) startTimeEl.removeAttribute('required');
            if (endTimeEl) endTimeEl.removeAttribute('required');

            halfDaySessionContainer.classList.add('d-none');
            toDateContainer.classList.remove('d-none');
            fromDateContainer.className = 'col-6';
            
            const fromLabel = document.querySelector("label[for='from_date']");
            if (fromLabel) fromLabel.innerText = 'Start Date';
            
            const warnEl = document.getElementById('att_warning');
            if (warnEl) warnEl.classList.add('d-none');
        }
        checkLeaveImpact();
    }

    function updateLeaveDetails() {
        const typeId = typeSelect.value;
        const leaveTypesArr = Array.isArray(leaveTypes) ? leaveTypes : Object.values(leaveTypes);
        const selectedType = leaveTypesArr.find(t => t.id == typeId);
        const fromDate = fromDateInput.value;

        if (selectedType) {
            const code      = selectedType.code.toUpperCase();
            const isMAT     = code === 'MAT';
            const isPAT     = code === 'PAT';
            const isSpecial = isMAT || isPAT;
            const isSHL     = code === 'SHL';
            const isHDL     = code === 'HDL';  // Half Day Leave — always 0.5 days
            const isWFH     = code === 'WFH';  // Work From Home — same as HDL, different label

            // Half day configuration
            if (isHDL || isWFH) {
                // HDL/WFH: force half-day, show session picker, hide end date
                document.getElementById('is_half_day').value = '1';
                handleHalfDayToggle();
                // Reset session picker to First Half by default
                selectSession('first_half');
            } else {
                // All other types: full day, reset half-day state
                document.getElementById('is_half_day').value = '0';
                handleHalfDayToggle();
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
            // HDL deducts from PL pool (same as regular paid leave but 0.5 days).
            // WFH is paid but never pooled — it's always fully paid directly,
            // never by deducting a balance, so it must never show as poolable.
            const excludedCodes = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL', 'WFH'];
            const isPoolable = selectedType.is_paid && !excludedCodes.includes(code);
            
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
                // Only show badge if it's NOT poolable or SHL (since those are in the top summary).
                // WFH has no balance concept at all — showing "0.0" would wrongly imply it's exhausted.
                if (!isPoolable && !isSHL && !isWFH) {
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

    // 3. Past Date Validation & Range Restriction (Allowed up to 1 week back).
    // The earliest date comes from the server (LeaveBackdate), the same rule the
    // form is checked against - worked out in the browser it was a UTC date, a
    // day behind in India before 5:30 am.
    const minDate = @json(\App\Support\LeaveBackdate::earliestFor(auth()->id()));
    
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
            const typeId = typeSelect.value;
            const leaveTypesArr = Array.isArray(leaveTypes) ? leaveTypes : Object.values(leaveTypes);
            const selectedType = leaveTypesArr.find(t => t.id == typeId);
            const isSHL = selectedType && selectedType.code.toUpperCase() === 'SHL';

            if (isHalfDaySelected() || isSHL) {
                syncHiddenEndDate();
                if (!isSHL) {
                    checkAttendance();
                }
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

    // Duration calculation for short leave times
    const startTimeEl = document.getElementById('start_time');
    const endTimeEl = document.getElementById('end_time');
    const durationEl = document.getElementById('duration_hours');

    function calculateDuration() {
        if (startTimeEl && endTimeEl && durationEl && startTimeEl.value && endTimeEl.value) {
            const start = startTimeEl.value.split(':');
            const end = endTimeEl.value.split(':');
            const startDate = new Date(0, 0, 0, start[0], start[1], 0);
            const endDate = new Date(0, 0, 0, end[0], end[1], 0);
            let diff = endDate.getTime() - startDate.getTime();
            if (diff < 0) {
                diff += 24 * 60 * 60 * 1000; // Next day
            }
            const hours = diff / (1000 * 60 * 60);
            durationEl.value = hours.toFixed(2);
        }
    }

    if (startTimeEl) startTimeEl.addEventListener('change', calculateDuration);
    if (endTimeEl) endTimeEl.addEventListener('change', calculateDuration);

    // Initial state
    if (typeSelect && typeSelect.value) {
        updateLeaveDetails();
    }
});

// ── Outdoor Duty ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const statusMap = { pending: 'warning', approved: 'success', rejected: 'danger', cancelled: 'secondary' };

    function loadOdList() {
        $.get('{{ route("user.outdoor_duty.listAjax") }}', function(res) {
            const rows = res.data || [];
            if (!rows.length) {
                $('#odListWrap').html('<p class="text-center text-muted small py-3 mb-0">No outdoor duty requests yet.</p>');
                return;
            }
            let html = '<div class="list-group list-group-flush">';
            rows.slice(0, 10).forEach(function(r) {
                const color = statusMap[r.status] || 'secondary';
                html += `<div class="list-group-item px-0 py-2 border-0 border-bottom d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 text-center" style="min-width:52px">
                        <div class="fw-bold small">${r.date_fmt}</div>
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="text-truncate small fw-semibold">${r.purpose}</div>
                        ${r.location ? `<div class="text-muted" style="font-size:11px">${r.location}</div>` : ''}
                    </div>
                    <span class="badge bg-label-${color} text-capitalize flex-shrink-0">${r.status}</span>
                    <button class="btn btn-sm btn-icon btn-label-info od-view-btn flex-shrink-0" data-id="${r.id}"><i class="bx bx-show"></i></button>
                </div>`;
            });
            html += '</div>';
            if (rows.length > 10) html += `<p class="text-muted text-center small py-2 mb-0">Showing latest 10 of ${rows.length} requests.</p>`;
            $('#odListWrap').html(html);
        }).fail(function() {
            $('#odListWrap').html('<p class="text-center text-muted small py-3 mb-0">Could not load outdoor duty records.</p>');
        });
    }

    loadOdList();

    // "To Date" can't be before "From Date" - mirrors the regular leave form's
    // own from/to sync.
    $('#od_date').on('change', function() {
        $('#od_to_date').attr('min', this.value);
        if ($('#od_to_date').val() && $('#od_to_date').val() < this.value) {
            $('#od_to_date').val(this.value);
        }
    });

    // Submit OD request
    $('#odSubmitBtn').on('click', function() {
        const date    = $('#od_date').val();
        const toDate  = $('#od_to_date').val();
        const purpose = $('#od_purpose').val().trim();
        if (!date)    { alert('Please select a date.'); return; }
        if (!purpose) { alert('Please enter the purpose.'); return; }
        if (toDate && toDate < date) { alert('To Date cannot be before From Date.'); return; }

        $(this).prop('disabled', true).text('Submitting...');
        $.ajax({
            url: '{{ route("user.outdoor_duty.storeAjax") }}',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
            data: {
                od_date:            date,
                od_to_date:         toDate || date,
                od_purpose:         purpose,
                od_location:        $('#od_location').val(),
                od_expected_return: $('#od_return_time').val()
            },
            success: function(response) {
                // The server can return HTTP 200 with status:'error' (e.g. a
                // duplicate request for the same date) -- this never checked
                // that, so a rejected submit showed "submitted successfully"
                // and silently dropped the real error message.
                if (response && response.status === 'error') {
                    const msg = response.message || 'Could not submit the request.';
                    if (typeof toastr !== 'undefined') toastr.error(msg);
                    else alert(msg);
                    return;
                }
                const okMsg = response.message || 'Outdoor duty request submitted successfully.';
                if (typeof toastr !== 'undefined') toastr.success(okMsg);
                else alert(okMsg);
                $('#odApplyModal').modal('hide');
                $('#od_date, #od_to_date, #od_purpose, #od_location, #od_return_time').val('');
                loadOdList();
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || xhr.responseJSON?.error || 'Failed to submit.';
                if (typeof toastr !== 'undefined') toastr.error(msg);
                else alert(msg);
            },
            complete: function() {
                $('#odSubmitBtn').prop('disabled', false).text('Submit Request');
            }
        });
    });

    // View OD detail
    $(document).on('click', '.od-view-btn', function() {
        const id = $(this).data('id');
        $('#odViewBody').html('<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>');
        $('#odViewModal').modal('show');
        $.get('{{ url("user/outdoor-duty/getByIdAjax") }}/' + id, function(d) {
            const color = statusMap[d.status] || 'secondary';
            const badge = `<span class="badge bg-label-${color} text-capitalize">${d.status}</span>`;
            $('#odViewBody').html(`
                <div class="row g-3">
                    <div class="col-6"><strong>Date</strong><br>${d.date}</div>
                    <div class="col-6"><strong>Status</strong><br>${badge}</div>
                    <div class="col-12"><strong>Purpose</strong><br>${d.purpose}</div>
                    ${d.location ? `<div class="col-6"><strong>Location</strong><br>${d.location}</div>` : ''}
                    ${d.expectedReturnTime ? `<div class="col-6"><strong>Expected Return</strong><br>${d.expectedReturnTime}</div>` : ''}
                    ${d.approvalNotes ? `<div class="col-12"><strong>Notes</strong><br>${d.approvalNotes}</div>` : ''}
                    <div class="col-12 text-muted small">Applied on ${d.createdAt}</div>
                </div>
            `);
        });
    });
});
</script>
@endsection
@endsection
