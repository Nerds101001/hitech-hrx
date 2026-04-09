@php
  $title = 'Card View';
@endphp
@extends('layouts/layoutMaster')

@section('title', $title)

@section('page-style')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    /* General Styles */
    .employee-card {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      background: #fff;
    }

    .employee-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
    }

    /* Profile Section */
    .profile-section {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 10px 15px;
      border-bottom: 1px solid #f1f1f1;
    }

    .avatar-wrapper {
      width: 60px;
      height: 60px;
      overflow: hidden;
      border-radius: 50%;
      border: 2px solid #ddd;
      flex-shrink: 0;
    }

    .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .details h6 {
      margin: 0;
      font-size: 16px;
      font-weight: bold;
    }

    .details small {
      font-size: 12px;
      color: #6c757d;
    }

    /* Status Icons */
    .status-icons {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 15px;
      border-bottom: 1px solid #f1f1f1;
    }

    .status-icons div {
      text-align: center;
    }

    .status-icons i {
      font-size: 18px;
      margin-bottom: 5px;
    }

    .status-icons span {
      font-size: 12px;
    }

    /* Attendance Info */
    .attendance-info {
      padding: 10px 15px;
      border-bottom: 1px solid #f1f1f1;
      font-size: 14px;
    }

    .attendance-info span {
      display: block;
    }

    /* Metrics */
    .metrics {
      padding: 10px 15px;
      display: flex;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      border-bottom: 1px solid #f1f1f1;
    }

    .metrics span {
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    /* Footer */
    .card-footer {
      padding: 10px 15px;
      background: #f9fafb;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      color: #6c757d;
    }

    .card-footer a {
      font-size: 12px;
      color: #007bff;
      text-decoration: none;
    }

    .badge-status {
      font-size: 0.85rem;
      padding: 4px 8px;
      border-radius: 15px;
    }

    /* Avatar Wrapper */
    .avatar-wrapper {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      border: 2px solid #ddd;
      background-color: #f5f5f5;
      overflow: hidden;
      flex-shrink: 0;
    }

    /* Avatar Image */
    .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* Avatar Initials */
    .avatar-initial {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      height: 100%;
      font-size: 1rem;
      font-weight: bold;
      color: #555;
      text-transform: uppercase;
      background: #e0e0e0;
    }
  </style>
@endsection

@section('content')

  <!-- Filters -->
  <div class="filter-tabs d-flex align-items-center justify-content-between mb-4">
    <div>
      <button class="btn btn-primary active" data-filter="all">All</button>
      <button class="btn btn-secondary" data-filter="on-duty">On Duty</button>
      <button class="btn btn-secondary" data-filter="inactive">Inactive</button>
      <button class="btn btn-secondary" data-filter="off-duty">Off Duty</button>
    </div>
    <div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="autoRefreshSwitch" checked>
        <label class="form-check-label" for="autoRefreshSwitch">Auto Refresh</label>
      </div>
    </div>
  </div>

  <!-- Employee Grid -->
  <div class="row g-4">
    @foreach($teams as $team)
      <div class="col-12">
        <h5 class="mb-3">{{ $team['name'] }} ({{ $team['totalEmployees'] }} Employees)</h5>
        <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-3">
          @foreach($team['cardItems'] as $cardItem)
            <div class="col">
              <div class="card employee-card">

                <!-- Profile Section -->
                <div class="profile-section">
                  <div class="avatar-wrapper">
                    @if($cardItem['profilePicture'])
                      <img src="{{ $cardItem['profilePicture'] }}" alt="Avatar">
                    @else
                      <span
                        class="avatar-initial rounded-circle bg-label-primary text-center">{{ $cardItem['initials'] }}</span>
                    @endif
                  </div>
                  <div class="details">
                    <h6>{{ $cardItem['name'] }}</h6>
                    <small>Code: {{ $cardItem['employeeCode'] }}</small>
                  </div>
                </div>

                <!-- Status Icons -->
                <div class="status-icons">
                  <div id="{{ $cardItem['id'].'BatteryLevel' }}">
                    <i class="bi bi-battery-half text-primary"></i>
                    <span>{{ $cardItem['batteryLevel'] }}%</span>
                  </div>
                  <div id="{{ $cardItem['id'].'IsWifiOn' }}">
                    <i class="bi {{ $cardItem['isWifiOn'] ? 'bi-wifi text-success' : 'bi-wifi-off text-danger' }}"></i>
                    <span>WiFi</span>
                  </div>
                  <div id="{{ $cardItem['id'].'IsGpsOn' }}">
                    <i
                      class="bi {{ $cardItem['isGpsOn'] ? 'bi-geo-alt-fill text-success' : 'bi-geo-alt-fill text-danger' }}"></i>
                    <span>GPS</span>
                  </div>
                </div>

                <!-- Attendance Info -->
                <div class="attendance-info">
                  <span class="d-flex justify-content-between align-items-center"><strong>In Time:</strong> {{ $cardItem['attendanceInAt'] ?? 'N/A' }}</span>
                  <span class="d-flex justify-content-between align-items-center mt-1"><strong>Out Time:</strong> {{ $cardItem['attendanceOutAt'] ?? 'N/A' }}</span>
                </div>

                <!-- Metrics -->
                <div class="metrics">
                  <span><i class="bi bi-geo-alt text-primary"></i> {{ $cardItem['visitsCount'] }} Visits</span>
                  <span><i class="bi bi-cart text-success"></i> {{ $cardItem['ordersCount'] }} Orders</span>
                  <span><i class="bi bi-clipboard text-info"></i> {{ $cardItem['formsFilled'] }} Forms</span>
                </div>

                <!-- Footer -->
                <div class="card-footer">
                  <a href="{{route('liveLocationView')}}">
                    <i class="bi bi-map"></i> Open in Maps
                  </a>
                  <span>Last Updated: {{ $cardItem['updatedAt'] }}</span>
                </div>

              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  </div>
@endsection



@section('page-script')
  <script>
    $(function () {
      // Start the auto-refresh timer
      let refreshTimer;

      $('#autoRefreshSwitch').on('change', function () {
        if ($(this).is(':checked')) {
          startAutoRefresh();
        } else {
          stopAutoRefresh();
        }
      });

      // Start auto-refresh
      function startAutoRefresh() {
        refreshTimer = setInterval(() => {
          console.log('Auto-refresh triggered');
          fetchData();
        }, 2000);
      }

      // Stop auto-refresh
      function stopAutoRefresh() {
        if (refreshTimer) {
          clearInterval(refreshTimer);
          console.log('Auto-refresh stopped');
        }
      }

      // Fetch data from the server
      function fetchData() {
        $.ajax({
          type: 'GET',
          url: '{{ route("cardViewAjax") }}',
          success: function (response) {
            updateDashboard(response);
          },
          error: function (response) {
            console.error('Error fetching data:', response.responseText);
          }
        });
      }

      // Update the dashboard with fetched data
      function updateDashboard(data) {
        let onlineCount = 0,
          offlineCount = 0,
          inactiveCount = 0,
          offDutyCount = 0;

        data.forEach((user) => {
          if (user.isOnline) {
            onlineCount++;
            updateUserStatus(user.id, user, true);
          } else {
            offlineCount++;
            updateUserStatus(user.id, user, false);
          }
          // Increment counters based on attendance
          if (user.attendanceInAt && !user.attendanceOutAt) {
            inactiveCount++;
          } else if (!user.attendanceInAt && !user.attendanceOutAt) {
            offDutyCount++;
          }
        });

        // Update counters on the UI
        $('#onlinecount').text(onlineCount);
        $('#offlinecount').text(offlineCount);
        $('#inactivecount').text(inactiveCount);
        $('#offdutycount').text(offDutyCount);
        $('#allcount').text(onlineCount + offlineCount + inactiveCount + offDutyCount);
      }

      // Update user-specific data
      function updateUserStatus(userId, user, isOnline) {
        const locationSelector = `#${userId}location`;
        const batterySelector = `#${userId}BatteryLevel`;
        const gpsSelector = `#${userId}IsGpsOn`;
        const wifiSelector = `#${userId}IsWifiOn`;

        if (isOnline) {
          // Update location
          $(locationSelector).html(
            `<a href="http://www.google.com/maps/place/${user.latitude},${user.longitude}" target="_blank" class="text-primary"><i class="bx bx-map"></i> Open in maps</a>`
          );

          // Update battery level
          $(batterySelector).html(
            `<i class="bi ${getBatteryIcon(user.batteryLevel)}"></i> <span>${user.batteryLevel}%</span>`
          );

          // Update GPS status
          $(gpsSelector).html(
            `<i class="bx ${user.isGpsOn ? 'bi bi-crosshair text-success' : 'bi bi-crosshair text-danger'}"></i> <span>GPS</span>`
          );

          // Update WiFi status
          $(wifiSelector).html(
            `<i class="bx ${user.isWifiOn ? 'bx-wifi text-success' : 'bx-wifi-off text-danger'}"></i></i> <span>WiFi</span>`
          );
        } else {
          // Clear data for offline users
          $(locationSelector).html('<span class="text-muted">Offline</span>');
          $(batterySelector).html('<i class="bx bx-battery text-muted"></i>');
          $(gpsSelector).html('<i class="bi bi-crosshair text-muted"></i>');
          $(wifiSelector).html('<i class="bx bx-wifi-off text-muted"></i>');
        }
      }

      // Determine the appropriate battery icon based on percentage
      function getBatteryIcon(batteryLevel) {
        if (batteryLevel > 75) {
          return 'bi-battery-full text-success';
        } else if (batteryLevel > 50) {
          return 'bi-battery-half text-primary';
        } else if (batteryLevel > 25) {
          return 'bi-battery-half text-warning';
        } else {
          return 'bi-battery text-danger';
        }
      }

      // Start auto-refresh on page load
      startAutoRefresh();

      // ── Filter buttons ──────────────────────────────────────────
      document.querySelectorAll('.filter-tabs .btn[data-filter]').forEach(function(btn) {
        btn.addEventListener('click', function() {
          document.querySelectorAll('.filter-tabs .btn[data-filter]').forEach(function(b) {
            b.classList.remove('active');
            b.classList.add('btn-secondary');
            b.classList.remove('btn-primary');
          });
          this.classList.add('active', 'btn-primary');
          this.classList.remove('btn-secondary');

          var filter = this.getAttribute('data-filter');
          document.querySelectorAll('.col .card.employee-card').forEach(function(card) {
            var col = card.closest('.col');
            if (!col) return;
            if (filter === 'all') {
              col.style.display = '';
              return;
            }
            var inTime  = card.querySelector('.attendance-info span:first-child');
            var outTime = card.querySelector('.attendance-info span:last-child');
            var hasIn   = inTime  && !inTime.textContent.includes('N/A');
            var hasOut  = outTime && !outTime.textContent.includes('N/A');

            var show = false;
            if (filter === 'on-duty'  && hasIn && !hasOut) show = true;
            if (filter === 'inactive' && hasIn && hasOut)  show = true;
            if (filter === 'off-duty' && !hasIn)           show = true;
            col.style.display = show ? '' : 'none';
          });
        });
      });
    });

  </script>
@endsection

