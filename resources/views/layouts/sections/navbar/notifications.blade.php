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
