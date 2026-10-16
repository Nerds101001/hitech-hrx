@php
$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/blankLayout')

@section('title', 'Login - Hitech Secure Gateway')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/@form-validation/form-validation.scss'])
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
@endsection

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/@form-validation/popular.js',
  'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
  'resources/assets/vendor/libs/@form-validation/auto-focus.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/js/pages-auth.js'])
@endsection

@section('content')
<div class="hitech-gateway-wrapper">
  <div class="gateway-container">
    <div class="gateway-card animate__animated animate__zoomIn">

      {{-- LEFT IDENTITY PANEL (desktop only) --}}
      <div class="identity-panel animate__animated animate__fadeInLeft">
        <div class="content">
          <img src="{{ asset('assets/img/logo-white.png') }}" alt="HRX — Hitech Group"
               class="identity-logo" style="width: 200px; max-width: 80%; height: auto; flex-shrink: 0; margin-bottom: 1.5rem;">
          <div class="brand-section">
            <h1 class="visually-hidden">HI TECH <span class="hrx-light">HRX</span></h1>
            <div class="hitech-slogan">Next Gen HRMS</div>
          </div>
          <div class="features">
            <div class="feature-item">
              <i class="bx bx-check-shield"></i>
              <p>End-to-end encrypted administrative session</p>
            </div>
            <div class="feature-item">
              <i class="bx bx-bar-chart-alt-2"></i>
              <p>All access attempts are logged and audited</p>
            </div>
            <div class="feature-item">
              <i class="bx bx-lock-alt"></i>
              <p>256-bit SSL secured connection</p>
            </div>
          </div>
        </div>
        <div class="footer-note">SYSTEM V4.2.0-ADMIN</div>
      </div>

      {{-- RIGHT PANEL --}}
      <div class="form-panel animate__animated animate__fadeInRight">

        {{-- MOBILE BRAND BANNER --}}
        <div class="mobile-brand-banner">
          <img src="{{ asset('assets/img/logo-white.png') }}" alt="HRX — Hitech Group"
               class="mobile-logo mb-3" style="width: 160px; max-width: 70%; height: auto; flex-shrink: 0;">
          <h2 class="visually-hidden">HI TECH <span class="fw-light">HRX</span></h2>
          <div class="hitech-slogan" style="color: rgba(255,255,255,0.6); margin: 0.25rem 0;">Next Gen HRMS</div>
          <span class="version-badge">SYSTEM V4.2.0-ADMIN</span>
        </div>

        {{-- THE FORM --}}
        <div class="form-inner">

          {{-- ROLE SWITCHER --}}
          <div class="hitech-role-switcher">
            <div class="switcher-pill">
              <button type="button" class="role-option role-employee-btn" onclick="switchRole('employee')">Employee / Manager</button>
              <button type="button" class="role-option active role-admin-btn" onclick="switchRole('admin')">Admin / HR</button>
            </div>
          </div>

          <div class="form-header">
            <h2 class="login-title">Secure Login</h2>
            <p class="login-subtitle">Please verify your identity to access the management portal.</p>
          </div>

          <form action="{{ route('auth.loginPost') }}" method="POST" onsubmit="return validateAndSubmit(event)">
            @csrf

            <div class="input-container">
              <label class="form-label label-username">ADMINISTRATOR EMAIL</label>
              <div class="hitech-input-group">
                <i class="bx bx-envelope group-icon"></i>
                <input type="text" class="form-control input-email" name="email"
                  placeholder="admin@hitechgroup.in" value="{{ old('email') }}" required autofocus>
              </div>
              @error('email')<span class="text-danger small">{{ $message }}</span>@enderror
            </div>

            <div class="input-container">
              <label class="form-label">PASSWORD</label>
              <div class="hitech-input-group">
                <i class="bx bx-lock-alt group-icon"></i>
                <input type="password" class="form-control" name="password" id="login_password"
                  placeholder="············" required />
                <button type="button" class="password-toggle-btn border-0 bg-transparent p-0 ms-2 d-flex align-items-center" onclick="togglePasswordVisibility('login_password', 'toggle_icon')">
                    <i class="bx bx-show group-icon-end" id="toggle_icon" style="color: #94a3b8; font-size: 1.25rem; transition: color 0.3s; cursor: pointer;"></i>
                </button>
              </div>
              @error('password')<span class="text-danger small">{{ $message }}</span>@enderror
            </div>

            {{-- CAPTCHA --}}
            <div class="captcha-section">
              <label class="form-label">VERIFICATION</label>
              <div class="hitech-captcha-box">
                <div class="captcha-display">
                  <span class="captcha-code-display code-text">8B2K</span>
                  <button type="button" class="refresh-btn" onclick="generateCaptcha()"><i class="bx bx-refresh"></i></button>
                </div>
                <input type="text" class="form-control captcha-input-field" placeholder="Enter code" required>
              </div>
              <div class="captcha-error text-danger small mt-1" style="display:none;">Invalid captcha code.</div>
            </div>
           @if (session('error'))
            <div class="alert alert-danger d-flex align-items-center justify-content-between p-3 mb-4 animate__animated animate__shakeX" style="border-radius: 12px; background: rgba(220, 38, 38, 0.15); border: 1px solid rgba(220, 38, 38, 0.3); color: #fca5a5;">
              <div class="d-flex align-items-center">
                <i class="bx bx-error-circle me-2 fs-4"></i>
                <div style="font-size: 14px;">{{ session('error') }}</div>
              </div>
              @if(str_contains(session('error'), 'locked') || str_contains(session('error'), 'blocked'))
                <a href="{{ route('auth.unlock.request.form') }}" target="_blank" class="btn btn-sm btn-outline-danger ms-2" style="border-radius: 8px; font-size: 12px; white-space: nowrap;">Request Unlock</a>
              @endif
            </div>
          @endif

            <button class="btn btn-primary d-flex align-items-center justify-content-center w-100 hitech-btn-admin mt-3" type="submit">
              <span class="btn-text">ADMIN ACCESS</span>
              <i class="bx bx-right-arrow-alt ms-2"></i>
            </button>
          </form>

          <div class="form-footer mt-3">
            <a href="{{ route('password.request') }}" class="footer-link link-forgot">Forgot Admin Password?</a>
          </div>

        </div>{{-- end .form-inner --}}
      </div>{{-- end .form-panel --}}

    </div>
  </div>
</div>

{{-- LOCAL DEVELOPMENT QUICK LOGIN --}}
{{-- Dev one-click logins: local environment only, and only for plain localhost traffic or a tunnel request that has
     already passed the passphrase gate (App\Http\Middleware\TunnelAccessGate) -- so the panel, and the staff emails
     listed in its page source, never reach an anonymous visitor arriving through a tunnel or proxy hostname. --}}
@if (\App\Http\Middleware\TunnelAccessGate::isLocalShortcutAllowed(request()))
<style>
  /* The panel is anchored to the bottom and grows upward. At 16 accounts it
     ran off the top of the screen and those buttons became unreachable, so it
     is capped to the viewport and the list scrolls inside it. */
  .quick-login-panel { max-height: calc(100vh - 32px); display: flex; flex-direction: column; }
  .quick-login-panel .qlp-list {
    overflow-y: auto; overflow-x: hidden; min-height: 0;
    scrollbar-width: thin; scrollbar-color: rgba(6,237,249,.45) transparent;
  }
  .quick-login-panel .qlp-list::-webkit-scrollbar { width: 6px; }
  .quick-login-panel .qlp-list::-webkit-scrollbar-thumb {
    background: rgba(6,237,249,.45); border-radius: 3px;
  }
  /* Compact enough that the whole list fits on a laptop screen. */
  .quick-login-panel .btn {
    font-size: 9px !important; line-height: 1.15; padding: 4px 6px !important;
    white-space: normal; text-align: left;
  }
</style>
<div class="quick-login-panel animate__animated animate__fadeInUp" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(10px); padding: 10px; border-radius: 14px; border: 1px solid rgba(6, 237, 249, 0.3); box-shadow: 0 10px 25px rgba(0,0,0,0.5); width: 205px;">
    <div style="color: #06edf9; font-size: 10px; font-weight: 800; letter-spacing: 1px; margin-bottom: 6px; text-align: center; border-bottom: 1px solid rgba(6, 237, 249, 0.2); padding-bottom: 4px; flex-shrink: 0;">DEV QUICK ACCESS</div>
    <div class="d-grid gap-1 qlp-list">
        <button type="button" class="btn btn-sm btn-outline-info" style="font-size: 10px; border-color: rgba(6, 237, 249, 0.5); color: #fff;" onclick="quickLogin('ai@rustx.com', 'admin')">LOGIN AS ADMIN (NAGENDER)</button>
        <button type="button" class="btn btn-sm btn-outline-info" style="font-size: 10px; border-color: rgba(6, 237, 249, 0.5); color: #fff;" onclick="quickLogin('suchita@rustx.com', 'admin')">LOGIN AS HR (SUCHITA)</button>
        <button type="button" class="btn btn-sm btn-outline-info" style="font-size: 10px; border-color: rgba(6, 237, 249, 0.5); color: #fff;" onclick="quickLogin('manager@demo.com', 'employee')">LOGIN AS MANAGER</button>
        <button type="button" class="btn btn-sm btn-outline-info" style="font-size: 10px; border-color: rgba(6, 237, 249, 0.5); color: #fff;" onclick="quickLogin('emp@demo.com', 'employee')">LOGIN AS EMPLOYEE</button>
        <button type="button" class="btn btn-sm" style="font-size: 10px; border: 1px solid rgba(245,158,11,0.6); color: #fcd34d; background: rgba(245,158,11,0.12);" onclick="quickLogin('pa1@rustx.com', 'employee')">🔁 HEENA (DELEGATE)</button>
        <button type="button" class="btn btn-sm btn-outline-primary" style="font-size: 10px; border-color: rgba(105, 108, 255, 0.5); color: #fff;" onclick="quickLogin('newbiz@doctorrust.com', 'employee')">SALES (NAVIN JOSHI)</button>
        <button type="button" class="btn btn-sm btn-outline-danger" style="font-size: 10px; border-color: rgba(255, 62, 29, 0.5); color: #fff;" onclick="quickLogin('ccare5@rustx.com', 'employee')">CCARE (SHALLU)</button>
        <button type="button" class="btn btn-sm btn-outline-danger" style="font-size: 10px; border-color: rgba(255, 62, 29, 0.5); color: #fff;" onclick="quickLogin('ccare4@rustx.com', 'employee')">CCARE (NIDHI)</button>
        <button type="button" class="btn btn-sm btn-outline-danger" style="font-size: 10px; border-color: rgba(255, 62, 29, 0.5); color: #fff;" onclick="quickLogin('ccare2@rustx.com', 'employee')">CCARE+NEWBIZ (POONAM)</button>
        <button type="button" class="btn btn-sm" style="font-size: 10px; border: 1px solid rgba(14,165,233,0.6); color: #7dd3fc; background: rgba(14,165,233,0.12);" onclick="quickLogin('export3@rustx.com', 'employee')">EXPORT (DEBLINA)</button>
        <button type="button" class="btn btn-sm" style="font-size: 10px; border: 1px solid rgba(217,70,239,0.6); color: #f0abfc; background: rgba(217,70,239,0.12);" onclick="quickLogin('finance@rustx.com', 'employee')">FINANCE MGR (ARPANA)</button>
        <button type="button" class="btn btn-sm btn-outline-success" style="font-size: 10px; border-color: rgba(40, 199, 111, 0.5); color: #fff;" onclick="quickLogin('accounts5@rustx.com', 'employee')">ACCOUNTS (SHUBHAM)</button>
        <button type="button" class="btn btn-sm btn-outline-warning" style="font-size: 10px; border-color: rgba(255, 159, 67, 0.5); color: #fff;" onclick="quickLogin('priyanshuchoker1@gmail.com', 'employee')">LOGIN AS ONBOARDING</button>
        <button type="button" class="btn btn-sm" style="font-size: 10px; border: 1px solid rgba(168,85,247,0.6); color: #d8b4fe; background: rgba(168,85,247,0.12);" onclick="quickLogin('tanisharana0910@gmail.com', 'employee')">📋 SUBMITTED (REVIEW)</button>
        <button type="button" class="btn btn-sm" style="font-size: 10px; border: 1px solid rgba(251,146,60,0.6); color: #fdba74; background: rgba(251,146,60,0.12);" onclick="quickLogin('JAIPRABHU18@GMAIL.COM', 'employee')">🎓 SUBMITTED + TRAINING</button>
    </div>
    <div style="color: rgba(255,255,255,0.5); font-size: 8px; margin-top: 6px; text-align: center; flex-shrink: 0;">OTP bypass enabled: 123456</div>
</div>

<script>
function quickLogin(email, role, pass = 'password') {
    switchRole(role);
    document.querySelector('.input-email').value = email;
    document.getElementById('login_password').value = pass;
    // Small delay to let role switch visuals finish
    setTimeout(() => {
        document.querySelector('.captcha-input-field').value = currentCaptcha;
        document.querySelector('form').submit();
    }, 300);
}
</script>
@endif

<script>
  let currentCaptcha = '';

  function generateCaptcha() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < 4; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
    currentCaptcha = code;
    document.querySelectorAll('.captcha-code-display').forEach(el => el.innerText = code);
  }

  function switchRole(role) {
    const empBtns  = document.querySelectorAll('.role-employee-btn');
    const adminBtns = document.querySelectorAll('.role-admin-btn');
    const isEmployee = role === 'employee';

    empBtns.forEach(b => b.classList.toggle('active', isEmployee));
    adminBtns.forEach(b => b.classList.toggle('active', !isEmployee));

    document.querySelectorAll('.label-username').forEach(l => l.innerText = isEmployee ? 'EMPLOYEE ID / EMAIL' : 'ADMINISTRATOR EMAIL');
    document.querySelectorAll('.input-email').forEach(i => i.placeholder = isEmployee ? 'employee@hitechgroup.in' : 'admin@hitechgroup.in');
    document.querySelectorAll('.login-title').forEach(t => t.innerText = 'Secure Login');
    document.querySelectorAll('.login-subtitle').forEach(s => s.innerText = isEmployee ? 'Please verify your identity to access your personal portal.' : 'Please verify your identity to access the management portal.');
    document.querySelectorAll('.btn-text').forEach(b => b.innerText = isEmployee ? 'PORTAL ACCESS' : 'ADMIN ACCESS');
    document.querySelectorAll('.link-forgot').forEach(l => l.innerText = isEmployee ? 'Forgot Password?' : 'Forgot Admin Password?');
  }

  function validateAndSubmit(e) {
    const input = document.querySelector('.captcha-input-field');
    if (!input || input.value.toUpperCase() !== currentCaptcha) {
      e.preventDefault();
      document.querySelectorAll('.captcha-error').forEach(el => el.style.display = 'block');
      generateCaptcha();
      return false;
    }
    document.querySelectorAll('.captcha-error').forEach(el => el.style.display = 'none');
    return true;
  }

  function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bx-show', 'bx-hide');
        icon.style.color = '#005a5a';
    } else {
        input.type = 'password';
        icon.classList.replace('bx-hide', 'bx-show');
        icon.style.color = '#94a3b8';
    }
  }

  window.onload = generateCaptcha;
</script>
@endsection
