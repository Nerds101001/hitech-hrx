<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phases that gate the account
    |--------------------------------------------------------------------------
    |
    | A new joiner is held in onboarding until every module in THESE phases is
    | passed. Phases outside this list (CRM Training, Learn @ Hi Tech) stay in
    | the portal to work through, but never block the account.
    |
    | Matched on phase ID, deliberately, not on `order`: the orders collide on
    | production today - "Phase 3: CRM Training" (id 4) carries order = 2, the
    | same as "Phase 2: Brand /Product Knowledge" (id 2) - so ordering cannot
    | identify a phase unambiguously. IDs can.
    |
    |   1 = Phase 1: Culture & Integrity        (4 modules,  23 questions)
    |   2 = Phase 2: Brand /Product Knowledge   (7 modules, 222 questions)
    |
    | Both phases gate the account, by decision: company policy (phase 1) and
    | product knowledge (phase 2). That is 11 modules and 245 questions at 80%
    | each, and an account stays shut - no attendance, no leave, no tasks -
    | until every one of them is passed. Narrow this to '1' if that proves too
    | heavy a bar to hold people out of the portal over.
    |
    */

    'onboarding_phase_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', env('TRAINING_ONBOARDING_PHASE_IDS', '1,2'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Who hears about a completion
    |--------------------------------------------------------------------------
    |
    | Emailed when someone passes the gating phases and their account opens up.
    | The unlock is automatic, so this is a record of what happened rather than
    | a request to go and do something.
    |
    */

    'completion_notify' => array_values(array_filter(array_map(
        'trim',
        explode(',', env(
            'TRAINING_COMPLETION_NOTIFY',
            'suchita@rustx.com,hr@rustx.com,ai@rustx.com'
        ))
    ))),

];
