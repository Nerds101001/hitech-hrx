@extends('layouts/layoutMaster')

@section('title', 'NOC Detail — ' . optional($leaver)->getFullName())

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="layout-full-width">
<div class="px-4 py-3">

  {{-- ── Breadcrumb ──────────────────────────────────────────────────────── --}}
  <nav class="mb-3">
    <a href="{{ route('exit-clearance.index') }}" class="text-muted small">
      <i class="bx bx-arrow-back me-1"></i>Back to NOC tracker
    </a>
  </nav>

  {{-- ── Employee identity card ─────────────────────────────────────────── --}}
  <div class="hitech-card-white p-4 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">

      <div class="d-flex align-items-center gap-3">
        <span class="avatar avatar-lg">
          @if(optional($leaver)->getProfilePicture())
            <img src="{{ $leaver->getProfilePicture() }}" class="rounded-circle" alt="">
          @else
            <span class="avatar-initial rounded-circle bg-label-danger fs-4">
              {{ optional($leaver)->getInitials() ?? '?' }}
            </span>
          @endif
        </span>
        <div>
          <h4 class="fw-extrabold mb-0">{{ optional($leaver)->getFullName() ?? '—' }}</h4>
          <div class="text-muted small">
            {{ optional($leaver)->employee_code ?? optional($leaver)->code ?? '' }}
            @if(optional($leaver->department)->name)
              &middot; {{ $leaver->department->name }}
            @endif
            @if(optional($leaver)->designation)
              &middot; {{ optional($leaver)->designation }}
            @endif
          </div>
        </div>
      </div>

      {{-- Progress ring / badge --}}
      <div class="text-end">
        <div class="fs-2 fw-extrabold {{ $progress['done'] ? 'text-success' : 'text-warning' }}">
          {{ $progress['cleared'] }}<span class="fs-5 text-muted fw-normal">/{{ $progress['total'] }}</span>
        </div>
        <small class="text-muted">departments cleared</small>
        @if($progress['blocked'])
          <div class="badge bg-label-danger mt-1 d-block">{{ $progress['blocked'] }} blocked</div>
        @endif
      </div>
    </div>

    {{-- Exit metadata --}}
    <hr class="my-3">
    <div class="row g-3 small">
      <div class="col-6 col-md-3">
        <span class="text-muted d-block">Exit Type</span>
        <span class="fw-bold text-capitalize">{{ ucfirst($type) }}</span>
      </div>
      @if($exitRecord)
        <div class="col-6 col-md-3">
          <span class="text-muted d-block">Filed on</span>
          <span class="fw-bold">{{ optional($exitRecord->created_at)->format('d M Y') ?? '—' }}</span>
        </div>
        <div class="col-6 col-md-3">
          <span class="text-muted d-block">Last Working Day</span>
          <span class="fw-bold">
            {{ optional($exitRecord->last_working_day)->format('d M Y') ?? '—' }}
          </span>
        </div>
        <div class="col-6 col-md-3">
          <span class="text-muted d-block">Exit Status</span>
          <span class="badge {{ $exitRecord->status === 'approved' ? 'bg-label-success' : 'bg-label-warning' }}">
            {{ ucfirst($exitRecord->status) }}
          </span>
        </div>
        @if($exitRecord->reason)
          <div class="col-12">
            <span class="text-muted d-block">Reason</span>
            <span>{{ $exitRecord->reason }}</span>
          </div>
        @endif
      @endif
    </div>
  </div>

  {{-- ── Progress bar ────────────────────────────────────────────────────── --}}
  @php
    $pct = $progress['total'] > 0 ? round($progress['cleared'] / $progress['total'] * 100) : 0;
  @endphp
  <div class="mb-4">
    <div class="d-flex justify-content-between small text-muted mb-1">
      <span>Clearance progress</span>
      <span>{{ $pct }}%</span>
    </div>
    <div class="progress" style="height:10px; border-radius:6px;">
      <div class="progress-bar {{ $progress['done'] ? 'bg-success' : 'bg-warning' }}"
           role="progressbar" style="width:{{ $pct }}%"></div>
    </div>
  </div>

  {{-- ── Department clearance steps ──────────────────────────────────────── --}}
  <div class="row g-3 mb-4">
    @foreach($rows as $c)
      @php
        $canSign = $c->isSignableBy($currentUser) && $c->status !== 'cleared';
      @endphp
      <div class="col-12 col-md-6 col-xl-4">
        <div class="hitech-card-white p-4 h-100 border-start border-4
          @if($c->status === 'cleared') border-success
          @elseif($c->status === 'blocked') border-danger
          @else border-warning @endif">

          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <span class="fw-bold fs-6">{{ $c->label }}</span>
              @if($canSign)
                <span class="badge bg-label-primary ms-1 small">Your step</span>
              @endif
            </div>
            <span class="badge
              @if($c->status === 'cleared') bg-label-success
              @elseif($c->status === 'blocked') bg-label-danger
              @else bg-label-warning @endif">
              {{ ucfirst($c->status) }}
            </span>
          </div>

          @if($c->clearedBy)
            <div class="small text-muted mb-1">
              <i class="bx bx-user me-1"></i>{{ $c->clearedBy->getFullName() }}
            </div>
            <div class="small text-muted mb-1">
              <i class="bx bx-calendar me-1"></i>{{ optional($c->cleared_at)->format('d M Y, h:i A') }}
            </div>
          @elseif($c->status === 'pending')
            <div class="small text-muted mb-1">
              <i class="bx bx-time me-1"></i>Awaiting sign-off
            </div>
          @endif

          @if($c->remarks)
            <div class="mt-2 p-2 rounded" style="background:var(--bs-light,#f8f9fa);">
              <small class="fst-italic text-muted">"{{ $c->remarks }}"</small>
            </div>
          @endif

          @if($canSign)
            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-sm btn-success flex-grow-1 ec-action"
                      data-id="{{ $c->id }}" data-status="cleared">
                <i class="bx bx-check me-1"></i>Give NOC
              </button>
              <button class="btn btn-sm btn-outline-danger ec-action"
                      data-id="{{ $c->id }}" data-status="blocked"
                      title="Mark dues/items outstanding">
                <i class="bx bx-block"></i>
              </button>
            </div>
          @elseif($c->status === 'cleared')
            <div class="mt-3">
              <span class="text-success small"><i class="bx bx-check-circle me-1"></i>Clearance given</span>
            </div>
          @elseif(!$canSign && $c->status === 'pending' && $isOverseer)
            <div class="mt-3">
              <button class="btn btn-sm btn-outline-warning w-100 ec-action"
                      data-id="{{ $c->id }}" data-status="cleared">
                <i class="bx bx-shield me-1"></i>Override &amp; Clear (HR)
              </button>
            </div>
          @endif

        </div>
      </div>
    @endforeach
  </div>

  {{-- ── Audit trail ─────────────────────────────────────────────────────── --}}
  <div class="hitech-card-white p-4">
    <h6 class="fw-bold mb-3">
      <i class="bx bx-history me-1 text-muted"></i>Clearance audit trail
    </h6>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead>
          <tr>
            <th>Department</th>
            <th>Status</th>
            <th>Cleared / Blocked by</th>
            <th>Date &amp; Time</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $c)
            <tr>
              <td class="fw-bold">{{ $c->label }}</td>
              <td>
                <span class="badge
                  @if($c->status === 'cleared') bg-label-success
                  @elseif($c->status === 'blocked') bg-label-danger
                  @else bg-label-warning @endif">
                  {{ ucfirst($c->status) }}
                </span>
              </td>
              <td>{{ optional($c->clearedBy)->getFullName() ?? '—' }}</td>
              <td>{{ optional($c->cleared_at)->format('d M Y, h:i A') ?? '—' }}</td>
              <td><small class="text-muted">{{ $c->remarks ?: '—' }}</small></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>

{{-- ── NOC action modal (same as tracker page) ────────────────────────── --}}
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
        <div id="nocBlockedAlert" style="display:none" class="mb-3">
          <div class="alert alert-warning mb-0">
            <i class="bx bx-info-circle me-1"></i>
            <strong>Blocked</strong> means this employee has outstanding dues or items
            with your department. The exit stays open until this is resolved.
          </div>
        </div>
        <label class="form-label">Remarks <small class="text-muted">(optional)</small></label>
        <textarea id="nocRemarks" class="form-control" rows="3"
                  placeholder="Any dues, items pending, or notes…"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn" id="nocConfirmBtn">Confirm</button>
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
      if (d.success) { window.location.reload(); }
      else { alert(d.message || 'Could not save.'); confirmBtn.disabled = false; }
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
