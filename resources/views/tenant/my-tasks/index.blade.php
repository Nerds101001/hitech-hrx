@extends('layouts/layoutMaster')

@section('title', 'My Tasks')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('page-style')
<style>
  .mt-group{background:#fff;border:1px solid #e6ecf1;border-radius:16px;margin-bottom:18px;overflow:hidden}
  .mt-head{display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid #eef2f6}
  .mt-ic{width:40px;height:40px;flex:0 0 40px;border-radius:11px;display:grid;place-items:center;
    font-size:1.25rem;color:var(--c);background:color-mix(in srgb,var(--c) 12%,#fff)}
  .mt-title{font-size:1rem;font-weight:800;color:#0f172a;margin:0}
  .mt-sub{font-size:.78rem;color:#64748b}
  .mt-count{margin-left:auto;background:#005a5a;color:#fff;font-size:.72rem;font-weight:800;
    padding:3px 11px;border-radius:999px}
  .mt-row{display:flex;align-items:center;gap:12px;padding:13px 20px;border-bottom:1px solid #f4f8fa}
  .mt-row:last-child{border-bottom:none}
  .mt-row:hover{background:#fbfdfe}
  .mt-nm{font-weight:700;color:#0f172a;font-size:.88rem}
  .mt-mt{font-size:.74rem;color:#64748b}
  .mt-tags{display:flex;gap:5px;flex-wrap:wrap;margin-left:auto}
  .mt-tag{font-size:.65rem;font-weight:800;padding:2px 9px;border-radius:999px;
    background:#eef6f6;color:#0f766e;white-space:nowrap}
  .mt-go{background:#005a5a;color:#fff;border-radius:8px;padding:6px 14px;font-size:.76rem;
    font-weight:700;text-decoration:none;white-space:nowrap}
  .mt-go:hover{background:#00403f;color:#fff}
  .mt-empty{background:#fff;border:1px solid #e6ecf1;border-radius:16px;padding:56px 20px;text-align:center}
  .mt-empty i{font-size:3rem;color:#0d9488;opacity:.5}
  .mt-empty h5{margin:14px 0 4px;font-weight:800;color:#0f172a}
  .mt-empty p{margin:0;color:#64748b;font-size:.86rem}
</style>
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4 py-2">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">My Tasks</h3>
      <p class="text-muted mb-0 small">Everything waiting on you, in one place.</p>
    </div>
    @php $total = collect($groups)->sum(fn($g) => count($g['items'])); @endphp
    <span class="badge {{ $total ? 'bg-label-warning' : 'bg-label-success' }} fs-6 px-3 py-2">
      {{ $total ? $total . ' pending' : 'All caught up' }}
    </span>
  </div>

  <div class="px-4">
    @forelse($groups as $g)
      <div class="mt-group" style="--c: {{ $g['tone'] === 'danger' ? '#dc2626' : '#0d9488' }}">
        <div class="mt-head">
          <span class="mt-ic"><i class="bx {{ $g['icon'] }}"></i></span>
          <span>
            <h6 class="mt-title">{{ $g['title'] }}</h6>
            <span class="mt-sub">{{ $g['sub'] }}</span>
          </span>
          <span class="mt-count">{{ count($g['items']) }}</span>
        </div>

        @foreach($g['items'] as $item)
          <div class="mt-row">
            <span>
              <span class="mt-nm d-block">{{ $item['title'] }}</span>
              <span class="mt-mt">{{ $item['meta'] }}</span>
            </span>
            <span class="mt-tags">
              @foreach($item['tags'] as $tag)
                <span class="mt-tag">{{ $tag }}</span>
              @endforeach
            </span>
            @if(!empty($item['modal']))
              <button type="button" class="mt-go js-setup-task"
                      data-id="{{ $item['modal']['id'] }}"
                      data-name="{{ $item['modal']['name'] }}"
                      data-meta="{{ $item['modal']['meta'] }}"
                      data-emp="{{ $item['modal']['emp'] }}"
                      data-needs-emp="{{ $item['modal']['needs_emp'] }}"
                      data-needs-bio="{{ $item['modal']['needs_bio'] }}"
                      style="border:none">Complete</button>
            @else
              <a href="{{ $item['url'] }}" class="mt-go">Complete</a>
            @endif
          </div>
        @endforeach
      </div>
    @empty
      <div class="mt-empty">
        <i class="bx bx-check-circle"></i>
        <h5>Nothing pending</h5>
        <p>You have no tasks waiting. Anything assigned to you will appear here.</p>
      </div>
    @endforelse
  </div>
</div>

@include('tenant.employees._setup-modal')
@endsection
