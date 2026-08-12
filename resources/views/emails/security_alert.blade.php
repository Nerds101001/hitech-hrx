@extends('emails.layout')

@section('title', 'Security Alert')
@section('badge', 'Security Alert')
@section('header_title', 'Unusual Activity Detected')

@section('greeting', 'Security Notification,')

@section('style')
<style>
  .alert-card {
    background: #fef2f2; border: 1px solid #fecaca;
    border-radius: 20px; padding: 8px 24px; margin-bottom: 28px;
  }
  .alert-label {
    font-size: 11px; font-weight: 700; color: #991b1b;
    text-transform: uppercase; letter-spacing: 1px;
  }
  .alert-value { font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 2px; }
  .alert-row { padding: 12px 0; border-bottom: 1px solid #fee2e2; }
  .alert-row:last-child { border-bottom: none; }
</style>
@endsection

@section('content')
<p class="message-text">
    The Hi Tech Group security system detected multiple failed sign-in attempts on
    your account and has taken defensive measures to protect your data.
</p>

<div class="alert-card">
    <div class="alert-row">
        <div class="alert-label">Incident Reason</div>
        <div class="alert-value">{{ $details['reason'] }}</div>
    </div>
    <div class="alert-row">
        <div class="alert-label">Target Account</div>
        <div class="alert-value">{{ $details['email'] }}</div>
    </div>
    <div class="alert-row">
        <div class="alert-label">Source IP</div>
        <div class="alert-value">{{ $details['ip'] }}</div>
    </div>
    <div class="alert-row">
        <div class="alert-label">Action Taken</div>
        <div class="alert-value">{{ $details['action'] }}</div>
    </div>
</div>

<div class="cta-container">
    <a href="{{ url('/password/reset') }}" class="cta-button">Secure My Account</a>
</div>

<p style="font-size: 12.5px; color: #94a3b8; text-align: center; line-height: 1.7;">
    If this was not you, notify our security team immediately at
    <a href="mailto:suchita@rustx.com" style="color: #991b1b; font-weight: 700;">suchita@rustx.com</a>
</p>
@endsection
