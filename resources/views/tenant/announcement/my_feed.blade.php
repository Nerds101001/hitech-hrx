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
    <div class="row g-4">
      @forelse($announcements as $a)
        @php
          $tone = match($a->priority) {
            'critical' => ['#991b1b', '#fee2e2', '#dc2626', 'bx-error-circle', 'Critical'],
            'high'     => ['#92400e', '#fef3c7', '#f59e0b', 'bx-error', 'High'],
            'medium'   => ['#3730a3', '#e0e7ff', '#6366f1', 'bx-info-circle', 'Medium'],
            default    => ['#475569', '#f1f5f9', '#94a3b8', 'bx-message-dots', 'Low'],
          };
          // Card preview is always flattened to plain text — is_html content
          // (rich, arbitrary markup) is never a safe or predictable fit for a
          // clamped 3-line tile. The full markup still renders in the modal.
          $preview = trim(html_entity_decode(strip_tags(preg_replace('/<\s*br\s*\/?>/i', "\n", (string) $a->content))));
          $preview = trim(preg_replace('/\s+/', ' ', $preview));
          $authorName = $a->createdBy?->getFullName() ?? 'HR';
          $authorPhoto = $a->createdBy?->getProfilePicture() ?: ('https://ui-avatars.com/api/?name=' . urlencode($authorName) . '&background=0d9488&color=fff&bold=true&size=64');
          $whenShort = ($a->published_at ?? $a->start_date)?->format('d M Y');
          $whenFull  = ($a->published_at ?? $a->start_date)?->format('d M Y, h:i A');
        @endphp
        <div class="col-md-6 col-lg-4">
          <div class="card hitech-card-white ann-card border-0 shadow-sm rounded-4 h-100"
               style="border-top: 4px solid {{ $tone[2] }};"
               role="button" tabindex="0"
               data-ann-open data-target="#ann-full-{{ $a->id }}"
               data-title="{{ $a->title }}" data-tone-icon="{{ $tone[3] }}">
            <div class="card-body p-4 d-flex flex-column h-100">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0"
                     style="width:42px; height:42px; background:{{ $tone[1] }}; color:{{ $tone[0] }};">
                  <i class="bx {{ $tone[3] }}" style="font-size:1.35rem;"></i>
                </div>
                <span class="badge rounded-pill" style="background:{{ $tone[1] }}; color:{{ $tone[0] }};">{{ $tone[4] }}</span>
              </div>

              <h5 class="fw-extrabold text-dark mb-2 ann-clamp-2">{{ $a->title }}</h5>

              <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge badge-brand-soft rounded-pill">{{ ucfirst($a->type) }}</span>
                @if($a->audience_type === 'team')
                  <span class="badge badge-brand rounded-pill"><i class="bx bx-group me-1"></i>Team</span>
                @endif
                @if($a->requires_acknowledgment)
                  <span class="badge bg-label-warning rounded-pill"><i class="bx bx-check-double me-1"></i>Ack. required</span>
                @endif
              </div>

              <p class="ann-clamp-3 mb-4" style="color:#64748b; font-size:.875rem; line-height:1.6;">
                {{ $preview !== '' ? $preview : 'Open to view this announcement.' }}
              </p>

              <div class="d-flex align-items-center justify-content-between mt-auto pt-3 border-top">
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar avatar-xs">
                    <img src="{{ $authorPhoto }}" alt="" class="rounded-circle">
                  </div>
                  <small class="text-muted">{{ $a->createdBy?->first_name ?? 'HR' }}</small>
                </div>
                <small class="text-muted">{{ $whenShort }}</small>
              </div>
              <div class="mt-2 fw-semibold" style="color:var(--hitech-primary); font-size:.8rem;">
                Read full announcement <i class="bx bx-right-arrow-alt"></i>
              </div>
            </div>
          </div>

          <template id="ann-full-{{ $a->id }}">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
              <span class="badge rounded-pill" style="background:{{ $tone[1] }}; color:{{ $tone[0] }};">{{ $tone[4] }} priority</span>
              <span class="badge badge-brand-soft rounded-pill">{{ ucfirst($a->type) }}</span>
              @if($a->audience_type === 'team')
                <span class="badge badge-brand rounded-pill"><i class="bx bx-group me-1"></i>Team</span>
              @endif
              @if($a->requires_acknowledgment)
                <span class="badge bg-label-warning rounded-pill"><i class="bx bx-check-double me-1"></i>Acknowledgement required</span>
              @endif
            </div>

            <div style="white-space: pre-line; color:#334155; line-height:1.7;">
              {{-- is_html is only ever set by trusted, server-generated content (see
                   HrNotificationService::publishWelcomeAnnouncement) — never by the
                   plain-textarea creation forms, so this can't render unsanitised
                   user input. --}}
              @if($a->is_html)
                {!! $a->content !!}
              @else
                {{ $a->content }}
              @endif
            </div>

            @if($a->end_date)
              <div class="mt-3 pt-3 border-top small text-muted">
                <i class="bx bx-time-five me-1"></i>Valid until {{ $a->end_date->format('d M Y') }}
              </div>
            @endif

            <div class="mt-3 small text-muted">
              Published {{ $whenFull }} by {{ $authorName }}
            </div>
          </template>
        </div>
      @empty
        <div class="col-12">
          <div class="hitech-card-white border-0 shadow-sm rounded-4">
            <div class="card-body text-center py-6">
              <i class="bx bx-bell-off text-muted" style="font-size:3rem;"></i>
              <h5 class="fw-bold mt-3 mb-1">No announcements right now</h5>
              <p class="text-muted mb-0">Anything posted for your department, location, role or team will appear here.</p>
            </div>
          </div>
        </div>
      @endforelse
    </div>

    <div class="d-flex justify-content-center mt-4">{{ $announcements->links() }}</div>
  </div>
</div>

<!-- Announcement detail modal -->
<div class="modal fade" id="annDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-message-dots" id="annModalIcon"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0" id="annModalTitle">Announcement</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech" id="annModalBody"></div>
    </div>
  </div>
</div>

<style>
  .ann-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .ann-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .ann-card { transition: transform .2s ease, box-shadow .2s ease; cursor: pointer; }
  .ann-card:hover { transform: translateY(-3px); }
  .ann-card:focus-visible { outline: 2px solid var(--hitech-primary); outline-offset: 2px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var modalEl = document.getElementById('annDetailModal');
  if (!modalEl) return;
  var titleEl = document.getElementById('annModalTitle');
  var bodyEl = document.getElementById('annModalBody');
  var iconEl = document.getElementById('annModalIcon');

  var openCard = function (card) {
    var tmpl = document.querySelector(card.dataset.target);
    if (!tmpl) return;
    titleEl.textContent = card.dataset.title || 'Announcement';
    iconEl.className = 'bx ' + (card.dataset.toneIcon || 'bx-message-dots');
    bodyEl.innerHTML = tmpl.innerHTML;
    if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };

  document.querySelectorAll('[data-ann-open]').forEach(function (card) {
    card.addEventListener('click', function () { openCard(card); });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCard(card); }
    });
  });
});
</script>
@endsection
