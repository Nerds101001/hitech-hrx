<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restore Access - Hi Tech Group</title>
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
    .logo-img { height: 46px; width: auto; display: block; }
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
    }
    .header-title { font-size: 28px; font-weight: 800; color: #ffffff; line-height: 1.25; }

    /* BODY */
    .email-body { padding: 40px 40px 32px; }
    .greeting { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
    .message-text { font-size: 14.5px; color: #64748b; line-height: 1.8; margin-bottom: 28px; }

    /* CTA BUTTON */
    .cta-container { text-align: center; margin: 32px 0; }
    .cta-button {
      display: inline-block;
      background: linear-gradient(135deg, #0f172a, #334155);
      color: #ffffff !important;
      text-decoration: none;
      font-size: 15px; font-weight: 700;
      padding: 16px 48px; border-radius: 50px;
      box-shadow: 0 8px 24px rgba(15,23,42,0.3);
    }

    /* FOOTER */
    .email-footer {
      background: #f8fafc; border-top: 1px solid #e2e8f0;
      padding: 28px 40px; text-align: center;
    }
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
      <div class="header-badge">Security Service</div>
      <h1 class="header-title">Unlock Your Account</h1>
    </div>
  </div>

  <div class="email-body">
    <p class="greeting">Hello,</p>
    <p class="message-text">
        We received a request to restore access to your Hi Tech Group portal account. If you've been locked out or requested a password reset, please click the secure link below to proceed.
    </p>

    <div class="cta-container">
      <a href="{{ $unlockUrl }}" class="cta-button">Restore My Access</a>
    </div>

    <p style="font-size: 13px; color: #94a3b8; text-align: center; line-height: 1.6;">
        This secure link will expire in 15 minutes. If you did not request this, please ignore this email; your account will remain protected.
    </p>

    <hr style="border: none; border-top: 1px solid #f1f5f9; margin: 32px 0;">

    <p style="font-size: 12px; color: #94a3b8; text-align: center;">
      Need immediate help? Contact IT Support at
      <a href="mailto:suchita@rustx.com" style="color: #475569; font-weight: 700;">suchita@rustx.com</a>
    </p>
  </div>

  <div class="email-footer">
    <p class="footer-copy">(c) {{ date('Y') }} Hi Tech Group. All rights reserved.</p>
  </div>
</div>
</body>
</html>
