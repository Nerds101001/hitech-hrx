@extends('layouts/layoutMaster')

@section('title', __('Audit Log Details'))

@section('content')
  <div class="row g-4">
    <!-- Audit Log Details -->
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5>@lang('Audit Log Details')</h5>
        </div>
        <div class="card-body">
          <ul class="list-group list-group-flush">
            <!-- User Details -->
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <strong>@lang('User'):</strong>
              <span>{{ $auditLog['user']['first_name'] }} {{ $auditLog['user']['last_name'] }} ({{ $auditLog['user']['email'] }})</span>
            </li>

            <!-- Event -->
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <strong>@lang('Event'):</strong>
              <span>{{ ucfirst($auditLog['event']) }}</span>
            </li>

            <!-- Model Affected -->
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <strong>@lang('Model Affected'):</strong>
              <span>{{ class_basename($auditLog['auditable_type']) }} (ID: {{ $auditLog['auditable_id'] }})</span>
            </li>

            <!-- URL -->
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <strong>@lang('URL'):</strong>
              <span><a href="{{ $auditLog['url'] }}" target="_blank">{{ $auditLog['url'] }}</a></span>
            </li>

            <!-- IP Address -->
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <strong>@lang('IP Address'):</strong>
              <span>{{ $auditLog['ip_address'] }}</span>
            </li>

            <!-- User Agent -->
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <strong>@lang('User Agent'):</strong>
              <span>{{ $auditLog['user_agent'] }}</span>
            </li>

            <!-- Created At -->
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <strong>@lang('Created At'):</strong>
              <span>{{ $auditLog['created_at'] }}</span>
            </li>
          </ul>

          <!-- Old Values -->
          <h5 class="mt-4">@lang('Old Values')</h5>
          @if(!empty($auditLog['old_values']))
            <ul class="list-group list-group-flush">
              @foreach ($auditLog['old_values'] as $key => $value)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                  <span>{{ $value }}</span>
                </li>
              @endforeach
            </ul>
          @else
            <p>@lang('No old values available.')</p>
          @endif

          <!-- New Values -->
          <h5 class="mt-4">@lang('New Values')</h5>
          @if(!empty($auditLog['new_values']))
            <ul class="list-group list-group-flush">
              @foreach ($auditLog['new_values'] as $key => $value)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                  <span>{{ $value }}</span>
                </li>
              @endforeach
            </ul>
          @else
            <p>@lang('No new values available.')</p>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
