@php
  $title = 'Live Location';
@endphp
@extends('layouts/layoutMaster')

@section('title', $title)

@section('content')

  <div class="row g-3">
    <!-- 🧑‍💼 Employee List Column (4-col) -->
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">Employee List</h5>
          <input type="text" id="employeeSearch" class="form-control mt-2" placeholder="Search employees...">
        </div>
        <div class="card-body overflow-auto" style="max-height: 80vh;" id="employeeList">
          <!-- Employee cards will be dynamically populated -->
        </div>
      </div>
    </div>

    <!-- 🗺️ Map Column (8-col) -->
    <div class="col-md-8">
      <div class="row mb-3 align-items-center justify-content-between">
        <!-- Online and Offline Stats -->
        <div class="col-auto">
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-primary">
              Online <span class="badge bg-success" id="online">0</span>
            </button>
            <button type="button" class="btn btn-outline-primary">
              Offline <span class="badge bg-danger" id="offline">0</span>
            </button>
          </div>
        </div>
        <!-- Refresh Button -->
        <div class="col-auto">
          <a class="btn btn-outline-primary d-flex align-items-center" href="{{ route('liveLocationView') }}">
            <i class="bi bi-arrow-clockwise me-2"></i> Refresh
          </a>
        </div>
      </div>

      <div class="card shadow-sm">
        <div id="map" style="height:80vh;"></div>
      </div>
    </div>
  </div>

@endsection

@section('page-script')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
  <style>
    /* Unique Map Indicators */
    .status-indicator .dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      display: inline-block;
      box-shadow: 0 0 4px rgba(0, 0, 0, 0.2);
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&callback=initMap&v=weekly"
          async defer></script>

  <script>
    let map, markers = [], bounds;
    const iconBase = window.location.origin + '/assets/img/map/';
    let employees = []; // Store employees globally for filtering

    // 🗺️ Initialize Map
    function initMap() {
      const centerLat = parseFloat('{{ $settings->center_latitude }}');
      const centerLng = parseFloat('{{ $settings->center_longitude }}');
      const zoomLevel = parseInt('{{ $settings->map_zoom_level }}');

      bounds = new google.maps.LatLngBounds();
      map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: centerLat, lng: centerLng },
        zoom: zoomLevel,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        gestureHandling: 'greedy',
        streetViewControl: false,
        zoomControl: true,
        fullscreenControl: true
      });

      fetchLiveLocations();
    }

    // 📡 Fetch Live Location Data
    function fetchLiveLocations() {
      $.ajax({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        url: "{{ route('liveLocationAjax') }}",
        type: 'GET',
        dataType: 'json',
        success: (response) => {
          let active = 0, offline = 0;
          clearMarkers();
          employees = response; // Store employees globally

          $('#employeeList').html(''); // Clear employee list

          response.forEach(user => {
            const position = new google.maps.LatLng(user.latitude, user.longitude);
            let markerIcon = user.status === 'online'
              ? { url: iconBase + 'green_circle.png', scaledSize: new google.maps.Size(32, 32) }
              : { url: iconBase + 'red_circle.png', scaledSize: new google.maps.Size(32, 32) };

            const marker = new google.maps.Marker({
              position,
              map,
              title: user.name,
              icon: markerIcon
            });

            const infoWindow = new google.maps.InfoWindow({
              content: `
              <div>
                <h6>${user.name}</h6>
                <p>Status: ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</p>
                <p>Last Updated: ${user.updatedAt}</p>
              </div>`
            });

            marker.addListener('click', () => {
              infoWindow.open(map, marker);
            });

            markers.push(marker);
            bounds.extend(position);

            if (user.status === 'online') active++;
            else offline++;

            var profileHtml = '';
            if (user.profilePicture) {
              profileHtml = `<img src="${user.profilePicture}" alt="Avatar" class="avatar rounded-circle " />`;
            } else {
              profileHtml = `<span class="avatar-initial rounded-circle bg-label-primary">${user.initials}</span>`;
            }

            // 📋 Add to Employee List
            $('#employeeList').append(`
  <div class="card mb-2 p-2 shadow-sm position-relative">
    <!-- Online/Offline Indicator -->
    <div class="status-indicator position-absolute" style="top: 8px; right: 8px;">
      <span class="dot ${user.status === 'online' ? 'bg-success' : 'bg-danger'}"></span>
    </div>

    <!-- User Details -->
    <div>
      <div class="d-flex justify-content-start align-items-center user-name">
        <div class="avatar-wrapper">
          <div class="avatar avatar-sm me-4">
            ${profileHtml}
          </div>
        </div>
        <div class="d-flex flex-column">
          <a href="#" class="text-heading text-truncate fw-medium">${user.name}</a>
          <small>Code: ${user.code}</small>
        </div>
      </div>
      <p class="mb-1 mt-3">Designation: ${user.designation}</p>
      <p class="mb-1">Last Updated: ${user.updatedAt}</p>
    </div>

    <!-- Focus Button -->
    <button class="btn btn-sm btn-outline-primary mt-2 w-100" onclick="focusOnMap(${user.latitude}, ${user.longitude}, '${user.name}')">
      Focus on Map
    </button>
  </div>
`);


          });

          map.fitBounds(bounds);
          $('#online').text(active);
          $('#offline').text(offline);
        },
        error: (e) => console.error(e)
      });
    }

    let currentInfoWindow = null;

    // 📍 Focus on Specific Marker with Animation and InfoWindow Handling
    function focusOnMap(lat, lng, name) {
      const position = new google.maps.LatLng(lat, lng);

      // Pan and zoom map to the selected position
      map.panTo(position);
      map.setZoom(25);

      // Close the previously open InfoWindow
      if (currentInfoWindow) {
        currentInfoWindow.close();
      }

      // Find the marker and add animation
      markers.forEach(marker => {
        if (marker.getPosition().lat() === lat && marker.getPosition().lng() === lng) {
          marker.setAnimation(google.maps.Animation.BOUNCE);
          setTimeout(() => marker.setAnimation(null), 1400); // Stop bouncing after 1.4 seconds

          // Open a new InfoWindow for the focused marker
          currentInfoWindow = new google.maps.InfoWindow({
            content: `<div><h6>${name}</h6><p>Focused on map</p></div>`
          });
          currentInfoWindow.open(map, marker);
        }
      });
    }

    // 🔄 Clear Markers
    function clearMarkers() {
      markers.forEach(marker => marker.setMap(null));
      markers = [];
    }

    // 🔍 Search Employees
    $('#employeeSearch').on('input', function() {
      const query = $(this).val().toLowerCase();

      $('#employeeList').html(''); // Clear current list

      employees
        .filter(user => user.name.toLowerCase().includes(query))
        .forEach(user => {
          var profileHtml = '';
          if (user.profilePicture) {
            profileHtml = `<img src="${user.profilePicture}" alt="Avatar" class="avatar rounded-circle " />`;
          } else {
            profileHtml = `<span class="avatar-initial rounded-circle bg-label-primary">${user.initials}</span>`;
          }
          $('#employeeList').append(`
  <div class="card mb-2 p-2 shadow-sm position-relative">
    <!-- Online/Offline Indicator -->
    <div class="status-indicator position-absolute" style="top: 8px; right: 8px;">
      <span class="dot ${user.status === 'online' ? 'bg-success' : 'bg-danger'}"></span>
    </div>

    <!-- User Details -->
    <div>
      <div class="d-flex justify-content-start align-items-center user-name">
        <div class="avatar-wrapper">
          <div class="avatar avatar-sm me-4">
            ${profileHtml}
          </div>
        </div>
        <div class="d-flex flex-column">
          <a href="#" class="text-heading text-truncate fw-medium">${user.name}</a>
          <small>Code: ${user.code}</small>
        </div>
      </div>
      <p class="mb-1 mt-3">Designation: ${user.designation}</p>
      <p class="mb-1">Last Updated: ${user.updatedAt}</p>
    </div>

    <!-- Focus Button -->
    <button class="btn btn-sm btn-outline-primary mt-2 w-100" onclick="focusOnMap(${user.latitude}, ${user.longitude}, '${user.name}')">
      Focus on Map
    </button>
  </div>
`);
        });
    });
  </script>
@endsection
