{{-- One side of a call: name (and role), then phone and email as links. $p is null when nothing was recorded; $empty is what to say then. --}}
@if($p && ($p['name'] || $p['phone'] || $p['email']))
    <div class="cp-party-name">{{ $p['name'] ?: 'Not in contacts' }}@if(!empty($p['role']))<span class="cp-party-role">{{ $p['role'] }}</span>@endif</div>
    <div class="cp-party-meta">
        @if($p['phone'])<a href="tel:{{ $p['phone'] }}"><i class="bx bx-phone"></i>{{ $p['phone'] }}</a>@endif
        @if($p['email'])<a href="mailto:{{ $p['email'] }}"><i class="bx bx-envelope"></i>{{ $p['email'] }}</a>@endif
        @if(!$p['phone'] && !$p['email'])<span class="text-muted">no phone or email on file</span>@endif
    </div>
@else
    <span class="text-muted small">{{ $empty }}</span>
@endif
