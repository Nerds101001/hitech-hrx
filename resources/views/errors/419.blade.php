@php
  $customizerHidden = 'customizer-hide';
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Session Expired')

@section('page-style')
  <!-- Page -->
  @vite(['resources/assets/vendor/scss/pages/page-misc.scss'])
@endsection


@section('content')
  <div class="hitech-error-page">
    {{-- Animated Background Elements --}}
    <div class="glass-orb orb-1" style="background: rgba(239, 68, 68, 0.4);"></div>
    <div class="glass-orb orb-2" style="background: rgba(245, 158, 11, 0.3);"></div>
    
    <div class="error-glass-card animate__animated animate__fadeInUp">
      {{-- Timeout Clock Icon --}}
      <div class="mb-4 text-warning opacity-75">
          <i class="bx bx-time-five" style="font-size: 80px;"></i>
      </div>
      
      <h1 class="error-h1">419</h1>
      <h4 class="error-title-h4">Secure Session Expired</h4>
      <p class="error-p">
        Your connection token has expired due to inactivity to protect your account. <br>
        Please re-authenticate to generate a new security token.
      </p>
      
      <div class="action-zone mt-4">
        <a href="{{ route('login') }}" class="btn btn-hitech-primary shadow">
          <i class="bx bx-refresh fs-4"></i> Renew Session
        </a>
      </div>
      
      {{-- Interactive Message --}}
      <div class="mt-10 pt-6 border-top border-light">
          <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
              <i class="bx bx-shield-x text-warning fs-5"></i>
              <span class="small text-muted fw-bold">PROTOCOL: TOKEN_MISMATCH_OR_EXPIRED</span>
          </div>
          <p class="small text-muted mb-0">Security measures dictate sessions time out automatically. This is completely normal.</p>
      </div>
    </div>
  </div>
@endsection
