@extends('layouts/layoutMaster')

@section('title', 'Exit Clearance')

{{-- hitech-portal.scss defines hitech-card-white and the rest of the portal
     styling. Without it this page renders as unstyled text. --}}
@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="layout-full-width">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4 py-2">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Exit Clearance (NOC)</h3>
      <p class="text-muted mb-0 small">
        Access is revoked as soon as an exit is logged. The record stays pending until
        IT, HR, Finance and Accounts have each signed off.
      </p>
    </div>
    <span class="badge {{ $pendingCount ? 'bg-label-warning' : 'bg-label-success' }} fs-6 px-3 py-2">
      {{ $pendingCount ? $pendingCount . ' clearance(s) pending' : 'All clear' }}
    </span>
  </div>

  <div class="px-4">
    @forelse($groups as $key => $rows)
      @php
        $first = $rows->first();
        $done  = $rows->every(fn($r) => $r->status === 'cleared');
        $cleared = $rows->where('status','cleared')->count();
      @endphp
      <div class="hitech-card-white p-4 mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div class="d-flex align-items-center gap-2">
            <span class="avatar avatar-sm">
              @if(optional($first->user)->getProfilePicture())
                <img src="{{ $first->user->getProfilePicture() }}" class="rounded-circle" alt="">
              @else
                <span class="avatar-initial rounded-circle bg-label-danger">
                  {{ optional($first->user)->getInitials() ?? '?' }}
                </span>
              @endif
            </span>
            <div>
              <span class="fw-bold d-block">{{ optional($first->user)->getFullName() ?? '—' }}</span>
              <small class="text-muted">
                {{ ucfirst($first->exit_type) }}
                &middot; {{ optional(optional($first->user)->department)->name ?? '—' }}
              </small>
            </div>
          </div>
          <span class="badge {{ $done ? 'bg-label-success' : 'bg-label-warning' }}">
            {{ $cleared }} of {{ $rows->count() }} cleared
          </span>
        </div>

        <div class="row g-2">
          @foreach($rows as $c)
            <div class="col-md-3 col-sm-6">
              <div class="border rounded-3 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="fw-bold small">{{ $c->label }}</span>
                  <span class="badge
                    @if($c->status === 'cleared') bg-label-success
                    @elseif($c->status === 'blocked') bg-label-danger
                    @else bg-label-warning @endif">{{ ucfirst($c->status) }}</span>
                </div>
                @if($c->remarks)
                  <small class="text-muted d-block fst-italic">"{{ $c->remarks }}"</small>
                @endif
                @if($c->clearedBy)
                  <small class="text-muted d-block">
                    {{ $c->clearedBy->getFullName() }},
                    {{ optional($c->cleared_at)->format('d M Y') }}
                  </small>
                @endif
                @if($c->status !== 'cleared')
                  <button class="btn btn-sm btn-primary mt-2 w-100 ec-sign" data-id="{{ $c->id }}">
                    Give NOC
                  </button>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @empty
      <div class="hitech-card-white p-5 text-center text-muted">No exits awaiting clearance.</div>
    @endforelse
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.ec-sign').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var remarks = prompt('Remarks for this clearance (optional):') || '';
      btn.disabled = true;
      btn.textContent = 'Saving…';
      fetch("{{ url('/exit-clearance') }}/" + btn.dataset.id, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json', 'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ status: 'cleared', remarks: remarks })
      })
      .then(r => r.json())
      .then(d => {
        if (d.success) { window.location.reload(); }
        else { alert(d.message || 'Could not save.'); btn.disabled = false; btn.textContent = 'Give NOC'; }
      })
      .catch(() => { alert('Network error.'); btn.disabled = false; btn.textContent = 'Give NOC'; });
    });
  });
});
</script>
@endsection
