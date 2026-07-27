<?php

/*
|--------------------------------------------------------------------------
| Role Presets (persona templates)
|--------------------------------------------------------------------------
|
| Ready-made permission bundles for common jobs, so a role can be created
| with one click instead of ticking dozens of individual boxes. Each preset
| lists permission keys drawn from config/permissions_registry.php.
|
| Consumed by:
|   - RoleController@index  → passed to the role modal as a "Start from a
|     template" dropdown (picking one auto-ticks its permissions).
|   - RolePresetSeeder      → optionally creates each as a ready-made role.
|
| Keep every key here in sync with permissions_registry.php — only wired
| permissions belong in a preset, so a preset never grants a dead checkbox.
|
*/

return [

    'accounts_payroll' => [
        'label'       => 'Accounts — Payroll, Payments & Billing',
        'description' => 'Full payroll processing, expense & travel-claim approval/payment, and loan approvals. For the accounts/finance team.',
        'permissions' => [
            'payroll.view',
            'payroll.process',
            'expense.approve',
            'expense.manage_types',
            'travel_claim.verify',
            'travel_claim.approve',
            'travel_claim.pay',
            'loans.view_all',
            'loans.approve',
            'reports.view',
        ],
    ],

    'learning_travel' => [
        'label'       => 'Learning & Travel Coordinator',
        'description' => 'Manages the training portal & content, the document library, and verifies travel claims. For the learning/training department.',
        'permissions' => [
            'training.manage',
            'vault.upload',
            'vault.chat',
            'travel_claim.verify',
        ],
    ],

    'attendance_manager' => [
        'label'       => 'Attendance & Leave Manager',
        'description' => 'Views and edits team attendance, imports attendance, and approves leave & outdoor duty. For an HR/ops attendance owner.',
        'permissions' => [
            'attendance.view_page',
            'attendance.manage',
            'attendance.import',
            'leave.approve',
            'leave.view_all',
            'outdoor_duty.approve',
        ],
    ],

    'sales_manager_office' => [
        'label'       => 'Sales Back-Office',
        'description' => 'Views sales visits, reports, pipeline and analytics, and manages the client master — without setting targets. For a sales coordinator.',
        'permissions' => [
            'sales_visits.view',
            'sales_report.view',
            'sales_pipeline.view',
            'sales_analytics.view',
            'client_master.view',
            'client_master.manage',
        ],
    ],

    'recruiter' => [
        'label'       => 'Recruitment & Onboarding',
        'description' => 'Reviews and approves onboarding submissions. For an HR recruiter/onboarding reviewer.',
        'permissions' => [
            'onboarding.manage',
        ],
    ],

];
