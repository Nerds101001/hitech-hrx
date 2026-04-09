@php
    $title = 'Create Client';
@endphp
@extends('layouts/layoutMaster')

@section('title', __($title))

    @section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="float-start">
                <h4 class="mt-2">{{ $title }}</h4>
            </div>
        </div>
    </div>
    <div class = "card mb-3">
        <div class = "card-body">
            <div class="row">
                <div class="col-6">
                    <form action="{{ route('client.store') }}" method="post">
                        <input id="latitude" name="latitude" type="hidden" />
                        <input id="longitude" name="longitude" type="hidden" />
                        @csrf
                        <div class="">
                            <div class="">
                                <div class="mt-2">
                                    <h5> Basic Details</h5>
                                    <div class="">
                                        <div class="form-group row">
                                            <div class="form-group col-md-12 mb-3">
                                                <label for="name" class="control-label">Name</label>
                                                <input id="name" name="name" class="form-control"
                                                    value="{{ old('name') }}" />
                                                <span class="text-danger">{{ $errors->first('name', ':message') }}</span>
                                            </div>
                                            <div class="form-group col-md-12 mb-3">
                                                <label for="address" class="control-label">Address</label>
                                                <input id="address" name="address" class="form-control"
                                                    value="{{ old('address') }}" />
                                                <span class="text-danger">{{ $errors->first('address', ':message') }}</span>
                                            </div>
                                        </div>
                                        <div class="row mt-4">
                                            <div class="form-group col-md-6">
                                                <label for="city" class="control-label">City</label>
                                                <input id="city" name="city" class="form-control"
                                                    value="{{ old('city') }}" />
                                                <span class="text-danger">{{ $errors->first('city', ':message') }}</span>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="radius" class="control-label">Radius</label>
                                                <input id="radius" name="radius" class="form-control" type="number"
                                                    value="{{ old('radius') }}" />
                                                <span class="text-danger">{{ $errors->first('Radius', ':message') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
    <hr>
                                <div class="mt-2">
                                    <h5> Personal Details</h5>
                                    <div class="">
                                        <div class="form-group row">
                                            <div class="form-group col-md-12 mb-3">
                                                <label for="phone" class="control-label">Phone Number</label>
                                                <input id="phone" name="phone" type="number" class="form-control"
                                                    value="{{ old('phone') }}" />
                                                <span class="text-danger">{{ $errors->first('phone', ':message') }}</span>
                                            </div>
                                            <div class="form-group col-md-12 mb-3">
                                                <label for="email" class="control-label">Email</label>
                                                <input id="email" name="email" type="email" class="form-control"
                                                    value="{{ old('email') }}" />
                                                <span class="text-danger">{{ $errors->first('email', ':message') }}</span>
                                            </div>
                                            <div class="form-group col-md-12 mb-3">
                                                <label for="contactPersonName" class="control-label">Contact Person
                                                    Name</label>
                                                <input id="contactPersonName" name="contactPersonName" class="form-control"
                                                    value="{{ old('contactPersonName') }}" />
                                                <span
                                                    class="text-danger">{{ $errors->first('contactPersonName', ':message') }}</span>
                                            </div>
                                        </div>
                                        <div class="row mt-4">
                                            <div class="form-group col-md-12">
                                                <label for="remarks" class="control-label">Remarks</label>
                                                <textarea id="remarks" rows="5" name="remarks" class="form-control">{{ old('remarks') }}</textarea>
                                                <span
                                                    class="text-danger">{{ $errors->first('remarks', ':message') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class=" mt-3">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Create</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-6">
                    <div class="form-group row mb-3">
                        <div class="form-group col-md-12">
                            <label for="locationSearch" class="control-label">Location Search</label>
                            <input id="locationSearch" name="locationSearch" class="form-control"
                                placeholder="Search for a location" />
                        </div>
                    </div>
                    <div id="map" style="height: 400px; width: 100%;"></div>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection

@section('page-script')
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places&callback=initMap"
        async defer></script>

    <script>
        let map;
        let marker;
        let circle;
        let autocomplete;

        function initMap() {
            const latitude = '{{ $settings->center_latitude }}';
            const longitude = '{{ $settings->center_longitude }}';
            const zoomLevel = '{{ $settings->map_zoom_level }}';

            const defaultLocation = {
                lat: parseFloat(latitude),
                lng: parseFloat(longitude)
            };

            // Initialize the map
            map = new google.maps.Map(document.getElementById("map"), {
                center: defaultLocation,
                zoom: parseInt(zoomLevel),
            });

            // Initialize marker
            marker = new google.maps.Marker({
                position: defaultLocation,
                map: map,
                draggable: true
            });

            // Initialize circle with a default radius of 100 meters
            circle = new google.maps.Circle({
                map: map,
                radius: 100,
                fillColor: '#AA0000',
                strokeColor: '#AA0000'
            });
            circle.bindTo('center', marker, 'position');

            // Add event listener for marker drag
            marker.addListener("dragend", () => {
                updateLatLng(marker.getPosition());
            });

            // Update marker position and fields on map click
            map.addListener("click", (event) => {
                marker.setPosition(event.latLng);
                updateLatLng(event.latLng);
            });

            // Initialize autocomplete for search box
            autocomplete = new google.maps.places.Autocomplete(
                document.getElementById("locationSearch"), {
                    types: ["geocode"]
                }
            );

            // Add listener for place selection
            autocomplete.addListener("place_changed", () => {
                const place = autocomplete.getPlace();

                if (place.geometry) {
                    map.panTo(place.geometry.location);
                    marker.setPosition(place.geometry.location);
                    updateLatLng(place.geometry.location);
                    //set zoom level
                    map.setZoom(15);
                } else {
                    alert("No details available for the selected location!");
                }
            });

            // Set default radius to 100 meters
            document.getElementById("radius").value = 100;

            // Update the circle radius based on input change
            document.getElementById("radius").addEventListener("input", function() {
                const radius = parseFloat(this.value);
                circle.setRadius(radius);
            });
        }

        // Update Latitude and Longitude input fields
        function updateLatLng(location) {
            document.getElementById("latitude").value = location.lat();
            document.getElementById("longitude").value = location.lng();
        }
    </script>
@endsection
