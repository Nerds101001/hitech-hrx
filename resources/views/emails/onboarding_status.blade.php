<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Onboarding Update - Hi Tech Group</title>
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

    /* STATUS BOX */
    .status-card {
      background: linear-gradient(135deg, #f0fdfb 0%, #f8fafc 100%);
      border: 1px solid #b2e0e0;
      border-radius: 20px;
      padding: 28px 32px;
      margin-bottom: 28px;
      text-align: center;
    }
    .status-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 16px;
    }
    .status-approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .status-resubmit { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }

    .notes-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 20px;
        margin-top: 20px;
        text-align: left;
    }
    .notes-label {
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .notes-content {
        font-size: 13.5px;
        color: #475569;
        line-height: 1.6;
    }

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

    @media (max-width: 640px) {
      .email-wrapper { margin: 0; border-radius: 0; }
      .email-header, .email-body, .email-footer { padding: 28px 24px; }
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
      <div class="header-badge">Application Update</div>
      <h1 class="header-title">
        @if($status === 'approved')
            Onboarding Confirmed
        @else
            Update Required
        @endif
      </h1>
    </div>
  </div>

  <div class="email-body">
    <p class="greeting">Hi {{ $user->first_name }},</p>
    <p class="message-text">
        @if($status === 'approved')
            Great news! Your onboarding application has been <strong>approved</strong>. You now have full access to the Hi Tech Group employee portal.
        @else
            Thank you for submitting your onboarding details. Our HR team has reviewed your application and requires some <strong>additional information</strong> or corrections before we can proceed.
        @endif
    </p>

    <div class="status-card">
      <div class="status-badge {{ $status === 'approved' ? 'status-approved' : 'status-resubmit' }}">
        {{ $status === 'approved' ? 'Approved' : 'Needs Update' }}
      </div>
      
      @if($notes)
        <div class="notes-box">
            <div class="notes-label">Notes from HR Team</div>
            <div class="notes-content">{{ $notes }}</div>
        </div>
      @endif
    </div>

    <div class="cta-container">
      <a href="{{ url('/login') }}" class="cta-button">
        {{ $status === 'approved' ? 'Go to Dashboard' : 'Review & Resubmit' }}
      </a>
    </div>

    <p style="font-size: 12.5px; color: #94a3b8; text-align: center; line-height: 1.7;">
      If you have any questions regarding your status, please contact HR at
      <a href="mailto:suchita@rustx.com" style="color: #008080;">suchita@rustx.com</a>
    </p>
  </div>

  <div class="email-footer">
    <div class="footer-logo">
      <span class="footer-logo-text">Hi Tech <span>Group</span></span>
    </div>
    <p class="footer-copy">(c) {{ date('Y') }} Hi Tech Group. All rights reserved.</p>
  </div>
</div>

</body>
</html>
