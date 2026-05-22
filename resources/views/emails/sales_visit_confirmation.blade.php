<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; }
  .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg, #004d54 0%, #008080 100%); padding: 36px 40px; color: white; }
  .header h1 { margin: 0 0 6px; font-size: 28px; font-weight: 800; letter-spacing: -0.5px; }
  .header p { margin: 0; opacity: 0.8; font-size: 14px; }
  .badge { display: inline-block; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 50px; padding: 4px 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 12px; }
  .body { padding: 36px 40px; }
  .body p { color: #4a5568; line-height: 1.7; font-size: 15px; }
  .detail-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin: 24px 0; }
  .detail-row { display: flex; align-items: flex-start; margin-bottom: 14px; }
  .detail-row:last-child { margin-bottom: 0; }
  .detail-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; width: 130px; flex-shrink: 0; padding-top: 2px; }
  .detail-value { font-size: 14px; color: #1e293b; font-weight: 600; flex: 1; }
  .type-badge { display: inline-block; background: #e0f2f1; color: #004d54; border-radius: 6px; padding: 3px 10px; font-size: 12px; font-weight: 700; }
  .notice { background: rgba(0,77,84,0.06); border-left: 4px solid #004d54; border-radius: 0 8px 8px 0; padding: 16px 20px; margin: 24px 0; }
  .notice p { margin: 0; color: #004d54; font-size: 14px; font-weight: 600; }
  .footer { background: #f8fafc; padding: 24px 40px; text-align: center; border-top: 1px solid #e2e8f0; }
  .footer p { margin: 0; font-size: 12px; color: #94a3b8; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>HRX</h1>
    <p>Sales & Field Operations</p>
    <span class="badge">{{ strtoupper(str_replace('_', ' ', $visit->visit_type)) }} CONFIRMATION</span>
  </div>
  <div class="body">
    @if($recipientType === 'salesperson')
      <p>Hi <strong>{{ $visit->salesperson->first_name }}</strong>,</p>
      <p>A new client visit has been assigned to you by <strong>{{ $visit->ccAgent->first_name }} {{ $visit->ccAgent->last_name }}</strong>. Please review the details below and make sure to be on time.</p>
    @else
      <p>Dear <strong>{{ $visit->client->contact_person ?: $visit->client->name }}</strong>,</p>
      <p>Thank you for your interest. Our sales representative will visit you at the scheduled time. Please find the details below.</p>
    @endif

    <div class="detail-card">
      <div class="detail-row">
        <div class="detail-label">Client</div>
        <div class="detail-value">{{ $visit->client->name }}</div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Visit Type</div>
        <div class="detail-value"><span class="type-badge">{{ str_replace('_', ' ', ucwords($visit->visit_type)) }}</span></div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Scheduled</div>
        <div class="detail-value">{{ $visit->scheduled_at->format('l, d M Y') }} at {{ $visit->scheduled_at->format('h:i A') }}</div>
      </div>
      @if($visit->client->address)
      <div class="detail-row">
        <div class="detail-label">Address</div>
        <div class="detail-value">{{ $visit->client->address }}{{ $visit->client->city ? ', ' . $visit->client->city : '' }}</div>
      </div>
      @endif
      @if($visit->client->phone)
      <div class="detail-row">
        <div class="detail-label">Contact No.</div>
        <div class="detail-value">{{ $visit->client->phone }}</div>
      </div>
      @endif
      @if($visit->notes)
      <div class="detail-row">
        <div class="detail-label">Notes</div>
        <div class="detail-value" style="color:#64748b; font-weight:400;">{{ $visit->notes }}</div>
      </div>
      @endif
    </div>

    @if($recipientType === 'salesperson')
    <div class="notice">
      <p>📍 Please log your GPS location and submit photo proof once the visit is complete via your HRX dashboard.</p>
    </div>
    @endif
  </div>
  <div class="footer">
    <p>This is an automated message from HRX &mdash; Sales Operations.<br>Please do not reply to this email.</p>
  </div>
</div>
</body>
</html>
