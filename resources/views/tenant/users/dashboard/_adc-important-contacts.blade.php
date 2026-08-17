{{-- Important Contacts card — shared across all 5 role dashboards.
     Data built by HrNotificationService::importantContactsFor($user):
     the viewer's own reporting manager (if any) + HR. Never the viewer
     themself. See that method for why a hardcoded IT contact isn't here. --}}
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
            @if(!empty($c['phone']))<a href="tel:{{ preg_replace('/[^0-9+]/','',$c['phone']) }}" title="Call {{ $c['phone'] }}"><i class="bx bx-phone"></i></a>@endif
            @if(!empty($c['email']))<a href="mailto:{{ $c['email'] }}" title="Email {{ $c['email'] }}"><i class="bx bx-message-rounded"></i></a>@endif
          </span>
        </li>
      @endforeach
    </ul>
  @endif
</div>
