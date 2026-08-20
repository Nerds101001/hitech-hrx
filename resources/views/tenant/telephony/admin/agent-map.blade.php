@extends('layouts/layoutMaster')

@section('title', 'Telephony agent mapping')

@section('content')
<div class="layout-full-width">

  @include('tenant.telephony._partials._header', [
    'title' => 'Agent Mapping',
    'subtitle' => 'Which HRX employee is which dialer agent. Every integration of this kind goes wrong here first.',
    'crumb' => 'Agent Mapping',
  ])

  <div class="px-4">

    @if($unmappedCalls > 0)
      <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>
          <i class="bx bx-error-circle me-1"></i>
          <strong>{{ number_format($unmappedCalls) }}</strong> call(s) arrived that could not be matched to an employee.
        </span>
        <a href="{{ route('user.telephony.admin.exceptions') }}" class="btn btn-sm btn-hitech-primary">Review them</a>
      </div>
    @endif

    <div class="row g-4">

      {{-- Add form --}}
      <div class="col-12 col-xl-4">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4">
          <h6 class="fw-bold mb-1">Add a mapping</h6>
          <p class="text-muted small mb-3">
            Fill in whatever the dialer knows. A handset dial often reports only an extension,
            so the more identifiers here, the more calls resolve automatically.
          </p>

          <form method="POST" action="{{ route('user.telephony.admin.agent-map.store') }}">
            @csrf

            <div class="mb-3">
              <label class="form-label small fw-semibold mb-1">Employee <span class="text-danger">*</span></label>
              <select name="user_id" class="form-select form-select-sm" required>
                <option value="">Choose…</option>
                @foreach($users as $u)
                  <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>
                    {{ trim($u->first_name.' '.$u->last_name) }}@if($u->code) ({{ $u->code }})@endif
                  </option>
                @endforeach
              </select>
              @if($users->isEmpty())
                <div class="form-text">Every employee already has a mapping.</div>
              @endif
            </div>

            @php
              $fields = [
                ['agent_id', 'Dialer agent id', 'e.g. AG-1042'],
                ['agent_code', 'Agent code', 'e.g. mehak'],
                ['extension', 'Extension', 'e.g. 1042'],
                ['agent_number', 'Agent phone / SIM', 'e.g. +919812345678'],
                ['default_campaign_id', 'Default campaign id', 'optional'],
              ];
            @endphp
            @foreach($fields as [$name, $label, $placeholder])
              <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">{{ $label }}</label>
                <input type="text" name="{{ $name }}" value="{{ old($name) }}"
                       class="form-control form-control-sm" placeholder="{{ $placeholder }}">
              </div>
            @endforeach

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" checked>
              <label class="form-check-label small" for="isActive">Active</label>
            </div>

            <button type="submit" class="btn btn-hitech-primary btn-sm w-100">Save mapping</button>
            <p class="text-muted mt-2 mb-0" style="font-size:.72rem;">
              Saving also re-matches any calls that already arrived unassigned with these identifiers.
            </p>
          </form>
        </div>
      </div>

      {{-- Table --}}
      <div class="col-12 col-xl-8">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 mb-3">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-9">
              <label class="form-label small fw-semibold mb-1">Search agent id, code, extension or number</label>
              <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-3 d-flex gap-2">
              <button type="submit" class="btn btn-hitech-primary btn-sm flex-grow-1">Search</button>
              <a href="{{ route('user.telephony.admin.agent-map') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
          </form>
        </div>

        <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Agent id</th>
                  <th>Code</th>
                  <th>Extension</th>
                  <th>Phone / SIM</th>
                  <th>Last seen</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @forelse($rows as $row)
                  <tr>
                    <td>
                      {{ $row->user ? trim($row->user->first_name.' '.$row->user->last_name) : 'Deleted user' }}
                      @if($row->user?->code)
                        <div class="text-muted" style="font-size:.72rem;">{{ $row->user->code }}</div>
                      @endif
                    </td>
                    <td class="small">{{ $row->agent_id ?: '—' }}</td>
                    <td class="small">{{ $row->agent_code ?: '—' }}</td>
                    <td class="small">{{ $row->extension ?: '—' }}</td>
                    <td class="small">{{ $row->agent_number ?: '—' }}</td>
                    <td class="small text-muted">
                      {{ $row->last_seen_at ? $row->last_seen_at->diffForHumans() : 'Never' }}
                    </td>
                    <td>
                      @if($row->is_active)
                        <span class="badge bg-label-success rounded-pill">Active</span>
                      @else
                        <span class="badge bg-label-secondary rounded-pill">Inactive</span>
                      @endif
                    </td>
                    <td class="text-end text-nowrap">
                      <button class="btn btn-sm btn-outline-secondary rounded-pill"
                              data-bs-toggle="collapse" data-bs-target="#edit{{ $row->id }}">Edit</button>
                      <form method="POST" action="{{ route('user.telephony.admin.agent-map.destroy', $row->id) }}"
                            class="d-inline"
                            onsubmit="return confirm('Remove this mapping? Existing call history keeps its employee.');">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">Remove</button>
                      </form>
                    </td>
                  </tr>
                  <tr class="collapse" id="edit{{ $row->id }}">
                    <td colspan="8" style="background:#f8fafc;">
                      <form method="POST" action="{{ route('user.telephony.admin.agent-map.update', $row->id) }}"
                            class="row g-2 align-items-end">
                        @csrf @method('PUT')
                        <input type="hidden" name="user_id" value="{{ $row->user_id }}">
                        @foreach([['agent_id','Agent id'],['agent_code','Code'],['extension','Extension'],['agent_number','Phone / SIM']] as [$n, $l])
                          <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold mb-1">{{ $l }}</label>
                            <input type="text" name="{{ $n }}" value="{{ $row->{$n} }}" class="form-control form-control-sm">
                          </div>
                        @endforeach
                        <div class="col-6 col-md-2">
                          <label class="form-label small fw-semibold mb-1">Active</label>
                          <select name="is_active" class="form-select form-select-sm">
                            <option value="1" @selected($row->is_active)>Yes</option>
                            <option value="0" @selected(!$row->is_active)>No</option>
                          </select>
                        </div>
                        <div class="col-6 col-md-2">
                          <button class="btn btn-hitech-primary btn-sm w-100" type="submit">Update</button>
                        </div>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="text-center py-5">
                      <i class="bx bx-id-card text-muted" style="font-size:2.4rem;"></i>
                      <p class="text-muted mb-1 mt-2">No mappings yet.</p>
                      <p class="text-muted small mb-0">
                        Until at least one exists, every call will arrive unassigned.
                      </p>
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @if($rows->hasPages())
            <div class="p-3 border-top">{{ $rows->links() }}</div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
