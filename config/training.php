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

    /*
    |--------------------------------------------------------------------------
    | Retry limit
    |--------------------------------------------------------------------------
    |
    | After this many failed attempts in a row on a module, the next attempt
    | waits retry_cooldown_minutes. Unlimited instant retries let people learn a
    | small question bank by trial and error (21 attempts on one catalogue).
    |
    */

    'max_failed_attempts' => (int) env('TRAINING_MAX_FAILED_ATTEMPTS', 3),

    'retry_cooldown_minutes' => (int) env('TRAINING_RETRY_COOLDOWN_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Learn @ Hi Tech: videos straight from the Digital Library
    |--------------------------------------------------------------------------
    |
    | These training phases list the Digital Library's videos of the given
    | sub-category live, instead of modules of their own: a recording added to
    | the library shows up in training with nothing copied. A video counts as
    | watched after library_video_min_minutes on it; the phase is complete once
    | library_videos_required of them are watched - any of them, not all.
    |
    */

    'library_video_phase_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', env('TRAINING_LIBRARY_VIDEO_PHASE_IDS', '3'))
    ))),

    'library_video_subcategory' => env('TRAINING_LIBRARY_VIDEO_SUBCATEGORY', 'Learn @ Hi Tech'),

    // Phases (by id) that must be finished before the Learn @ Hi Tech videos
    // open - not every earlier phase. Phase 1 (Culture & Integrity) and 2
    // (Product Knowledge); the CRM phase does not hold it back.
    'library_video_prerequisite_phase_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', env('TRAINING_LIBRARY_VIDEO_PREREQUISITE_PHASE_IDS', '1,2'))
    ))),

    'library_videos_required' => (int) env('TRAINING_LIBRARY_VIDEOS_REQUIRED', 3),

    'library_video_min_minutes' => (int) env('TRAINING_LIBRARY_VIDEO_MIN_MINUTES', 20),

    'completion_notify' => array_values(array_filter(array_map(
        'trim',
        explode(',', env(
            'TRAINING_COMPLETION_NOTIFY',
            'suchita@rustx.com,hr@rustx.com,ai@rustx.com'
        ))
    ))),

];
