@extends('layouts/layoutMaster')

@section('title', $isOverseer ? 'Exit Clearance' : 'My NOC Clearances')

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

  {{-- ── Page header ─────────────────────────────────────────────────────── --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4 py-2">
    <div>
      @if($isOverseer)
        <h3 class="fw-extrabold mb-0 text-dark">Exit Clearance (NOC)</h3>
        <p class="text-muted mb-0 small">
          Access is revoked as soon as an exit is logged. The exit closes once every
          department has signed off.
        </p>
      @else
        <h3 class="fw-extrabold mb-0 text-dark">My NOC Clearances</h3>
        <p class="text-muted mb-0 small">
          Exits where your department's sign-off is required. You only see the steps
          assigned to you — not other departments' rows.
        </p>
      @endif
    </div>
    <span class="badge {{ $pendingCount ? 'bg-label-warning' : 'bg-label-success' }} fs-6 px-3 py-2">
      {{ $pendingCount ? $pendingCount . ' clearance(s) pending' : 'All clear' }}
    </span>
  </div>

  <div class="px-4">

    {{-- ── Overseer: status filter ──────────────────────────────────────── --}}
    @if($isOverseer)
      <div class="mb-3 d-flex gap-2">
        <a href="{{ url()->current() }}"
           class="btn btn-sm {{ !request('status') ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
        <a href="{{ url()->current() }}?status=pending"
           class="btn btn-sm {{ request('status') === 'pending' ? 'btn-warning' : 'btn-outline-warning' }}">Pending</a>
        <a href="{{ url()->current() }}?status=blocked"
           class="btn btn-sm {{ request('status') === 'blocked' ? 'btn-danger' : 'btn-outline-danger' }}">Blocked</a>
        <a href="{{ url()->current() }}?status=cleared"
           class="btn btn-sm {{ request('status') === 'cleared' ? 'btn-success' : 'btn-outline-success' }}">Cleared</a>
      </div>
    @endif

    {{-- ── Pending / active clearances ─────────────────────────────────── --}}
    @forelse($groups as $key => $rows)
      @php
        $first   = $rows->first();
        // For overseers use their own grouped rows for the progress badge;
        // for signatories use the full progressMap so they see the whole exit.
        if ($isOverseer) {
            $cleared = $rows->where('status','cleared')->count();
            $total   = $rows->count();
            $done    = ($cleared === $total);
        } else {
            $prog    = $progressMap[$key] ?? [];
            $cleared = $prog['cleared'] ?? 0;
            $total   = $prog['total'] ?? 0;
            $done    = $prog['done'] ?? false;
        }
      @endphp

      <div class="hitech-card-white p-4 mb-3">
        {{-- Employee header --}}
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
            {{ $cleared }} of {{ $total }} dept{{ $total != 1 ? 's' : '' }} cleared
          </span>
        </div>

        {{-- Clearance step cards --}}
        <div class="row g-2">
          @foreach($rows as $c)
            <div class="{{ $isOverseer ? 'col-md-3 col-sm-6' : 'col-12 col-md-6' }}">
              <div class="border rounded-3 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="fw-bold small">{{ $c->label }}</span>
                  <span class="badge
                    @if($c->status === 'cleared') bg-label-success
                    @elseif($c->status === 'blocked') bg-label-danger
                    @else bg-label-warning @endif">{{ ucfirst($c->status) }}</span>
                </div>
                @if($c->remarks)
                  <small class="text-muted d-block fst-italic mb-1">"{{ $c->remarks }}"</small>
                @endif
                @if($c->clearedBy)
                  <small class="text-muted d-block">
                    {{ $c->clearedBy->getFullName() }},
                    {{ optional($c->cleared_at)->format('d M Y') }}
                  </small>
                @endif
                @if($c->status !== 'cleared')
                  <div class="d-flex gap-1 mt-2">
                    <button class="btn btn-sm btn-success flex-grow-1 ec-action"
                            data-id="{{ $c->id }}" data-status="cleared">
                      <i class="bx bx-check me-1"></i>Give NOC
                    </button>
                    <button class="btn btn-sm btn-outline-danger ec-action"
                            data-id="{{ $c->id }}" data-status="blocked"
                            title="Mark as blocked (dues pending)">
                      <i class="bx bx-block"></i>
                    </button>
                  </div>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @empty
      @if(!$isOverseer && $myHistory->isEmpty())
        <div class="hitech-card-white p-5 text-center text-muted">
          <i class="bx bx-check-circle fs-1 d-block mb-2 text-success"></i>
          No clearances assigned to you.
        </div>
      @elseif($isOverseer)
        <div class="hitech-card-white p-5 text-center text-muted">No exits awaiting clearance.</div>
      @endif
    @endforelse

    {{-- ── NOC history (dept signatories only) ────────────────────────── --}}
    @if(!$isOverseer && $myHistory->isNotEmpty())
      <h5 class="fw-bold mb-3 mt-4 text-muted">
        <i class="bx bx-history me-1"></i>NOC given by me ({{ $myHistory->count() }})
      </h5>
      @foreach($myHistory as $c)
        <div class="hitech-card-white p-3 mb-2 border-start border-4 border-success">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
              <span class="avatar avatar-sm">
                <span class="avatar-initial rounded-circle bg-label-success">
                  {{ optional($c->user)->getInitials() ?? '?' }}
                </span>
              </span>
              <div>
                <span class="fw-bold d-block">{{ optional($c->user)->getFullName() ?? '—' }}</span>
                <small class="text-muted">
                  {{ ucfirst($c->exit_type) }} · {{ $c->label }} NOC
                  &middot; Cleared {{ optional($c->cleared_at)->format('d M Y') }}
                </small>
              </div>
            </div>
            <span class="badge bg-label-success">Cleared</span>
          </div>
          @if($c->remarks)
            <small class="text-muted fst-italic mt-1 d-block">"{{ $c->remarks }}"</small>
          @endif
        </div>
      @endforeach
    @endif

  </div>{{-- /px-4 --}}
</div>

{{-- ── NOC action modal ────────────────────────────────────────────────── --}}
<div class="modal fade" id="nocModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title" id="nocModalTitle">Give NOC</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="nocClearanceId">
        <input type="hidden" id="nocStatusValue">
        <div class="mb-3" id="nocBlockedAlert" style="display:none">
          <div class="alert alert-warning mb-0">
            <i class="bx bx-info-circle me-1"></i>
            Marking as <strong>blocked</strong> means this employee has outstanding
            dues or items with your department. The exit will stay open until this is
            resolved.
          </div>
        </div>
        <label class="form-label">Remarks <small class="text-muted">(optional)</small></label>
        <textarea id="nocRemarks" class="form-control" rows="3"
                  placeholder="Any dues, items, or notes…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn" id="nocConfirmBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var modal      = new bootstrap.Modal(document.getElementById('nocModal'));
  var titleEl    = document.getElementById('nocModalTitle');
  var alertEl    = document.getElementById('nocBlockedAlert');
  var remarksEl  = document.getElementById('nocRemarks');
  var cidEl      = document.getElementById('nocClearanceId');
  var statusEl   = document.getElementById('nocStatusValue');
  var confirmBtn = document.getElementById('nocConfirmBtn');

  document.querySelectorAll('.ec-action').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var status = btn.dataset.status;
      cidEl.value    = btn.dataset.id;
      statusEl.value = status;
      remarksEl.value = '';

      if (status === 'cleared') {
        titleEl.textContent = 'Give NOC — Confirm Clearance';
        alertEl.style.display = 'none';
        confirmBtn.className = 'btn btn-success';
        confirmBtn.textContent = 'Give NOC';
      } else {
        titleEl.textContent = 'Mark as Blocked';
        alertEl.style.display = '';
        confirmBtn.className = 'btn btn-danger';
        confirmBtn.textContent = 'Mark Blocked';
      }
      modal.show();
    });
  });

  confirmBtn.addEventListener('click', function () {
    var id      = cidEl.value;
    var status  = statusEl.value;
    var remarks = remarksEl.value.trim();

    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Saving…';

    fetch("{{ url('/exit-clearance') }}/" + id, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({ status: status, remarks: remarks })
    })
    .then(r => r.json())
    .then(d => {
      modal.hide();
      if (d.success) {
        window.location.reload();
      } else {
        alert(d.message || 'Could not save.');
        confirmBtn.disabled = false;
      }
    })
    .catch(() => {
      modal.hide();
      alert('Network error.');
      confirmBtn.disabled = false;
    });
  });
});
</script>
@endsection
