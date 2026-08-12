@extends('emails.layout')

@section('title', 'The Hi Tech Group Portal is Now Live')
@section('badge', 'The Wait is Over')
@section('header_title', 'The Hi Tech Group Portal is Now Live')

@section('greeting', 'Hi ' . $user->first_name . ',')

@section('style')
<style>
  .cred-row { padding: 14px 0; border-bottom: 1px solid rgba(0,128,128,0.08); }
  .cred-row:last-child { border-bottom: none; padding-bottom: 0; }
  .cred-label {
    font-size: 11px; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: 1px;
  }
  .cred-value {
    font-size: 15px; font-weight: 800; color: #005a5a;
    margin-top: 4px; font-family: 'Courier New', monospace; word-break: break-all;
  }
  .credentials-title {
    font-size: 12px; font-weight: 800; color: #1e293b;
    text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 6px;
  }
  .steps-title {
    font-size: 12px; font-weight: 700; color: #1e293b;
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px;
  }
  .steps-table { border-collapse: collapse; width: 100%; margin-bottom: 26px; }
  .steps-table td { padding: 0; vertical-align: top; }
  .step-num-cell { width: 34px; padding-bottom: 14px; }
  .step-circle {
    display: inline-block; width: 28px; height: 28px;
    background: linear-gradient(135deg, #005a5a, #008080); color: #fff;
    border-radius: 50%; text-align: center; line-height: 28px;
    font-size: 13px; font-weight: 700;
  }
  .step-text-cell { padding-left: 10px; padding-bottom: 14px; }
  .step-text { font-size: 14px; color: #475569; line-height: 1.65; }
</style>
@endsection

@section('content')
<p class="message-text">
    We are pleased to announce the official launch of the <strong>Hi Tech Group Portal</strong>
    — your gateway to a faster, more seamless workplace experience.
    <br><br>
    To finish setting you up, please sign in and complete your profile: personal details,
    bank account, emergency contacts and documents.
</p>

<div class="info-card">
    <div class="credentials-title">Your Launch Credentials</div>
    <div class="cred-row">
        <div class="cred-label">Login Email</div>
        <div class="cred-value">{{ $user->email }}</div>
    </div>
    <div class="cred-row">
        <div class="cred-label">Temporary Password</div>
        <div class="cred-value">{{ $password }}</div>
    </div>
</div>

<p class="steps-title">How to get started</p>
<table class="steps-table" role="presentation">
    <tr>
        <td class="step-num-cell"><span class="step-circle">1</span></td>
        <td class="step-text-cell"><span class="step-text"><strong>Step into the portal</strong> — sign in with the credentials above.</span></td>
    </tr>
    <tr>
        <td class="step-num-cell"><span class="step-circle">2</span></td>
        <td class="step-text-cell"><span class="step-text"><strong>Complete your profile</strong> — personal details, bank account, emergency contacts and documents.</span></td>
    </tr>
</table>

<p class="message-text">
    If you hit any snags, the HR team is standing by to help.
</p>

<div class="cta-container">
    <a href="{{ url('/auth/login') }}" class="cta-button">Access My Portal Now</a>
</div>

<hr style="border: none; border-top: 1px solid #f1f5f9; margin: 32px 0;">

<p style="font-size: 12.5px; color: #94a3b8; text-align: center; line-height: 1.7;">
    If you did not expect this email, ignore it or contact HR at
    <a href="mailto:suchita@rustx.com" style="color: #008080;">suchita@rustx.com</a>
</p>
@endsection
