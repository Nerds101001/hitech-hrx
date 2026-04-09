@php
  $title = 'Timeline';
@endphp

@extends('layouts/layoutMaster')

@section('title', $title)

<!-- Vendor Styles -->
@section('vendor-style')
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css">
  @vite(['resources/assets/vendor/libs/select2/select2.scss'])
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css"/>
  <style>
    .accordion.map-controls {
      max-width: 300px;
      max-height: 80vh;
      overflow-y: auto;
      border-radius: 8px;
    }

    .accordion-button {
      font-size: 0.9rem;
    }

    .accordion-body .form-check {
      margin-bottom: 5px;
    }
  </style>
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/select2/select2.js'])

  <script src="https://cdn.jsdelivr.net/gh/mcstudios/glightbox/dist/js/glightbox.min.js"></script>
@endsection

@section('content')

  <!-- 🗓️ Filters Section -->
  <div class="row mb-4 g-3">
    <div class="col-md-3">
      <label for="date" class="form-label">Filter by date</label>
      <input type="date" id="date" class="form-control" value="{{ now()->format('Y-m-d') }}">
    </div>
    <div class="col-md-4">
      <label for="emp" class="form-label">Filter by employee</label>
      <select class="form-select select2" id="emp">
        <option selected disabled>Please select an employee</option>
        @foreach($employees as $employee)
          <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3 d-none mb-3" id="attendanceLogFilterDiv">
      <label for="attendanceLogFilter">Filter by Check-In/Out</label>
      <select id="attendanceLogFilter" class="form-select">
        <option value="">All Check-Ins</option>
      </select>
    </div>
    <div class="col-auto justify-content-end float-end">
      <button type="button" class=" mt-6 btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#helpModal">
        <i class="bi bi-question-circle"></i>
      </button>
    </div>
  </div>

  <!-- 📊 Stats Section -->
  <div class="row mb-4 g-3" id="statsSection">
    <div class="col-md-12 text-center text-muted">
      <p class="mt-3">Please select an employee and date to view their daily activity.</p>
    </div>
  </div>


  <div class="d-flex justify-content-end">

  </div>

  <!-- 📊 Main Content Layout -->
  <div class="row g-4">
    <!-- 📋 Left Column: Tabs Section -->
    <div class="col-md-4">
      <div class="card shadow-sm p-3" style="height: 700px; overflow-y: auto;">
        <h5 id="employeeName" class="text-center">Employee Details</h5>
        <ul class="nav nav-tabs mb-3">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#timeline">Timeline</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#visits">Visits</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#breaks">Breaks</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#orders">Orders</a></li>
        </ul>
        <div class="tab-content" style="max-height: 700px; overflow-y: auto;">
          <div class="tab-pane fade show active" id="timeline">
            <p class="text-muted text-center">No timeline data available.</p>
          </div>
          <div class="tab-pane fade" id="visits">
            <p class="text-muted text-center">No visits data available.</p>
          </div>
          <div class="tab-pane fade" id="breaks">
            <p class="text-muted text-center">No breaks data available.</p>
          </div>
          <div class="tab-pane fade" id="orders">
            <p class="text-muted text-center">No orders data available.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 🗺️ Right Column: Map Section -->
    <div class="col-md-8">
      <div class="card shadow-sm position-relative">
        <!-- Map Controls Accordion - Floating Top-Right -->
        <div
          class="accordion map-controls position-absolute top-0 end-0 m-6 p-2 rounded shadow-lg bg-white"
          id="mapControlsAccordion" style="z-index: 999;">
          <!-- Markers Section -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingMarkers">
              <button class="accordion-button" type="button" data-bs-toggle="collapse"
                      data-bs-target="#collapseMarkers">
                Markers
              </button>
            </h2>
            <div id="collapseMarkers" class="accordion-collapse collapse show">
              <div class="accordion-body">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="toggleDeviceMarkers" checked>
                  <label class="form-check-label" for="toggleDeviceMarkers">Device Markers</label>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="toggleVisitsMarkers" checked>
                  <label class="form-check-label" for="toggleVisitsMarkers">Visit Markers</label>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="toggleActivityMarkers" checked>
                  <label class="form-check-label" for="toggleActivityMarkers">Activity Markers</label>
                </div>
              </div>
            </div>
          </div>

          <!-- Heatmap Section -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingHeatmap">
              <button class="accordion-button" type="button" data-bs-toggle="collapse"
                      data-bs-target="#collapseHeatmap">
                Heatmap
              </button>
            </h2>
            <div id="collapseHeatmap" class="accordion-collapse collapse show">
              <div class="accordion-body">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="toggleDeviceHeatmap" checked>
                  <label class="form-check-label" for="toggleDeviceHeatmap">Device Heatmap</label>
                </div>
              </div>
            </div>
          </div>

          <!-- Polyline Section -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingPolyline">
              <button class="accordion-button" type="button" data-bs-toggle="collapse"
                      data-bs-target="#collapsePolyline">
                Polyline
              </button>
            </h2>
            <div id="collapsePolyline" class="accordion-collapse collapse show">
              <div class="accordion-body">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="togglePolyline" checked>
                  <label class="form-check-label" for="togglePolyline">Show Polyline</label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Map Container -->
        <div id="map" style="height: 700px;"
             class="text-muted text-center d-flex align-items-center justify-content-center">
          <p>Please select an employee to load the map.</p>
        </div>
      </div>
    </div>
  </div>



  <div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-hitech">
        <div class="modal-header modal-header-hitech">
          <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3"><i class="bx bx-help-circle fs-3"></i></div>
            <h5 class="modal-title modal-title-hitech">Map Controls Guide</h5>
          </div>
          <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body modal-body-hitech px-5 py-4">
          <div class="d-flex mb-4">
            <div class="badge bg-label-primary p-2 me-3 h-px-35 w-px-35 d-flex align-items-center justify-content-center rounded-3">
              <i class="bx bx-map-pin"></i>
            </div>
            <div>
              <h6 class="mb-1 text-dark fw-bold uppercase-tracking">Device Markers</h6>
              <p class="text-muted small mb-0">Toggle individual breadcrumb markers showing where the device reported coordinates during the workday.</p>
            </div>
          </div>
          <div class="d-flex mb-4">
            <div class="badge bg-label-success p-2 me-3 h-px-35 w-px-35 d-flex align-items-center justify-content-center rounded-3">
              <i class="bx bx-walk"></i>
            </div>
            <div>
              <h6 class="mb-1 text-dark fw-bold uppercase-tracking">Heatmap Analysis</h6>
              <p class="text-muted small mb-0">Visualize location density to see where the employee spent the most time. Hotter areas indicate longer stay times.</p>
            </div>
           </div>
           <div class="d-flex mb-0">
             <div class="badge bg-label-info p-2 me-3 h-px-35 w-px-35 d-flex align-items-center justify-content-center rounded-3">
               <i class="bx bx-trending-up"></i>
             </div>
             <div>
               <h6 class="mb-1 text-dark fw-bold uppercase-tracking">Route Polyline</h6>
               <p class="text-muted small mb-0">Draw a connected path between markers with directional arrows to trace the chronological movement of the user.</p>
             </div>
           </div>
        </div>
        <div class="modal-footer border-0 pb-5">
           <button type="button" class="btn btn-hitech w-100 py-3 shadow-none" data-bs-dismiss="modal">Got it, thanks!</button>
        </div>
      </div>
    </div>
  </div>

@endsection

@section('page-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=geometry,visualization&callback=initMap&v=weekly"
    async defer></script>

  <script>
    let map, markers = {device: [], visits: [], activity: []};
    let polyline, ltlng = [];
    let heatmap;

    $(function () {
      $('#emp').select2();
      $('#emp, #date').on('change', fetchData);
    });

    // 🗺️ Initialize Map
    function initMap() {

      const centerLat = '{{ $settings->center_latitude }}';
      const centerLng = '{{ $settings->center_longitude }}';
      const zoomLevel = parseInt('{{ $settings->map_zoom_level }}');

      var center = new google.maps.LatLng(centerLat, centerLng);

      map = new google.maps.Map(document.getElementById('map'), {
        zoom: zoomLevel,
        center: center,
        scrollWheel: true,
        draggable: true,
        mapTypeControlOptions: {
          mapTypeIds: [google.maps.MapTypeId.ROADMAP, google.maps.MapTypeId.HYBRID]
        },
        streetViewControl: false,
        scaleControl: true,
        zoomControl: true,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        gestureHandling: 'greedy',
        fullscreenControlOptions: {
          position: google.maps.ControlPosition.RIGHT_BOTTOM // Move Full-Screen to Top-Left
        }
      });

      // Initialize heatmap layer
      heatmap = new google.maps.visualization.HeatmapLayer({
        map: map,
        radius: 30,
        opacity: 0.6
      });
    }

    // Fetch Data
    function fetchData() {
      const userId = $('#emp').val();
      const date = $('#date').val();

      console.log('Fetching data for user: ' + userId + ' on date: ' + date);

      clearAllMarkers();

      if (!userId) {
        resetUI();
      }

      loadAttendanceLogs(userId, date);
      loadStatsForTimeLine(userId, date);
      loadActivity(userId, date);
      loadDeviceLocation(userId, date);

    }

    $('#attendanceLogFilter').on('change', function () {
      const userId = $('#emp').val();
      const date = $('#date').val();
      const attendanceLog = $(this).val();

      if (attendanceLog === '') {
        loadStatsForTimeLine(userId, date);
        loadActivity(userId, date);
        loadDeviceLocation(userId, date);
      } else {
        loadStatsForTimeLine(userId, date, attendanceLog);
        loadActivity(userId, date, attendanceLog);
        loadDeviceLocation(userId, date, attendanceLog);
      }


    });

    function loadAttendanceLogs(userId, date) {
      $.ajax({
        url: 'getAttendanceLogAjax/' + userId + '/' + date,
        method: 'GET',
        success: (response) => {
          var data = response.data;
          console.log('getAttendanceLog response: >>>');
          console.log(data);
          console.log('getAttendanceLog response: <<<');

          if (data.length > 0) {
            $('#attendanceLogFilterDiv').removeClass('d-none');
            $('#attendanceLogFilter').empty().append('<option value="">All Check-Ins</option>');
            data.forEach((log) => {
              $('#attendanceLogFilter').append(`<option value="${log.id}">Check In At - ${log.created_at}</option>`);
            });
          } else {
            $('#attendanceLogFilterDiv').addClass('d-none');
          }

        },
        error: (err) => {
          console.error(err);
          resetUI();
        }
      });
    }

    function loadStatsForTimeLine(userId, date, attendanceLog = null) {
      $.ajax({
        url: 'getStatsForTimeLineAjax/' + userId + '/' + date + '/' + attendanceLog,
        method: 'GET',
        success: (response) => {
          console.log('getStatsForTimeLine response ');
          console.log(response);
          if (response.data) {

            $('#statsSection').html(renderStats(response.data));

            renderTabVisits('#visits', response.data.visits);
            renderTabBreaks('#breaks', response.data.breaks);
            renderTabOrders('#orders', response.data.orders);

            $('#employeeName').text(response.data.name + ' (' + response.data.code + ')');

            updateMapVisits(response.data.visits);

            const lightbox = GLightbox();
          }
        },
        error: (err) => {
          console.error(err);
        }
      });
    }

    function loadActivity(userId, date, attendanceLog = null) {
      $.ajax({
        url: 'getActivityAjax/' + userId + '/' + date + '/' + attendanceLog,
        method: 'GET',
        success: (response) => {
          var data = response.data;
          console.log('getActivity response ');
          console.log(data);
          renderTabTimeLine('#timeline', data);
          updateMapActivity(data);
        },
        error: (err) => {
          console.error(err);
        }
      });
    }

    function loadDeviceLocation(userId, date, attendanceLog = null) {
      $.ajax({
        url: 'getDeviceLocationAjax/' + userId + '/' + date + '/' + attendanceLog,
        method: 'GET',
        success: (response) => {
          var data = response.data;
          console.log('getDeviceLocation response ');
          console.log(data);
          updateMapDevice(data.logs);
          updateHeatmap(data.logs);
          $('#averageSpeed').text(data.averageTravelledSpeed + 'KM/H');
          $('#travelledDistance').text(data.totalTravelledDistance + 'KM');
        },
        error: (err) => {
          console.error(err);
        }
      });
    }

    // Update Map Activity
    function updateMapActivity(timeLineItems) {
      clearMarkers('activity');
      ltlng += timeLineItems.map((item, index) => {
        let latLng = new google.maps.LatLng(item.latitude, item.longitude);

        // Create a marker with a number label
        let marker = new google.maps.Marker({
          position: latLng,
          map: $('#toggleActivityMarkers').is(':checked') ? map : null,
          label: {
            text: (index + 1).toString(),
            color: 'white',
            fontWeight: 'bold'
          },
          icon: {
            url: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
            scaledSize: new google.maps.Size(40, 40),
            labelOrigin: new google.maps.Point(20, 14)
          }
        });

        markers.activity.push(marker);
        return latLng;
      });
    }

    //Update Map for Device
    function updateMapDevice(timeLineItems) {
      clearMarkers('device');
      ltlng = timeLineItems.map((item, index) => {
        let latLng = new google.maps.LatLng(item.latitude, item.longitude);

        // Create a marker with a number label
        let marker = new google.maps.Marker({
          position: latLng,
          map: $('#toggleDeviceMarkers').is(':checked') ? map : null,
          label: {
            text: (index + 1).toString(),
            color: 'white',
            fontWeight: 'bold'
          },
          icon: {
            url: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png',
            scaledSize: new google.maps.Size(40, 40),
            labelOrigin: new google.maps.Point(20, 14)
          }
        });

        markers.device.push(marker);
        return latLng;
      });

      drawPolyline(); // Draw polyline connecting markers
    }

    // Update Map for Client Visits
    function updateMapVisits(visits) {
      clearMarkers('visits');
      ltlng = visits.map((visit, index) => {
        let latLng = new google.maps.LatLng(visit.latitude, visit.longitude);

        // Create a marker with a number label
        let marker = new google.maps.Marker({
          position: latLng,
          map: $('#toggleVisitsMarkers').is(':checked') ? map : null,
          label: {
            text: (index + 1).toString(), // Marker number
            color: 'white',
            fontWeight: 'bold'
          },
          icon: {
            url: 'http://maps.google.com/mapfiles/ms/icons/green-dot.png',
            scaledSize: new google.maps.Size(40, 40), // Increase size (width, height)
            labelOrigin: new google.maps.Point(20, 14) // Adjust label position for larger icon
          }
        });

        markers.visits.push(marker);
        return latLng;
      });
    }

    function drawPolyline() {
      if (polyline) polyline.setMap(null);

      if (ltlng.length < 2) {
        console.warn('Not enough points to draw a polyline with arrows.');
        return;
      }

      polyline = new google.maps.Polyline({
        path: ltlng,
        geodesic: true,
        strokeColor: '#4285F4',
        strokeOpacity: 1.0,
        strokeWeight: 4,
        icons: [
          {
            icon: {
              path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
              scale: 4,
              strokeColor: '#1E90FF',
              fillColor: '#1E90FF',
              fillOpacity: 1
            },
            offset: '100%', // Position the arrow at the end of the polyline
            repeat: '50px' // Repeat arrow every 50 pixels along the line
          }
        ]
      });

      // Show polyline only if the toggle is checked
      if ($('#togglePolyline').is(':checked')) {
        polyline.setMap(map);
      }

      // Adjust map bounds to fit all points
      const bounds = new google.maps.LatLngBounds();
      ltlng.forEach(point => bounds.extend(point));
      map.fitBounds(bounds);

      // Slight zoom adjustment
      setTimeout(() => {
        map.setZoom(map.getZoom() - 1);
      }, 300);
    }

    function createMarkerWithInfo(latLng, infoContent) {
      const marker = new google.maps.Marker({
        position: latLng,
        map: map
      });

      const infoWindow = new google.maps.InfoWindow({
        content: infoContent
      });

      marker.addListener('click', () => {
        infoWindow.open(map, marker);
      });

      return marker;
    }

    function centerMap() {
      const bounds = new google.maps.LatLngBounds();
      ltlng.forEach(point => bounds.extend(point));
      map.fitBounds(bounds);
    }

    function clearMarkers(type) {
      console.log('Clearing markers for type:', type);
      console.log('Markers:', markers);
      markers[type].forEach(marker => marker.setMap(null));
      markers[type] = [];
    }

    function clearAllMarkers() {
      clearMarkers('device');
      clearMarkers('visits');
      clearMarkers('activity');
    }

    function updateHeatmap(locations) {
      const heatmapData = locations.map(location => ({
        location: new google.maps.LatLng(location.latitude, location.longitude),
        weight: 10 // Adjust weight based on activity type or frequency if needed
      }));

      heatmap.setData(heatmapData);
    }

    $('#toggleDeviceMarkers').on('change', function () {
      markers.device.forEach(marker => marker.setMap(this.checked ? map : null));
    });

    $('#toggleDeviceHeatmap').on('change', function () {
      heatmap.setMap(this.checked ? map : null);
    });

    $('#toggleVisitsMarkers').on('change', function () {
      markers.visits.forEach(marker => marker.setMap(this.checked ? map : null));
    });

    $('#toggleActivityMarkers').on('change', function () {
      markers.activity.forEach(marker => marker.setMap(this.checked ? map : null));
    });

    $('#togglePolyline').on('change', function () {
      if (polyline) {
        polyline.setMap(this.checked ? map : null);
      }
    });

    // 📊 Render Stats
    function renderStats(data) {
      return `
      <div class="col-md-2 text-center">
        <div class="card p-3 shadow-sm"><i class="bi bi-clock-history fs-3 text-primary"></i><h6>Tracked Time</h6><p>${data.attendanceDuration}</p></div>
      </div>
      <div class="col-md-2 text-center">
        <div class="card p-3 shadow-sm"><i class="bi bi-calendar-check fs-3 text-success"></i><h6>Average Speed</h6><p id=averageSpeed>-</p></div>
      </div>
      <div class="col-md-2 text-center">
        <div class="card p-3 shadow-sm"><i class="bi bi-geo-alt fs-3 text-danger"></i><h6>Distance</h6><p id="travelledDistance">-</p></div>
      </div>
      <div class="col-md-2 text-center">
        <div class="card p-3 shadow-sm"><i class="bi bi-briefcase fs-3 text-warning"></i><h6>Visits</h6><p>${data.visitsCount}</p> </div>
      </div>
      <div class="col-md-2 text-center">
        <div class="card p-3 shadow-sm"><i class="bi bi-cup-hot fs-3 text-info"></i> <h6>Breaks</h6><p>${data.breaksCount}</p> </div>
      </div>
      <div class="col-md-2 text-center">
        <div class="card p-3 shadow-sm"><i class="bi bi-box-seam fs-3 text-secondary"></i><h6>Orders</h6><p>${data.ordersCount}</p> </div>
      </div>
    `;
    }

    function resetUI() {
      log('Resetting UI...');
      $('#statsSection').html('<p>Select an employee.</p>');
      $('#employeeName').text('Employee Details');
      $('#mapControlsAccordion').css('display', 'none');
    }

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

    function getOrderStatusBadge(status) {
      switch (status.toLowerCase()) {
        case 'pending':
          return 'bg-warning text-dark';
        case 'completed':
          return 'bg-success text-white';
        case 'cancelled':
          return 'bg-danger text-white';
        default:
          return 'bg-secondary text-white';
      }
    }

    function capitalizeFirstLetter(string) {
      return string.charAt(0).toUpperCase() + string.slice(1);
    }

    // 📋 Render Tabs
    function renderTabTimeLine(tabId, items) {

      const content = items.map(item => `
    <div class="card p-2 mb-2 shadow-sm rounded">
      <!--  Activity Details -->
      <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="text-primary mb-0">
      <i class="bi bi-clock"></i> ${item.startTime} - ${item.endTime}
      </h6>
      <span class="badge bg-info text-dark">${item.type}</span>
      </div>

        <!-- Elapsed Time -->
      <div class="mb-2">
      <small><i class="bi bi-hourglass-split text-warning"></i> Elapsed Time: ${item.elapseTime}</small>
      </div>

        <!--  Status Indicators -->
      <div class="d-flex justify-content-around status-icons p-2 bg-light rounded">
      <div class="text-center">
      <i class="bi bi-battery-half text-primary fs-5"></i>
      <div class="small">${item.batteryPercentage ?? 0}%</div>
      </div>
      <div class="text-center">
      <i class="bi bi-wifi ${item.isWifiOn ? 'text-success' : 'text-danger'} fs-5"></i>
      <div class="small">${item.isWifiOn ? 'On' : 'Off'}</div>
      </div>
      <div class="text-center">
      <i class="bi bi-geo-alt-fill ${item.isGPSOn ? 'text-success' : 'text-danger'} fs-5"></i>
      <div class="small">${item.isGPSOn ? 'Active' : 'Inactive'}</div>
      </div>
      </div>

        <!--  Location Details -->
      <div class="mt-2">
      <small>
      <i class="bi bi-geo-alt"></i> ${item.address ?? 'No address available'}
      </small>
      </div>
      </div>
      `






























                                                                                                                            ).join('');

                                                                                                                                  $(tabId).html(content || '<p class="text-muted text-center">No data available.</p>');
                                                                                                                                }

                                                                                                                            //Render Tab Visits
                                                                                                                            function renderTabVisits(tabId, items) {

                                                                                                                              const content = items.map(item =>






























      `<div class='card p-2 mb-2 shadow-sm rounded'> <!--  Time Information -->
      <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="text-primary mb-0">
      <i class="bi bi-clock"></i> ${item.created_at}
      </h6>
      <span class="badge bg-info text-dark">Visit</span>
      </div>

        <!-- Client Information -->
      <div class="mb-2 mt-2">
      <p><strong><i class="bi bi-person text-primary"></i> Client:</strong> ${item.client_name ?? 'N/A'}</p>
      </div>

        <!-- Location Information -->
      <div class="mb-2">
      <p><strong><i class="bi bi-geo-alt text-danger"></i> Location:</strong> ${item.address ?? 'N/A'}</p>
      </div>

        <!--  Remarks -->
      <div class="mb-2">
      <p><strong><i class="bi bi-chat-left-text text-secondary"></i> Remarks:</strong> ${item.remarks ?? 'No remarks available'}</p>
      </div>

        <!--Image Preview -->
      ${item.img_url ? `
















































                                                                                                                                                                                                    <div class="text-center mb-2">
                                                                                                                                                                                                                        <a href="$
                                                                                                                                                                                                      {
                                                                                                                                                                                                        item.img_url
                                                                                                                                                                                                      }

                                                                                                                                                                                                                        " class="glightbox" data-title="$
                                                                                                                                                                                                                          {
                                                                                                                                                                                                                            item.client_name
                                                                                                                                                                                                                          }
                                                                                                                                                                                                                         visit">
                                                                                                                                                                                                                                  <img src="$
                                                                                                                                                                                                                          {
                                                                                                                                                                                                                            item.img_url
                                                                                                                                                                                                                          }
                                                                                                                                                                                                                        " alt="Visit Image" class="img-fluid rounded shadow-sm" style="max-width: 100%; height: auto;">
                                                                                                                                                                                                                        </a>
                                                                                                                                                                                                                                </div>






















































      ` : ''}
      </div>
      `





















































                                                                                                                                                                                                                        ).join('');

                                                                                                                                                                                                                              $(tabId).html(content || '<p class="text-muted text-center">No data available.</p>');
                                                                                                                                                                                                                            }


                                                                                                                                                                                                                             // Render Tab Breaks
                                                                                                                                                                                                                    function renderTabBreaks(tabId, items) {

                                                                                                                                                                                                                      const content = items.map(item =>



















































      `
      <div class='card p-2 mb-2 shadow-sm rounded'>
        <!-- Time Information -->
      <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="text-primary mb-0">
      <i class="bi bi-clock"></i> ${item.created_at}
      </h6>
      <span class="badge bg-warning text-dark">Break</span>
      </div>

        <!--  Break Start & End Time -->
      <div class="d-flex justify-content-between align-items-center mb-2">
      <p><strong><i class="bi bi-arrow-right-circle text-success"></i> Start Time:</strong> ${item.start_time ?? 'N/A'}</p>
      <p><strong><i class="bi bi-arrow-left-circle text-danger"></i> End Time:</strong> ${item.end_time ?? 'N/A'}</p>
      </div>

        <!-- Duration -->
      <div class="mb-2">
      <p><strong><i class="bi bi-hourglass-split text-info"></i> Duration:</strong> ${item.duration ?? 'N/A'}</p>
      </div>
      </div>
      `



















































                                                                                                                                                                                                                ).join('');

                                                                                                                                                                                                                      $(tabId).html(content || '<p class="text-muted text-center">No break data available.</p>');
                                                                                                                                                                                                                    }

                                                                                                                                                                                                                    // Render Tab Orders
                                                                                                                                                                                                                    function renderTabOrders(tabId, items) {
                                                                                                                                                                                                                      console.log(items);

                                                                                                                                                                                                                      const content = items.map(item =>



















































      `
      <div class='card p-2 mb-2 shadow-sm rounded'>
        <!--  Order Number & Status -->
      <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="text-primary mb-0">
      <i class="bi bi-receipt-cutoff"></i> ${item.order_number ?? 'N/A'}
      </h6>
      <span class="badge ${getOrderStatusBadge(item.status)}">${capitalizeFirstLetter(item.status)}</span>
      </div>

        <!--  Total Items & Amount -->
      <div class="d-flex justify-content-between align-items-center mb-2">
      <p><strong><i class="bi bi-box text-info"></i> Total Items:</strong> ${item.total_items ?? '0'}</p>
      <p><strong><i class="bi bi-currency-dollar text-success"></i> Total Amount:</strong> ${item.total_amount ?? 'N/A'}</p>
      </div>

        <!--  Created At -->
      <div class="mb-2">
      <p><strong><i class="bi bi-clock text-warning"></i> Created At:</strong> ${item.created_at ?? 'N/A'}</p>
      </div>

        <!-- User Remarks -->
      <div class="mb-2">
      <p><strong><i class="bi bi-chat-left-text text-secondary"></i> Remarks:</strong> ${item.user_remarks ?? 'No remarks available'}</p>
      </div>
      </div>
      `



















































                                                                                                                                                                                                                ).join('');

                                                                                                                                                                                                                      $(tabId).html(content || '<p class="text-muted text-center">No order data available.</p>');
                                                                                                                                                                                                                    }





</script>
@endsection
