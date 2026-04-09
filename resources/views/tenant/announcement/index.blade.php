@php

  @endphp

@extends('layouts/layoutMaster')

@section('title', 'Announcements')

@section('vendor-style')
  @vite([
      'resources/assets/vendor/libs/animate-css/animate.scss', 
      'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="px-4">
    {{-- HERO SECTION --}}
    <div class="hitech-page-hero animate__animated animate__fadeIn">
        <div class="hitech-page-hero-text">
            <div class="greeting">@lang('Announcements & Notifications')</div>
            <div class="sub-text">Communicate important updates to your organization.</div>
        </div>
        <div>
            <a href="{{ route('announcements.create') }}" class="btn btn-hitech">
                <i class="bx bx-plus-circle me-2"></i> @lang('New Announcement')
            </a>
        </div>
    </div>

    <div class="row g-6">
        <div class="col-md-6 col-lg-4">
            <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                <div class="hitech-card-header">
                    <h5 class="title">Create Announcement</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">Draft and broadcast internal news to all departments or specific roles.</p>
                    <a class="btn btn-primary w-100" href="{{ route('announcements.create') }}">
                        <i class="bx bx-send me-2"></i> @lang('Start Drafting')
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                <div class="hitech-card-header">
                    <h5 class="title">Notification Logs</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">View technical logs and JSON structure of sent system notifications.</p>
                    <a class="btn btn-outline-primary w-100" href="{{ route('notifications.index') }}">
                        <i class="bx bx-code-alt me-2"></i> @lang('View Logs')
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
        <div class="hitech-card p-4 bg-light border-dashed">
            <p class="text-muted small mb-0">For advanced configuration and API integrations, please consult the <a
            href="{{ config('variables.documentation') ? config('variables.documentation').'/laravel-introduction.html' : '#' }}"
            target="_blank" rel="noopener noreferrer" class="text-primary fw-bold">Platform Documentation</a>.</p>
        </div>
    </div>
</div>
@endsection
