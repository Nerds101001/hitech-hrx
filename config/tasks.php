<?php

return [
    /*
     * A task still open this many days past its deadline is escalated to the
     * assignee's manager (once). Calendar days.
     */
    'escalate_after_days' => (int) env('TASKS_ESCALATE_AFTER_DAYS', 2),

    /*
     * The storm guard. A task whose deadline fell BEFORE this date is never
     * escalated, so turning escalation on does not dump the whole existing
     * backlog on managers in one morning - only tasks that cross the overdue
     * line on or after this date escalate. Set to the day escalation went
     * live; move it forward to re-baseline, never backward.
     */
    'escalate_from' => env('TASKS_ESCALATE_FROM', '2026-10-05'),

    /*
     * "Due soon" nudge: a timed task is reminded when its deadline is within
     * this many hours and still ahead. A day-only task is reminded the day
     * before it is due.
     */
    'reminder_lead_hours' => (int) env('TASKS_REMINDER_LEAD_HOURS', 24),

    /*
     * Who gets the ONE consolidated escalation email per run. Each individual
     * manager still gets an instant in-app + push notice for their own report
     * (TaskEscalated - mail dropped from it on purpose); every escalation
     * across the whole run is additionally rolled into a single email here,
     * instead of a separate email per manager per task. Comma-separated -
     * does not have to be a registered HRX user, any mailbox works.
     */
    'escalation_desk_emails' => array_values(array_filter(array_map('trim',
        explode(',', (string) env('TASKS_ESCALATION_DESK_EMAILS', 'ai@rustx.com'))
    ))),
];
