@extends('layouts/layoutMaster')

@section('title', 'Announcements')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Announcements</h3>
      <p class="text-muted mb-0 small">Notices for your department, location, role and team.</p>
    </div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ url('/') }}">Dashboard</a></li>
        <li class="breadcrumb-item active">Announcements</li>
      </ol>
    </nav>
  </div>

  <div class="px-4">
    @forelse($announcements as $a)
      @php
        $tone = match($a->priority) {
          'critical' => ['#991b1b', '#fee2e2', '#dc2626', 'bx-error-circle'],
          'high'     => ['#92400e', '#fef3c7', '#f59e0b', 'bx-error'],
          'medium'   => ['#3730a3', '#e0e7ff', '#6366f1', 'bx-info-circle'],
          default    => ['#475569', '#f1f5f9', '#94a3b8', 'bx-message-dots'],
        };
      @endphp
      <div class="hitech-card-white mb-4 overflow-hidden animate__animated animate__fadeInUp"
           style="border-left: 5px solid {{ $tone[2] }};">
        <div class="card-body p-4">
          <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div class="d-flex align-items-start gap-3">
              <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0"
                   style="width:44px; height:44px; background: {{ $tone[1] }}; color: {{ $tone[0] }};">
                <i class="bx {{ $tone[3] }}" style="font-size:1.5rem;"></i>
              </div>
              <div>
                <h5 class="fw-extrabold mb-1 text-dark">{{ $a->title }}</h5>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                  <span class="badge rounded-pill" style="background: {{ $tone[1] }}; color: {{ $tone[0] }};">
                    {{ ucfirst($a->priority) }}
                  </span>
                  <span class="badge bg-label-primary rounded-pill">{{ ucfirst($a->type) }}</span>
                  @if($a->audience_type === 'team')
                    <span class="badge bg-label-info rounded-pill"><i class="bx bx-group me-1"></i>Team</span>
                  @endif
                  @if($a->requires_acknowledgment)
                    <span class="badge bg-label-warning rounded-pill"><i class="bx bx-check-double me-1"></i>Acknowledgement required</span>
                  @endif
                </div>
              </div>
            </div>
            <div class="text-end small text-muted flex-shrink-0">
              <div>{{ $a->published_at ? $a->published_at->format('d M Y, h:i A') : $a->start_date?->format('d M Y') }}</div>
              <div>by {{ $a->createdBy?->getFullName() ?? 'HR' }}</div>
            </div>
          </div>

          <div class="mt-3 ps-md-5" style="white-space: pre-line; color:#334155; line-height:1.7;">{{ $a->content }}</div>

          @if($a->end_date)
            <div class="mt-3 ps-md-5 small text-muted">
              <i class="bx bx-time-five me-1"></i>Valid until {{ $a->end_date->format('d M Y') }}
            </div>
          @endif
        </div>
      </div>
    @empty
      <div class="hitech-card-white">
        <div class="card-body text-center py-6">
          <i class="bx bx-bell-off text-muted" style="font-size:3rem;"></i>
          <h5 class="fw-bold mt-3 mb-1">No announcements right now</h5>
          <p class="text-muted mb-0">Anything posted for your department, location, role or team will appear here.</p>
        </div>
      </div>
    @endforelse

    <div class="mt-4">{{ $announcements->links() }}</div>
  </div>
</div>
@endsection
