@extends('layouts/layoutMaster')

@section('title', 'Telephony exceptions')

@section('content')
@include('tenant.telephony._partials._helpers')
<div class="layout-full-width">

  @include('tenant.telephony._partials._header', [
    'title' => 'Exceptions',
    'subtitle' => 'Calls that arrived but could not be matched to an employee. They are never dropped — a dropped call is invisible, an unassigned one gets fixed.',
    'crumb' => 'Exceptions',
  ])

  <div class="px-4">

    @if($groups->isEmpty())
      <div class="hitech-card-white border-0 shadow-sm rounded-4 p-5 text-center">
        <i class="bx bx-check-circle" style="font-size:3rem; color:#15803d;"></i>
        <h6 class="fw-bold mt-3 mb-1">Nothing unassigned</h6>
        <p class="text-muted small mb-0">Every call that arrived has been matched to an employee.</p>
      </div>
    @else

      {{-- Grouped by identifier, because the fix is almost always one new
           mapping rather than one edit per call. --}}
      <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="p-3 border-bottom">
          <h6 class="fw-bold mb-0">Unmatched agents</h6>
          <p class="text-muted small mb-0">
            Each row is one set of identifiers the gateway reported that no mapping covers.
            Assigning one fixes every call in the group at once.
          </p>
        </div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Agent id</th><th>Code</th><th>Extension</th><th>Phone / SIM</th>
                <th class="text-end">Calls</th><th>First seen</th><th>Last seen</th>
                <th style="min-width:340px;">Assign to</th>
              </tr>
            </thead>
            <tbody>
              @foreach($groups as $g)
                <tr>
                  <td class="small">{{ $g->agent_id ?: '—' }}</td>
                  <td class="small">{{ $g->agent_code ?: '—' }}</td>
                  <td class="small fw-semibold">{{ $g->extension ?: '—' }}</td>
                  <td class="small">{{ $g->agent_number ?: '—' }}</td>
                  <td class="text-end small fw-semibold">{{ number_format($g->calls) }}</td>
                  <td class="small text-muted">{{ $g->first_seen ? \Carbon\Carbon::parse($g->first_seen)->format('d M') : '—' }}</td>
                  <td class="small text-muted">{{ $g->last_seen ? \Carbon\Carbon::parse($g->last_seen)->format('d M') : '—' }}</td>
                  <td>
                    @if(!$g->agent_id && !$g->agent_code && !$g->extension && !$g->agent_number)
                      <span class="text-muted small">
                        No agent identifier at all — ask CentroVision to include the agent block.
                      </span>
                    @else
                      <form method="POST" action="{{ route('user.telephony.admin.exceptions.assign') }}"
                            class="d-flex flex-wrap align-items-center gap-2">
                        @csrf
                        <input type="hidden" name="agent_id" value="{{ $g->agent_id }}">
                        <input type="hidden" name="agent_code" value="{{ $g->agent_code }}">
                        <input type="hidden" name="extension" value="{{ $g->extension }}">
                        <input type="hidden" name="agent_number" value="{{ $g->agent_number }}">

                        <select name="user_id" class="form-select form-select-sm" style="max-width:180px;" required>
                          <option value="">Choose employee…</option>
                          @foreach($users as $u)
                            <option value="{{ $u->id }}">
                              {{ trim($u->first_name.' '.$u->last_name) }}@if($u->code) ({{ $u->code }})@endif
                            </option>
                          @endforeach
                        </select>

                        <div class="form-check mb-0">
                          <input class="form-check-input" type="checkbox" name="create_mapping" value="1"
                                 id="map{{ $loop->index }}" checked>
                          <label class="form-check-label small" for="map{{ $loop->index }}">Remember</label>
                        </div>

                        <button class="btn btn-sm btn-hitech-primary" type="submit">Assign</button>
                      </form>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="p-3 border-top">
          <p class="text-muted small mb-0">
            <strong>Remember</strong> also saves a permanent mapping, so future calls from that agent
            resolve on their own. Leave it ticked unless this was a one-off.
          </p>
        </div>
      </div>

      {{-- The individual calls, so nobody has to take the grouping on trust. --}}
      <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="p-3 border-bottom">
          <h6 class="fw-bold mb-0">The calls themselves</h6>
        </div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>When</th><th>Direction</th><th>From</th><th>To</th>
                <th>Extension</th><th>How it started</th><th>Outcome</th><th class="text-end">Talk</th><th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($calls as $c)
                @php $tone = telephony_tone($c->status); @endphp
                <tr>
                  <td class="small text-muted text-nowrap">{{ $c->initiated_at?->format('d M, H:i') ?? '—' }}</td>
                  <td class="small">{{ ucfirst($c->direction ?? '—') }}</td>
                  {{-- Admin-only screen, so numbers are shown in full: matching a
                       call to a person is the entire job here. --}}
                  <td class="small">{{ $c->caller_number ?: '—' }}</td>
                  <td class="small">{{ $c->callee_number ?: '—' }}</td>
                  <td class="small fw-semibold">{{ $c->extension ?: '—' }}</td>
                  <td class="small">{{ telephony_origin_label($c->origin) }}</td>
                  <td>
                    <span class="badge rounded-pill" style="background:{{ $tone[0] }}; color:{{ $tone[1] }};">
                      {{ ucfirst(str_replace('_',' ', $c->status ?? '—')) }}
                    </span>
                  </td>
                  <td class="text-end small">{{ telephony_hms($c->talk_seconds) }}</td>
                  <td class="text-end">
                    <a href="{{ route('user.telephony.reports.call', $c->call_uid) }}"
                       class="btn btn-sm btn-outline-secondary rounded-pill">Detail</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if($calls->hasPages())
          <div class="p-3 border-top">{{ $calls->links() }}</div>
        @endif
      </div>
    @endif

  </div>
</div>
@endsection
