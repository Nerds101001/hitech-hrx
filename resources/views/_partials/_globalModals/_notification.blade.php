<div class="offcanvas offcanvas-end" tabindex="-1" id="offCanvasNotification" aria-labelledby="offCanvasNotification">
  <div class="offcanvas-header border-bottom">
    <h5 id="offCanvasNotificationLabel" class="offcanvas-title">@lang('Notifications')</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>

  <!-- Tab Filters for Notification Categories -->
  <div class="offcanvas-body mx-0 p-4">
    <ul class="nav nav-tabs" id="notificationTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance"
                type="button" role="tab" aria-controls="attendance" aria-selected="true">
          <i class="bx bx-time-five"></i> @lang('Attendance')
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="approvals-tab" data-bs-toggle="tab" data-bs-target="#approvals" type="button"
                role="tab" aria-controls="approvals" aria-selected="false">
          <i class="bx bx-check-circle"></i> @lang('Approvals')
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="others-tab" data-bs-toggle="tab" data-bs-target="#others" type="button" role="tab"
                aria-controls="others" aria-selected="false">
          <i class="bx bx-bell"></i> @lang('Other')
        </button>
      </li>
    </ul>

    <!-- Tab Content for Notifications -->
    <div class="tab-content mt-4" id="notificationTabContent">

      <!-- Attendance Notifications -->
      <div class="tab-pane fade show active" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
        <div class="notification-list">
          <!-- Sample Data for Attendance Notifications -->
          @foreach(range(1, 10) as $i)
            <div class="notification-item d-flex align-items-start p-3 border-bottom">
              <i class="bx bx-user-circle me-3 fs-4 text-primary"></i>
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between">
                  <h6 class="mb-1">@lang('Employee {{ $i }}')</h6>
                  <small class="text-muted">{{ now()->subHours(rand(1, 24))->format('h:i A') }}</small>
                </div>
                <p class="mb-1">@lang('Missed clock-in for morning shift.')</p>
                <button class="btn btn-sm btn-outline-primary">@lang('Mark as Read')</button>
              </div>
            </div>
          @endforeach
        </div>
      </div>

      <!-- Approval Notifications -->
      <div class="tab-pane fade" id="approvals" role="tabpanel" aria-labelledby="approvals-tab">
        <div class="notification-list">
          <!-- Sample Data for Approval Notifications -->
          @foreach(range(1, 10) as $i)
            <div class="notification-item d-flex align-items-start p-3 border-bottom">
              <i class="bx bx-file me-3 fs-4 text-warning"></i>
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between">
                  <h6 class="mb-1">@lang('Employee {{ $i }}')</h6>
                  <small class="text-muted">{{ now()->subDays(rand(1, 5))->format('M d') }}</small>
                </div>
                <p class="mb-1">@lang('Expense request for {{ rand(100, 1000) }} ' . ($settings->currency ?? 'USD') . ' awaiting approval.')</p>
                <button class="btn btn-sm btn-outline-primary">@lang('Mark as Read')</button>
              </div>
            </div>
          @endforeach
        </div>
      </div>

      <!-- Other Notifications -->
      <div class="tab-pane fade" id="others" role="tabpanel" aria-labelledby="others-tab">
        <div class="notification-list">
          <!-- Sample Data for Other Notifications -->
          @foreach(range(1, 10) as $i)
            <div class="notification-item d-flex align-items-start p-3 border-bottom">
              <i class="bx bx-info-circle me-3 fs-4 text-info"></i>
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between">
                  <h6 class="mb-1">@lang('System Notification')</h6>
                  <small class="text-muted">{{ now()->subHours(rand(1, 72))->format('M d, h:i A') }}</small>
                </div>
                <p class="mb-1">@lang('New updates available for review. Please check the dashboard.')</p>
                <button class="btn btn-sm btn-outline-primary">@lang('Mark as Read')</button>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <!-- Footer with Clear All and View All options -->
  <div class="offcanvas-footer p-3 border-top d-flex justify-content-between">
    <button class="btn btn-outline-secondary">@lang('Clear All')</button>
    <button class="btn btn-primary">@lang('Mark all as read')</button>
  </div>
</div>
