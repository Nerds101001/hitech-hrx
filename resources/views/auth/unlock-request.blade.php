@php
$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/blankLayout')

@section('title', 'Account Unlock - Hitech Secure Gateway')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/@form-validation/form-validation.scss'])
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
@endsection

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
<style>
    .unlock-email-field {
        width: 100%;
        height: 52px;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        background: rgba(255, 255, 255, 0.8);
        color: #005a5a;
        font-size: 15px;
        font-weight: 600;
        padding: 0 16px;
        transition: all 0.3s ease;
    }
    .unlock-email-field:focus {
        border-color: #007a7a;
        box-shadow: 0 0 0 4px rgba(0, 122, 122, 0.1);
        outline: none;
        background: #fff;
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
          <img src="{{ asset('assets/img/logo-white.png') }}" alt="HRX — Hitech Group"
               class="identity-logo" style="width: 200px; max-width: 80%; height: auto; flex-shrink: 0; margin-bottom: 1.5rem;">
          <div class="brand-section">
            <h1>ACCOUNT <span class="hrx-light">UNLOCK</span></h1>
            <div class="hitech-slogan">Self-Service Recovery</div>
          </div>
          <div class="features">
            <div class="feature-item">
              <i class="bx bx-mail-send"></i>
              <p>Enter your registered email and we will send you a secure unlock link.</p>
            </div>
            <div class="feature-item">
              <i class="bx bx-time-five"></i>
              <p>The unlock link is valid for 15 minutes for your protection.</p>
            </div>
          </div>
        </div>
        <div class="footer-note">SECURE ACCOUNT RECOVERY</div>
      </div>

      {{-- RIGHT PANEL --}}
      <div class="form-panel animate__animated animate__fadeInRight">
        <div class="form-inner">

          <div class="text-center">
            <div class="security-badge">
              <i class="bx bx-lock-open-alt me-2"></i> Account Unlock
            </div>
            <h2 class="login-title mb-2" style="font-weight: 800 !important;">Unlock Your Account</h2>
            <p class="login-subtitle">Enter the email address registered with your account. If it is locked, an unlock link will be emailed to you.</p>
          </div>

          @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm animate__animated animate__fadeInDown">
              <i class="bx bx-check-circle me-2"></i> {{ session('success') }}
            </div>
          @endif

          @if(session('error'))
            <div class="alert alert-danger d-flex align-items-center p-3 mb-4 animate__animated animate__shakeX" style="border-radius: 12px; background: rgba(220, 38, 38, 0.15); border: 1px solid rgba(220, 38, 38, 0.3); color: #fca5a5;">
              <i class="bx bx-error-circle me-2 fs-4"></i>
              <div style="font-size: 14px;">{{ session('error') }}</div>
            </div>
          @endif

          @if(session('unlock_link'))
            {{-- One-click unlock: appears right after the email is submitted --}}
            <a href="{{ session('unlock_link') }}"
               class="btn btn-success d-flex align-items-center justify-content-center w-100 mb-3 animate__animated animate__pulse"
               style="border-radius: 12px; font-weight: 700; padding: 0.9rem;">
              <i class="bx bx-lock-open-alt me-2 fs-5"></i> UNLOCK MY ACCOUNT
            </a>
            <p class="text-center text-muted small mb-3">Click the button above to unlock your account, then log in. (A copy was also emailed to you.)</p>
          @else
          <form action="{{ route('auth.unlock.request') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="form-label" for="unlock-email">REGISTERED EMAIL</label>
              <input type="email" name="email" id="unlock-email" class="unlock-email-field" placeholder="you@company.com" value="{{ old('email') }}" required autofocus>
              @error('email')<span class="text-danger small">{{ $message }}</span>@enderror
            </div>

            <button class="btn btn-primary d-flex align-items-center justify-content-center w-100 hitech-btn-admin mt-3" type="submit">
              <span class="btn-text">GET UNLOCK LINK</span>
              <i class="bx bx-lock-open-alt ms-2"></i>
            </button>
          </form>
          @endif

          <div class="form-footer mt-4">
            <a href="{{ route('login') }}" class="footer-link"><i class="bx bx-arrow-back me-1"></i> Back to Login</a>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>
@endsection
