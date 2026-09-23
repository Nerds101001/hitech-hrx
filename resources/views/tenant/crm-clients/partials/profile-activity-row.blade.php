<div class="activity-item">
    <div class="activity-icon type-{{ $activity->type->value }}">
        <i class="bx {{ $activity->type->value === 'call' ? 'bx-phone' : ($activity->type->value === 'system' ? 'bx-cog' : 'bx-comment-detail') }}"></i>
    </div>
    <div class="flex-grow-1" style="min-width:0;">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
            <span class="fw-semibold" style="font-size:0.82rem;">{{ $activity->createdBy->first_name ?? 'System' }} {{ $activity->createdBy->last_name ?? '' }}</span>
            <span class="text-muted" style="font-size:0.72rem;">{{ $activity->created_at->diffForHumans() }}</span>
        </div>
        <div style="font-size:0.83rem;white-space:pre-line;">{{ $activity->body }}</div>
        @if($activity->needs_follow_up)
            <span class="follow-up-chip mt-1 d-inline-block">
                <i class="bx bx-alarm-exclamation"></i>
                {{ $activity->follow_up_completed_at ? 'Follow-up done' : 'Needs follow-up' }}
                @if($activity->follow_up_due_at && !$activity->follow_up_completed_at) — {{ $activity->follow_up_due_at->format('d M') }}@endif
            </span>
        @endif
    </div>
</div>
