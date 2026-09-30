{{-- One row of the Calls tab: the call, both sides with their contact details, when, status. $entry is a row from CustomerProfileController::callLog(). --}}
@php $e = $entry; @endphp
<tr>
    <td data-label="Call" style="min-width:200px;">
        <div class="fw-semibold cp-clamp" title="{{ $e['headline'] }}">{{ $e['headline'] }}</div>
        @if($e['notes'] !== '')
            <div class="text-muted small cp-clamp" title="{{ $e['notes'] }}">{{ preg_replace('/\s+/', ' ', $e['notes']) }}</div>
        @endif
    </td>
    <td data-label="Our team" style="min-width:220px;">@include('tenant.crm-clients.partials.profile-call-party', ['p' => $e['our'], 'empty' => '—'])</td>
    <td data-label="Customer contact" style="min-width:220px;">@include('tenant.crm-clients.partials.profile-call-party', ['p' => $e['customer'], 'empty' => 'Not recorded'])</td>
    <td class="small" data-label="When">
        <div class="text-nowrap">{{ $e['at']->format('d M Y, h:i A') }}</div>
        @if($e['followUp'])<div class="text-nowrap {{ $e['followUp']->isPast() ? 'cp-overdue' : 'text-muted' }}" @if($e['followType']) title="Follow-up: {{ $e['followType'] }}" @endif>follow-up {{ $e['followUp']->format('d M') }}</div>@endif
    </td>
    <td class="text-end cp-td-status"><span class="cp-status-chip cp-status-{{ $e['chipClass'] }}" @if($e['kind'] === 'manual') title="Logged by hand: this call was made outside Smart Agent" @endif>{{ $e['chip'] }}</span></td>
</tr>
