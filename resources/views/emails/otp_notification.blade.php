@extends('emails.layout')

@section('title', 'Your Verification Code')
@section('badge', 'Verification Required')
@section('header_title', 'Identity Check')

@section('greeting', 'Hi ' . $user->first_name . ',')

@section('style')
<style>
  .otp-card {
    background: linear-gradient(135deg, #f0fdfb 0%, #f8fafc 100%);
    border: 1px solid #b2e0e0; border-radius: 20px;
    padding: 28px 24px; margin-bottom: 28px; text-align: center;
  }
  .otp-label {
    font-size: 11px; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: 1px;
  }
  .otp-code {
    font-size: 38px; font-weight: 800; color: #005a5a;
    letter-spacing: 10px; margin: 12px 0 8px; font-family: 'Courier New', monospace;
  }
  .otp-expiry { font-size: 12.5px; color: #64748b; }
</style>
@endsection

@section('content')
<p class="message-text">
    To keep your account secure we need a quick verification. Use the six-digit code
    below to finish signing in to the <strong>Hi Tech Group Portal</strong>.
</p>

<div class="otp-card">
    <div class="otp-label">Your Verification Code</div>
    <div class="otp-code">{{ $otp }}</div>
    <div class="otp-expiry">Valid for the next 10 minutes.</div>
</div>

<p style="font-size: 13px; color: #94a3b8; line-height: 1.7;">
    If you did not try to sign in, contact our security team immediately at
    <strong style="color: #475569;">suchita@rustx.com</strong> and change your password.
</p>

<hr style="border: none; border-top: 1px solid #f1f5f9; margin: 32px 0;">

<p style="font-size: 12px; color: #94a3b8; text-align: center;">
    This is a secure notification from Hi Tech Group.
</p>
@endsection
