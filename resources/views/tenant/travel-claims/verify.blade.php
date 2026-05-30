@extends('layouts.layoutMaster')

@section('title', 'Verify Travel Claims')

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
            <h4 class="greeting text-white mb-1">Verify Travel Claims</h4>
            <p class="sub-text text-white-50 mb-0">Review and verify travel expenses submitted by employees.</p>
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
                            <th>Month</th>
                            <th>Company</th>
                            <th>Net Payable</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $claim)
                        <tr>
                            <td><strong>#{{ $claim->id }}</strong></td>
                            <td>{{ $claim->user->name }}</td>
                            <td>{{ $claim->claim_month }}</td>
                            <td>{{ $claim->company }}</td>
                            <td>
                                <strong>₹{{ number_format($claim->net_payable, 2) }}</strong>
                                @if($claim->late_penalty_applied)
                                    <br><span class="badge bg-danger">Late Penalty Applied</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $claim->status == 'submitted' ? 'info' : 'secondary' }}">{{ ucfirst($claim->status) }}</span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#verifyModal{{ $claim->id }}">Review</button>
                            </td>
                        </tr>

                        <!-- Review Modal -->
                        <div class="modal fade" id="verifyModal{{ $claim->id }}" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header bg-light">
                                        <h5 class="modal-title">Review Claim #{{ $claim->id }} - {{ $claim->user->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0" style="font-size: 0.8rem;">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Locations</th>
                                                        <th>Party</th>
                                                        <th>Mode & KMs</th>
                                                        <th>Conveyance</th>
                                                        <th>Food</th>
                                                        <th>Lodging</th>
                                                        <th>Courier</th>
                                                        <th>Photo Proof</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($claim->items as $item)
                                                    <tr>
                                                        <td>{{ $item->date->format('d M') }}</td>
                                                        <td>{{ $item->from_location }} -> {{ $item->to_location }}</td>
                                                        <td>{{ $item->party_visited }}</td>
                                                        <td>{{ $item->mode_of_travel }} <br> <strong>{{ $item->distance_km }} KM</strong></td>
                                                        <td>₹{{ $item->conveyance_amount }}
                                                            @if($item->penalty_applied) <br><span class="text-danger small">No Photo Penalty</span> @endif
                                                        </td>
                                                        <td>₹{{ $item->food_allowance }}</td>
                                                        <td>₹{{ $item->lodging_amount }}</td>
                                                        <td>₹{{ $item->courier_amount }}</td>
                                                        <td>
                                                            @if($item->photo_path)
                                                                <a href="{{ Storage::url($item->photo_path) }}" target="_blank" class="btn btn-xs btn-outline-primary">View</a>
                                                            @else
                                                                <span class="text-muted">N/A</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <form action="{{ route('travel-claims.verify', $claim->id) }}" method="POST">
                                            @csrf
                                            <div class="input-group">
                                                <input type="text" name="remarks" class="form-control" placeholder="Optional remarks...">
                                                <button type="submit" class="btn btn-success">Mark as Verified</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No claims pending verification.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
