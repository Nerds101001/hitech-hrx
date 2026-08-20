{{-- Shared page header for every telephony screen.

     $title / $subtitle are required. $crumb is the breadcrumb leaf.
     $tabs is optional; pass false to hide the nav (used on the call detail
     page, which is reached from a row rather than from the tab bar). --}}
@php
  $telephonyTabs = [
    ['route' => 'user.telephony.reports.calls',        'label' => 'Calls',        'icon' => 'bx-phone'],
    ['route' => 'user.telephony.reports.productivity', 'label' => 'Productivity', 'icon' => 'bx-time-five'],
    ['route' => 'user.telephony.reports.analytics',    'label' => 'Analytics',    'icon' => 'bx-bar-chart-alt-2'],
    ['route' => 'user.telephony.admin.connection',     'label' => 'Connection',   'icon' => 'bx-key'],
    ['route' => 'user.telephony.admin.webhook-log',    'label' => 'Webhook Log',  'icon' => 'bx-transfer'],
    ['route' => 'user.telephony.admin.agent-map',      'label' => 'Agent Mapping','icon' => 'bx-id-card'],
    ['route' => 'user.telephony.admin.exceptions',     'label' => 'Exceptions',   'icon' => 'bx-error-circle'],
    ['route' => 'user.telephony.admin.reconciliation', 'label' => 'Reconciliation','icon' => 'bx-check-double'],
  ];

  // Nav::allows() reads the authorisation middleware each route really
  // carries, so the admin tabs disappear for an employee instead of showing
  // them a link straight to a 403.
  $telephonyTabs = collect($telephonyTabs)
    ->filter(fn ($t) => \App\Support\Nav::allows(route($t['route'])))
    ->values();
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
  <div>
    <h3 class="fw-extrabold mb-0 text-dark">{{ $title }}</h3>
    <p class="text-muted mb-0 small">{{ $subtitle }}</p>
  </div>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ url('/') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="{{ route('user.telephony.reports.calls') }}">Telephony</a></li>
      <li class="breadcrumb-item active">{{ $crumb ?? $title }}</li>
    </ol>
  </nav>
</div>

@if(($tabs ?? true) && $telephonyTabs->isNotEmpty())
  <div class="px-4 mb-4">
    <div class="d-flex flex-wrap gap-2">
      @foreach($telephonyTabs as $t)
        @php $active = request()->routeIs($t['route']); @endphp
        <a href="{{ route($t['route']) }}"
           class="btn btn-sm rounded-pill {{ $active ? 'btn-hitech-primary' : 'btn-outline-secondary' }}">
          <i class="bx {{ $t['icon'] }} me-1"></i>{{ $t['label'] }}
        </a>
      @endforeach
    </div>
  </div>
@endif

@if(session('success'))
  <div class="px-4"><div class="alert alert-success d-flex align-items-center gap-2">
    <i class="bx bx-check-circle"></i><span>{{ session('success') }}</span>
  </div></div>
@endif
@if(session('error'))
  <div class="px-4"><div class="alert alert-danger d-flex align-items-center gap-2">
    <i class="bx bx-error-circle"></i><span>{{ session('error') }}</span>
  </div></div>
@endif
@if($errors->any())
  <div class="px-4"><div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div></div>
@endif
