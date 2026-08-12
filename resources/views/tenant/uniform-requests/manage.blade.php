@extends('layouts/layoutMaster')

@section('title', 'Uniform Requests')

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')

<div class="row g-4 mb-4 mx-0">
  <div class="col-12 px-0">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h4 class="fw-bold mb-1">Uniform Requests</h4>
        <p class="text-muted mb-0 small">Approve or reject shirt and t-shirt requests, with a remark.</p>
      </div>
      <a href="{{ route('uniformRequests.index') }}" class="btn btn-hitech-pill-outline rounded-pill px-4 fw-bold">
        <i class="bx bx-user me-1"></i> My Own Request
      </a>
    </div>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible mx-0">{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible mx-0">{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- counters double as filters --}}
<div class="row g-3 mb-4 mx-0">
  @foreach(['pending' => ['Pending','#f59e0b','bx-time-five'], 'approved' => ['Approved','#10b981','bx-check-circle'], 'rejected' => ['Rejected','#ef4444','bx-x-circle']] as $key => [$label,$colour,$icon])
    <div class="col-md-4 {{ $loop->first ? 'ps-0' : '' }} {{ $loop->last ? 'pe-0' : '' }}">
      <a href="{{ route('uniformRequests.manage', ['status' => $key]) }}" class="text-decoration-none">
        <div class="card h-100 {{ request('status') === $key ? 'border-2' : '' }}"
             style="{{ request('status') === $key ? 'border-color:'.$colour.' !important;' : '' }}">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="d-flex align-items-center justify-content-center rounded-3"
                 style="width:46px;height:46px;background:{{ $colour }}1a;color:{{ $colour }};">
              <i class="bx {{ $icon }} fs-4"></i>
            </div>
            <div>
              <h4 class="mb-0 fw-bold">{{ $counts[$key] }}</h4>
              <span class="small text-muted">{{ $label }}</span>
            </div>
          </div>
        </div>
      </a>
    </div>
  @endforeach
</div>

<div class="card mx-0">
  <div class="card-header border-bottom">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-bold small text-muted text-uppercase mb-1">Employee</label>
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Name or code">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-bold small text-muted text-uppercase mb-1">Item</label>
        <select name="item_type" class="form-select">
          <option value="">All</option>
          @foreach($items as $v => $l)
            <option value="{{ $v }}" @selected(request('item_type') === $v)>{{ $l }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-bold small text-muted text-uppercase mb-1">Status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          @foreach(['pending','approved','rejected','cancelled'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-hitech rounded-pill flex-fill fw-bold">Search</button>
        <a href="{{ route('uniformRequests.manage') }}" class="btn btn-hitech-pill-outline rounded-pill">Clear</a>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Item</th>
          <th>Size</th>
          <th>Qty</th>
          <th>Their Remarks</th>
          <th>Requested</th>
          <th>Status</th>
          <th class="text-end">Decision</th>
        </tr>
      </thead>
      <tbody>
        @forelse($requests as $r)
          <tr>
            <td>
              <span class="fw-bold">{{ optional($r->user)->first_name }} {{ optional($r->user)->last_name }}</span>
              <div class="text-muted smallest">{{ optional($r->user)->code }}</div>
            </td>
            <td class="fw-bold">{{ $r->item_label }}</td>
            <td>{{ $r->size }}</td>
            <td>{{ $r->quantity }}</td>
            <td class="small" style="max-width:220px;">{{ $r->remarks ?: '—' }}</td>
            <td class="small text-muted">{{ $r->created_at->format('d M Y') }}</td>
            <td>
              <span class="badge {{ $r->status_badge }} rounded-pill px-3">{{ ucfirst($r->status) }}</span>
              @if($r->decision_note)
                <div class="smallest text-muted mt-1">{{ $r->decision_note }}</div>
              @endif
              @if($r->actionedBy)
                <div class="smallest text-muted">
                  — {{ $r->actionedBy->first_name }}, {{ optional($r->actioned_at)->format('d M Y') }}
                </div>
              @endif
            </td>
            <td class="text-end">
              @if($r->isPending())
                <button class="btn btn-sm btn-hitech rounded-pill fw-bold"
                        data-bs-toggle="modal" data-bs-target="#decideModal"
                        data-id="{{ $r->id }}" data-decision="approve"
                        data-who="{{ optional($r->user)->first_name }} {{ optional($r->user)->last_name }}"
                        data-what="{{ $r->quantity }} × {{ $r->item_label }} ({{ $r->size }})">
                  Approve
                </button>
                <button class="btn btn-sm btn-outline-danger rounded-pill fw-bold"
                        data-bs-toggle="modal" data-bs-target="#decideModal"
                        data-id="{{ $r->id }}" data-decision="reject"
                        data-who="{{ optional($r->user)->first_name }} {{ optional($r->user)->last_name }}"
                        data-what="{{ $r->quantity }} × {{ $r->item_label }} ({{ $r->size }})">
                  Reject
                </button>
              @else
                <span class="text-muted small">Decided</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="text-center text-muted py-5">
              <i class="bx bx-t-shirt fs-1 d-block mb-2 opacity-50"></i>
              No requests match this view.
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

{{-- one modal, reused for both decisions --}}
<div class="modal fade" id="decideModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" id="decideForm">
      @csrf
      <input type="hidden" name="decision" id="decideAction">
      <div class="modal-header">
        <h5 class="modal-title" id="decideTitle">Decision</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3" id="decideSummary"></p>
        <label class="form-label fw-bold small text-muted text-uppercase" id="decideNoteLabel">Remarks</label>
        <textarea name="decision_note" id="decideNote" class="form-control" rows="3"
                  placeholder="Add a remark"></textarea>
        <div class="form-text" id="decideHint"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-hitech-pill-outline rounded-pill" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn rounded-pill fw-bold" id="decideSubmit">Confirm</button>
      </div>
    </form>
  </div>
</div>

@endsection

@section('page-script')
<script>
  // One modal serves both buttons; it rewrites its own action and wording.
  document.getElementById('decideModal').addEventListener('show.bs.modal', function (event) {
    var btn      = event.relatedTarget;
    var id       = btn.getAttribute('data-id');
    var decision = btn.getAttribute('data-decision');
    var approve  = decision === 'approve';

    document.getElementById('decideForm').action =
      "{{ url('uniform-requests') }}/" + id + "/decide";
    document.getElementById('decideAction').value = decision;
    document.getElementById('decideTitle').textContent = approve ? 'Approve Request' : 'Reject Request';
    document.getElementById('decideSummary').innerHTML =
      '<strong>' + btn.getAttribute('data-who') + '</strong> — ' + btn.getAttribute('data-what');

    // A rejection must carry a reason; an approval note is optional.
    var note = document.getElementById('decideNote');
    note.value = '';
    note.required = !approve;
    document.getElementById('decideNoteLabel').textContent = approve ? 'Remarks (optional)' : 'Reason for rejection';
    document.getElementById('decideHint').textContent = approve
      ? 'The employee will see this on their request.'
      : 'Required. The employee will see this.';

    var submit = document.getElementById('decideSubmit');
    submit.textContent = approve ? 'Approve' : 'Reject';
    submit.className = 'btn rounded-pill fw-bold ' + (approve ? 'btn-hitech' : 'btn-danger');
  });
</script>
@endsection
