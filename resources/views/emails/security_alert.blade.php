<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Security Alert - Hi Tech Group</title>
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
      position: relative; z-index: 1;
    }
    .header-title {
      font-size: 28px;
      font-weight: 800;
      color: #ffffff;
      line-height: 1.25;
      position: relative; z-index: 1;
    }

    /* BODY */
    .email-body { padding: 40px 40px 32px; }
    .greeting { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
    .message-text { font-size: 14.5px; color: #64748b; line-height: 1.8; margin-bottom: 28px; }

    /* ALERT BOX */
    .alert-card {
      background: #fef2f2;
      border: 1px solid #fee2e2;
      border-radius: 20px;
      padding: 24px;
      margin-bottom: 28px;
    }
    .alert-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #fee2e2;
    }
    .alert-row:last-child { border-bottom: none; }
    .alert-label { font-size: 12px; color: #991b1b; font-weight: 700; text-transform: uppercase; }
    .alert-value { font-size: 13px; color: #1e293b; font-weight: 600; }

    /* CTA BUTTON */
    .cta-container { text-align: center; margin-bottom: 32px; }
    .cta-button {
      display: inline-block;
      background: #991b1b;
      color: #ffffff !important;
      text-decoration: none;
      font-size: 15px; font-weight: 700;
      padding: 16px 48px; border-radius: 50px;
      box-shadow: 0 8px 24px rgba(153,27,27,0.3);
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
      <div class="header-badge">Security Alert</div>
      <h1 class="header-title">Unusual Activity Detected</h1>
    </div>
  </div>

  <div class="email-body">
    <p class="greeting">Security Notification,</p>
    <p class="message-text">
        The Hi Tech Group security system has detected multiple failed login attempts on your account and has taken defensive measures to protect your data.
    </p>

    <div class="alert-card">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #fee2e2;">
                    <span style="font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase;">Incident Reason</span><br>
                    <span style="font-size: 14px; font-weight: 700; color: #1e293b;">{{ $details['reason'] }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #fee2e2;">
                    <span style="font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase;">Target Account</span><br>
                    <span style="font-size: 14px; font-weight: 700; color: #1e293b;">{{ $details['email'] }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px 10px 10px 0; width: 50%; border-bottom: 1px solid #fee2e2;">
                    <span style="font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase;">Source IP</span><br>
                    <span style="font-size: 14px; font-weight: 700; color: #1e293b;">{{ $details['ip'] }}</span>
                </td>
                <td style="padding: 10px 0; width: 50%; border-bottom: 1px solid #fee2e2;">
                    <span style="font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase;">Action Taken</span><br>
                    <span style="font-size: 14px; font-weight: 700; color: #1e293b;">{{ $details['action'] }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
      <a href="{{ url('/password/reset') }}" class="cta-button">Secure My Account</a>
    </div>

    <p style="font-size: 12.5px; color: #94a3b8; text-align: center; line-height: 1.7;">
      If this was not you, please immediately notify our security team at
      <a href="mailto:suchita@rustx.com" style="color: #991b1b; font-weight: 700;">suchita@rustx.com</a>
    </p>
  </div>

  <div class="email-footer">
    <p class="footer-copy">(c) {{ date('Y') }} Hi Tech Group. All rights reserved.</p>
  </div>
</div>
</body>
</html>
