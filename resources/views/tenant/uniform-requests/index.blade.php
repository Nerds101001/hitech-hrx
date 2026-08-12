@extends('layouts/layoutMaster')

@section('title', 'Request Uniform')

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')

<div class="row g-4 mb-4 mx-0">
  <div class="col-12 px-0">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h4 class="fw-bold mb-1">Uniform Request</h4>
        <p class="text-muted mb-0 small">Ask for a shirt or a t-shirt. Admin or HR will review it.</p>
      </div>
      @if($canApprove)
        <a href="{{ route('uniformRequests.manage') }}" class="btn btn-hitech rounded-pill px-4 fw-bold">
          <i class="bx bx-list-check me-1"></i> Review All Requests
        </a>
      @endif
    </div>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible mx-0" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible mx-0" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if($errors->any())
  <div class="alert alert-danger mx-0">
    <ul class="mb-0 ps-3">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="row g-4 mx-0">

  {{-- ── the form ─────────────────────────────────────────────────────── --}}
  <div class="col-lg-4 ps-0">
    <div class="card h-100">
      <div class="card-header border-bottom">
        <h6 class="mb-0 fw-bold"><i class="bx bx-t-shirt me-1"></i> New Request</h6>
      </div>
      <div class="card-body">
        @if($hasPending)
          <div class="text-center py-4">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                 style="width:64px;height:64px;background:#fef3c7;color:#f59e0b;">
              <i class="bx bx-time-five fs-3"></i>
            </div>
            <h6 class="fw-bold mb-1">A request is already open</h6>
            <p class="text-muted small mb-0">
              You can raise the next one once this has been approved or rejected.
            </p>
          </div>
        @else
          <form action="{{ route('uniformRequests.store') }}" method="POST">
            @csrf

            <div class="mb-3">
              <label class="form-label fw-bold small text-muted text-uppercase">Item</label>
              <select name="item_type" class="form-select" required>
                @foreach($items as $value => $label)
                  <option value="{{ $value }}" @selected(old('item_type') === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-7">
                <label class="form-label fw-bold small text-muted text-uppercase">Size</label>
                <select name="size" class="form-select" required>
                  @foreach($sizes as $s)
                    <option value="{{ $s }}" @selected(old('size') === $s)>{{ $s }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-5">
                <label class="form-label fw-bold small text-muted text-uppercase">Qty</label>
                <input type="number" name="quantity" class="form-control" min="1" max="5"
                       value="{{ old('quantity', 1) }}" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small text-muted text-uppercase">Remarks <span class="fw-normal text-lowercase">(optional)</span></label>
              <textarea name="remarks" class="form-control" rows="3"
                        placeholder="Anything the approver should know">{{ old('remarks') }}</textarea>
            </div>

            <button type="submit" class="btn btn-hitech w-100 rounded-pill fw-bold">
              <i class="bx bx-send me-1"></i> Submit Request
            </button>
          </form>
        @endif
      </div>
    </div>
  </div>

  {{-- ── my requests ──────────────────────────────────────────────────── --}}
  <div class="col-lg-8 pe-0">
    <div class="card h-100">
      <div class="card-header border-bottom d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-bold"><i class="bx bx-history me-1"></i> My Requests</h6>
        <span class="badge bg-label-secondary rounded-pill">{{ $requests->total() }}</span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Item</th>
              <th>Size</th>
              <th>Qty</th>
              <th>Requested</th>
              <th>Status</th>
              <th>Reviewer's Remarks</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($requests as $r)
              <tr>
                <td class="fw-bold">{{ $r->item_label }}</td>
                <td>{{ $r->size }}</td>
                <td>{{ $r->quantity }}</td>
                <td class="small text-muted">{{ $r->created_at->format('d M Y') }}</td>
                <td>
                  <span class="badge {{ $r->status_badge }} rounded-pill px-3">{{ ucfirst($r->status) }}</span>
                </td>
                <td class="small">
                  @if($r->decision_note)
                    {{ $r->decision_note }}
                    <div class="text-muted smallest">
                      — {{ optional($r->actionedBy)->first_name }} {{ optional($r->actionedBy)->last_name }},
                      {{ optional($r->actioned_at)->format('d M Y') }}
                    </div>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="text-end">
                  @if($r->isPending())
                    <form action="{{ route('uniformRequests.cancel', $r->id) }}" method="POST"
                          onsubmit="return confirm('Withdraw this request?')">
                      @csrf
                      <button class="btn btn-sm btn-hitech-pill-outline rounded-pill">Withdraw</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted py-5">
                  <i class="bx bx-t-shirt fs-1 d-block mb-2 opacity-50"></i>
                  You have not requested anything yet.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($requests->hasPages())
        <div class="card-footer">{{ $requests->links() }}</div>
      @endif
    </div>
  </div>

</div>

@endsection
