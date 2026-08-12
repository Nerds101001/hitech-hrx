@extends('emails.layout')

@section('title', 'Your Password Was Reset')
@section('badge', 'Account Security')
@section('header_title', 'Password Reset')

@section('greeting', 'Hi ' . $user->first_name . ',')

@section('content')
<p class="message-text">
    An administrator has reset your portal password. You can sign in to the
    <strong>Hi Tech Group Portal</strong> with the temporary password below.
</p>

<div class="info-card" style="text-align: center;">
    <div class="info-label">Your New Password</div>
    <div class="info-value" style="font-size: 20px; letter-spacing: 2px; font-family: 'Courier New', monospace; margin-top: 6px;">
        {{ $password }}
    </div>
    <div style="font-size: 12.5px; color: #b45309; margin-top: 14px; line-height: 1.6;">
        Please change this password after your first sign-in.
    </div>
</div>

<div class="cta-container">
    <a href="{{ url('/login') }}" class="cta-button">Login to Portal</a>
</div>

<p style="font-size: 13px; color: #94a3b8; line-height: 1.7;">
    If you did not request this, or you believe it is an error, contact your HR
    department or the security team at
    <strong style="color: #475569;">suchita@rustx.com</strong>.
</p>

<hr style="border: none; border-top: 1px solid #f1f5f9; margin: 32px 0;">

<p style="font-size: 12px; color: #94a3b8; text-align: center;">
    This is an automated security notification from Hi Tech Group.
</p>
@endsection
