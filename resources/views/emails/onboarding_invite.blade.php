<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome to the Team - Hi Tech HRX</title>
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
    .email-header::after {
      content: '';
      position: absolute;
      width: 180px; height: 180px;
      border-radius: 50%;
      background: rgba(255,255,255,0.03);
      bottom: -60px; left: -30px;
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
    .header-subtitle {
      color: rgba(255,255,255,0.72);
      font-size: 14px;
      margin-top: 8px;
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

    /* CREDENTIALS CARD */
    .credentials-card {
      background: linear-gradient(135deg, #f0fdfb 0%, #f8fafc 100%);
      border: 1px solid #b2e0e0;
      border-radius: 20px;
      padding: 28px 32px;
      margin-bottom: 28px;
    }
    .credentials-title {
      font-size: 11px;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .credentials-title::before {
      content: '';
      display: inline-block;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: #008080;
    }
    .cred-row {
      width: 100%;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 14px 18px;
      margin-bottom: 12px;
    }
    .cred-row:last-child { margin-bottom: 0; }
    .cred-icon-cell { width: 36px; vertical-align: middle; }
    .cred-icon-badge {
      width: 36px; height: 36px;
      line-height: 36px;
      border-radius: 10px;
      background: rgba(0,128,128,0.1);
      display: inline-block;
      text-align: center;
      font-size: 14px;
      font-weight: 700;
      color: #005a5a;
    }
    .cred-content {
      vertical-align: middle;
      padding-left: 14px;
    }
    .cred-label {
      font-size: 11px;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 2px;
    }
    .cred-value {
      font-size: 14px;
      font-weight: 700;
      color: #1e293b;
      font-family: 'Courier New', monospace;
      letter-spacing: 0.3px;
    }

    /* STEPS */
    .steps-section { margin-bottom: 28px; }
    .steps-title {
      font-size: 13px;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 16px;
    }
    .step-item { width: 100%; margin-bottom: 14px; }
    .step-number {
      width: 18px;
      font-size: 13px;
      font-weight: 800;
      color: #0f172a;
      line-height: 1;
      text-align: right;
      vertical-align: top;
      padding-right: 10px;
    }
    .step-text {
      font-size: 13.5px;
      color: #475569;
      line-height: 1.6;
      padding-top: 2px;
      padding-left: 10px;
    }

    /* WARNING BANNER */
    .warning-banner {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-radius: 14px;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 32px;
      font-size: 13px;
      color: #92400e;
      line-height: 1.5;
    }
    .warning-icon { font-size: 14px; font-weight: 700; flex-shrink: 0; color: #b45309; }

    /* CTA BUTTON */
    .cta-container { text-align: center; margin-bottom: 32px; }
    .cta-button {
      display: inline-block;
      background: linear-gradient(135deg, #005a5a, #008080);
      color: #ffffff !important;
      text-decoration: none;
      font-size: 15px;
      font-weight: 700;
      padding: 16px 48px;
      border-radius: 50px;
      box-shadow: 0 8px 24px rgba(0,90,90,0.35);
      letter-spacing: 0.3px;
    }

    /* DIVIDER */
    .divider {
      border: none;
      border-top: 1px solid #f1f5f9;
      margin: 24px 0;
    }

    /* FOOTER */
    .email-footer {
      background: #f8fafc;
      border-top: 1px solid #e2e8f0;
      padding: 28px 40px;
      text-align: center;
    }
    .footer-logo {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 14px;
    }
    .footer-logo-text {
      font-size: 14px;
      font-weight: 800;
      color: #005a5a;
    }
    .footer-logo-text span { color: #00a3a3; }
    .footer-tagline {
      font-size: 11px;
      color: #94a3b8;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 16px;
    }
    .footer-links {
      margin-bottom: 16px;
    }
    .footer-links a {
      color: #64748b;
      text-decoration: none;
      font-size: 12px;
      margin: 0 8px;
    }
    .footer-copy {
      font-size: 11px;
      color: #cbd5e1;
    }

    @media (max-width: 640px) {
      .email-wrapper { margin: 0; border-radius: 0; }
      .email-header, .email-body, .email-footer { padding: 28px 24px; }
      .header-title { font-size: 22px; }
      .cred-row { flex-direction: column; align-items: flex-start; gap: 8px; }
    }
  </style>
</head>
<body>

<div class="email-wrapper">
  <!-- HEADER -->
  <div class="email-header">
    <div class="logo-container">
      <img src="{{ asset('assets/img/logo-white.png') }}" alt="Hi Tech Group" class="logo-img">
    </div>
    <div>
      <div class="header-badge">You're Invited</div>
      <h1 class="header-title">Welcome to the Team,<br>{{ $user->first_name }}!</h1>
      <p class="header-subtitle">Your onboarding journey starts now.</p>
    </div>
  </div>

  <!-- BODY -->
  <div class="email-body">
    <p class="greeting">Hi {{ $user->first_name }},</p>
    <p class="message-text">
      We're thrilled to have you join <strong>Hi Tech Group</strong>! Your HR team has set up your account and you're all set to begin your onboarding process. Please use the credentials below to log in and complete your pre-joining formalities.
    </p>

    <!-- CREDENTIALS CARD -->
    <div class="credentials-card">
      <div class="credentials-title">Your Login Credentials</div>

      <div class="cred-row">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td class="cred-icon-cell">
            <span class="cred-icon-badge">@</span>
          </td>
          <td class="cred-content">
            <div class="cred-label">Login Email</div>
            <div class="cred-value">{{ $user->email }}</div>
          </td>
        </tr>
      </table>
      </div>

      <div class="cred-row">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td class="cred-icon-cell">
            <span class="cred-icon-badge">Key</span>
          </td>
          <td class="cred-content">
            <div class="cred-label">Temporary Password</div>
            <div class="cred-value">{{ $password }}</div>
          </td>
        </tr>
      </table>
      </div>
    </div>

    <!-- STEPS -->
    <div class="steps-section">
      <p class="steps-title">Here's What to Do Next:</p>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="step-item">
        <tr>
          <td class="step-number">1.</td>
          <td class="step-text">Click the <strong>"Start Onboarding"</strong> button below and log in with your credentials.</td>
        </tr>
      </table>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="step-item">
        <tr>
          <td class="step-number">2.</td>
          <td class="step-text">Complete all sections of the onboarding form - personal details, documents, and bank information.</td>
        </tr>
      </table>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="step-item">
        <tr>
          <td class="step-number">3.</td>
          <td class="step-text">Submit your form for HR review. You'll be notified once your account is fully activated.</td>
        </tr>
      </table>
    </div>

    <!-- WARNING -->
    <div class="warning-banner">
      <span class="warning-icon">!</span>
      <span>Please complete your onboarding form within <strong>3 days</strong>. After this period, your temporary account will be deactivated for security reasons.</span>
    </div>

    <!-- CTA -->
    <div class="cta-container">
      <a href="{{ url('/login') }}" class="cta-button">Start My Onboarding</a>
    </div>

    <hr class="divider">

    <p style="font-size: 12.5px; color: #94a3b8; text-align: center; line-height: 1.7;">
      If you did not expect this email or believe this was sent in error, please ignore it or contact HR at
      <a href="mailto:suchita@rustx.com" style="color: #008080;">suchita@rustx.com</a>
    </p>
  </div>

  <!-- FOOTER -->
  <div class="email-footer">
    <div class="footer-logo">
      <span class="footer-logo-text">Hi Tech <span>HRX</span></span>
    </div>
    <p class="footer-tagline">Next-Gen HRMS - Powered by Hi Tech Group</p>
    <div class="footer-links">
      <a href="{{ url('/') }}">Portal</a>
      <a href="{{ url('/onboarding/status') }}">Onboarding Status</a>
      <a href="mailto:suchita@rustx.com">Contact HR</a>
    </div>
    <p class="footer-copy">(c) {{ date('Y') }} Hi Tech Group. All rights reserved.<br>
    Plot 6 IMT Manesar, Gurugram, Haryana - +91 9814215000</p>
  </div>
</div>

</body>
</html>
