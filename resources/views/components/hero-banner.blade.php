@props([
    'title' => 'Dashboard',
    'subtitle' => null,
    'icon' => 'bx-home',
    'gradient' => 'primary',
    'quote' => null,
    'showStats' => false,
    'breadcrumb' => null,
])

{{--
  Compact page header.

  This used to render a full-width gradient hero ~160px tall, and most pages
  then followed it with a second card containing nothing but a single button —
  so two thirds of the first screen was chrome before any data appeared. It now
  renders one line: icon + title (and optional subtitle) on the left, with
  breadcrumb and page actions on the right, matching the Holiday and
  Announcement dashboards.

  Every existing prop is still accepted so all 24 call sites keep working.
  `gradient`, `quote` and `showStats` are deliberately accepted and ignored —
  dropping them from the signature would break those pages.

  Pass page actions in the default slot to keep them on the same line:
      <x-hero-banner title="Employee Promotions" icon="bx-trending-up">
        <button class="btn-hitech ...">New Promotion</button>
      </x-hero-banner>
--}}

<div class="page-header-compact d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <div class="page-header-icon">
      <i class="bx {{ $icon }}"></i>
    </div>
    <div>
      <h4 class="fw-extrabold mb-0 text-dark lh-1">{{ $title }}</h4>
      @if($subtitle)
        <p class="text-muted mb-0 small mt-1">{{ $subtitle }}</p>
      @endif
    </div>
  </div>

  <div class="d-flex align-items-center flex-wrap gap-2">
    @if($breadcrumb)
      <nav aria-label="breadcrumb" class="me-2 d-none d-md-block">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="{{ url('/') }}">Dashboard</a></li>
          <li class="breadcrumb-item active">{{ $breadcrumb }}</li>
        </ol>
      </nav>
    @endif
    {{ $slot }}
  </div>
</div>
