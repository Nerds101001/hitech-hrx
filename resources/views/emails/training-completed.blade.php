@extends('emails.layout')

@section('title', 'Training Completed')
@section('badge', 'Training')
@section('header_title', 'Training Completed')

@section('content')
<p class="message-text">
    <strong>{{ $employee->getFullName() }}</strong>
    @if($employee->code) ({{ $employee->code }}) @endif
    has passed all {{ $moduleCount }} onboarding training module{{ $moduleCount === 1 ? '' : 's' }}.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse;margin:24px 0;font-size:14px;color:#334155;">
    <tr>
        <td style="padding:8px 0;color:#64748b;width:45%;">Employee</td>
        <td style="padding:8px 0;font-weight:600;">{{ $employee->getFullName() }}</td>
    </tr>
    @if($employee->email)
    <tr>
        <td style="padding:8px 0;color:#64748b;">Email</td>
        <td style="padding:8px 0;">{{ $employee->email }}</td>
    </tr>
    @endif
    @if($employee->date_of_joining)
    <tr>
        <td style="padding:8px 0;color:#64748b;">Date of joining</td>
        <td style="padding:8px 0;">{{ \Carbon\Carbon::parse($employee->date_of_joining)->format('d M Y') }}</td>
    </tr>
    @endif
    <tr>
        <td style="padding:8px 0;color:#64748b;">Modules passed</td>
        <td style="padding:8px 0;">{{ $moduleCount }}</td>
    </tr>
    @if(!is_null($averageScore))
    <tr>
        <td style="padding:8px 0;color:#64748b;">Average score</td>
        <td style="padding:8px 0;font-weight:600;">{{ number_format($averageScore, 1) }}%</td>
    </tr>
    @endif
    <tr>
        <td style="padding:8px 0;color:#64748b;">Completed at</td>
        <td style="padding:8px 0;">{{ now()->format('d M Y, H:i') }}</td>
    </tr>
</table>

@if($accountActivated)
<p class="message-text">
    Their account has already been set to <strong>Active</strong> automatically — full
    access to attendance, leave and the rest of the portal is open as of now.
    No action is needed on your side; this is a record of the change.
</p>
@else
<p class="message-text">
    Their account was not being held by training, so its status is unchanged.
</p>
@endif

<hr style="border: none; border-top: 1px solid #f1f5f9; margin: 32px 0;">

<p style="font-size: 12px; color: #94a3b8; text-align: center;">
    Sent automatically by HRX when onboarding training is completed.
</p>
@endsection
