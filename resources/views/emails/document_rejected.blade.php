<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document Rejected — Action Required</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; background-color: #f0f4f8; -webkit-font-smoothing: antialiased; }

    .email-wrapper {
      max-width: 620px;
      margin: 32px auto;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,0.12);
      background: #ffffff;
    }

    /* ── HEADER ── */
    .email-header {
      background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 50%, #b91c1c 100%);
      padding: 36px 40px 32px;
      text-align: center;
    }
    .logo-box {
      display: inline-block;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 12px;
      padding: 10px 20px;
      margin-bottom: 22px;
    }
    .logo-box img { height: 44px; width: auto; display: block; }
    .header-badge {
      display: inline-block;
      background: rgba(252,165,165,0.2);
      border: 1px solid rgba(252,165,165,0.4);
      color: #fca5a5;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 1.8px;
      text-transform: uppercase;
      padding: 5px 16px;
      border-radius: 20px;
      margin-bottom: 18px;
    }
    .header-icon-circle {
      width: 70px; height: 70px;
      background: rgba(255,255,255,0.14);
      border: 2px solid rgba(255,255,255,0.22);
      border-radius: 50%;
      margin: 0 auto 18px;
      line-height: 70px;
      text-align: center;
      font-size: 30px;
    }
    .header-title {
      font-size: 26px;
      font-weight: 800;
      color: #ffffff;
      line-height: 1.25;
      margin-bottom: 8px;
    }
    .header-subtitle {
      font-size: 14px;
      color: rgba(255,255,255,0.72);
      line-height: 1.5;
    }

    /* ── BODY ── */
    .email-body { padding: 36px 40px; }

    .greeting { font-size: 16px; color: #1e293b; font-weight: 700; margin-bottom: 10px; }
    .intro-text { font-size: 14px; color: #475569; line-height: 1.75; margin-bottom: 24px; }

    /* Rejected doc alert */
    .doc-alert {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-left: 4px solid #ef4444;
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 22px;
    }
    .doc-alert-label {
      font-size: 11px;
      font-weight: 700;
      color: #991b1b;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 4px;
    }
    .doc-alert-name {
      font-size: 17px;
      font-weight: 800;
      color: #7f1d1d;
    }

    /* Remarks box */
    .remarks-box {
      background: #fff8f8;
      border: 1px dashed #fca5a5;
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 26px;
    }
    .remarks-label {
      font-size: 11px;
      font-weight: 700;
      color: #991b1b;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 8px;
    }
    .remarks-text {
      font-size: 14px;
      color: #374151;
      line-height: 1.75;
      font-style: italic;
    }

    /* Steps — table-based, email-safe */
    .steps-heading {
      font-size: 12px;
      font-weight: 700;
      color: #1e293b;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 16px;
    }
    .steps-table { border-collapse: collapse; width: 100%; margin-bottom: 28px; }
    .steps-table td { padding: 0; vertical-align: top; }
    .step-num-cell { width: 34px; padding-bottom: 14px; }
    .step-circle {
      display: inline-block;
      width: 28px;
      height: 28px;
      background: #ef4444;
      color: #ffffff;
      border-radius: 50%;
      text-align: center;
      line-height: 28px;
      font-size: 13px;
      font-weight: 700;
    }
    .step-text-cell { padding-left: 10px; padding-bottom: 14px; }
    .step-text { font-size: 14px; color: #475569; line-height: 1.65; }
    .step-text strong { color: #1e293b; font-weight: 700; }

    /* CTA button */
    .cta-wrapper { text-align: center; margin: 4px 0 28px; }
    .cta-btn {
      display: inline-block;
      background: #b91c1c;
      color: #ffffff !important;
      text-decoration: none;
      font-size: 15px;
      font-weight: 700;
      padding: 14px 40px;
      border-radius: 50px;
      letter-spacing: 0.3px;
    }

    /* Divider */
    .divider { border: none; border-top: 1px dashed #e2e8f0; margin: 24px 0; }

    /* Info table */
    .info-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    .info-table tr td { padding: 10px 14px; font-size: 13px; }
    .info-table tr:nth-child(odd) td { background: #f8fafc; }
    .info-table tr:nth-child(even) td { background: #ffffff; }
    .info-table td:first-child {
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: 0.5px;
      width: 38%;
      border-right: 1px solid #f1f5f9;
    }
    .info-table td:last-child { font-weight: 600; color: #1e293b; }

    /* Footer */
    .email-footer { background: #1e293b; padding: 24px 40px; text-align: center; }
    .footer-brand { font-size: 13px; font-weight: 700; color: #cbd5e1; margin-bottom: 6px; }
    .footer-text { font-size: 12px; color: #94a3b8; line-height: 1.65; }
    .note-text { font-size: 12px; color: #94a3b8; text-align: center; line-height: 1.65; }
  </style>
</head>
<body>
<div class="email-wrapper">

  <!-- HEADER -->
  <div class="email-header">
    <div class="logo-box">
      <img src="{{ asset('assets/img/logo.png') }}" alt="Hi Tech Group">
    </div>
    <div class="header-badge">⚠&nbsp;&nbsp;Action Required</div>
    <div class="header-icon-circle">🚫</div>
    <div class="header-title">Document Rejected</div>
    <div class="header-subtitle">An uploaded document requires your immediate attention</div>
  </div>

  <!-- BODY -->
  <div class="email-body">

    <p class="greeting">Hello, {{ $employee->first_name ?? $employee->name }}!</p>
    <p class="intro-text">
      Your HR team has reviewed your profile and found that one of your submitted documents
      does not meet the required standards. Please re-upload the correct document at your
      earliest convenience to maintain your account access.
    </p>

    <!-- Rejected document -->
    <div class="doc-alert">
      <div class="doc-alert-label">Rejected Document</div>
      <div class="doc-alert-name">{{ $documentName }}</div>
    </div>

    <!-- Remarks -->
    <div class="remarks-box">
      <div class="remarks-label">Reason / Remarks from HR</div>
      <div class="remarks-text">&ldquo;{{ $adminRemarks }}&rdquo;</div>
    </div>

    <!-- Steps -->
    <div class="steps-heading">What You Need To Do</div>
    <table class="steps-table" role="presentation">
      <tr>
        <td class="step-num-cell"><span class="step-circle">1</span></td>
        <td class="step-text-cell"><span class="step-text">Log in to the HR portal using your registered credentials.</span></td>
      </tr>
      <tr>
        <td class="step-num-cell"><span class="step-circle">2</span></td>
        <td class="step-text-cell"><span class="step-text">Navigate to your <strong>Onboarding Form</strong> — it will be re-opened for you automatically.</span></td>
      </tr>
      <tr>
        <td class="step-num-cell"><span class="step-circle">3</span></td>
        <td class="step-text-cell"><span class="step-text">Upload a clear, valid copy of your <strong>{{ $documentName }}</strong>.</span></td>
      </tr>
      <tr>
        <td class="step-num-cell"><span class="step-circle">4</span></td>
        <td class="step-text-cell"><span class="step-text">Submit the form and wait for HR to re-verify your profile.</span></td>
      </tr>
    </table>

    <!-- CTA -->
    <div class="cta-wrapper">
      <a href="{{ url('/') }}" class="cta-btn">Login &amp; Re-upload Document</a>
    </div>

    <hr class="divider">

    <!-- Info table -->
    <table class="info-table" role="presentation">
      <tr>
        <td>Employee Name</td>
        <td>{{ $employee->name }}</td>
      </tr>
      <tr>
        <td>Employee ID</td>
        <td>{{ $employee->code ?? 'N/A' }}</td>
      </tr>
      <tr>
        <td>Reviewed By</td>
        <td>{{ $reviewerName }}</td>
      </tr>
      <tr>
        <td>Date &amp; Time</td>
        <td>{{ now()->format('d M Y, h:i A') }}</td>
      </tr>
    </table>

    <hr class="divider">

    <p class="note-text">
      If you believe this is a mistake or need assistance, please contact your HR department directly.<br>
      Do not reply to this automated email.
    </p>

  </div>

  <!-- FOOTER -->
  <div class="email-footer">
    <p class="footer-brand">Hi Tech Group &mdash; HR Portal</p>
    <p class="footer-text">
      This is an automated notification from the HRX system.<br>
      &copy; {{ date('Y') }} Hi Tech Group. All rights reserved.
    </p>
  </div>

</div>
</body>
</html>
