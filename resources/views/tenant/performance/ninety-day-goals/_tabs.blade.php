{{-- Goals | Dashboard | Past | Comments. $tab is one of goals, dashboard, past, comments. --}}
@php
  $tabs = [
      'goals'     => ['Goals', 'bx-target-lock', route('performance.ninety-day-goals.index', ['view' => ($seesMany ?? false) ? 'overview' : 'mine'])],
      'dashboard' => ['Dashboard', 'bx-bar-chart-alt-2', route('performance.ninety-day-goals.dashboard')],
      'past'      => ['Past rounds', 'bx-history', route('performance.ninety-day-goals.past')],
      'comments'  => ['Comments', 'bx-comment-detail', route('performance.ninety-day-goals.comments')],
  ];
@endphp
<div class="d-flex gap-2 flex-wrap mb-6 mx-4 ndg-tabs">
  @foreach($tabs as $key => [$label, $icon, $href])
    <a href="{{ $href }}" class="ndg-tab {{ $tab === $key ? 'on' : '' }}"><i class="bx {{ $icon }}"></i>{{ $label }}</a>
  @endforeach
</div>
<style>
  .ndg-tab { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 999px; border: 1px solid #d5e3e1; background: #fff; color: #334155; font-weight: 700; font-size: .85rem; text-decoration: none; }
  .ndg-tab:hover { border-color: #007a7a; color: #007a7a; }
  .ndg-tab.on { background: #007a7a; border-color: #007a7a; color: #fff; }
</style>
