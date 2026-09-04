@php
  $notifications = auth()->user()->unreadNotifications;
  $isUnread = $notifications->count();
@endphp
<li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1">
  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown"
     data-bs-auto-close="outside" aria-expanded="false">
    <span class="position-relative d-inline-block">
        <i class="bx bx-bell"></i>
        @if($isUnread > 0)
        <span class="badge rounded-pill bg-danger badge-dot border border-white position-absolute top-0 end-0 mt-1 me-1"></span>
        @endif
    </span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end p-0">
    <li class="dropdown-header border-bottom">
      <div class="d-flex align-items-center">
        <h6 class="mb-0 me-auto">Notifications</h6>
        @if($isUnread > 0)
          <span class="badge bg-label-primary px-3 rounded-pill">{{$isUnread}} New</span>
        @endif
      </div>
      {{-- Browser push opt-in (standard Web Push, not Firebase). --}}
      <div id="hrxPushPrompt" class="small mt-2" style="display:none;">
        <a href="javascript:void(0);" id="hrxPushEnableBtn" class="text-primary fw-semibold">
          <i class="bx bx-bell-plus me-1"></i>Enable browser notifications
        </a>
      </div>
      {{-- Browser-level "Block" can't be undone from JS at all -- this at
           least says so instead of showing nothing and looking broken. --}}
      <div id="hrxPushBlocked" class="small mt-2 text-danger" style="display:none;">
        <i class="bx bx-bell-off me-1"></i>Notifications are blocked for this site in your browser.
        <a href="javascript:void(0);" id="hrxPushBlockedHelp" class="fw-semibold">How to unblock</a>
      </div>
    </li>
    <li class="dropdown-notifications-list scrollable-container">
      <ul class="list-group list-group-flush">
        @forelse($notifications as $notification)
          @php
            $data = $notification->data;
            $title = $data['title'] ?? ($data['type'] ?? 'Notification');
            $message = $data['message'] ?? ($data['data'] ?? 'No message content');
          @endphp
          <a href="{{ $data['action_url'] ?? 'javascript:void(0);' }}" class="list-group-item list-group-item-action dropdown-notifications-item">
            <div class="d-flex align-items-start">
              <div class="flex-shrink-0 me-3">
                <div class="avatar">
                  <span class="avatar-initial">
                    <i class="bx bx-bell"></i>
                  </span>
                </div>
              </div>
              <div class="flex-grow-1">
                <h6 class="mb-1">{{ $title }}</h6>
                <small class="text-body d-block">{{ $message }}</small>
                <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
              </div>
              <div class="flex-shrink-0 ms-2">
                <div class="unread-dot-indicator"></div>
              </div>
            </div>
          </a>
        @empty
          <li class="list-group-item text-center py-5">
            <i class="bx bx-bell-off fs-1 text-muted mb-2"></i>
            <p class="text-muted mb-0">No new notifications</p>
          </li>
        @endforelse
      </ul>
    </li>
    <li class="view-all-footer border-top">
        <a href="{{route('notifications.myNotifications')}}">
          View all notifications
        </a>
    </li>
  </ul>
</li>

@if(config('webpush.public_key'))
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var prompt = document.getElementById('hrxPushPrompt');
    var btn = document.getElementById('hrxPushEnableBtn');
    var blocked = document.getElementById('hrxPushBlocked');
    var blockedHelp = document.getElementById('hrxPushBlockedHelp');
    if (!prompt || !btn || !window.HrxWebPush || !window.HrxWebPush.isSupported()) return;

    // A manual fallback next to the bell for anyone who dismissed the
    // auto-popup (see webpush-subscribe.js autoInit/showBanner) and wants
    // it back later. Shown regardless of current permission state -- a
    // 'granted' permission with no saved subscription (a previous save
    // that failed) needs this to still be reachable, not hidden forever.
    // 'denied' can't be fixed from JS at all -- that gets its own message
    // instead of showing nothing, which just looked broken.
    window.HrxWebPush.permissionState().then(function (state) {
      if (state === 'denied') {
        if (blocked) blocked.style.display = '';
      } else {
        prompt.style.display = '';
      }
    });

    if (blockedHelp) {
      blockedHelp.addEventListener('click', function () {
        alert(
          'Your browser is blocking notifications for this site.\n\n' +
          'Chrome/Edge/Brave: click the icon just left of the address bar ' +
          '(lock or "i" icon) -> Site settings / Permissions -> set ' +
          'Notifications to "Allow" -> reload this page.\n\n' +
          'Safari (Mac): Safari menu -> Settings for This Website -> ' +
          'Notifications -> Allow.'
        );
      });
    }

    btn.addEventListener('click', function () {
      btn.textContent = 'Enabling...';
      window.HrxWebPush.subscribe('{{ config('webpush.public_key') }}', '{{ csrf_token() }}')
        .then(function () {
          btn.innerHTML = '<i class="bx bx-bell-check me-1"></i>Notifications enabled';
        })
        .catch(function (err) {
          btn.innerHTML = '<i class="bx bx-bell-plus me-1"></i>Enable browser notifications';
          alert('Could not enable notifications: ' + err.message);
        });
    });
  });
</script>
@endif
