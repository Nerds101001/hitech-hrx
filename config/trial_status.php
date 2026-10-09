<?php

/*
|--------------------------------------------------------------------------
| Trial follow-up statuses
|--------------------------------------------------------------------------
|
| The standard dropdown a salesperson picks from each time they update a
| trial's outcome. A trial's outcome is a timeline (sales_trial_status_updates):
| the newest update is the trial's current status.
|
|  needs_date  : the update must carry a "next expected date" (Waiting / Retrial)
|  terminal    : a settled outcome; no follow-up is expected afterwards
|  color       : the badge colour on the scorecard-style timeline
|
| Reorder, rename, add or remove freely — the UI, validation and reminders all
| read this one list.
|
*/

return [

    'statuses' => [
        'pending' => [
            'label'      => 'Pending',
            'needs_date' => false,
            'terminal'   => false,
            'color'      => '#64748b',
            'help'       => 'Trial done, outcome not known yet.',
        ],
        'waiting' => [
            'label'      => 'Waiting for Result',
            'needs_date' => true,
            'terminal'   => false,
            'color'      => '#d97706',
            'help'       => 'Result expected on a later date.',
        ],
        'retrial' => [
            'label'      => 'Retrial Needed',
            'needs_date' => true,
            'terminal'   => false,
            'color'      => '#7c3aed',
            'help'       => 'Another trial required; pick the next trial date.',
        ],
        'pass' => [
            'label'      => 'Pass',
            'needs_date' => false,
            'terminal'   => true,
            'color'      => '#16a34a',
            'help'       => 'Trial succeeded.',
        ],
        'order' => [
            'label'      => 'Order (Converted)',
            'needs_date' => false,
            'terminal'   => true,
            'color'      => '#059669',
            'help'       => 'Converted into an order — the win.',
        ],
        'fail' => [
            'label'      => 'Fail',
            'needs_date' => false,
            'terminal'   => true,
            'color'      => '#dc2626',
            'help'       => 'Trial did not succeed.',
        ],
        'lost' => [
            'label'      => 'Lost / Dropped',
            'needs_date' => false,
            'terminal'   => true,
            'color'      => '#6b7280',
            'help'       => 'Customer went elsewhere or dropped it.',
        ],
    ],

    // The status a freshly completed trial starts on, before anyone updates it.
    'default' => 'pending',

];
