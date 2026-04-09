@extends('layouts/layoutMaster')

@section('title', __('Audit Logs'))

<!-- Vendor Styles -->
@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss'
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
@endsection


@section('content')

  
  <!-- Audit Log table card -->
  <div class="hitech-card animate__animated animate__fadeInUp">
    <div class="hitech-card-header">
         <h5 class="mb-0 text-white">System Audit Logs</h5>
    </div>
    <div class="card-datatable table-responsive">
      <table id="datatable" class="datatables-users table border-top">
        <thead>
        <tr>
          <th>@lang('Id')</th>
          <th>@lang('User')</th>
          <th>@lang('Event')</th>
          <th>@lang('Ip')</th>
          <th>@lang('Model')</th>
          <th>@lang('Created At')</th>
          <th>@lang('Actions')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($auditLogs as $auditLog)
          <tr>
            <td>{{$auditLog->id}}</td>
            <td>
              @if($auditLog->user == null)
                <span class="text-muted">@lang('N/A')</span>
              @else
              <div class="d-flex justify-content-start align-items-center user-name">
                <div class="avatar-wrapper">
                  <div class="avatar avatar-sm me-3">
                    <img
                      src="{{ !is_null($auditLog->user->profile_picture) ? $auditLog->user->profile_picture : 'https://avatar.iran.liara.run/username?username='.$auditLog->user->first_name.'+'.$auditLog->user->last_name}}"
                      alt class="w-px-30 h-auto rounded-circle">
                  </div>
                </div>
                <div class="d-flex flex-column">
                  <span
                    class="fw-bold text-white">{{$auditLog->user->first_name.' '.$auditLog->user->last_name}}</span>
                  <span class="text-muted small">{{$auditLog->user->email}}</span>
                </div>
              </div>
              @endif
            </td>
            <td>
                @if($auditLog->event == 'created')
                    <span class="badge bg-label-success">Created</span>
                @elseif($auditLog->event == 'updated')
                    <span class="badge bg-label-info">Updated</span>
                @elseif($auditLog->event == 'deleted')
                    <span class="badge bg-label-danger">Deleted</span>
                @else
                    <span class="badge bg-label-secondary">{{$auditLog->event}}</span>
                @endif
            </td>
            <td><code class="text-muted">{{$auditLog->ip_address}}</code></td>
            <td><span class="text-white">{{class_basename($auditLog->auditable_type)}}</span></td>
            <td>{{$auditLog->created_at->format('d M Y, h:i A')}}</td>
            <td>
              <a href="{{route('auditLogs.show', $auditLog->id)}}"
                 class="btn btn-sm btn-icon btn-label-primary">
                <i class="bx bx-show"></i>
              </a>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
