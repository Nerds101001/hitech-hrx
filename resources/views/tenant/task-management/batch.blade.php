@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', 'Task Batch')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss',
  ])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">{{ $tasks->first()->title }}</h3>
      <p class="text-muted mb-0 small">
        Assigned by {{ optional($tasks->first()->assignedBy)->getFullName() ?? '—' }}
        @if($tasks->first()->due_date) &middot; due {{ $tasks->first()->due_date->format('d M Y') }} @endif
        &middot; {{ $tasks->count() }} {{ \Illuminate\Support\Str::plural('person', $tasks->count()) }}
      </p>
    </div>
    <a href="{{ route('task-management.index') }}" class="btn btn-hitech-cancel shadow-sm rounded-pill px-4">
      <i class="bx bx-arrow-back"></i> Back to Task Management
    </a>
  </div>

  @if($tasks->first()->description)
    <div class="px-4 mb-6">
      <div class="hitech-card-white">
        <div class="card-body p-4">
          <p class="mb-0 text-muted">{{ $tasks->first()->description }}</p>
        </div>
      </div>
    </div>
  @endif

  <div class="px-4">
    <div class="hitech-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width:200px">Person</th>
              <th>Status</th>
              <th>Completed</th>
              <th>Timing</th>
              <th style="min-width:220px">Latest remark</th>
            </tr>
          </thead>
          <tbody>
            @include('tenant.task-management._batch_children', ['tasks' => $tasks])
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
