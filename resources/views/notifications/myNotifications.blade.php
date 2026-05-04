@php
  $configData = Helper::appClasses();
@endphp
@extends('layouts/layoutMaster')
@section('title', __('My Notifications'))
<!-- Vendor Styles -->
@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/css/notification.css',
  ])
@endsection
<!-- Vendor Scripts -->
@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  ])
@endsection
@section('page-script')
  @vite(['resources/js/main-datatable.js'])
  <script>
    $(document).ready(function() {
      // Handle Mark all as read button click
      $('#mark-all-read-btn').on('click', function(e) {
        e.preventDefault();
        
        $.ajax({
          url: $(this).attr('href'),
          type: 'GET',
          success: function(response) {
            // Update all notification rows
            $('.unread').removeClass('unread').addClass('read');
            $('.notification-dot').remove();
            $('form[action^="{{route('notifications.markAsRead', '')}}"]').each(function() {
              $(this).replaceWith('<span class="badge bg-success">Read</span>');
            });
            
            // Show success message
            alert('All notifications marked as read.');
          },
          error: function(error) {
            console.error('Error marking all notifications as read:', error);
            alert('Failed to mark all notifications as read.');
          }
        });
      });
      
      // Submit mark as read form via AJAX
      $('form[action^="{{route('notifications.markAsRead', '')}}"]').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var notificationId = form.attr('action').split('/').pop();
        var notificationRow = $('#notification-' + notificationId);
        
        $.ajax({
          url: form.attr('action'),
          type: 'POST',
          data: form.serialize(),
          success: function(response) {
            // Update visual cues
            notificationRow.removeClass('unread').addClass('read');
            notificationRow.find('.notification-dot').remove();
            form.replaceWith('<span class="badge bg-success">Read</span>');
            
            // Show success message
            if(response.success) {
              alert(response.success);
            }
          },
          error: function(error) {
            console.error('Error marking notification as read:', error);
            alert('Failed to mark notification as read.');
          }
        });
      });
    });
  </script>
@endsection

@section('content')
  <div class="row">
    <div class="col">
      <h4>@lang('My Notifications')</h4>
    </div>
    <div class="col text-end">
      <a class="btn btn-primary" href="{{route('notifications.marksAllAsRead')}}" id="mark-all-read-btn">
        <i class="bx bx-check-all bx-sm me-0 me-sm-2"></i> @lang('Mark all as read')
      </a>
    </div>
  </div>
  <!-- Notification table card -->
  <div class="card">
    <div class="card-datatable table-responsive">
      <table id="datatable" class="datatables-users table border-top">
        <thead>
        <tr>
          <th></th>
          <th>@lang('Id')</th>
          <th>@lang('From')</th>
          <th>@lang('Type')</th>
          <th>@lang('Title')</th>
          <th>@lang('Message')</th>
          <th>@lang('Actions')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($notifications as $notification)
          @php
              $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
              $title = $data['title'] ?? 'System Notification';
              $message = $data['message'] ?? 'No message content';
              $fromName = $data['from_name'] ?? ($data['sender_name'] ?? 'System');
              $fromEmail = $data['from_email'] ?? ($data['sender_email'] ?? 'noreply@hitech.com');
              $type = str_replace(['App\\Notifications\\', 'Notification'], '', $notification->type);
          @endphp
          <tr id="notification-{{$notification->id}}" class="{{($notification->read_at === null) ? 'unread' : 'read'}}">
            <td>
              @if($notification->read_at === null)
                <span class="notification-dot"></span>
              @endif
            </td>
            <td>
              {{$loop->iteration}}
            </td>
            <td>
              <div class="d-flex justify-content-start align-items-center user-name">
                <div class="avatar-wrapper">
                  <div class="avatar avatar-sm me-3">
                    <span class="avatar-initial rounded-circle bg-label-primary fw-bold">{{ substr($fromName, 0, 1) }}</span>
                  </div>
                </div>
                <div class="d-flex flex-column">
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;">{{ $fromName }}</span>
                  <small class="text-muted" style="font-size: 0.75rem;">{{ $fromEmail }}</small>
                </div>
              </div>
            </td>
            <td>
                <span class="badge bg-label-info rounded-pill px-3">{{ $type }}</span>
            </td>
            <td>
                <span class="fw-extrabold text-dark">{{ $title }}</span>
            </td>
            <td style="max-width: 300px;">
                <p class="mb-0 text-muted small text-truncate-2" title="{{ $message }}">{{ $message }}</p>
            </td>
            <td>
              @if($notification->read_at === null)
                <form action="{{route('notifications.markAsRead', $notification->id)}}" method="POST">
                  @csrf
                  <button type="submit" class="btn btn-icon btn-label-primary btn-sm me-2" data-bs-toggle="tooltip"
                          title="Mark as read"><i class="bx bx-check"></i>
                  </button>
                </form>
              @else
                <span class="badge bg-label-success rounded-pill">Read</span>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
