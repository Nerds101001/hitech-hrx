@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — Removal Requests')

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4 mx-4">
    <div>
      <nav class="small text-muted mb-1"><a href="{{ route('tenant.dashboard') }}" class="text-muted">Dashboard</a> <i class="bx bx-chevron-right"></i> 90-Day Goals <i class="bx bx-chevron-right"></i> Removal Requests</nav>
      <h3 class="fw-extrabold mb-1 text-dark">Removal Requests</h3>
      <p class="text-muted mb-0 small">Every ask from the sales team to remove a customer from their 90-Day program — approve to actually remove it, or reject to keep it.</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs', ['navTab' => 'delete-requests'])

  <div class="px-4">
    <div class="d-flex gap-2 flex-wrap mb-4">
      @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
        <a href="{{ route('performance.ninety-day-goals.delete-requests.index', ['tab' => $key]) }}"
           class="ndg-tab {{ $tab === $key ? 'on' : '' }}">{{ $label }} <span class="ms-1 opacity-75">({{ $counts[$key] ?? 0 }})</span></a>
      @endforeach
    </div>

    <div class="hitech-card mb-6">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0" style="font-size:.85rem">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Salesperson</th>
                <th>Reason</th>
                <th>Requested</th>
                @if($tab !== 'pending')<th>Decided by</th><th>Remark</th>@endif
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($requests as $r)
                @php $g = $r->goal; $owner = optional(optional($g)->round)->user; @endphp
                <tr>
                  <td class="fw-semibold text-dark">{{ $g->client_name ?? '(customer removed)' }}</td>
                  <td>
                    @if($owner)
                      <a href="{{ route('performance.ninety-day-goals.round', $g->round_id) }}">{{ trim($owner->first_name . ' ' . $owner->last_name) }}</a>
                    @else — @endif
                  </td>
                  <td class="small text-muted">{{ $r->reason ?: '—' }}</td>
                  <td class="small text-nowrap">{{ trim(optional($r->requestedBy)->first_name . ' ' . optional($r->requestedBy)->last_name) }}<div class="text-muted">{{ $r->created_at->format('d M Y, H:i') }}</div></td>
                  @if($tab !== 'pending')
                    <td class="small text-nowrap">{{ trim(optional($r->decidedBy)->first_name . ' ' . optional($r->decidedBy)->last_name) }}<div class="text-muted">{{ optional($r->decided_at)->format('d M Y, H:i') }}</div></td>
                    <td class="small text-muted">{{ $r->admin_remarks ?: '—' }}</td>
                  @endif
                  <td class="text-end text-nowrap">
                    @if($tab === 'pending' && $g)
                      <button type="button" class="btn btn-sm btn-hitech rounded-pill px-3 ndg-dr-decide" data-id="{{ $r->id }}" data-status="approved" data-title="{{ e($g->client_name) }}">Approve</button>
                      <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 ndg-dr-decide" data-id="{{ $r->id }}" data-status="rejected" data-title="{{ e($g->client_name) }}">Reject</button>
                    @elseif($tab === 'pending')
                      <span class="small text-muted">Customer already gone</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted p-4">No {{ $tab }} removal requests.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      @if($requests->hasPages())
        <div class="card-body border-top">{{ $requests->links() }}</div>
      @endif
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.querySelectorAll('.ndg-dr-decide').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var status = btn.dataset.status;
    var verb = status === 'approved' ? 'approve (this removes the customer)' : 'reject';
    if (!confirm('Really ' + verb + ' the removal request for "' + (btn.dataset.title || 'this customer') + '"?')) { return; }
    var remarks = window.prompt('Remark for the requester (optional):') || '';
    btn.disabled = true;
    fetch('{{ url('performance/ninety-day-goals/delete-request') }}/' + btn.dataset.id, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
      body: JSON.stringify({ status: status, admin_remarks: remarks.trim() }),
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.success) { btn.disabled = false; alert(j.message || 'Could not save that.'); return; }
        location.reload();
      })
      .catch(function () { btn.disabled = false; alert('Request failed. Try again.'); });
  });
});
</script>
@endsection
