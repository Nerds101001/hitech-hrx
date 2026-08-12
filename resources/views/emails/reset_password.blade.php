@extends('emails.layout')

@section('title', 'Reset Your Password')
@section('badge', 'Account Security')
@section('header_title', 'Reset Your Password')

@section('greeting', 'Hi ' . ($user->first_name ?? 'there') . ',')

@section('content')
<p class="message-text">
    We received a request to reset the password for your <strong>Hi Tech Group Portal</strong>
    account. Use the button below to choose a new one. If you did not make this request,
    you can safely ignore this email.
</p>

<div class="cta-container">
    <a href="{{ $resetUrl }}" class="cta-button">Reset Password</a>
</div>

<div class="info-card" style="text-align: center;">
    <div class="info-label">Link Expires In</div>
    <div class="info-value">{{ $expireMinutes ?? 60 }} minutes</div>
</div>

<p style="font-size: 12px; color: #94a3b8; line-height: 1.7; word-break: break-all;">
    If the button does not work, copy this link into your browser:<br>
    <a href="{{ $resetUrl }}" style="color: #005a5a;">{{ $resetUrl }}</a>
</p>

<hr style="border: none; border-top: 1px solid #f1f5f9; margin: 32px 0;">

<p style="font-size: 12px; color: #94a3b8; text-align: center; line-height: 1.7;">
    If you did not request a password reset, contact your HR department or the security
    team at <strong style="color: #475569;">suchita@rustx.com</strong>.
</p>
@endsection
