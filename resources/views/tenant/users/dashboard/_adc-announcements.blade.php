{{--
  Latest Announcements — shared by the admin, HR, manager, finance and employee
  dashboards so the feed cannot drift between them.

  Expects $recentNotices from App\Support\DashboardAnnouncements::forUser().
  Each row opens a detail popup; a "Welcome to the team" post also carries the
  joiner's designation, department and email plus a box to welcome them.

  Self-contained: the modal and its script live here, guarded so including the
  partial twice on one page cannot bind the handlers twice.
--}}
<div class="adc-card mb-4">
  <div class="adc-card-head">
    <div><h6 class="adc-card-title">Latest Announcements</h6></div>
    <div class="d-flex align-items-center gap-3">
      {{-- Publishing lives in the Announcements module (same page as the sidebar). --}}
      @if(auth()->user()->hasRole(['admin','hr']))
        <a href="{{ url('/announcements') }}" class="adc-link"><i class="bx bx-plus"></i> Publish</a>
      @endif
      <a href="{{ url('/announcements/feed') }}" class="adc-link">View all</a>
    </div>
  </div>
  @if(collect($recentNotices ?? [])->isEmpty())
    <div class="adc-empty adc-empty-sm"><i class="bx bx-megaphone"></i><span>No current announcements.</span></div>
  @else
    <ul class="adc-feed">
      @foreach(collect($recentNotices)->take(5) as $n)
        @php
          $j = $n->joiner ?? null;
          // Announcements are stored as HTML. Flatten to text here so the popup
          // can never inject stored markup into the page.
          $body = trim(html_entity_decode(strip_tags(preg_replace('/<\s*br\s*\/?>/i', "\n", (string) ($n->content ?? $n->description ?? '')))));
          $body = preg_replace('/\n{2,}/', "\n", $body);
        @endphp
        <li role="button" tabindex="0" class="adc-ann-open"
            data-title="{{ $n->title ?? 'Announcement' }}"
            data-when="{{ optional($n->published_at ?? $n->created_at)->format('d M Y, h:i A') }}"
            data-body="{{ $body }}"
            @if($j)
              data-uid="{{ $j['id'] }}" data-name="{{ $j['name'] }}" data-desig="{{ $j['designation'] }}"
              data-dept="{{ $j['department'] }}" data-email="{{ $j['email'] }}"
              data-joined="{{ $j['joined'] }}" data-photo="{{ $j['photo'] }}"
            @endif>
          <span class="adc-feed-dot adc-tone-info"><i class="bx {{ $j ? 'bx-user-plus' : 'bx-news' }}"></i></span>
          <div class="adc-feed-body">
            <div class="adc-feed-title">{{ $n->title ?? 'Notice' }}</div>
            <div class="adc-feed-meta">{{ optional($n->published_at ?? $n->created_at)->diffForHumans() }}@if($j) &middot; {{ $j['department'] }}@endif</div>
          </div>
          <i class="bx bx-chevron-right text-muted"></i>
        </li>
      @endforeach
    </ul>
  @endif
</div>

<div class="modal fade" id="adcAnnModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="adcAnnTitle">Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="adcAnnJoiner" class="d-none">
          <div class="text-center mb-3">
            <img id="adcAnnPhoto" src="" alt=""
                 style="width:84px;height:84px;border-radius:50%;object-fit:cover;border:3px solid var(--hitech-bg-soft,#f1f5f9);">
            <div class="fw-bold mt-2" style="font-size:1.05rem;color:#0f172a;" id="adcAnnName"></div>
            <div class="text-muted" style="font-size:.8rem;" id="adcAnnDesig"></div>
            <div class="text-muted" style="font-size:.78rem;" id="adcAnnDept"></div>
          </div>
          <label class="form-label small fw-semibold" for="adcAnnMsg">Send them a welcome</label>
          <textarea id="adcAnnMsg" class="form-control" rows="3" maxlength="1000"></textarea>
          <div class="form-text">Delivered as an in-app notification.</div>
          <div id="adcAnnAlert" class="alert d-none py-2 px-3 mt-2 mb-0" role="alert"></div>
        </div>
        <div id="adcAnnPlain">
          <p id="adcAnnBody" class="mb-1" style="white-space:pre-line;color:#475569;"></p>
          <div class="adc-card-sub" id="adcAnnWhen"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary d-none" id="adcAnnSend"><i class="bx bx-send"></i> Send</button>
      </div>
    </div>
  </div>
</div>

<style>
  .adc-ann-open{cursor:pointer;border-radius:8px;transition:background .15s ease;}
  .adc-ann-open:hover,.adc-ann-open:focus-visible{background:var(--hitech-bg-soft,#f1f5f9);}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var modalEl = document.getElementById('adcAnnModal');
  if (!modalEl || modalEl.dataset.bound) return;   // guard against a double include
  modalEl.dataset.bound = '1';

  var set = function (id, v) { var n = document.getElementById(id); if (n) n.textContent = v || ''; };
  var avatar = function (n) {
    return 'https://ui-avatars.com/api/?name=' + encodeURIComponent(n) + '&background=0d9488&color=fff&bold=true&size=168';
  };
  var alertBox = document.getElementById('adcAnnAlert');
  var sendBtn = document.getElementById('adcAnnSend');
  var msgBox = document.getElementById('adcAnnMsg');
  var current = null;

  var note = function (t, kind) {
    if (!alertBox) return;
    alertBox.className = 'alert py-2 px-3 mt-2 mb-0 alert-' + kind;
    alertBox.textContent = t;
  };

  document.querySelectorAll('.adc-ann-open').forEach(function (row) {
    var open = function (e) {
      e.preventDefault();
      var d = row.dataset;
      current = d;
      set('adcAnnTitle', d.title);
      if (alertBox) { alertBox.className = 'alert d-none py-2 px-3 mt-2 mb-0'; alertBox.textContent = ''; }

      var joiner = document.getElementById('adcAnnJoiner');
      var plain = document.getElementById('adcAnnPlain');
      if (d.name) {
        joiner.classList.remove('d-none');
        plain.classList.add('d-none');
        sendBtn.classList.remove('d-none');
        sendBtn.disabled = false;
        set('adcAnnName', d.name);
        set('adcAnnDesig', d.desig);
        set('adcAnnDept', d.dept + (d.joined ? ' · joined ' + d.joined : ''));
        var img = document.getElementById('adcAnnPhoto');
        img.src = d.photo || avatar(d.name);
        img.onerror = function () { this.src = avatar(d.name); };
        msgBox.value = 'Welcome aboard, ' + (d.name || '').split(' ')[0] + '! Great to have you with us.';
      } else {
        joiner.classList.add('d-none');
        plain.classList.remove('d-none');
        sendBtn.classList.add('d-none');
        set('adcAnnBody', d.body);
        set('adcAnnWhen', d.when ? 'Published ' + d.when : '');
      }
      if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };
    row.addEventListener('click', open);
    row.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') open(e); });
  });

  if (sendBtn) {
    sendBtn.addEventListener('click', async function () {
      if (!current || !current.uid) { note('Could not identify this person.', 'danger'); return; }
      var message = (msgBox.value || '').trim();
      if (!message) { note('Write a short message first.', 'warning'); return; }
      sendBtn.disabled = true;
      note('Sending…', 'info');
      try {
        var res = await fetch('{{ url('/birthdays/send') }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json', 'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({ receiver_id: current.uid, message: message, wish_type: 'welcome' })
        });
        var body = await res.json().catch(function () { return {}; });
        if (res.ok && body.success) {
          note('Welcome message sent.', 'success');
          setTimeout(function () { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); }, 1100);
        } else {
          note(body.error || body.message || ('HTTP ' + res.status), 'danger');
          sendBtn.disabled = false;
        }
      } catch (err) {
        note('Could not send: ' + err.message, 'danger');
        sendBtn.disabled = false;
      }
    });
  }
});
</script>
