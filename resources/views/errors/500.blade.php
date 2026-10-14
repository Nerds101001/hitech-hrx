@php
  $customizerHidden = 'customizer-hide';
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Something Went Wrong')

@section('page-style')
  <!-- Page -->
  @vite(['resources/assets/vendor/scss/pages/page-misc.scss'])
@endsection


@section('content')
  <div class="hitech-error-page">
    {{-- Animated Background Elements --}}
    <div class="glass-orb orb-1" style="background: rgba(220, 38, 38, 0.2);"></div>
    <div class="glass-orb orb-2" style="background: rgba(220, 38, 38, 0.12);"></div>

    <div class="error-glass-card animate__animated animate__zoomIn">
      <div class="hitech-logo-error mb-10">
          <img src="{{ asset('assets/img/logo.png') }}" alt="Logo" width="60" class="animate__animated animate__pulse animate__infinite">
      </div>

      <h1 class="error-h1">500</h1>
      <h4 class="error-title-h4">Something Went Wrong</h4>
      <p class="error-p">
        An unexpected error occurred on our end. It has been recorded, <br>
        and our team has already been notified.
      </p>

      <div class="action-zone mt-4">
        <a href="{{ url('/') }}" class="btn btn-hitech-error shadow">
          <i class="bx bx-left-arrow-circle fs-4"></i> Back to Dashboard
        </a>
      </div>

      {{-- Interactive Message --}}
      <div class="mt-10 pt-6 border-top border-light">
          <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
              <i class="bx bx-error-circle text-danger fs-5"></i>
              <span class="small text-muted fw-bold">PROTOCOL: INTERNAL_SERVER_ERROR</span>
          </div>
          <p class="small text-muted mb-0">If this keeps happening, please contact your Chief Identity Officer (CIO).</p>
      </div>
    </div>
  </div>
@endsection
