@extends('emails.layout')

@section('title', 'Document Rejected - ' . $documentName)
@section('badge', 'Action Required')
@section('header_title', 'Document Rejected')

@section('greeting', 'Hello ' . ($employee->first_name ?? $employee->name) . ',')

@section('style')
<style>
  .doc-alert {
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 20px;
    padding: 20px 24px; margin-bottom: 20px;
  }
  .doc-alert-label {
    font-size: 11px; font-weight: 700; color: #991b1b;
    text-transform: uppercase; letter-spacing: 1px;
  }
  .doc-alert-name { font-size: 17px; font-weight: 800; color: #1e293b; margin-top: 4px; }
  .remarks-box {
    background: #f8fafc; border-left: 4px solid #ef4444; border-radius: 12px;
    padding: 16px 18px; margin-bottom: 28px;
  }
  .remarks-label {
    font-size: 11px; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;
  }
  .remarks-text { font-size: 14px; color: #475569; line-height: 1.7; font-style: italic; }
  .steps-heading {
    font-size: 12px; font-weight: 700; color: #1e293b;
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px;
  }
  .steps-table { border-collapse: collapse; width: 100%; margin-bottom: 28px; }
  .steps-table td { padding: 0; vertical-align: top; }
  .step-num-cell { width: 34px; padding-bottom: 14px; }
  .step-circle {
    display: inline-block; width: 28px; height: 28px;
    background: #ef4444; color: #ffffff; border-radius: 50%;
    text-align: center; line-height: 28px; font-size: 13px; font-weight: 700;
  }
  .step-text-cell { padding-left: 10px; padding-bottom: 14px; }
  .step-text { font-size: 14px; color: #475569; line-height: 1.65; }
  .step-text strong { color: #1e293b; font-weight: 700; }
  .cta-button-danger {
    display: inline-block; background: #b91c1c; color: #ffffff !important;
    text-decoration: none; font-size: 15px; font-weight: 700;
    padding: 16px 40px; border-radius: 50px;
    box-shadow: 0 8px 24px rgba(185,28,28,0.28);
  }
</style>
@endsection

@section('content')
<p class="message-text">
    Your HR team has reviewed your profile and found that one of your submitted documents
    does not meet the required standards. Please re-upload the correct document at your
    earliest convenience to keep your account access.
</p>

<div class="doc-alert">
    <div class="doc-alert-label">Rejected Document</div>
    <div class="doc-alert-name">{{ $documentName }}</div>
</div>

<div class="remarks-box">
    <div class="remarks-label">Reason / Remarks from HR</div>
    <div class="remarks-text">&ldquo;{{ $adminRemarks }}&rdquo;</div>
</div>

<div class="steps-heading">What You Need To Do</div>
<table class="steps-table" role="presentation">
    <tr>
        <td class="step-num-cell"><span class="step-circle">1</span></td>
        <td class="step-text-cell"><span class="step-text">Log in to the HR portal using your registered credentials.</span></td>
    </tr>
    <tr>
        <td class="step-num-cell"><span class="step-circle">2</span></td>
        <td class="step-text-cell"><span class="step-text">Go to your <strong>Onboarding Form</strong> — it is re-opened for you automatically.</span></td>
    </tr>
    <tr>
        <td class="step-num-cell"><span class="step-circle">3</span></td>
        <td class="step-text-cell"><span class="step-text">Upload a clear, valid copy of your <strong>{{ $documentName }}</strong>.</span></td>
    </tr>
    <tr>
        <td class="step-num-cell"><span class="step-circle">4</span></td>
        <td class="step-text-cell"><span class="step-text">Submit the form and wait for HR to re-verify your profile.</span></td>
    </tr>
</table>

<div class="cta-container">
    <a href="{{ url('/') }}" class="cta-button-danger">Login &amp; Re-upload Document</a>
</div>

<div class="info-card">
    <div class="info-row">
        <div class="info-label">Employee Name</div>
        <div class="info-value">{{ $employee->name }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Employee ID</div>
        <div class="info-value">{{ $employee->code ?? 'N/A' }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Reviewed By</div>
        <div class="info-value">{{ $reviewerName }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">Date &amp; Time</div>
        <div class="info-value">{{ now()->format('d M Y, h:i A') }}</div>
    </div>
</div>
@endsection
