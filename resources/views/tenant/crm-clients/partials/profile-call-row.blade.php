{{-- Overview "Recent Calls": two short lines per call. $entry is a row from CustomerProfileController::callLog(). --}}
@php
    $e = $entry;
    [$icon, $iconBg, $iconFg] = $e['kind'] === 'manual' ? ['bx-edit-alt', '#eff6ff', '#2563eb']
        : ($e['ended'] ? ['bx-phone-incoming', '#ecfdf3', '#067647'] : ['bx-phone-outgoing', '#fef3f2', '#b42318']);
    $line = trim($e['headline'] . ($e['notes'] !== '' ? ' — ' . preg_replace('/\s+/', ' ', $e['notes']) : ''));
    $theirs = $e['customer']['name'] ?? ($e['customer']['phone'] ?? null);
@endphp
<div class="cp-call-list-row">
    <div class="cp-call-icon" style="background:{{ $iconBg }};color:{{ $iconFg }};"><i class="bx {{ $icon }}"></i></div>
    <div class="flex-grow-1" style="min-width:0;">
        <div class="fw-semibold small text-truncate" title="{{ $line }}">{{ $line }}</div>
        <div class="text-muted small text-truncate">
            {{ $e['our']['name'] ?? '—' }} &harr; {{ $theirs ?: 'contact not recorded' }}
            &middot; {{ $e['at']->format('d M, h:i A') }}@if($e['followUp']) &middot; follow-up {{ $e['followUp']->format('d M') }}{{ $e['followType'] ? ' (' . $e['followType'] . ')' : '' }}@endif
        </div>
    </div>
    <span class="cp-status-chip cp-status-{{ $e['chipClass'] }}" @if($e['kind'] === 'manual') title="Logged by hand: this call was made outside Smart Agent" @endif>{{ $e['chipShort'] }}</span>
</div>
