@php
$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/blankLayout')

@section('title', 'Verify Identity - Hitech Secure Gateway')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/@form-validation/form-validation.scss'])
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
@endsection

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
<style>
    .otp-input-wrapper {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin: 2rem 0;
    }
    .otp-field {
        width: 100%;
        height: 60px;
        text-align: center;
        font-size: 24px;
        font-weight: 800;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        background: rgba(255, 255, 255, 0.8);
        color: #005a5a;
        transition: all 0.3s ease;
        letter-spacing: 0.5rem;
    }
    .otp-field:focus {
        border-color: #007a7a;
        box-shadow: 0 0 0 4px rgba(0, 122, 122, 0.1);
        outline: none;
        background: #fff;
    }
    .resend-section {
        text-align: center;
        margin-top: 1.5rem;
        font-size: 14px;
        color: #64748b;
    }
    .resend-link {
        color: #007a7a;
        font-weight: 700;
        text-decoration: none;
        transition: color 0.3s;
    }
    .resend-link:hover {
        color: #005a5a;
        text-decoration: underline;
    }
    .security-badge {
        display: inline-flex;
        align-items: center;
        background: rgba(0, 122, 122, 0.1);
        color: #007a7a;
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 1.5rem;
    }
</style>
@endsection

@section('content')
<div class="hitech-gateway-wrapper">
  <div class="gateway-container">
    <div class="gateway-card animate__animated animate__zoomIn">

      {{-- LEFT IDENTITY PANEL --}}
      <div class="identity-panel animate__animated animate__fadeInLeft">
        <div class="content">
          <div class="glass-x-logo logo-lg">
            <div class="x-leg-1"></div>
            <div class="x-leg-2"></div>
            <i class="bx bx-shield-quarter pulse-line"></i>
          </div>
          <div class="brand-section">
            <h1>SECURITY <span class="hrx-light">CHECK</span></h1>
            <div class="hitech-slogan">Two-Step Verification</div>
          </div>
          <div class="features">
            <div class="feature-item">
              <i class="bx bx-mail-send"></i>
              <p>A secure 6-digit code has been sent to your registered email address.</p>
            </div>
            <div class="feature-item">
              <i class="bx bx-time-five"></i>
              <p>The code is valid for 10 minutes for your protection.</p>
            </div>
          </div>
        </div>
        <div class="footer-note">MULTI-FACTOR-AUTH ENABLED</div>
      </div>

      {{-- RIGHT PANEL --}}
      <div class="form-panel animate__animated animate__fadeInRight">
        <div class="form-inner">
          
          <div class="text-center">
            <div class="security-badge">
              <i class="bx bx-lock-alt me-2"></i> Identity Verification
            </div>
            <h2 class="login-title mb-2" style="font-weight: 800 !important;">Check Your Email</h2>
            <p class="login-subtitle">Please enter the verification code sent to your inbox to continue.</p>
          </div>

          @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm animate__animated animate__fadeInDown">
              <i class="bx bx-check-circle me-2"></i> {{ session('success') }}
            </div>
          @endif

          @if(session('error'))
            <div class="alert alert-danger d-flex align-items-center justify-content-between p-3 mb-4 animate__animated animate__shakeX" style="border-radius: 12px; background: rgba(220, 38, 38, 0.15); border: 1px solid rgba(220, 38, 38, 0.3); color: #fca5a5;">
              <div class="d-flex align-items-center">
                <i class="bx bx-error-circle me-2 fs-4"></i>
                <div style="font-size: 14px;">{{ session('error') }}</div>
              </div>
              @if(str_contains(session('error'), 'locked') || str_contains(session('error'), 'blocked'))
                <form action="{{ route('auth.unlock.request') }}" method="POST" class="ms-2">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 8px; font-size: 12px; white-space: nowrap;">Request Unlock</button>
                </form>
              @endif
            </div>
          @endif

          <form action="{{ route('auth.otp.verify') }}" method="POST">
            @csrf
            <div class="otp-input-wrapper">
              <input type="text" name="otp" class="otp-field" maxlength="6" placeholder="000000" autocomplete="one-time-code" required autofocus>
            </div>

            <button class="btn btn-primary d-flex align-items-center justify-content-center w-100 hitech-btn-admin" type="submit">
              <span class="btn-text">VERIFY & LOG IN</span>
              <i class="bx bx-check-double ms-2"></i>
            </button>
          </form>

          <div class="resend-section">
            Didn't receive the code? 
            <form action="{{ route('auth.otp.resend') }}" method="POST" class="d-inline">
              @csrf
              <button type="submit" class="bg-transparent border-0 p-0 resend-link">Resend OTP</button>
            </form>
          </div>

          <div class="form-footer mt-4">
            <a href="{{ route('login') }}" class="footer-link"><i class="bx bx-arrow-back me-1"></i> Back to Login</a>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>
@endsection
