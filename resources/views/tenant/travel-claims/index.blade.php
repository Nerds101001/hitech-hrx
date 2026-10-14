@extends('layouts.layoutMaster')

@section('title', 'My Travel Claims')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])

<style>
/* Compact, quiet table: the figures should carry the page, not the chrome. */
.tc-h {
    font-size: .7rem; text-transform: uppercase; letter-spacing: .04em;
    font-weight: 700; color: #64748b; white-space: nowrap;
}
@media (max-width: 480px) {
    .tc-h { font-size: .75rem; }
    /* The 10-column header text is already above 11px except the badge
       below, which is the actual sub-12px offender on this page. */
}
table.table > thead > tr > th { padding: .6rem; }
table.table > tbody > tr > td { vertical-align: middle; font-size: .86rem; padding: .55rem .6rem; }
table.table > tbody > tr:hover { background: #f1f5f9; }

/* Square icon action - comfortable to hit, yet three still fit in 104px. */
.tc-act {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; padding: 0; margin-left: 2px;
    border: 1px solid #cbd5e1; border-radius: 6px;
    background: #fff; color: #0f766e; font-size: 15px; line-height: 1;
    cursor: pointer; transition: background .12s, border-color .12s, color .12s;
}
/* Hover tints the button rather than flooding it. A solid saturated fill on a
   28px control reads as a warning block and fought with the rest of the row;
   a soft wash plus a matching border gives the same affordance quietly. */
.tc-act:hover      { background: #f0fdfa; border-color: #5eead4; color: #0f766e; }
.tc-act:active     { background: #ccfbf1; }

.tc-act-edit       { color: #c2740a; }
.tc-act-edit:hover { background: #fffbeb; border-color: #fcd34d; color: #b45309; }
.tc-act-edit:active{ background: #fef3c7; }

.tc-act-del        { color: #e11d48; }
.tc-act-del:hover  { background: #fff1f2; border-color: #fda4af; color: #be123c; }
.tc-act-del:active { background: #ffe4e6; }

.tc-act:focus-visible { outline: 2px solid #14b8a6; outline-offset: 1px; }

/* Soft status pills. Solid Bootstrap badges shouted louder than the figures
   they sat next to; tinted backgrounds keep the status readable at a glance
   without competing with the amounts. */
.tc-pill {
    display: inline-block; padding: .22rem .6rem; border-radius: 999px;
    font-size: .7rem; font-weight: 700; letter-spacing: .02em; white-space: nowrap;
    border: 1px solid transparent;
}
.tc-draft     { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
.tc-pending   { background: #eff6ff; color: #1d4ed8; border-color: #dbeafe; }
.tc-verified  { background: #ecfeff; color: #0e7490; border-color: #cffafe; }
.tc-approved  { background: #fffbeb; color: #b45309; border-color: #fef3c7; }
.tc-paid      { background: #ecfdf5; color: #047857; border-color: #d1fae5; }
.tc-objection { background: #fef2f2; color: #b91c1c; border-color: #fee2e2; }

/* Money should read as money: aligned, tabular, easy to scan down a column. */
.font-monospace { font-variant-numeric: tabular-nums; font-size: .85rem; }

/* The header card was competing with the table; calm the card, keep the table
   the focus. */
table.table { border-color: #e2e8f0; }
table.table > thead > tr > th { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
</style>
@endsection

@section('content')
<div class="container-fluid animate__animated animate__fadeIn">
    {{-- Hero Header --}}
    <div class="hitech-page-hero mb-6">
        <div class="hitech-page-hero-text d-flex justify-content-between align-items-center w-100">
            <div>
                <h4 class="greeting text-white mb-1">My Travel Claims</h4>
                <p class="sub-text text-white-50 mb-0">Track and submit your monthly travel and expense claims.</p>
            </div>
            <div>
                {{-- Styled inline, without .btn/.btn-light: the theme stylesheet strips
                     the background off those classes, which left this button as bare
                     white text lost on the teal header. --}}
                <a href="{{ route('travel-claims.create') }}"
                   style="display:inline-flex;align-items:center;gap:.35rem;
                          background:#ffffff;color:#0f766e;font-weight:700;
                          padding:.5rem 1.15rem;border-radius:999px;
                          font-size:.88rem;text-decoration:none;
                          box-shadow:0 2px 6px rgba(0,0,0,.18);transition:transform .12s;"
                   onmouseover="this.style.transform='translateY(-1px)'"
                   onmouseout="this.style.transform='none'">
                    <i class="bx bx-plus me-1"></i> New Claim
                </a>
            </div>
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
                            <th class="tc-h" style="width:70px;">ID</th>
                            <th class="tc-h" style="width:92px;">Month</th>
                            <th class="tc-h">Company</th>
                            <th class="tc-h text-end" style="width:115px;">Total</th>
                            <th class="tc-h text-end" style="width:130px;">Net Payable</th>
                            <th class="tc-h">Remarks / Objection</th>
                            <th class="tc-h" style="width:110px;">Status</th>
                            <th class="tc-h" style="width:150px;">Payment</th>
                            <th class="tc-h" style="width:100px;">Submitted</th>
                            <th class="tc-h text-end" style="width:104px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $claim)
                        <tr>
                            <td><strong>#{{ $claim->id }}</strong></td>
                            <td>{{ $claim->claim_month }}</td>
                            <td>{{ $claim->company }}</td>
                            <td class="text-end font-monospace">₹{{ number_format($claim->total_amount, 2) }}</td>
                            <td class="text-end">
                                <strong class="font-monospace">₹{{ number_format($claim->net_payable, 2) }}</strong>
                                {{-- A total with no day-rows behind it means the lines were lost.
                                     Say so, rather than showing money that opens to an empty grid. --}}
                                @if($claim->total_amount > 0 && $claim->items->isEmpty())
                                    <div><span class="badge bg-label-danger mt-1" style="font-size:.75rem;"
                                          title="This claim has a total but no daily rows - the entries need filling in again.">
                                        <i class="bx bx-error"></i> lines missing</span></div>
                                @endif
                                @if($claim->late_penalty_applied)
                                    <br><span class="badge bg-danger">10% Late Penalty</span>
                                @endif
                            </td>
                            <td>
                                @if($claim->objection_notes)
                                    <div class="text-danger fw-bold"><i class="bx bx-error-circle"></i> {{ $claim->objection_notes }}</div>
                                @elseif($claim->remarks)
                                    <div class="text-muted small">{{ $claim->remarks }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($claim->status == 'draft') <span class="tc-pill tc-draft">Draft</span>
                                @elseif($claim->status == 'submitted') <span class="tc-pill tc-pending">Pending HR</span>
                                @elseif($claim->status == 'verified') <span class="tc-pill tc-verified">Verified</span>
                                @elseif($claim->status == 'approved') <span class="tc-pill tc-approved">Approved</span>
                                @elseif($claim->status == 'paid') <span class="tc-pill tc-paid">Paid</span>
                                @elseif($claim->status == 'objection') <span class="tc-pill tc-objection">Objection</span>
                                @else <span class="tc-pill tc-draft">{{ ucfirst($claim->status) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($claim->status == 'approved' || $claim->status == 'paid')
                                    <small class="d-block text-muted">85%: ₹{{ $claim->split_85_amount }} on {{ $claim->split_85_paid_on ? $claim->split_85_paid_on->format('d M') : '11th' }}</small>
                                    <small class="d-block text-muted">15%: ₹{{ $claim->split_15_amount }} on {{ $claim->split_15_paid_on ? $claim->split_15_paid_on->format('d M') : '25th' }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $claim->created_at->format('d M, Y') }}</td>
                            {{-- Icons rather than stacked buttons: three word-buttons per row forced
                                 the Actions column wide enough to break the table. Titles keep the
                                 meaning available on hover. --}}
                            <td class="text-end text-nowrap">
                                <button type="button" class="tc-act" title="View details"
                                        data-bs-toggle="modal" data-bs-target="#viewModal{{ $claim->id }}">
                                    <i class="bx bx-show"></i>
                                </button>
                                @if($claim->status === 'objection' || $claim->status === 'draft')
                                    <a href="{{ route('travel-claims.edit', $claim->id) }}" class="tc-act tc-act-edit"
                                       title="{{ $claim->status === 'draft' ? 'Continue this draft' : 'Edit and resubmit' }}">
                                        <i class="bx bx-edit-alt"></i>
                                    </a>
                                @endif
                                @if($claim->status === 'draft')
                                    <form action="{{ route('travel-claims.destroy', $claim->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this draft claim? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="tc-act tc-act-del" title="Delete draft">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>

                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">You have not submitted any travel claims yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modals live OUTSIDE the table on purpose. They used to sit inside <tbody>,
     which only permits <tr>: the browser hoisted each <div> out of the table and
     the rows after it collapsed — cells stacked vertically, badges spanning the
     full width, and the detail grid rendering inline instead of in a dialog. --}}
@foreach($claims as $claim)
                        <!-- View Details Modal -->
                        <div class="modal fade" id="viewModal{{ $claim->id }}" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header bg-light">
                                        <h5 class="modal-title">Claim #{{ $claim->id }} Details</h5>
                                        <a href="{{ route('travel-claims.download-attachments', $claim->id) }}" class="btn btn-sm btn-outline-primary ms-auto me-3" style="border-radius: 8px;">
                                            <i class="bx bx-download"></i> Download Attachments (ZIP)
                                        </a>
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
                                                        <th>Conveyance/<br>Amount (₹)</th>
                                                        <th>Conveyance/<br>Bill Proof</th>
                                                        <th>Food</th>
                                                        <th>Food Bill<br>Proof</th>
                                                        <th>Lodging</th>
                                                        <th>Courier (Transport/<br>Freight) etc</th>
                                                        <th>Courier / Transport<br>Proof</th>
                                                        <th>Toll/Parking</th>
                                                        <th>Toll/Parking<br>Proof</th>
                                                        <th>Customer Expense</th>
                                                        <th>Customer Expense Proof</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @if(isset($claim->items))
                                                    @foreach($claim->items as $item)
                                                    <tr>
                                                        <td>{{ $item->date->format('d M') }}</td>
                                                        <td>{{ $item->from_location }} -> {{ $item->to_location }}</td>
                                                        <td>{{ $item->party_visited }}</td>
                                                        <td>{{ $item->mode_of_travel }} <br> <strong>{{ $item->distance_km }} KM</strong></td>
                                                        <td>₹{{ number_format($item->conveyance_amount, 2) }}
                                                            @if($item->penalty_applied) <br><span class="text-danger small">No Photo Penalty</span> @endif
                                                        </td>
                                                        <td>
                                                            @if($item->petrol_slip_proof)
                                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($item->petrol_slip_proof) }}" target="_blank" class="btn btn-xs btn-outline-primary">View</a>
                                                            @else
                                                                 <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td>₹{{ number_format($item->food_allowance, 2) }}</td>
                                                        <td>
                                                            @if($item->additional_food_proof)
                                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($item->additional_food_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td>₹{{ number_format($item->lodging_amount, 2) }}</td>
                                                        <td>₹{{ number_format($item->courier_amount, 2) }}</td>
                                                        <td>
                                                            @if($item->courier_proof)
                                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($item->courier_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td>₹{{ number_format($item->toll_amount, 2) }}</td>
                                                        <td>
                                                            @if($item->toll_proof)
                                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($item->toll_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td>₹{{ number_format($item->special_approval_amount ?? 0, 2) }}</td>
                                                        <td>
                                                            @if($item->special_approval_proof)
                                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($item->special_approval_proof) }}" target="_blank" class="btn btn-xs btn-outline-info">View</a>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
@endforeach
@endsection
