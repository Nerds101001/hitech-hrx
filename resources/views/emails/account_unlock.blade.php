@extends('emails.layout')

@section('title', 'Unlock Your Account')
@section('badge', 'Security Service')
@section('header_title', 'Unlock Your Account')

@section('content')
<p class="message-text">
    We received a request to restore access to your Hi Tech Group portal account.
    If you have been locked out or requested a password reset, use the secure link
    below to proceed.
</p>

<div class="cta-container">
    <a href="{{ $unlockUrl }}" class="cta-button">Restore My Access</a>
</div>

<p style="font-size: 13px; color: #94a3b8; text-align: center; line-height: 1.6;">
    This secure link expires in 15 minutes. If you did not request it, ignore this
    email — your account stays protected.
</p>

<hr style="border: none; border-top: 1px solid #f1f5f9; margin: 32px 0;">

<p style="font-size: 12px; color: #94a3b8; text-align: center;">
    Need immediate help? Contact IT Support at
    <a href="mailto:suchita@rustx.com" style="color: #475569; font-weight: 700;">suchita@rustx.com</a>
</p>
@endsection
