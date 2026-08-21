{{-- Important Contacts card — shared across all 5 role dashboards.
     Data built by HrNotificationService::importantContactsFor($user): the
     viewer's own reporting manager, HR and IT (never the viewer themself).

     Styles are scoped here (not left to the including page) because this
     partial is dropped into 5 different dashboards and only one of them
     happened to define .emp-contact-av — on the other 4 the avatar <img>
     had no size rule at all and rendered at native photo resolution. --}}
<style>
  .emp-contacts{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:12px;}
  .emp-contacts li{display:flex;gap:10px;align-items:center;}
  .emp-contact-av{width:36px;height:36px;border-radius:50%;object-fit:cover;flex:none;}
  .emp-contact-body{flex:1;min-width:0;}
  .emp-contact-name{font-size:.83rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .emp-contact-role{font-size:.72rem;color:var(--faint,#94a3b8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .emp-contact-act{display:flex;gap:6px;flex:none;}
  .emp-contact-act a{width:28px;height:28px;border-radius:8px;display:grid;place-items:center;background:var(--bg,#f1f5f9);color:var(--p,#0d9488);text-decoration:none;font-size:1rem;transition:all .12s;}
  .emp-contact-act a:hover{background:var(--p,#0d9488);color:#fff;}
</style>
<div class="adc-card {{ $importantContactsClass ?? 'mb-4' }}">
  <div class="adc-card-head"><div><h6 class="adc-card-title">Important Contacts</h6></div></div>
  @if(collect($importantContacts ?? [])->isEmpty())
    <div class="adc-empty adc-empty-sm"><i class="bx bx-user-voice"></i><span>No contacts available.</span></div>
  @else
    <ul class="emp-contacts">
      @foreach($importantContacts as $c)
        <li>
          @php $cAv = 'https://ui-avatars.com/api/?name='.urlencode($c['name']).'&background='.($c['bg'] ?? '0d9488').'&color=fff&bold=true'; @endphp
          <img class="emp-contact-av" src="{{ $c['photo'] ?? $cAv }}" alt="" onerror="this.src='{{ $cAv }}'">
          <div class="emp-contact-body">
            <div class="emp-contact-name">{{ $c['name'] }}</div>
            <div class="emp-contact-role">{{ $c['role'] }}</div>
          </div>
          <span class="emp-contact-act">
            {{-- The number is carried as data, not a tel: href. A tel: link is an
                 external-protocol reference and Brave prompts "access other apps
                 and services on this device" for it; dialling on click keeps the
                 behaviour without putting the scheme in the document. --}}
            @if(!empty($c['phone']))<a href="javascript:void(0);" class="adc-contact-call" data-tel="{{ preg_replace('/[^0-9+]/','',$c['phone']) }}" title="Call {{ $c['phone'] }}"><i class="bx bx-phone"></i></a>@endif
            @if(!empty($c['email']))<a href="mailto:{{ $c['email'] }}" title="Email {{ $c['email'] }}"><i class="bx bx-message-rounded"></i></a>@endif
          </span>
        </li>
      @endforeach
    </ul>
  @endif
</div>

<script>
// Bound once even if more than one dashboard partial ends up on a page.
(function () {
  if (window.__adcContactCallBound) return;
  window.__adcContactCallBound = true;
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('.adc-contact-call');
    if (!a) return;
    e.preventDefault();
    var n = a.dataset.tel;
    if (n) window.location.href = 'tel:' + n;
  });
})();
</script>
