@extends('layouts.layoutMaster')

@section('title', 'Accounts Travel Payouts')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="container-fluid animate__animated animate__fadeIn">
    {{-- Hero Header --}}
    <div class="hitech-page-hero mb-6">
        <div class="hitech-page-hero-text">
            <h4 class="greeting text-white mb-1">Accounts Travel Payouts</h4>
            <p class="sub-text text-white-50 mb-0">Approve verified claims and manage 85%/15% split payouts.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Claim ID</th>
                            <th>Employee</th>
                            <th>Banking Details</th>
                            <th>Net Payable</th>
                            <th>Status</th>
                            <th>Payout Schedule (85% / 15%)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $claim)
                        <tr>
                            <td><strong>#{{ $claim->id }}</strong><br><small class="text-muted">{{ $claim->claim_month }}</small></td>
                            <td>{{ $claim->user->name }}</td>
                            <td>
                                <small>
                                    <strong>Name:</strong> {{ $claim->bank_account_name }}<br>
                                    <strong>A/C:</strong> {{ $claim->bank_account_no }}<br>
                                    <strong>IFSC:</strong> {{ $claim->bank_ifsc }}
                                </small>
                            </td>
                            <td><strong>₹{{ number_format($claim->net_payable, 2) }}</strong></td>
                            <td>
                                @if($claim->status == 'verified') <span class="badge bg-primary">Verified by HR</span>
                                @elseif($claim->status == 'approved') <span class="badge bg-warning text-dark">Approved</span>
                                @elseif($claim->status == 'paid') <span class="badge bg-success">Paid</span>
                                @endif
                            </td>
                            <td>
                                @if(in_array($claim->status, ['approved', 'paid']))
                                    <div class="small">
                                        <strong>85%:</strong> ₹{{ $claim->split_85_amount }} on {{ $claim->split_85_paid_on->format('d M') }}<br>
                                        <span class="text-{{ $claim->split_85_transaction ? 'success' : 'danger' }}">
                                            Trx: {{ $claim->split_85_transaction ?? 'Pending' }}
                                        </span>
                                    </div>
                                    <hr class="my-1">
                                    <div class="small">
                                        <strong>15%:</strong> ₹{{ $claim->split_15_amount }} on {{ $claim->split_15_paid_on->format('d M') }}<br>
                                        <span class="text-{{ $claim->split_15_transaction ? 'success' : 'danger' }}">
                                            Trx: {{ $claim->split_15_transaction ?? 'Pending' }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-muted text-center d-block">-</span>
                                @endif
                            </td>
                            <td>
                                @if($claim->status == 'verified')
                                    <form action="{{ route('travel-claims.approve', $claim->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-warning fw-bold">Approve Payout</button>
                                    </form>
                                @elseif($claim->status == 'approved')
                                    <button class="btn btn-sm btn-outline-success mb-1" data-bs-toggle="modal" data-bs-target="#payModal85_{{ $claim->id }}">Pay 85%</button><br>
                                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#payModal15_{{ $claim->id }}">Pay 15%</button>
                                @endif
                                <a href="{{ route('travel-claims.download-attachments', $claim->id) }}" class="btn btn-sm btn-outline-primary mt-1 w-100 d-block" style="border-radius: 8px; font-size: 0.75rem; text-align: center;">
                                    <i class="bx bx-download"></i> Attachments (ZIP)
                                </a>
                            </td>
                        </tr>

                        @if($claim->status == 'approved')
                        <!-- Pay 85% Modal -->
                        <div class="modal fade" id="payModal85_{{ $claim->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form action="{{ route('travel-claims.pay', $claim->id) }}" method="POST" class="modal-content">
                                    @csrf
                                    <input type="hidden" name="payment_type" value="85">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Record 85% Payment (₹{{ $claim->split_85_amount }})</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <label class="form-label">Transaction Reference No.</label>
                                        <input type="text" name="transaction" class="form-control" required>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-success">Mark Paid</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Pay 15% Modal -->
                        <div class="modal fade" id="payModal15_{{ $claim->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form action="{{ route('travel-claims.pay', $claim->id) }}" method="POST" class="modal-content">
                                    @csrf
                                    <input type="hidden" name="payment_type" value="15">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Record 15% Payment (₹{{ $claim->split_15_amount }})</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <label class="form-label">Transaction Reference No.</label>
                                        <input type="text" name="transaction" class="form-control" required>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-success">Mark Paid</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @endif
                        
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No verified claims pending accounts review.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
