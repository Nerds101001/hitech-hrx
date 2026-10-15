{{-- The module's navigation, the same as the sidebar: Dashboard / Programs / New Program for
     admin and HR, Team Dashboard for a team lead, My Dashboard / My Programs for a salesperson.
     Replaces the old Goals / Dashboard / Past rounds / Comments tab bar (2026-10-01), which
     belonged to retired pages and sent people between two different designs. --}}
@php
  $navUser = auth()->user();
  $navAll  = \App\Services\NinetyDayGoalService::seesEveryone($navUser);
  $navMgr  = \App\Services\NinetyDayGoalService::managesEveryone($navUser);
  $navSale = \App\Services\NinetyDayGoalService::isSalesStaff($navUser);
  $tabs = [];
  if ($navAll) {
      $tabs['dashboard'] = ['Dashboard', 'bx-grid-alt', route('performance.ninety-day-goals.program-dashboard')];
      $tabs['programs']  = ['Programs', 'bx-list-ul', route('performance.ninety-day-goals.programs.index')];
      if ($navMgr) {
          $tabs['new'] = ['New Program', 'bx-plus-circle', route('performance.ninety-day-goals.programs.create')];
          $pendingDeleteCount = \App\Models\NinetyDayGoalDeleteRequest::where('status', 'pending')->count();
          $tabs['delete-requests'] = [
              'Removal Requests' . ($pendingDeleteCount ? " ({$pendingDeleteCount})" : ''),
              'bx-trash', route('performance.ninety-day-goals.delete-requests.index'),
          ];
      }
  } else {
      if ($navSale) {
          $tabs['mine']     = ['My Dashboard', 'bx-grid-alt', route('performance.ninety-day-goals.my-dashboard')];
          $tabs['programs'] = ['My Programs', 'bx-target-lock', route('performance.ninety-day-goals.my-program')];
      }
      if (($seesMany ?? false)) {
          $tabs['team'] = ['Team Dashboard', 'bx-group', route('performance.ninety-day-goals.program-dashboard')];
      }
  }
  $navOn = $navTab ?? null;
@endphp
@if($tabs)
<div class="d-flex gap-2 flex-wrap mb-6 mx-4 ndg-tabs">
  @foreach($tabs as $key => [$label, $icon, $href])
    <a href="{{ $href }}" class="ndg-tab {{ $navOn === $key ? 'on' : '' }}"><i class="bx {{ $icon }}"></i>{{ $label }}</a>
  @endforeach
</div>
<style>
  .ndg-tab { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 999px; border: 1px solid #d5e3e1; background: #fff; color: #334155; font-weight: 700; font-size: .85rem; text-decoration: none; }
  .ndg-tab:hover { border-color: #007a7a; color: #007a7a; }
  .ndg-tab.on { background: #007a7a; border-color: #007a7a; color: #fff; }
</style>
@endif
