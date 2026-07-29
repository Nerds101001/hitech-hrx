<?php

/*
|--------------------------------------------------------------------------
| HR lifecycle notification recipients
|--------------------------------------------------------------------------
|
| Who gets told when someone joins or leaves. Kept in config (not hardcoded
| in controllers) so addresses can change without a code deploy.
|
| Every address may be overridden per-environment via .env.
|
*/

return [

    /*
     | ---------------- JOINING: onboarding approved ----------------
     |
     | One combined email so everyone can see the others on the thread:
     |   to  → accounts + the new joiner's REPORTING MANAGER (resolved at runtime)
     |   cc  → HR
     |
     | A second, separate email carrying the ID documents goes to
     | `documents_to` only.
     */
    'onboarding' => [
        'to' => array_filter([
            env('HR_NOTIFY_ACCOUNTS', 'accounts5@rustx.com'),
        ]),

        'cc' => array_filter([
            env('HR_NOTIFY_HR_1', 'suchita@rustx.com'),
            env('HR_NOTIFY_HR_2', 'priyal@rustx.com'),
        ]),

        // The reporting manager is added to the recipients automatically when
        // the new joiner has one; set false to keep the list fixed.
        'include_reporting_manager' => true,

        // Documents go only here.
        'documents_to' => array_filter([
            env('HR_NOTIFY_ACCOUNTS', 'accounts5@rustx.com'),
        ]),

        /*
         | Which onboarding documents accounts actually receives.
         |
         | Deliberately a strict allow-list, matched against the file-name
         | prefix used by OnboardingController. Only what payroll and
         | statutory records genuinely need is shared — education
         | certificates and anything else stay in HRX and are never emailed.
         |
         | Set to an empty array to send every uploaded document.
         */
        'share_documents' => [
            'profile_photo',
            'cancelled_cheque',
            'aadhaar_card',
            'pan_card',
        ],
    ],

    /*
     | ---------------- LEAVING: resignation / termination ----------------
     |
     | Each of these departments must give a No-Objection Certificate before
     | the exit can be finalised. The employee's access is revoked as soon as
     | HR logs the exit — clearance runs in parallel and is tracked.
     |
     | Clearance goes to the DEPARTMENT MANAGER only — never to employee-role
     | staff. The `manager` role cannot be used to find them: IT's manager
     | (Nagender Kumar) holds `admin`, HR's hold `hr`, and one Accounts
     | Senior Executive holds `manager` without being a manager. So each owner
     | is named explicitly here, and `department_id` is only a fallback used
     | to look up a manager-designation user if the address is ever cleared.
     |
     | `label`         shown in the clearance tracker
     | `emails`        the manager(s) who sign this NOC
     | `department_id` real department record (NOT the empty duplicates:
     |                 IT 31 not 4, HR 29 not 3, Finance 32 not 5,
     |                 Accounts 33 not 6)
     */
    'exit_clearances' => [
        'it' => [
            'label' => 'IT',
            'department_id' => 31,
            'emails' => array_filter([env('NOC_IT', 'ai@rustx.com')]),          // Nagender Kumar, Manager
        ],
        'hr' => [
            'label' => 'HR',
            'department_id' => 29,
            'emails' => array_filter([env('NOC_HR', 'hr@rustx.com')]),           // Ramandeep Kaur Gill, Manager
        ],
        'finance' => [
            'label' => 'Finance',
            'department_id' => 32,
            'emails' => array_filter([env('NOC_FINANCE', 'finance@rustx.com')]), // ARPANA Katyal, Head
        ],
        'accounts' => [
            'label' => 'Accounts',
            'department_id' => 33,
            'emails' => array_filter([env('NOC_ACCOUNTS', 'accounts@rustx.com')]), // RAJESH BHARDWAJ, Manager
        ],
    ],

    /*
     | Designations treated as managerial when falling back to a department
     | lookup. Used because the `manager` ROLE does not reliably identify
     | managers in this tenant.
     */
    'manager_designations' => ['manager', 'head', 'general manager', 'deputy general manager', 'director'],

    /*
     | Who is notified on EVERY exit alert and every NOC update.
     |
     | Resolved as the union of:
     |   • every active user holding one of `roles`  (so new accounts staff
     |     are included automatically — no config edit needed)
     |   • the fixed `emails` below
     |   • the leaver's own reporting manager, when they have one
     |
     | Employee-role staff are never included unless they hold one of the
     | roles listed here.
     */
    'exit_notify' => [
        'roles' => ['accounts'],

        'emails' => array_filter([
            env('HR_NOTIFY_HR_1',  'suchita@rustx.com'),   // Suchita Rustogi  — Dy. GM, HR
            env('HR_NOTIFY_HR_2',  'priyal@rustx.com'),    // Priyal Gangwar   — Sr. Manager, HR
            env('HR_NOTIFY_HR_3',  'hr@rustx.com'),        // Ramandeep K Gill — Manager, HR
            env('NOC_ACCOUNTS',    'accounts@rustx.com'),  // Rajesh Bhardwaj  — Accounts Manager
            env('NOC_FINANCE',     'finance@rustx.com'),   // Arpana Katyal    — Finance Head
        ]),

        'include_reporting_manager' => true,
    ],

    /*
     | ---------------- Accounts setup task ----------------
     |
     | After onboarding approval, accounts must record the employee's
     | biometric id and employee id. Departments listed here do not need a
     | biometric id (field staff who never punch a device) — the employee id
     | stays mandatory for everyone.
     */
    'biometric_exempt_departments' => ['sales', 'new biz', 'channel sales', 'pre sales'],
];
