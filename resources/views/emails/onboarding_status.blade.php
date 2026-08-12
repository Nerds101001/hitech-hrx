@extends('emails.layout')

@section('title', $status === 'approved' ? 'Onboarding Confirmed' : 'Onboarding Update Required')
@section('badge', 'Application Update')
@section('header_title', $status === 'approved' ? 'Onboarding Confirmed' : 'Update Required')

@section('greeting', 'Hi ' . $user->first_name . ',')

@section('style')
<style>
  .status-badge {
    display: inline-block; font-size: 11.5px; font-weight: 800;
    letter-spacing: 1px; text-transform: uppercase;
    border-radius: 50px; padding: 7px 18px; margin-bottom: 16px;
  }
  .status-approved { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
  .status-resubmit { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
  .notes-box {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px;
    padding: 16px 18px; margin-top: 8px;
  }
  .notes-label {
    font-size: 11px; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;
  }
  .notes-content { font-size: 14px; color: #475569; line-height: 1.7; }
</style>
@endsection

@section('content')
<p class="message-text">
    @if($status === 'approved')
        Good news — your onboarding application has been <strong>approved</strong>.
        You now have full access to the Hi Tech Group employee portal.
    @else
        Thank you for submitting your onboarding details. Our HR team has reviewed your
        application and needs some <strong>additional information</strong> or corrections
        before we can proceed.
    @endif
</p>

<div class="info-card">
    <span class="status-badge {{ $status === 'approved' ? 'status-approved' : 'status-resubmit' }}">
        {{ $status === 'approved' ? 'Approved' : 'Needs Update' }}
    </span>

    @if($notes)
    <div class="notes-box">
        <div class="notes-label">Notes from HR Team</div>
        <div class="notes-content">{{ $notes }}</div>
    </div>
    @endif
</div>

<div class="cta-container">
    <a href="{{ url('/login') }}" class="cta-button">
        {{ $status === 'approved' ? 'Go to Dashboard' : 'Review & Resubmit' }}
    </a>
</div>
@endsection
