@extends('layouts/layoutMaster')

@section('title', 'My Expenses')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')


<div class="container-xxl flex-grow-1 container-p-y">
    
    {{-- HERO SECTION --}}
    <div class="expenses-hero animate__animated animate__fadeIn">
        <div class="expenses-hero-text">
            <div class="greeting">Expense Claims</div>
            <div class="sub-text">Submit your business expenses and track reimbursement.</div>
        </div>
        <div>
            <button type="button" class="btn btn-hitech" data-bs-toggle="modal" data-bs-target="#hitechSubmitExpenseModal">
                <i class="bx bx-plus-circle me-2"></i> New Expense Claim
            </button>
        </div>
    </div>

    @php
        $totalClaimed = $expenses->sum('amount');
        $approvedAmount = $expenses->where('status', 'approved')->sum('amount');
        $pendingAmount = $expenses->where('status', 'pending')->sum('amount');
        $currency = $settings->currency_symbol ?? '₹';
    @endphp

    {{-- STATS SECTION --}}
    <div class="row g-4 mb-6">
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-teal"><i class="bx bx-wallet"></i></div>
                <div class="stat-label">Total Claimed</div>
                <div class="stat-value">{{ $currency }}{{ number_format($totalClaimed, 0) }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-blue"><i class="bx bx-check-shield"></i></div>
                <div class="stat-label">Approved</div>
                <div class="stat-value">{{ $currency }}{{ number_format($approvedAmount, 0) }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-amber"><i class="bx bx-timer"></i></div>
                <div class="stat-label">Pending Claim</div>
                <div class="stat-value">{{ $currency }}{{ number_format($pendingAmount, 0) }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-red"><i class="bx bx-x-circle"></i></div>
                <div class="stat-label">Rejected</div>
                <div class="stat-value">{{ $expenses->where('status', 'rejected')->count() }}</div>
            </div>
        </div>
    </div>

    {{-- TABLE SECTION --}}
    <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.25s">
        <div class="hitech-card-header">
            <h5 class="title">My Expense History</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-label-secondary"><i class="bx bx-filter me-1"></i> Filter</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Expense Type</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Proof</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenses as $expense)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon-wrap icon-teal me-3 mb-0" style="width:32px; height:32px; font-size:0.9rem;">
                                        <i class="bx bx-receipt"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $expense->expenseType->name }}</div>
                                        <small class="text-muted">{{ \Illuminate\Support\Str::limit($expense->remarks, 50) }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-semibold">
                                    {{ \Carbon\Carbon::parse($expense->for_date)->format('d M, Y') }}
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-bold fs-5">
                                    {{ $currency }}{{ number_format($expense->amount, 2) }}
                                </div>
                            </td>
                            <td>
                                @php
                                    $statusColor = 'secondary';
                                    if($expense->status == 'approved') $statusColor = 'success';
                                    elseif($expense->status == 'rejected') $statusColor = 'danger';
                                    elseif($expense->status == 'pending') $statusColor = 'warning';
                                    elseif($expense->status == 'cancelled') $statusColor = 'dark';
                                @endphp
                                <span class="badge badge-hitech bg-label-{{ $statusColor }}">
                                    <i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>
                                    {{ ucfirst($expense->status) }}
                                </span>
                            </td>
                            <td>
                                @if($expense->document_url)
                                <a href="{{ \App\Helpers\FileSecurityHelper::generateSecureUrl(\App\Constants\Constants::BaseFolderExpenseProofs . $expense->document_url) }}" target="_blank" class="btn btn-sm btn-icon btn-label-primary">
                                    <i class="bx bx-show-alt"></i>
                                </a>
                                @else
                                <span class="text-muted small">No proof</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">{{ $expense->created_at->format('d M, H:i') }}</small>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bx bx-info-circle fs-2 d-block mb-2 opacity-50"></i>
                                No expense claims found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Submit Expense Modal -->
<div class="modal fade" id="hitechSubmitExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-receipt fs-3"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech" id="hitechSubmitExpenseModalTitle">New Expense Claim</h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <form action="{{ route('user.expenses.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body modal-body-hitech">
                    <div class="row g-4">
                        <div class="col-12">
                            <label for="expense_type_id" class="form-label-hitech">Expense Category</label>
                            <select id="expense_type_id" name="expense_type_id" class="form-select form-select-hitech" required>
                                <option value="">Select Category...</option>
                                @foreach($expenseTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="for_date" class="form-label-hitech">Date of Expense</label>
                            <input type="date" id="for_date" name="for_date" class="form-control form-control-hitech" required>
                        </div>
                        <div class="col-md-6">
                            <label for="amount" class="form-label-hitech">Amount ({{ $currency }})</label>
                            <input type="number" id="amount" name="amount" class="form-control form-control-hitech" step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                        <div class="col-12">
                            <label for="remarks" class="form-label-hitech">Remarks / Description</label>
                            <textarea id="remarks" name="remarks" class="form-control form-control-hitech" rows="3" placeholder="Description of the expense..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label for="file" class="form-label-hitech">Upload Receipt (Proof)</label>
                            <input type="file" id="file" name="file" class="form-control form-control-hitech" accept="image/*,application/pdf">
                            <small class="text-muted mt-1 d-block">Accepted forms: JPG, PNG, PDF (Max 5MB)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-hitech px-4">Submit Claim</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
