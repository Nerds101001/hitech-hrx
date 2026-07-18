<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Your Password</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; background-color: #f0f4f8; }
    .email-wrapper {
      max-width: 620px; margin: 32px auto; border-radius: 24px; overflow: hidden;
      box-shadow: 0 16px 48px rgba(0,0,0,0.12); background: #ffffff;
    }
    .email-header {
      background: linear-gradient(135deg, #003d3d 0%, #005a5a 50%, #007a7a 100%);
      padding: 32px 40px; text-align: center; position: relative; overflow: hidden;
    }
    .email-header::before {
      content: ''; position: absolute; width: 250px; height: 250px; border-radius: 50%;
      background: rgba(255,255,255,0.04); top: -80px; right: -60px;
    }
    .logo-container {
      display: inline-flex; align-items: center; justify-content: center;
      background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.18);
      border-radius: 14px; padding: 10px 18px; min-height: 52px; margin: 0 auto 22px;
      position: relative; z-index: 1;
    }
    .logo-img { height: 46px; width: auto; display: block; }
    .header-badge {
      display: inline-block; background: rgba(110,231,231,0.2);
      border: 1px solid rgba(110,231,231,0.35); color: #a7f3f3; font-size: 11px;
      font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
      border-radius: 50px; padding: 5px 14px; margin-bottom: 20px; position: relative; z-index: 1;
    }
    .header-title {
      font-size: 28px; font-weight: 800; color: #ffffff; line-height: 1.25;
      position: relative; z-index: 1;
    }
    .email-body { padding: 40px 40px 32px; }
    .greeting { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
    .message-text { font-size: 14.5px; color: #64748b; line-height: 1.8; margin-bottom: 28px; }
    .btn-container { text-align: center; margin: 32px 0; }
    .btn {
      display: inline-block; background: #008080; color: #ffffff !important; text-decoration: none;
      padding: 16px 40px; border-radius: 12px; font-weight: 700; font-size: 15px;
    }
    .expiry-note {
      background: linear-gradient(135deg, #fff7ed 0%, #fffbeb 100%);
      border: 1px solid #fed7aa; border-radius: 14px; padding: 16px 20px;
      font-size: 13px; color: #9a3412; text-align: center; margin: 8px 0 24px; font-weight: 600;
    }
    .link-fallback { font-size: 12px; color: #94a3b8; text-align: center; line-height: 1.7; word-break: break-all; }
    .link-fallback a { color: #0d9488; }
    .warning-text { font-size: 13px; color: #94a3b8; text-align: center; margin-top: 32px; line-height: 1.6; }
    .divider { border: none; border-top: 1px solid #f1f5f9; margin: 32px 0; }
    .email-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 28px 40px; text-align: center; }
    .footer-logo-text { font-size: 14px; font-weight: 800; color: #005a5a; }
    .footer-logo-text span { color: #00a3a3; }
    .footer-copy { font-size: 11px; color: #cbd5e1; margin-top: 12px; }
  </style>
</head>
<body>

<div class="email-wrapper">
  <div class="email-header">
    <div class="logo-container">
      <img src="{{ asset('assets/img/logo-white.png') }}" alt="Hi Tech Group" class="logo-img">
    </div>
    <div>
      <div class="header-badge">Account Security</div>
      <h1 class="header-title">Reset Your Password</h1>
    </div>
  </div>

  <div class="email-body">
    <p class="greeting">Hi {{ $user->first_name ?? 'there' }},</p>
    <p class="message-text">
      We received a request to reset the password for your <strong>Hi Tech Group Portal</strong> account.
      Click the button below to choose a new password. If you did not make this request, you can safely ignore this email.
    </p>

    <div class="btn-container">
      <a href="{{ $resetUrl }}" class="btn">Reset Password</a>
    </div>

    <div class="expiry-note">⏱️ This password reset link will expire in {{ $expireMinutes ?? 60 }} minutes for your security.</div>

    <p class="link-fallback">
      If the button doesn't work, copy and paste this link into your browser:<br>
      <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
    </p>

    <p class="warning-text">
      If you did not request a password reset, please contact your HR department or the security team at <strong>suchita@rustx.com</strong>.
    </p>

    <hr class="divider">
    <p style="font-size: 12px; color: #94a3b8; text-align: center;">🛡️ This is an automated security notification from Hi Tech Group.</p>
  </div>

  <div class="email-footer">
    <div class="footer-logo">
      <span class="footer-logo-text">Hi Tech <span>HRX</span></span>
    </div>
    <p class="footer-copy">&copy; {{ date('Y') }} Hi Tech Group. All rights reserved.</p>
  </div>
</div>

</body>
</html>
