@extends('layouts/layoutMaster')

@section('title', 'NOC Detail — ' . optional($leaver)->getFullName())

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">

    {{-- Hero banner --}}
    <x-hero-banner
      title="NOC Clearance"
      :subtitle="'Exit clearance status for ' . (optional($leaver)->getFullName() ?? 'this employee')"
      icon="bx bx-user-x"
      :show-stats="false"
    />

    {{-- Back link --}}
    <div class="mb-4">
      <a href="{{ route('exit-clearance.index') }}" class="btn btn-label-secondary btn-sm rounded-pill">
        <i class="bx bx-arrow-back me-1"></i>Back to NOC Tracker
      </a>
    </div>

    {{-- Employee identity card --}}
    <div class="card hitech-card-white border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-xl">
              @if(optional($leaver)->getProfilePicture())
                <img src="{{ $leaver->getProfilePicture() }}" class="rounded-circle" alt="">
              @else
                <span class="avatar-initial rounded-circle bg-label-danger fs-3">
                  {{ optional($leaver)->getInitials() ?? '?' }}
                </span>
              @endif
            </div>
            <div>
              <h4 class="fw-extrabold mb-1">{{ optional($leaver)->getFullName() ?? '—' }}</h4>
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
          <div class="text-end">
            <div class="fs-1 fw-extrabold {{ $progress['done'] ? 'text-success' : ($progress['blocked'] ? 'text-danger' : 'text-warning') }}" style="line-height:1">
              {{ $progress['cleared'] }}<span class="fs-4 text-muted fw-normal">/{{ $progress['total'] }}</span>
            </div>
            <small class="text-muted">departments cleared</small>
            @if($progress['blocked'])
              <div class="badge bg-label-danger mt-1 d-block">{{ $progress['blocked'] }} blocked</div>
            @elseif($progress['done'])
              <div class="badge bg-label-success mt-1 d-block">All cleared</div>
            @endif
          </div>
        </div>

        <hr class="my-3">

        {{-- Exit metadata --}}
        <div class="row g-3">
          <div class="col-6 col-md-3">
            <div class="text-muted small mb-1">Exit Type</div>
            <div class="fw-bold text-capitalize">{{ ucfirst($type) }}</div>
          </div>
          @if($exitRecord)
            <div class="col-6 col-md-3">
              <div class="text-muted small mb-1">Filed on</div>
              <div class="fw-bold">{{ optional($exitRecord->created_at)->format('d M Y') ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-muted small mb-1">Last Working Day</div>
              <div class="fw-bold">{{ optional($exitRecord->last_working_day)->format('d M Y') ?? optional($exitRecord->termination_date ?? $exitRecord->relieving_date ?? null)->format('d M Y') ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-muted small mb-1">Exit Status</div>
              <span class="badge {{ $exitRecord->status === 'approved' || $exitRecord->status === 'accepted' || $exitRecord->status === 'completed' ? 'bg-label-success' : 'bg-label-warning' }}">
                {{ ucfirst($exitRecord->status) }}
              </span>
            </div>
            @if($exitRecord->reason)
              <div class="col-12">
                <div class="text-muted small mb-1">Reason</div>
                <div>{{ $exitRecord->reason }}</div>
              </div>
            @endif
          @endif
        </div>
      </div>
    </div>

    {{-- Progress bar --}}
    @php $pct = $progress['total'] > 0 ? round($progress['cleared'] / $progress['total'] * 100) : 0; @endphp
    <div class="card hitech-card-white border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-semibold">Clearance Progress</span>
          <span class="fw-bold {{ $progress['done'] ? 'text-success' : ($progress['blocked'] ? 'text-danger' : 'text-warning') }}">{{ $pct }}%</span>
        </div>
        <div class="progress rounded-pill" style="height:12px;">
          <div class="progress-bar rounded-pill {{ $progress['done'] ? 'bg-success' : ($progress['blocked'] ? 'bg-danger' : 'bg-warning') }}"
               role="progressbar" style="width:{{ $pct }}%"></div>
        </div>
      </div>
    </div>

    {{-- Department clearance step cards --}}
    <div class="row g-3 mb-4">
      @foreach($rows as $c)
        @php $canSign = $c->isSignableBy($currentUser) && $c->status !== 'cleared'; @endphp
        <div class="col-12 col-md-6 col-xl-4">
          <div class="card border-0 shadow-sm rounded-4 h-100"
               style="border-left: 4px solid {{ $c->status === 'cleared' ? '#28a745' : ($c->status === 'blocked' ? '#dc3545' : '#ffc107') }} !important; border-left-style: solid !important;">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <h6 class="fw-bold mb-0">{{ $c->label }}</h6>
                  @if($canSign)
                    <span class="badge bg-label-primary mt-1" style="font-size:.7rem;">Your step</span>
                  @endif
                </div>
                <span class="badge rounded-pill
                  {{ $c->status === 'cleared' ? 'bg-label-success' : ($c->status === 'blocked' ? 'bg-label-danger' : 'bg-label-warning') }}">
                  {{ ucfirst($c->status) }}
                </span>
              </div>

              @if($c->clearedBy)
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bx bx-user text-muted"></i>
                  <small class="text-muted">{{ $c->clearedBy->getFullName() }}</small>
                </div>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bx bx-calendar text-muted"></i>
                  <small class="text-muted">{{ optional($c->cleared_at)->format('d M Y, h:i A') }}</small>
                </div>
              @elseif($c->status === 'pending')
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bx bx-time text-muted"></i>
                  <small class="text-muted">Awaiting sign-off</small>
                </div>
              @endif

              @if($c->remarks)
                <div class="p-2 rounded mb-2" style="background:#f8fafc;">
                  <small class="fst-italic text-muted">"{{ $c->remarks }}"</small>
                </div>
              @endif

              @if($canSign)
                <div class="d-flex gap-2 mt-3">
                  <button class="btn btn-success flex-grow-1 btn-sm ec-action"
                          data-id="{{ $c->id }}" data-status="cleared">
                    <i class="bx bx-check me-1"></i>Give NOC
                  </button>
                  <button class="btn btn-outline-danger btn-sm ec-action"
                          data-id="{{ $c->id }}" data-status="blocked"
                          title="Mark dues/items outstanding">
                    <i class="bx bx-block"></i>
                  </button>
                </div>
              @elseif($c->status === 'cleared')
                <div class="mt-auto pt-2">
                  <span class="text-success small"><i class="bx bx-check-circle me-1"></i>Clearance given</span>
                </div>
              @elseif(!$canSign && $c->status === 'pending' && $isOverseer)
                <div class="mt-3">
                  <button class="btn btn-outline-warning btn-sm w-100 ec-action"
                          data-id="{{ $c->id }}" data-status="cleared">
                    <i class="bx bx-shield me-1"></i>Override &amp; Clear (HR)
                  </button>
                </div>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Audit trail --}}
    <div class="card hitech-card-white border-0 shadow-sm rounded-4">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">
          <i class="bx bx-history me-2 text-muted"></i>Clearance Audit Trail
        </h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
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
                  <td class="fw-semibold">{{ $c->label }}</td>
                  <td>
                    <span class="badge rounded-pill
                      {{ $c->status === 'cleared' ? 'bg-label-success' : ($c->status === 'blocked' ? 'bg-label-danger' : 'bg-label-warning') }}">
                      {{ ucfirst($c->status) }}
                    </span>
                  </td>
                  <td>{{ optional($c->clearedBy)->getFullName() ?? '—' }}</td>
                  <td class="text-nowrap"><small>{{ optional($c->cleared_at)->format('d M Y, h:i A') ?? '—' }}</small></td>
                  <td><small class="text-muted">{{ $c->remarks ?: '—' }}</small></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

{{-- NOC action modal --}}
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
        <button class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn" id="nocConfirmBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>

@section('page-script')
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
    .catch(() => { modal.hide(); alert('Network error.'); confirmBtn.disabled = false; });
  });
});
</script>
@endsection
@endsection
