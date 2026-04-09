<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🛡️ Security Alert: Your 2-Step Verification Code</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; background-color: #f0f4f8; }

    .email-wrapper {
      max-width: 620px;
      margin: 32px auto;
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 16px 48px rgba(0,0,0,0.12);
      background: #ffffff;
    }

    /* HEADER */
    .email-header {
      background: linear-gradient(135deg, #003d3d 0%, #005a5a 50%, #007a7a 100%);
      padding: 32px 40px 32px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .email-header::before {
      content: '';
      position: absolute;
      width: 250px; height: 250px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
      top: -80px; right: -60px;
    }
    .logo-container {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(255,255,255,0.1);
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 14px;
      padding: 10px 18px;
      min-height: 52px;
      margin: 0 auto 22px;
      position: relative;
      z-index: 1;
    }
    .logo-img {
      height: 46px;
      width: auto;
      display: block;
    }
    .header-badge {
      display: inline-block;
      background: rgba(110,231,231,0.2);
      border: 1px solid rgba(110,231,231,0.35);
      color: #a7f3f3;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      border-radius: 50px;
      padding: 5px 14px;
      margin-bottom: 20px;
      position: relative;
      z-index: 1;
    }
    .header-title {
      font-size: 28px;
      font-weight: 800;
      color: #ffffff;
      line-height: 1.25;
      position: relative;
      z-index: 1;
    }

    /* BODY */
    .email-body { padding: 40px 40px 32px; }

    .greeting {
      font-size: 16px;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 12px;
    }
    .message-text {
      font-size: 14.5px;
      color: #64748b;
      line-height: 1.8;
      margin-bottom: 28px;
    }

    /* OTP CARD */
    .otp-card {
      background: linear-gradient(135deg, #f0fdfb 0%, #f8fafc 100%);
      border: 1px solid #b2e0e0;
      border-radius: 20px;
      padding: 32px;
      text-align: center;
      margin-bottom: 32px;
    }
    .otp-label {
      font-size: 11px;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      margin-bottom: 16px;
    }
    .otp-code {
      font-size: 42px;
      font-weight: 800;
      color: #005a5a;
      letter-spacing: 8px;
      font-family: 'Courier New', monospace;
      margin-bottom: 8px;
    }
    .otp-expiry {
      font-size: 12px;
      color: #64748b;
    }

    /* WARNING */
    .warning-text {
        font-size: 13px;
        color: #94a3b8;
        text-align: center;
        margin-top: 32px;
        line-height: 1.6;
    }

    /* DIVIDER */
    .divider {
      border: none;
      border-top: 1px solid #f1f5f9;
      margin: 32px 0;
    }

    /* FOOTER */
    .email-footer {
      background: #f8fafc;
      border-top: 1px solid #e2e8f0;
      padding: 28px 40px;
      text-align: center;
    }
    .footer-logo-text {
      font-size: 14px;
      font-weight: 800;
      color: #005a5a;
    }
    .footer-logo-text span { color: #00a3a3; }
    .footer-copy {
      font-size: 11px;
      color: #cbd5e1;
      margin-top: 12px;
    }
  </style>
</head>
<body>

<div class="email-wrapper">
  <div class="email-header">
    <div class="logo-container">
      <img src="{{ asset('assets/img/logo-white.png') }}" alt="Hi Tech Group" class="logo-img">
    </div>
    <div>
      <div class="header-badge">Verification Required</div>
      <h1 class="header-title">Identity Check</h1>
    </div>
  </div>

  <div class="email-body">
    <p class="greeting">Hi {{ $user->first_name }},</p>
    <p class="message-text">
        To ensure your account remains secure, we require a quick verification. Please use the 6-digit code below to finalize your login to the <strong>Hitech HRX Portal</strong>.
    </p>

    <div class="otp-card">
      <div class="otp-label">Your Verification Code</div>
      <div class="otp-code">{{ $otp }}</div>
      <div class="otp-expiry">Valid for the next 10 minutes.</div>
    </div>

    <p class="warning-text">
        If you did not attempt to log in to your account, please contact our security team immediately at <strong>hr@hitechgroup.in</strong> and consider changing your password.
    </p>

    <hr class="divider">
    <p style="font-size: 12px; color: #94a3b8; text-align: center;">🛡️ This is a secure notification from Hi Tech HRX.</p>
  </div>

  <div class="email-footer">
    <div class="footer-logo">
      <span class="footer-logo-text">Hi Tech <span>HRX</span></span>
    </div>
    <p class="footer-copy">(c) {{ date('Y') }} Hi Tech Group. All rights reserved.</p>
  </div>
</div>

</body>
</html>
