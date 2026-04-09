@php
  $customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/blankLayout')

@section('title', 'Forgot Password - Hitech Secure Gateway')

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
@endsection

@section('content')
<div class="hitech-gateway-wrapper">
  <div class="gateway-container">
    <div class="gateway-card animate__animated animate__zoomIn">

      {{-- LEFT IDENTITY PANEL (desktop only) --}}
      <div class="identity-panel animate__animated animate__fadeInLeft">
        <div class="content">
          <img src="{{ asset('assets/img/logo-white.png') }}" alt="Logo" class="identity-logo" style="width: 130px; margin-bottom: 2rem;">
          <h1 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.5px;">Password<br>Recovery</h1>
          <div class="features" style="margin-top: 2rem;">
            <div class="feature-item" style="margin-bottom: 1.5rem;">
              <i class="bx bx-envelope" style="font-size: 1.25rem; opacity: 0.8;"></i>
              <p style="font-size: 0.85rem; opacity: 0.9; line-height: 1.4;">A secure reset link will be sent to your registered email address</p>
            </div>
            <div class="feature-item" style="margin-bottom: 1.5rem;">
              <i class="bx bx-shield-quarter" style="font-size: 1.25rem; opacity: 0.8;"></i>
              <p style="font-size: 0.85rem; opacity: 0.9; line-height: 1.4;">Password reset links expire within 60 minutes for your security</p>
            </div>
          </div>
        </div>
        <div class="footer-note" style="font-size: 0.65rem; opacity: 0.5;">SYSTEM V4.2.0-ADMIN</div>
      </div>

      {{-- RIGHT FORM PANEL --}}
      <div class="form-panel animate__animated animate__fadeInRight" style="animation-delay: 0.1s">

        {{-- MOBILE BRAND BANNER —shown only on phones --}}
        <div class="mobile-brand-banner">
          <img src="{{ asset('assets/img/logo-white.png') }}" alt="Logo" class="mobile-logo">
          <h2>Password Recovery</h2>
          <p>Secure Gateway — Enter your email to reset your password</p>
          <span class="version-badge">SYSTEM V4.2.0-ADMIN</span>
        </div>

        <div class="form-inner">

          <div class="form-header">
            <h2 class="login-title" style="font-size: 1.5rem; font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">Forgot Password?</h2>
            <p class="login-subtitle" style="font-size: 0.85rem; line-height: 1.6; color: #64748b; max-width: 320px;">Enter your registered email address and we&apos;ll send you a secure password reset link.</p>
          </div>

          {{-- Success Alert --}}
          @if (session('success'))
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
              <i class="bx bx-check-circle me-2 fs-5"></i>
              <div>{{ session('success') }}</div>
            </div>
          @endif

          {{-- Error Alert --}}
          @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
              <i class="bx bx-error-circle me-2 fs-5"></i>
              <div>{{ $errors->first() }}</div>
            </div>
          @endif

          <form action="{{ route('password.email') }}" method="POST">
            @csrf

            <div class="mb-4">
              <label class="form-label" style="font-size: 0.72rem; font-weight: 700; letter-spacing: 1px; color: #94a3b8; text-transform: uppercase;">REGISTERED EMAIL</label>
              <div class="hitech-input-group">
                <i class="bx bx-envelope group-icon"></i>
                <input
                  type="email"
                  class="form-control"
                  name="email"
                  placeholder="your@email.com"
                  value="{{ old('email') }}"
                  required
                  autofocus
                >
              </div>
            </div>

            <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center w-100 hitech-btn-admin">
              <span class="btn-text">Send Reset Link</span>
              <i class="bx bx-send ms-2"></i>
            </button>
          </form>

          <div class="form-footer mt-4 text-center">
            <a href="{{ route('login') }}" class="footer-link d-flex align-items-center justify-content-center gap-1" style="color: #64748b; text-decoration: none; font-size: 0.875rem;">
              <i class="bx bx-chevron-left"></i>
              Back to Login
            </a>
          </div>

        </div>{{-- end .form-inner --}}
      </div>{{-- end .form-panel --}}

    </div>
  </div>
</div>
@endsection
