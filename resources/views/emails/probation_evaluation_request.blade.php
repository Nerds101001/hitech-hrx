<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Probation Evaluation Required - Hi Tech HRX</title>
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
      position: relative;
      z-index: 1;
    }
    .header-title {
      font-size: 26px;
      font-weight: 800;
      color: #ffffff;
      line-height: 1.25;
      position: relative;
      z-index: 1;
    }

    /* BODY */
    .email-body { padding: 40px 40px 32px; }
    .greeting { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
    .message-text { font-size: 14.5px; color: #64748b; line-height: 1.8; margin-bottom: 28px; }

    /* INFO CARD */
    .info-card {
      background: linear-gradient(135deg, #f0fdfb 0%, #f8fafc 100%);
      border: 1px solid #b2e0e0;
      border-radius: 20px;
      padding: 24px;
      margin-bottom: 28px;
    }
    .info-row { margin-bottom: 12px; }
    .info-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; }
    .info-value { font-size: 14px; font-weight: 700; color: #1e293b; }

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
    .email-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 28px 40px; text-align: center; }
    .footer-logo-text { font-size: 14px; font-weight: 800; color: #005a5a; }
    .footer-logo-text span { color: #00a3a3; }
    .footer-tagline { font-size: 11px; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 16px; }
    .footer-copy { font-size: 11px; color: #cbd5e1; }
  </style>
</head>
<body>
<div class="email-wrapper">
  <div class="email-header">
    <div class="logo-container">
      <img src="{{ asset('assets/img/logo-white.png') }}" alt="Hi Tech Group" class="logo-img">
    </div>
    <div>
      <div class="header-badge">Action Required</div>
      <h1 class="header-title">Probation Evaluation Task</h1>
    </div>
  </div>

  <div class="email-body">
    <p class="greeting">Hi {{ $manager->first_name }},</p>
    <p class="message-text">
        The probation period for <strong>{{ $employee->name }}</strong> has concluded today. As their reporting manager, we require your feedback to determine their employment status. 
        Please complete the comprehensive performance evaluation form using the link below.
    </p>

    <div class="info-card">
        <div class="info-row">
            <div class="info-label">Employee Name</div>
            <div class="info-value">{{ $employee->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Employee Code</div>
            <div class="info-value">{{ $employee->code }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Designation</div>
            <div class="info-value">{{ $employee->designation?->name ?? 'N/A' }}</div>
        </div>
        <div class="info-row" style="margin-bottom: 0;">
            <div class="info-label">Joining Date</div>
            <div class="info-value">{{ \Carbon\Carbon::parse($employee->date_of_joining)->format('d M, Y') }}</div>
        </div>
    </div>

    <div class="cta-container">
      <a href="{{ $url }}" class="cta-button">Start Evaluation Form</a>
    </div>

    <p style="font-size: 13px; color: #94a3b8; line-height: 1.6;">
        Your evaluation is critical for the final confirmation of employment by the HR department. Please ensure all sections are filled accurately.
    </p>
  </div>

  <div class="email-footer">
    <div class="footer-logo">
      <span class="footer-logo-text">Hi Tech <span>HRX</span></span>
    </div>
    <p class="footer-tagline">Next-Gen HRMS - Powered by Hi Tech Group</p>
    <p class="footer-copy">(c) {{ date('Y') }} Hi Tech Group. All rights reserved.</p>
  </div>
</div>
</body>
</html>
