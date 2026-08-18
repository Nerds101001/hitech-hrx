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
          $joiner = $a->joiner ?? null; // attached by DashboardAnnouncements::attachJoiners()
          $joinerPhoto = $joiner['photo'] ?? null;
          $authorName = $a->createdBy?->getFullName() ?? 'HR';
          $authorPhoto = $a->createdBy?->getProfilePicture() ?: ('https://ui-avatars.com/api/?name=' . urlencode($authorName) . '&background=0d9488&color=fff&bold=true&size=64');
          $whenShort = ($a->published_at ?? $a->start_date)?->format('d M Y');
          $whenFull  = ($a->published_at ?? $a->start_date)?->format('d M Y, h:i A');
        @endphp
        <div class="col-md-6 col-lg-4">
          <div class="card hitech-card-white ann-card border-0 shadow-sm rounded-4 h-100"
               style="border-top: 4px solid {{ $tone[2] }};"
               role="button" tabindex="0"
               data-ann-open data-target="#ann-full-{{ $a->id }}">
            <div class="card-body p-4 d-flex flex-column h-100">
              <div class="d-flex align-items-center justify-content-between mb-3">
                @if($joinerPhoto)
                  <img src="{{ $joinerPhoto }}" alt="" class="rounded-circle flex-shrink-0"
                       style="width:42px; height:42px; object-fit:cover; border:2px solid {{ $tone[1] }};">
                @else
                  <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0"
                       style="width:42px; height:42px; background:{{ $tone[1] }}; color:{{ $tone[0] }};">
                    <i class="bx {{ $tone[3] }}" style="font-size:1.35rem;"></i>
                  </div>
                @endif
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
            @if($joiner)
              {{-- Joiner posts get the celebration treatment: a centered photo,
                   name and role, plus a way to send them a welcome message —
                   the same /birthdays/send flow the dashboard rail already uses. --}}
              <div class="text-center mb-3">
                <img src="{{ $joinerPhoto ?: ('https://ui-avatars.com/api/?name=' . urlencode($joiner['name']) . '&background=0d9488&color=fff&bold=true&size=168') }}"
                     alt="" class="rounded-circle" style="width:84px; height:84px; object-fit:cover; border:3px solid #0d9488;">
                <h4 class="fw-extrabold mt-3 mb-0" style="color:#0f172a;">{{ $joiner['name'] }}</h4>
                <div class="text-muted small mt-1">{{ $joiner['designation'] }} &middot; {{ $joiner['department'] }}</div>
                <span class="badge rounded-pill mt-2" style="background:rgba(13,148,136,.12); color:#0f766e; font-weight:800; letter-spacing:.5px; text-transform:uppercase; font-size:.68rem;">New Team Member</span>
              </div>
              <p class="text-center mb-0" style="color:#475569; line-height:1.7;">
                Please join us in welcoming <strong style="color:#0f172a;">{{ $joiner['name'] }}</strong> to Hi Tech Group.
                We're glad to have you on board &mdash; wishing you a great start and a successful journey with the team!
              </p>

              @if($joiner['id'] !== auth()->id())
                <div class="ann-welcome-action mt-4 pt-3 border-top" data-receiver="{{ $joiner['id'] }}">
                  <label class="form-label small fw-semibold">Send them a welcome</label>
                  <textarea class="form-control ann-welcome-msg" rows="2" maxlength="1000">Welcome aboard, {{ explode(' ', $joiner['name'])[0] }}! Great to have you with us.</textarea>
                  <div class="ann-welcome-alert alert d-none py-2 px-3 mt-2 mb-0" role="alert"></div>
                  <button type="button" class="btn btn-hitech-primary mt-2 ann-welcome-send"><i class="bx bx-send"></i> Send</button>
                </div>
              @endif
            @else
              <div class="text-center mb-3">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle"
                     style="width:64px; height:64px; background:{{ $tone[1] }}; color:{{ $tone[0] }};">
                  <i class="bx {{ $tone[3] }}" style="font-size:2rem;"></i>
                </div>
                <h4 class="fw-extrabold mt-3 mb-2" style="color:#0f172a;">{{ $a->title }}</h4>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                  <span class="badge rounded-pill" style="background:{{ $tone[1] }}; color:{{ $tone[0] }};">{{ $tone[4] }} priority</span>
                  <span class="badge badge-brand-soft rounded-pill">{{ ucfirst($a->type) }}</span>
                  @if($a->audience_type === 'team')
                    <span class="badge badge-brand rounded-pill"><i class="bx bx-group me-1"></i>Team</span>
                  @endif
                  @if($a->requires_acknowledgment)
                    <span class="badge bg-label-warning rounded-pill"><i class="bx bx-check-double me-1"></i>Acknowledgement required</span>
                  @endif
                </div>
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
            @endif

            <div class="mt-3 {{ $joiner ? 'pt-3 border-top ' : '' }}small text-muted">
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

<!-- Announcement detail modal — same shell as the Birthday / Work Anniversary
     popup (borderless header, plain close, soft gradient body) so celebratory
     posts feel like one family instead of two different UIs. -->
<div class="modal fade" id="annDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content ann-modal-body">
      <div class="modal-header border-0 pb-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-0" id="annModalBody"></div>
    </div>
  </div>
</div>

<style>
  .ann-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .ann-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .ann-card { transition: transform .2s ease, box-shadow .2s ease; cursor: pointer; }
  .ann-card:hover { transform: translateY(-3px); }
  .ann-card:focus-visible { outline: 2px solid var(--hitech-primary); outline-offset: 2px; }
  .ann-modal-body { background: linear-gradient(135deg, #f0fdfa 0%, #fff 100%); border-radius: 12px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var modalEl = document.getElementById('annDetailModal');
  if (!modalEl) return;
  var bodyEl = document.getElementById('annModalBody');

  var openCard = function (card) {
    var tmpl = document.querySelector(card.dataset.target);
    if (!tmpl) return;
    bodyEl.innerHTML = tmpl.innerHTML;
    if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };

  document.querySelectorAll('[data-ann-open]').forEach(function (card) {
    card.addEventListener('click', function () { openCard(card); });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCard(card); }
    });
  });

  // Delegated: the send-welcome button is re-injected via innerHTML on every
  // open, so it's bound once on the (static) modal body rather than per-button.
  bodyEl.addEventListener('click', function (e) {
    var btn = e.target.closest('.ann-welcome-send');
    if (!btn) return;
    var wrap = btn.closest('.ann-welcome-action');
    var textarea = wrap.querySelector('.ann-welcome-msg');
    var alertBox = wrap.querySelector('.ann-welcome-alert');
    var message = (textarea.value || '').trim();

    var note = function (text, kind) {
      alertBox.className = 'ann-welcome-alert alert py-2 px-3 mt-2 mb-0 alert-' + kind;
      alertBox.textContent = text;
      alertBox.classList.remove('d-none');
    };

    if (!message) { note('Write a short message first.', 'warning'); return; }

    btn.disabled = true;
    fetch('{{ url('/birthdays/send') }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json', 'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ receiver_id: wrap.dataset.receiver, message: message, wish_type: 'welcome' })
    })
      .then(function (res) { return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, body: body }; }); })
      .then(function (r) {
        if (r.ok && r.body.success) {
          note('Welcome message sent.', 'success');
          btn.innerHTML = '<i class="bx bx-check"></i> Sent';
        } else {
          note(r.body.error || r.body.message || 'Could not send.', 'danger');
          btn.disabled = false;
        }
      })
      .catch(function (err) {
        note('Could not send: ' + err.message, 'danger');
        btn.disabled = false;
      });
  });
});
</script>
@endsection
