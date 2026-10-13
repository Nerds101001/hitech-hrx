@extends('layouts/layoutMaster')

@section('title', 'Handover #' . $id)

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
@php
  $b = $batch;
  $stays = collect($customers)->sum('stays');
  $goes  = collect($customers)->sum('goes');
  $roles = collect($customers)->pluck('role')->unique()->values();
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Handover #{{ $id }}: {{ $leaver }}
        @if($b->status === 'applied')<span class="badge bg-teal-light text-teal rounded-pill px-3 ms-2" style="font-size:.75rem;">Applied</span>
        @else<span class="badge bg-label-secondary rounded-pill px-3 ms-2" style="font-size:.75rem;">Undone</span>@endif
      </h3>
      <p class="text-muted mb-0 mt-1">
        Customers moved from <strong>{{ $leaver }}</strong> to their replacement(s), credited to the new person from
        <strong>{{ \Carbon\Carbon::createFromFormat('Y-m', $b->effective_month)->format('F Y') }}</strong>.
        Done by {{ $by }} on {{ \Carbon\Carbon::parse($b->created_at)->format('d M Y H:i') }}@if($b->note) &middot; {{ $b->note }}@endif.
        @if($b->status !== 'applied') Undone by {{ $reverted_by ?: 'System' }} on {{ \Carbon\Carbon::parse($b->reverted_at)->format('d M Y H:i') }}.@endif
      </p>
    </div>
    <div class="d-flex gap-3 flex-wrap">
      <a href="{{ route('sales-visits.leaver-handover.export', $id) }}" class="btn btn-hitech-secondary shadow-sm rounded-pill px-4 d-flex align-items-center gap-2">
        <i class="bx bx-download fs-5"></i><span class="fw-bold">Download (Excel / CSV)</span>
      </a>
      <a href="{{ route('sales-visits.leaver-handover.index') }}" class="btn btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
        <i class="bx bx-left-arrow-alt fs-5"></i><span class="fw-bold">Leaver Handover</span>
      </a>
    </div>
  </div>

  <div class="row g-6 mb-6 mx-0 px-4">
    @foreach([
      ['bx-store', 'blue', count(array_unique(array_column($customers, 'pipeline_id'))), 'Customers'],
      ['bx-transfer-alt', 'teal', ($summary['role_moves'] ?? count($customers)), 'Role changes'],
      ['bx-history', 'amber', number_format($stays, 2) . ' L', 'Stays with ' . $leaver],
      ['bx-trending-up', 'blue', number_format($goes, 2) . ' L', 'Goes to the new person'],
    ] as [$icon, $tone, $value, $label])
      <div class="col-xl-3 col-md-6">
        <div class="hitech-stat-card dashboard-variant card-{{ $tone }} uniform-card">
          <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-{{ $tone }}" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx {{ $icon }}"></i></div></div>
          <div><h3 class="stat-value mb-0">{{ $value }}</h3><span class="stat-label">{{ $label }}</span></div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Customers --}}
  <div class="px-4 mb-6">
    <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
      <div class="p-4 pb-3 d-flex flex-wrap align-items-end justify-content-between gap-3">
        <div><h5 class="fw-bold mb-1 text-dark">Customers moved</h5>
          <div class="small text-muted">Who each customer was with, and who it is with now.</div></div>
        <div class="d-flex flex-wrap gap-2">
          <input type="search" id="fText" class="form-control" style="min-width:220px;" placeholder="Search customer or person…">
          <select id="fRole" class="form-select" style="min-width:170px;">
            <option value="">All roles</option>
            @foreach($roles as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
          </select>
          <select id="fSales" class="form-select" style="min-width:170px;">
            <option value="">All customers</option>
            <option value="goes">With sales from the new month</option>
            <option value="stays">With sales before it</option>
          </select>
        </div>
      </div>
      <div class="table-responsive" style="max-height:560px;overflow:auto;">
        <table class="table m-0 shadow-none border-0" id="hoTable">
          <thead class="text-dark" style="position:sticky;top:0;background:#fff;z-index:1;"><tr>
            <th class="ps-4">#</th><th>Customer</th><th>Role</th><th>From</th><th>To</th>
            <th class="text-end">Sale before (L)<br><span class="small text-muted fw-normal">stays with previous</span></th>
            <th class="text-end pe-4">Sale from {{ \Carbon\Carbon::createFromFormat('Y-m', $b->effective_month)->format('M Y') }} (L)<br><span class="small text-muted fw-normal">goes to new</span></th>
          </tr></thead>
          <tbody>
          @forelse($customers as $i => $c)
            <tr style="border-bottom:1px solid #f1f5f9;" data-role="{{ $c['role'] }}" data-stays="{{ $c['stays'] > 0 ? 1 : 0 }}" data-goes="{{ $c['goes'] > 0 ? 1 : 0 }}"
                data-text="{{ strtolower($c['customer'] . ' ' . $c['from'] . ' ' . $c['to'] . ' ' . $c['role']) }}">
              <td class="ps-4 text-muted">{{ $i + 1 }}</td>
              <td class="fw-medium">{{ $c['customer'] }}</td>
              <td><span class="badge bg-label-info rounded-pill">{{ $c['role'] }}</span></td>
              <td>{{ $c['from'] }}</td>
              <td><i class="bx bx-right-arrow-alt text-muted me-1"></i><strong>{{ $c['to'] }}</strong></td>
              <td class="text-end">{{ $c['stays'] ? number_format($c['stays'], 2) : '—' }}</td>
              <td class="text-end pe-4">{{ $c['goes'] ? number_format($c['goes'], 2) : '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center py-5 text-muted">No Sales Report customers in this handover.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="px-4 py-2 small text-muted" id="fCount"></div>
    </div>
  </div>

  @if(count($client_master))
  <div class="px-4 mb-6">
    <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
      <div class="p-4 pb-2"><h5 class="fw-bold mb-1 text-dark">Client Master records moved</h5></div>
      <div class="table-responsive"><table class="table m-0 shadow-none border-0">
        <thead class="text-dark"><tr><th class="ps-4">Customer</th><th>Field</th><th>From</th><th>To</th></tr></thead>
        <tbody>@foreach($client_master as $c)
          <tr style="border-bottom:1px solid #f1f5f9;"><td class="ps-4 fw-medium">{{ $c['customer'] }}</td><td>{{ $c['field'] }}</td><td>{{ $c['from'] }}</td><td><strong>{{ $c['to'] }}</strong></td></tr>
        @endforeach</tbody></table></div>
    </div>
  </div>
  @endif

  @if(count($ccare_map))
  <div class="px-4 mb-6">
    <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
      <div class="p-4 pb-2"><h5 class="fw-bold mb-1 text-dark">CCare map rows added</h5>
        <div class="small text-muted">The replacement was added beside the existing mapping; nothing was removed.</div></div>
      <div class="table-responsive"><table class="table m-0 shadow-none border-0">
        <thead class="text-dark"><tr><th class="ps-4">CCare person</th><th>Salesperson</th><th>Role</th></tr></thead>
        <tbody>@foreach($ccare_map as $c)
          <tr style="border-bottom:1px solid #f1f5f9;"><td class="ps-4 fw-medium">{{ $c['ccare'] }}</td><td>{{ $c['salesperson'] }}</td><td>{{ $c['role'] }}</td></tr>
        @endforeach</tbody></table></div>
    </div>
  </div>
  @endif
</div>

<script>
(function () {
  var rows = Array.prototype.slice.call(document.querySelectorAll('#hoTable tbody tr[data-text]'));
  var t = document.getElementById('fText'), r = document.getElementById('fRole'), s = document.getElementById('fSales'), c = document.getElementById('fCount');
  function run() {
    var q = t.value.trim().toLowerCase(), shown = 0;
    rows.forEach(function (tr) {
      var ok = (!q || tr.dataset.text.indexOf(q) !== -1) && (!r.value || tr.dataset.role === r.value)
            && (!s.value || tr.dataset[s.value] === '1');
      tr.style.display = ok ? '' : 'none'; if (ok) shown++;
    });
    c.textContent = shown + ' of ' + rows.length + ' customer row(s) shown';
  }
  [t, r, s].forEach(function (el) { el.addEventListener('input', run); el.addEventListener('change', run); });
  run();
})();
</script>
@endsection
