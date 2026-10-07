@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — Programs')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('content')
@php
  $stateLabel = ['active' => ['Active', '#d7f3ee', '#0b5b48'], 'upcoming' => ['Upcoming', '#e0f2fe', '#0369a1'], 'ended' => ['Ended', '#e2e8f0', '#475569']];
  $creator = fn ($p) => $p->creator ? trim($p->creator->first_name . ' ' . $p->creator->last_name) : '—';
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4 mx-4">
    <div>
      <nav class="small text-muted mb-1"><a href="{{ route('tenant.dashboard') }}" class="text-muted">Dashboard</a> <i class="bx bx-chevron-right"></i> 90-Day Goals <i class="bx bx-chevron-right"></i> Programs</nav>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals Programs</h3>
      <p class="text-muted mb-0 small">Every program in one list. Open one to see its progress, or Edit to change its name, dates, people or the window to add customers.</p>
    </div>
    @if($canManage)
      <a href="{{ route('performance.ninety-day-goals.programs.create') }}" class="btn-hitech rounded-pill px-4 d-flex align-items-center gap-2"><i class="bx bx-plus-circle"></i> New Program</a>
    @endif
  </div>

  @include('tenant.performance.ninety-day-goals._tabs', ['navTab' => 'programs'])

  <div class="px-4">
    <div class="d-flex gap-2 flex-wrap mb-4">
      @foreach(['all' => 'All', 'active' => 'Active', 'upcoming' => 'Upcoming', 'ended' => 'Ended'] as $key => $label)
        <a href="{{ route('performance.ninety-day-goals.programs.index', $key === 'all' ? [] : ['status' => $key]) }}"
           class="ndg-tab {{ $status === $key ? 'on' : '' }}">{{ $label }} <span class="ms-1 opacity-75">({{ $counts[$key] }})</span></a>
      @endforeach
      <a href="{{ route('performance.ninety-day-goals.past') }}" class="ndg-tab ms-auto"><i class="bx bx-history"></i> Finished rounds by salesperson</a>
    </div>

    <div class="hitech-card mb-6">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0" style="font-size:.85rem">
            <thead>
              <tr>
                <th>Program</th>
                <th>Dates</th>
                <th>Status</th>
                <th class="text-end">Participants</th>
                <th class="text-end">Customers</th>
                <th>Created by</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($programs as $p)
                @php [$lbl, $bg, $fg] = $stateLabel[$stateOf($p)]; @endphp
                <tr>
                  <td><a href="{{ route('performance.ninety-day-goals.program', $p->id) }}" class="fw-bold text-dark">{{ $p->name }}</a><div class="small text-muted">PRG-{{ $p->id }}</div></td>
                  <td class="text-nowrap">{{ $p->starts_on->format('d M Y') }} – {{ $p->ends_on->format('d M Y') }}</td>
                  <td><span class="badge rounded-pill fw-bold" style="background:{{ $bg }};color:{{ $fg }}">{{ $lbl }}</span></td>
                  <td class="text-end">{{ $participants[$p->id] ?? 0 }}</td>
                  <td class="text-end">{{ $customers[$p->id] ?? 0 }}</td>
                  <td>{{ $creator($p) }}</td>
                  <td class="text-end text-nowrap">
                    <a href="{{ route('performance.ninety-day-goals.program', $p->id) }}" class="btn btn-sm btn-hitech-cancel rounded-pill px-3">Open</a>
                    @if($canManage)
                      <a href="{{ route('performance.ninety-day-goals.program', ['id' => $p->id, 'manage' => 1]) }}" class="btn btn-sm btn-hitech rounded-pill px-3">Edit</a>
                    @endif
                    @if($canDelete)
                      <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 ndg-prog-del"
                              data-url="{{ route('performance.ninety-day-goals.program.destroy', $p->id) }}" data-name="{{ $p->name }}">Delete</button>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted p-4">
                  No {{ $status === 'all' ? '' : $status . ' ' }}programs yet.
                  @if($canManage && $status === 'all') <a href="{{ route('performance.ninety-day-goals.programs.create') }}">Create the first one</a>. @endif
                </td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
// Delete from the list: the same admin-only endpoint as Program Details, confirmed by typing the name.
document.querySelectorAll('.ndg-prog-del').forEach(function (b) {
  b.addEventListener('click', function () {
    var name = b.dataset.name;
    var typed = window.prompt('Delete "' + name + '"?\n\nThis removes the program with every participant round, customer, weekly status, note, the tasks it created and its notifications.\n\nType the program name to confirm:');
    if (typed === null) { return; }
    if (typed.trim() !== name.trim()) { alert('The name did not match - nothing was deleted.'); return; }
    b.disabled = true;
    fetch(b.dataset.url, { method: 'DELETE', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }, body: JSON.stringify({ confirm_name: typed }) })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var msg = j.message || 'Could not delete.';
        if (j.success) {
          if (typeof window.showSuccessSwal === 'function') { window.showSuccessSwal(msg).then(function () { location.reload(); }); } else { alert(msg); location.reload(); }
        } else {
          b.disabled = false;
          if (typeof window.showErrorSwal === 'function') { window.showErrorSwal(msg); } else { alert(msg); }
        }
      })
      .catch(function () { b.disabled = false; alert('Request failed. Try again.'); });
  });
});
</script>
@endsection
