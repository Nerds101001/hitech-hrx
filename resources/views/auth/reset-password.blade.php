@php
  $customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/blankLayout')

@section('title', 'Reset Password - Hitech Secure Gateway')

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
@endsection

@section('content')
<style>
  /* Keep the show/hide toggle inside the password box */
  .hitech-input-group { position: relative; }
  .hitech-input-group .form-control { padding-right: 44px; }
  .hitech-input-group .password-toggle-btn {
    position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
    z-index: 3; display: flex; align-items: center; height: 24px;
  }
  .hitech-input-group .password-toggle-btn i { font-size: 1.15rem; }
</style>
<div class="hitech-gateway-wrapper">
  <div class="gateway-container">
    <div class="gateway-card">

      {{-- LEFT IDENTITY PANEL (desktop only) --}}
      <div class="identity-panel">
        <div class="content">
          <img src="{{ asset('assets/img/logo-white.png') }}" alt="Logo" class="identity-logo" style="width: 200px; max-width: 80%; height: auto; flex-shrink: 0; margin-bottom: 1.5rem;">
          <h1>Set New Password</h1>
          <div class="features">
            <div class="feature-item">
              <i class="bx bx-lock-alt"></i>
              <p>Choose a strong password with at least 8 characters</p>
            </div>
            <div class="feature-item">
              <i class="bx bx-shield-quarter"></i>
              <p>Mix uppercase, lowercase, numbers and symbols for best security</p>
            </div>
            <div class="feature-item">
              <i class="bx bx-check-shield"></i>
              <p>Your password is encrypted and never stored in plain text</p>
            </div>
          </div>
        </div>
        <div class="footer-note">SYSTEM V4.2.0-ADMIN</div>
      </div>

      {{-- RIGHT FORM PANEL --}}
      <div class="form-panel">

        {{-- MOBILE BRAND BANNER --}}
        <div class="mobile-brand-banner">
          <img src="{{ asset('assets/img/logo-white.png') }}" alt="Logo" class="mobile-logo" style="width: 160px; max-width: 70%; height: auto; flex-shrink: 0;">
          <h2>Set New Password</h2>
          <p>Secure Gateway — Choose a strong new password below</p>
          <span class="version-badge">SYSTEM V4.2.0-ADMIN</span>
        </div>

        <div class="form-inner">

          <div class="form-header">
            <h2 class="login-title">Create New Password</h2>
            <p class="login-subtitle">Your new password must be different from your previously used passwords.</p>
          </div>

          {{-- Error Alert --}}
          @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
              <i class="bx bx-error-circle me-2 fs-5"></i>
              <div>{{ $errors->first() }}</div>
            </div>
          @endif

          <form action="{{ route('password.update') }}" method="POST" onsubmit="return validatePasswords(event)">
            @csrf

            {{-- Hidden fields required for reset --}}
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ request('email') }}">

            <div class="mb-4">
              <label class="form-label" style="font-size: 0.72rem; font-weight: 700; letter-spacing: 1px; color: #94a3b8; text-transform: uppercase;">NEW PASSWORD</label>
              <div class="hitech-input-group">
                <i class="bx bx-lock-alt group-icon"></i>
                <input
                  type="password"
                  class="form-control"
                  id="new_password"
                  name="password"
                  placeholder="············"
                  required
                  autofocus
                  minlength="8"
                >
                <button type="button" class="password-toggle-btn" onclick="togglePassword('new_password', this)" style="background:none;border:none;padding:0 12px;color:#94a3b8;cursor:pointer;position:absolute;right:0;top:50%;transform:translateY(-50%);">
                  <i class="bx bx-hide"></i>
                </button>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label" style="font-size: 0.72rem; font-weight: 700; letter-spacing: 1px; color: #94a3b8; text-transform: uppercase;">CONFIRM NEW PASSWORD</label>
              <div class="hitech-input-group">
                <i class="bx bx-lock group-icon"></i>
                <input
                  type="password"
                  class="form-control"
                  id="confirm_password"
                  name="password_confirmation"
                  placeholder="············"
                  required
                  minlength="8"
                >
                <button type="button" class="password-toggle-btn" onclick="togglePassword('confirm_password', this)" style="background:none;border:none;padding:0 12px;color:#94a3b8;cursor:pointer;position:absolute;right:0;top:50%;transform:translateY(-50%);">
                  <i class="bx bx-hide"></i>
                </button>
              </div>
              <div id="password-match-error" class="text-danger small mt-1" style="display:none;">
                <i class="bx bx-error-circle me-1"></i>Passwords do not match.
              </div>
            </div>

            {{-- Password Strength Indicator --}}
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-1">
                <span style="font-size:0.7rem; color:#94a3b8; font-weight:600;">Password Strength</span>
                <span id="strength-label" style="font-size:0.7rem; font-weight:700; color:#94a3b8;">—</span>
              </div>
              <div style="height:4px; border-radius:50px; background:rgba(0,0,0,0.06); overflow:hidden;">
                <div id="strength-bar" style="height:100%; width:0; border-radius:50px; transition: all 0.3s ease; background:#10b981;"></div>
              </div>
              <div id="password-strength-error" class="text-danger small mt-1" style="display:none;">
                <i class="bx bx-error-circle me-1"></i><span id="password-strength-error-text"></span>
              </div>
            </div>

            <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center w-100 hitech-btn-admin">
              <span class="btn-text">Set New Password</span>
              <i class="bx bx-check ms-2"></i>
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

<script>
  // Toggle password visibility
  function togglePassword(fieldId, btn) {
    const field = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (field.type === 'password') {
      field.type = 'text';
      icon.classList.replace('bx-hide', 'bx-show');
    } else {
      field.type = 'password';
      icon.classList.replace('bx-show', 'bx-hide');
    }
  }

  // Password strength meter. Kept in sync with the server-side rule
  // (AuthController::resetPasswordPost — min 8, mixed case, number, symbol)
  // so "Strong" here always means "will actually be accepted".
  function passwordChecks(val) {
    return {
      length: val.length >= 8,
      upper:  /[A-Z]/.test(val),
      lower:  /[a-z]/.test(val),
      number: /[0-9]/.test(val),
      symbol: /[^A-Za-z0-9]/.test(val),
    };
  }
  function isStrongPassword(val) {
    return Object.values(passwordChecks(val)).every(Boolean);
  }
  function passwordRequirementText(val) {
    const c = passwordChecks(val);
    const missing = [];
    if (!c.length) missing.push('at least 8 characters');
    if (!c.upper)  missing.push('an uppercase letter');
    if (!c.lower)  missing.push('a lowercase letter');
    if (!c.number) missing.push('a number');
    if (!c.symbol) missing.push('a symbol');
    return missing.length ? 'Password needs ' + missing.join(', ') + '.' : '';
  }

  document.getElementById('new_password').addEventListener('input', function () {
    const val = this.value;
    const c = passwordChecks(val);
    const score = ['length', 'upper', 'lower', 'number', 'symbol'].filter(k => c[k]).length;

    const bar   = document.getElementById('strength-bar');
    const label = document.getElementById('strength-label');
    const configs = [
      { w: '0%',   color: '#94a3b8', text: '—' },
      { w: '20%',  color: '#ef4444', text: 'Weak' },
      { w: '40%',  color: '#ef4444', text: 'Weak' },
      { w: '60%',  color: '#f59e0b', text: 'Fair' },
      { w: '80%',  color: '#3b82f6', text: 'Good' },
      { w: '100%', color: '#10b981', text: 'Strong' },
    ];
    const cfg = configs[score] || configs[0];
    bar.style.width       = cfg.w;
    bar.style.background  = cfg.color;
    label.textContent     = cfg.text;
    label.style.color     = cfg.color;
  });

  // Match + strength validation on submit — mirrors the server so a user is
  // never told "success" client-side only to be bounced back by validation.
  function validatePasswords(e) {
    const p1  = document.getElementById('new_password').value;
    const p2  = document.getElementById('confirm_password').value;
    const matchErr    = document.getElementById('password-match-error');
    const strengthErr = document.getElementById('password-strength-error');
    const strengthErrText = document.getElementById('password-strength-error-text');

    if (!isStrongPassword(p1)) {
      if (e) e.preventDefault();
      strengthErrText.textContent = passwordRequirementText(p1);
      strengthErr.style.display = 'block';
      return false;
    }
    strengthErr.style.display = 'none';

    // Trimmed comparison: a trailing space from a copy-paste shouldn't read as
    // a mismatch when the server (which also trims before saving) would accept it.
    if (p1.trim() !== p2.trim()) {
      if (e) e.preventDefault();
      matchErr.style.display = 'block';
      return false;
    }
    matchErr.style.display = 'none';
    return true;
  }

  // Clear stale errors as soon as the user starts correcting either field.
  function clearPasswordErrors() {
    document.getElementById('password-match-error').style.display = 'none';
    document.getElementById('password-strength-error').style.display = 'none';
  }
  document.getElementById('new_password').addEventListener('input', clearPasswordErrors);
  document.getElementById('confirm_password').addEventListener('input', clearPasswordErrors);
</script>
@endsection
