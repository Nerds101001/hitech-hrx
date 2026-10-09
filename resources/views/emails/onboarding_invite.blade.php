@extends('emails.layout')

@section('title', 'Welcome to Hi Tech Group')
@section('badge', "You're Invited")
@section('header_title', 'Welcome to the Team, ' . $user->first_name . '!')

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
  .warning-banner {
    background: #fffbeb; border: 1px solid #fde68a; border-radius: 14px;
    padding: 16px 18px; margin-bottom: 28px;
    font-size: 13.5px; color: #92400e; line-height: 1.7;
  }
</style>
@endsection

@section('content')
<p class="message-text">
    We are glad to have you joining <strong>Hi Tech Group</strong>. Your HR team has set
    up your account, so you can begin onboarding straight away. Use the credentials below
    to sign in and complete your pre-joining formalities.
</p>

<div class="info-card">
    <div class="credentials-title">Your Login Credentials</div>
    <div class="cred-row">
        <div class="cred-label">Login Email</div>
        <div class="cred-value">{{ $user->email }}</div>
    </div>
    <div class="cred-row">
        <div class="cred-label">Temporary Password</div>
        <div class="cred-value">{{ $resetUrl }}</div>
    </div>
</div>

<p class="steps-title">What to do next</p>
<table class="steps-table" role="presentation">
    <tr>
        <td class="step-num-cell"><span class="step-circle">1</span></td>
        <td class="step-text-cell"><span class="step-text">Sign in with the credentials above.</span></td>
    </tr>
    <tr>
        <td class="step-num-cell"><span class="step-circle">2</span></td>
        <td class="step-text-cell"><span class="step-text">Complete every section of the onboarding form — personal details, documents and bank information.</span></td>
    </tr>
    <tr>
        <td class="step-num-cell"><span class="step-circle">3</span></td>
        <td class="step-text-cell"><span class="step-text">Submit the form for HR review. You will be notified once your account is fully activated.</span></td>
    </tr>
</table>

<div class="warning-banner">
    ⏳ <strong>This invitation is valid for 3 days.</strong>
    @if(!empty($user->onboarding_deadline))
        Please sign in and complete your onboarding form by
        <strong>{{ \Illuminate\Support\Carbon::parse($user->onboarding_deadline)->format('l, d M Y, g:i A') }}</strong>.
    @else
        Please sign in and complete your onboarding form within <strong>3 days</strong> of receiving this email.
    @endif
    If it is not completed in time, your temporary account will be
    <strong>automatically deactivated and you will lose access</strong> — you will then
    need to contact HR to be re-invited.
</div>

<div class="cta-container">
    <a href="{{ url('/auth/login') }}" class="cta-button">Access My Portal</a>
</div>

<hr style="border: none; border-top: 1px solid #f1f5f9; margin: 32px 0;">

<p style="font-size: 12.5px; color: #94a3b8; text-align: center; line-height: 1.7;">
    If you did not expect this email, ignore it or contact HR at
    <a href="mailto:suchita@rustx.com" style="color: #008080;">suchita@rustx.com</a>
</p>
@endsection
