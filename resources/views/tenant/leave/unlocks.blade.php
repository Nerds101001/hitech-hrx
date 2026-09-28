@extends('layouts.layoutMaster')

@section('title', 'Leave Unlock Windows')

@section('content')
<div class="container-fluid py-3">

    <div class="rounded-3 p-4 mb-3" style="background:linear-gradient(90deg,#0d9488,#0891b2);">
        <h4 class="text-white fw-bold mb-1">Leave Unlock Windows</h4>
        <p class="text-white-50 mb-0 small">
            Normally leave can be applied at most {{ \App\Support\LeaveBackdate::MAX_DAYS }} days back. Open a window here to let
            people apply for leave on older dates - for everyone or only the people you pick.
        </p>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2"><ul class="mb-0 ps-3">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul></div>
    @endif

    {{-- ── new window ──────────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold py-3">Open a new window</div>
        <div class="card-body">
            <form method="POST" action="{{ route('leave-unlocks.store') }}" id="unlockForm">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">From date</label>
                        <input type="date" name="from_date" id="uFrom" class="form-select"
                               value="{{ old('from_date', now()->subDays(14)->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">To date</label>
                        <input type="date" name="to_date" id="uTo" class="form-select"
                               value="{{ old('to_date', now()->subDays(8)->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Applies to</label>
                        <select name="scope" id="uScope" class="form-select" onchange="toggleScope()">
                            <option value="all" @selected(old('scope') === 'all')>Everyone</option>
                            <option value="selected" @selected(old('scope') === 'selected')>Only selected people</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div id="uDays" class="small text-muted"></div>
                    </div>

                    <div class="col-12 d-none" id="uPeopleWrap">
                        <label class="form-label small fw-semibold">People who may apply for leave on these dates</label>
                        <select name="user_ids[]" id="uPeople" class="form-select" multiple size="6">
                            @foreach($people as $p)
                                <option value="{{ $p->id }}">
                                    {{ trim($p->first_name . ' ' . $p->last_name) }}
                                    @if($p->code) ({{ $p->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Hold Ctrl (or Cmd) to pick more than one.</div>
                    </div>

                    <div class="col-md-9">
                        <label class="form-label small fw-semibold">Reason (optional)</label>
                        <input type="text" name="reason" class="form-control" maxlength="255"
                               placeholder="e.g. was in hospital, could not apply in time" value="{{ old('reason') }}">
                    </div>
                    <div class="col-md-3 text-end">
                        <button class="btn text-white fw-semibold w-100" style="background:#0f766e;">
                            <i class="bx bx-lock-open-alt"></i> Open window
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ── existing windows ────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold py-3">Existing windows</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Applies to</th>
                        <th>Reason</th>
                        <th>Opened by</th>
                        <th>State</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($windows as $w)
                        <tr class="{{ $w->is_active ? '' : 'text-muted' }}">
                            <td class="fw-semibold">
                                {{ $w->from_date->format('d M Y') }} &rarr; {{ $w->to_date->format('d M Y') }}
                            </td>
                            <td>{{ $w->day_count }}</td>
                            <td>
                                @if($w->scope === 'all')
                                    <span class="badge badge-brand">Everyone</span>
                                @else
                                    @php
                                        $names = collect($w->user_ids ?? [])->map(function ($id) use ($peopleById) {
                                            $p = $peopleById->get($id);
                                            return $p ? trim($p->first_name . ' ' . $p->last_name) : "User #{$id}";
                                        });
                                    @endphp
                                    <span class="badge bg-label-info" style="cursor:pointer;"
                                          role="button" tabindex="0"
                                          onclick="showSelectedPeople(this)"
                                          data-names="{{ $names->implode('|') }}"
                                          title="Click to see who">
                                        {{ count($w->user_ids ?? []) }} selected
                                    </span>
                                @endif
                            </td>
                            <td class="small">{{ $w->reason ?: '—' }}</td>
                            <td class="small">{{ optional($w->creator)->name ?? '—' }}</td>
                            <td>
                                @if($w->phase === 'disabled')
                                    <span class="badge bg-label-secondary">Disabled</span>
                                @elseif($w->phase === 'scheduled')
                                    <span class="badge bg-label-warning">Scheduled</span>
                                @else
                                    <span class="badge bg-label-success">Unlocking</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('leave-unlocks.toggle', $w->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">
                                        {{ $w->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('leave-unlocks.destroy', $w->id) }}" class="d-inline"
                                      onsubmit="return confirm('Remove this window? People will go back to the normal one-week limit.');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">
                            No windows yet — the normal one-week limit applies to everyone.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── who's selected, on demand ───────────────────────────────────── --}}
    <div class="modal fade" id="selectedPeopleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Applies to</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul id="selectedPeopleList" class="mb-0 ps-3"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const MAX_DAYS = {{ \App\Models\LeaveUnlock::MAX_DAYS }};

function showSelectedPeople(el) {
    const names = (el.dataset.names || '').split('|').filter(Boolean);
    const list = document.getElementById('selectedPeopleList');
    list.innerHTML = names.length
        ? names.map(n => '<li>' + n.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</li>').join('')
        : '<li class="text-muted">No one — the window has no people left in it.</li>';
    new bootstrap.Modal(document.getElementById('selectedPeopleModal')).show();
}

function toggleScope() {
    const sel = document.getElementById('uScope').value === 'selected';
    document.getElementById('uPeopleWrap').classList.toggle('d-none', !sel);
}

/* Show the length live and block submit over the cap, so the user finds out
   before a round trip rather than after. */
function updateDays() {
    const f = document.getElementById('uFrom').value, t = document.getElementById('uTo').value;
    const out = document.getElementById('uDays');
    if (!f || !t) { out.textContent = ''; return; }
    const days = Math.round((new Date(t) - new Date(f)) / 86400000) + 1;
    if (days < 1) {
        out.innerHTML = '<span class="text-danger fw-semibold">To date is before From date.</span>';
    } else if (days > MAX_DAYS) {
        out.innerHTML = '<span class="text-danger fw-semibold">' + days + ' days — max is ' + MAX_DAYS + '.</span>';
    } else {
        out.innerHTML = '<span class="fw-semibold" style="color:#0f766e;">' + days + ' day(s) will be unlocked.</span>';
    }
}

document.getElementById('uFrom').addEventListener('change', updateDays);
document.getElementById('uTo').addEventListener('change', updateDays);
document.getElementById('unlockForm').addEventListener('submit', function (e) {
    const f = document.getElementById('uFrom').value, t = document.getElementById('uTo').value;
    const days = Math.round((new Date(t) - new Date(f)) / 86400000) + 1;
    if (days < 1 || days > MAX_DAYS) {
        e.preventDefault();
        alert('Pick a range between 1 and ' + MAX_DAYS + ' days.');
        return;
    }
    if (document.getElementById('uScope').value === 'selected'
        && document.getElementById('uPeople').selectedOptions.length === 0) {
        e.preventDefault();
        alert('Pick at least one person, or choose "Everyone".');
    }
});

toggleScope();
updateDays();
</script>
@endsection
