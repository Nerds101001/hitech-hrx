{{-- Delegate hub: top-navbar switcher for vacant-position access delegation.
     mode 'available' → list the accounts delegated to me with "Login as" buttons.
     mode 'acting'    → I'm inside a delegated account: show who I am + exit control. --}}
@if(isset($delegateHub))
<li class="nav-item dropdown me-2 me-xl-1">
  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside"
     title="{{ $delegateHub['mode'] === 'acting' ? 'You are logged in as a delegated position' : 'Delegate access available' }}">
    <span class="position-relative d-inline-flex">
      <i class="bx bx-transfer-alt bx-md" style="{{ $delegateHub['mode'] === 'acting' ? 'color:#b45309;' : '' }}"></i>
      @if($delegateHub['mode'] === 'acting')
        <span class="badge rounded-pill bg-warning position-absolute" style="top:-4px; right:-8px; font-size:9px; padding:3px 5px;">ON</span>
      @else
        <span class="badge rounded-pill bg-danger position-absolute" style="top:-4px; right:-8px; font-size:9px; padding:3px 5px;">{{ $delegateHub['accounts']->count() }}</span>
      @endif
    </span>
  </a>
  {{-- z-index above the mandatory-policy modal (Bootstrap's default 1055/1050 for the
       modal/backdrop): this dropdown is the one delegate-exit control that stays
       visible at EVERY screen width (the navbar strip below hides on phone), so it
       must reach above that modal too or an admin on a phone with a pending policy
       has no way back to their own account at all. Bootstrap dropdown-menu is
       already position:absolute, so z-index alone takes effect here. --}}
  <ul class="dropdown-menu dropdown-menu-end p-0" style="min-width:320px; border-radius:14px; overflow:hidden; z-index:1100;">
    <li>
      <div class="px-3 py-2" style="background:linear-gradient(135deg,#78350f,#b45309);">
        <div class="text-white fw-bold" style="font-size:13px;"><i class="bx bx-transfer-alt me-1"></i> Delegate Access</div>
        <div class="text-white-50" style="font-size:11px;">
          {{ $delegateHub['mode'] === 'acting' ? 'You are working as a delegated position' : 'Vacant positions assigned to you' }}
        </div>
      </div>
    </li>

    @if($delegateHub['mode'] === 'acting')
      <li class="px-3 py-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="avatar-initial rounded-circle bg-label-warning d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;font-weight:700;">
            <i class="bx bx-user-pin"></i>
          </span>
          <div style="line-height:1.3;">
            <div class="fw-bold" style="font-size:13px;">{{ $delegateHub['actingAs'] }}</div>
            <div class="text-muted" style="font-size:11px;">Delegate session · your account: {{ $delegateHub['originalName'] }}</div>
          </div>
        </div>
        <form method="POST" action="{{ route('user.delegation.exit') }}" class="mb-0">
          @csrf
          <button type="submit" class="btn btn-sm btn-danger w-100 fw-bold" style="border-radius:8px;">
            <i class="bx bx-log-out-circle me-1"></i> Exit Delegate Login
          </button>
        </form>
      </li>
    @else
      @foreach($delegateHub['accounts'] as $acct)
        <li class="px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
          <div class="d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2" style="min-width:0;">
              <span class="avatar-initial rounded-circle bg-label-warning d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:34px;height:34px;font-weight:700;font-size:12px;">
                {{ strtoupper(substr($acct->first_name, 0, 1) . substr($acct->last_name, 0, 1)) }}
              </span>
              <div style="line-height:1.25; min-width:0;">
                <div class="fw-semibold text-truncate" style="font-size:12.5px;">{{ $acct->first_name }} {{ $acct->last_name }}</div>
                <div class="text-muted" style="font-size:10.5px;">Vacant position · delegated to you</div>
              </div>
            </div>
            <form method="POST" action="{{ route('user.delegation.switch', $acct->id) }}" class="mb-0 flex-shrink-0">
              @csrf
              <button type="submit" class="btn btn-sm fw-bold" style="background:#b45309;color:#fff;border-radius:8px;font-size:11px;">
                <i class="bx bx-log-in-circle me-1"></i>Login as
              </button>
            </form>
          </div>
        </li>
      @endforeach
    @endif
  </ul>
</li>
@endif
