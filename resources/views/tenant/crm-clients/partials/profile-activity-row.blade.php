{{-- One feed entry, on a single line: who, what (cut to fit), follow-up flag, when. Click the row for the full text. --}}
@php
    $who = trim(($activity->createdBy->first_name ?? 'System') . ' ' . ($activity->createdBy->last_name ?? ''));
    $full = trim((string) $activity->body);
    $isCall = $activity->type->value === 'call';
    // a call's summary starts with the caller's name, which the row already shows
    $text = $isCall ? preg_replace('/^' . preg_quote($who, '/') . '\s+—\s+/u', '', $full) : $full;
    $oneLine = trim(preg_replace('/\s*\R\s*/u', ' ', $isCall ? preg_replace('/\R\R/', ' — ', $text, 1) : $text));
@endphp
<div class="activity-item">
    <div class="activity-icon type-{{ $activity->type->value }}">
        <i class="bx {{ $isCall ? 'bx-phone' : ($activity->type->value === 'system' ? 'bx-cog' : 'bx-comment-detail') }}"></i>
    </div>
    <div class="activity-main">
        <span class="activity-who">{{ $who }}</span>
        <span class="activity-text" title="{{ $full }}">{{ $oneLine }}</span>
        <span class="activity-full">{{ $text }}</span>
        @if($activity->needs_follow_up)
            @php
                $fuDone = (bool) $activity->follow_up_completed_at;
                $fuDate = $activity->follow_up_due_at && !$fuDone ? $activity->follow_up_due_at->format('d M') : null;
                $fuText = ($fuDone ? 'Follow-up done' : 'Needs follow-up') . ($fuDate ? ' — ' . $fuDate : '');
            @endphp
            <span class="follow-up-chip {{ $fuDate ? 'has-date' : '' }} {{ $fuDone ? 'is-done' : '' }}" title="{{ $fuText }}">
                <i class="bx bx-alarm-exclamation"></i>
                <span class="fu-label">{{ $fuDone ? 'Follow-up done' : 'Needs follow-up' }}</span>@if($fuDone)<span class="fu-short">Done</span>@endif@if($fuDate)<span class="fu-date">{{ $fuDate }}</span>@endif
            </span>
        @endif
    </div>
    <span class="activity-time" title="{{ $activity->created_at->format('d M Y, h:i A') }}"><span class="t-long">{{ $activity->created_at->diffForHumans() }}</span><span class="t-short">{{ $activity->created_at->diffForHumans(null, null, true) }}</span></span>
</div>
