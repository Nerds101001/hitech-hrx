@php
  $notifications = auth()->user()->unreadNotifications;
  $isUnread = $notifications->count();
  // Only these types pop up on load; every other notification type still
  // shows in the dropdown below exactly as before, it just doesn't
  // interrupt the page. Scoped narrowly on purpose so this doesn't turn
  // into "every notification in the app now pops a toast" - that's a much
  // bigger UX decision than what was actually asked for (task assignment).
  $popupTypes = ['App\\Notifications\\Task\\TaskAssigned', 'App\\Notifications\\Task\\TaskObjection'];
  $popupNotifications = $notifications->whereIn('type', $popupTypes)->values();
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
    </li>
    <li class="dropdown-notifications-list scrollable-container">
      <ul class="list-group list-group-flush">
        @forelse($notifications as $notification)
          @php
            $data = $notification->data;
            $title = $data['title'] ?? ($data['type'] ?? 'Notification');
            $message = $data['message'] ?? ($data['data'] ?? 'No message content');
          @endphp
          <li class="list-group-item list-group-item-action dropdown-notifications-item">
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
          </li>
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

@if($popupNotifications->isNotEmpty())
  {{-- Pops once per notification, then marks it read so a later page load
       (this one already loaded $notifications before any of these are
       marked) never fires it again — the dropdown above still shows it,
       same as any other notification, just no longer unread. Plain
       Bootstrap Toast, not SweetAlert2: this partial is loaded on every
       tenant page via the navbar, and SweetAlert2 is only opted into
       per-page — the exact bug just fixed on Task Management (Swal called
       without ever being loaded) is not one to risk repeating here. --}}
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    @foreach($popupNotifications as $n)
      <div class="toast align-items-center border-0 text-white bg-primary js-task-popup" role="alert" aria-live="assertive" aria-atomic="true"
           data-notification-id="{{ $n->id }}" data-action-url="{{ $n->data['action_url'] ?? '' }}">
        <div class="d-flex">
          <div class="toast-body">
            <strong>{{ $n->data['title'] ?? 'Notification' }}</strong><br>
            {{ $n->data['message'] ?? '' }}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    @endforeach
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('.js-task-popup').forEach(function (el) {
        const toast = new bootstrap.Toast(el, { delay: 8000 });
        toast.show();

        if (el.dataset.actionUrl) {
          el.querySelector('.toast-body').style.cursor = 'pointer';
          el.querySelector('.toast-body').addEventListener('click', function () {
            window.location.href = el.dataset.actionUrl;
          });
        }

        // Fire-and-forget: this is a "don't show it again" marker, not
        // something the user needs to wait on or be told about either way.
        fetch("{{ url('/notifications/markAsRead') }}/" + el.dataset.notificationId, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': "{{ csrf_token() }}",
            'Accept': 'application/json',
          },
        }).catch(function () {});
      });
    });
  </script>
@endif
