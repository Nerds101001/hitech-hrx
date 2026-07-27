<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title }} - Hi Tech HRX</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; background-color: #f0f4f8; }
    .email-wrapper {
      max-width: 620px; margin: 32px auto; border-radius: 24px; overflow: hidden;
      box-shadow: 0 16px 48px rgba(0,0,0,0.12); background: #ffffff;
    }
    .email-header {
      background: linear-gradient(135deg, #003d3d 0%, #005a5a 50%, #007a7a 100%);
      padding: 32px 40px; text-align: center;
    }
    .brand { color: #ffffff; font-size: 22px; font-weight: 800; letter-spacing: .5px; }
    .brand-sub { color: rgba(255,255,255,.75); font-size: 12px; letter-spacing: 2px; text-transform: uppercase; margin-top: 4px; }
    .body { padding: 36px 40px; }
    .chips { margin-bottom: 18px; }
    .chip {
      display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .06em; padding: 5px 12px; border-radius: 999px; margin-right: 6px;
    }
    .chip-type { background: #e6f4f1; color: #005a5a; }
    .chip-critical { background: #fee2e2; color: #991b1b; }
    .chip-high { background: #fef3c7; color: #92400e; }
    .chip-medium { background: #e0e7ff; color: #3730a3; }
    .chip-low { background: #f1f5f9; color: #475569; }
    h1 { font-size: 22px; color: #0f172a; font-weight: 800; line-height: 1.3; margin-bottom: 8px; }
    .meta { font-size: 13px; color: #64748b; margin-bottom: 22px; }
    .content {
      font-size: 15px; color: #334155; line-height: 1.7; white-space: pre-line;
      padding: 20px 22px; background: #f8fafc; border-left: 4px solid #007a7a; border-radius: 10px;
    }
    .ack {
      margin-top: 20px; padding: 14px 18px; background: #fff7ed;
      border: 1px solid #fdba74; border-radius: 10px; font-size: 13px; color: #9a3412;
    }
    .btn-wrap { text-align: center; margin: 30px 0 8px; }
    .btn {
      display: inline-block; background: linear-gradient(135deg, #005a5a, #007a7a);
      color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 14px;
      padding: 14px 34px; border-radius: 999px;
    }
    .footer { padding: 22px 40px 30px; text-align: center; font-size: 12px; color: #94a3b8; }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="email-header">
      <div class="brand">HI TECH HRX</div>
      <div class="brand-sub">Company Announcement</div>
    </div>

    <div class="body">
      <div class="chips">
        <span class="chip chip-type">{{ $type }}</span>
        <span class="chip chip-{{ strtolower($priority) }}">{{ $priority }} priority</span>
      </div>

      <h1>{{ $title }}</h1>
      <div class="meta">
        Posted by {{ $author }}
        @if($startDate) &middot; {{ $startDate }} @endif
        @if($endDate) &ndash; {{ $endDate }} @endif
      </div>

      <div class="content">{{ $content }}</div>

      @if($needsAck)
        <div class="ack">
          <strong>Acknowledgement required.</strong>
          Please open the portal and confirm you have read this announcement.
        </div>
      @endif

      <div class="btn-wrap">
        <a href="{{ $actionUrl }}" class="btn">View in Portal</a>
      </div>
    </div>

    <div class="footer">
      You are receiving this because it applies to your department, location or role.<br>
      Hi Tech Group &middot; HRX Portal
    </div>
  </div>
</body>
</html>
