<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; }
  .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg, #004d54 0%, #008080 100%); padding: 36px 40px; color: white; text-align: center; }
  .header h1 { margin: 0 0 6px; font-size: 28px; font-weight: 800; letter-spacing: -0.5px; }
  .header p { margin: 0; opacity: 0.8; font-size: 14px; }
  .body { padding: 36px 40px; text-align: center; }
  .body p { color: #4a5568; line-height: 1.7; font-size: 15px; text-align: left; }
  .btn-wrapper { margin: 30px 0; text-align: center; }
  .btn-survey {
    display: inline-block;
    background: #008080;
    color: white !important;
    text-decoration: none;
    padding: 12px 30px;
    font-size: 16px;
    font-weight: bold;
    border-radius: 50px;
    box-shadow: 0 4px 15px rgba(0, 128, 128, 0.3);
    transition: all 0.2s ease;
  }
  .detail-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 24px 0; text-align: left; }
  .detail-row { display: flex; align-items: flex-start; margin-bottom: 12px; }
  .detail-row:last-child { margin-bottom: 0; }
  .detail-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; width: 130px; flex-shrink: 0; }
  .detail-value { font-size: 14px; color: #1e293b; font-weight: 600; flex: 1; }
  .footer { background: #f8fafc; padding: 24px 40px; text-align: center; border-top: 1px solid #e2e8f0; }
  .footer p { margin: 0; font-size: 12px; color: #94a3b8; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>HRX</h1>
    <p>Client Feedback Survey</p>
  </div>
  <div class="body">
    <p>Dear <strong>{{ $visit->client->contact_person ?: $visit->client->name }}</strong>,</p>
    <p>Our sales representative, <strong>{{ $visit->salesperson->name }}</strong>, recently visited you regarding the scheduled <strong>{{ str_replace('_', ' ', $visit->visit_type) }}</strong>. We are committed to providing the highest quality of service and would appreciate it if you could take a brief moment to rate your experience.</p>
    
    <div class="detail-card">
      <div class="detail-row">
        <div class="detail-label">Visit Type</div>
        <div class="detail-value">{{ ucwords(str_replace('_', ' ', $visit->visit_type)) }}</div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Representative</div>
        <div class="detail-value">{{ $visit->salesperson->name }}</div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Completed On</div>
        <div class="detail-value">{{ $visit->completed_at ? $visit->completed_at->format('l, d M Y') : '' }}</div>
      </div>
    </div>

    <p>Please click the button below to rate the visit on a 5-emoji scale. Your feedback helps us serve you better!</p>

    <div class="btn-wrapper">
      <a href="{{ route('sales-visits.survey', $visit->survey_token) }}" target="_blank" class="btn-survey">
        ⭐ Rate Our Visit
      </a>
    </div>
  </div>
  <div class="footer">
    <p>This is an automated message from HRX &mdash; Client Relations.<br>Please do not reply to this email.</p>
  </div>
</div>
</body>
</html>
