{{--
    Delegate-session bar for full-screen pages (blank layout: onboarding form,
    training portal), which have no top navbar and so no delegate strip.
    Without it an admin who switched into an onboarding account had no way
    back to their own account short of logging out. Same amber as the navbar
    strip - the colour that means "you are not yourself right now".
--}}
@if(isset($delegateHub) && $delegateHub['mode'] === 'acting')
  <div class="d-flex align-items-center flex-wrap gap-2 px-3 py-2"
       style="background: linear-gradient(135deg, #78350f, #b45309); color: #fff; position: relative; z-index: 1100;"
       role="status" aria-live="polite">
    <i class="bx bx-transfer-alt fs-5"></i>
    <span class="small">
      <b class="text-uppercase" style="letter-spacing: .05em;">Delegate access active</b>
      &middot; working as {{ $delegateHub['actingAs'] }} &middot; you are {{ $delegateHub['originalName'] }}
    </span>
    <form method="POST" action="{{ route('user.delegation.exit') }}" class="mb-0 ms-auto">
      @csrf
      <button type="submit" class="btn btn-sm fw-bold" style="background: #fff; color: #b45309; border-radius: 999px;">
        <i class="bx bx-log-out-circle"></i> Exit
      </button>
    </form>
  </div>
@endif
