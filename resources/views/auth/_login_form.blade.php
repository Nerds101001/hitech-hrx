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

  <div class="mb-2">
    <label for="email" class="form-label label-username">ADMINISTRATOR EMAIL</label>
    <div class="hitech-input-group">
      <i class="bx bx-envelope group-icon"></i>
      <input type="email" class="form-control input-email" name="email"
        placeholder="admin@hitechgroup.in" value="{{ old('email') }}" required autofocus>
    </div>
    @error('email')
      <span class="text-danger small">{{ $message }}</span>
    @enderror
  </div>

  <div class="mb-2 form-password-toggle">
    <label class="form-label" for="password">PASSWORD</label>
    <div class="hitech-input-group">
      <i class="bx bx-lock-alt group-icon"></i>
      <input type="password" class="form-control" name="password"
        placeholder="············" required />
    </div>
    @error('password')
      <span class="text-danger small">{{ $message }}</span>
    @enderror
  </div>

  {{-- CAPTCHA --}}
  <div class="mb-3">
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

  <div class="mb-2">
    <button class="btn btn-primary d-flex align-items-center justify-content-center w-100 hitech-btn-admin" type="submit">
      <span class="btn-text">ADMIN ACCESS</span>
      <i class="bx bx-right-arrow-alt ms-2"></i>
    </button>
  </div>
</form>

<div class="form-footer">
  <a href="javascript:void(0);" class="footer-link link-forgot">Forgot Admin Password?</a>
</div>
