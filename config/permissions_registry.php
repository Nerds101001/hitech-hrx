<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Centralized Permissions Registry
    |--------------------------------------------------------------------------
    |
    | This file maps every system module to its specific, granular business
    | logic permissions. The seeder and the UI will read directly from this
    | registry to ensure complete synchronization across the platform.
    |
    */

    'Dashboard' => [
        'dashboard.view' => 'View Dashboard',
    ],

    'Employees' => [
        'employee.view' => 'View Employee List',
        'employee.create' => 'Create New Employee',
        'employee.invite' => 'Invite Employee',
        'employee.edit' => 'Edit Employee Profile',
        'employee.view_basic' => 'View Basic Details',
        'employee.view_banking' => 'View Banking Info',
        'employee.view_document' => 'View Documents',
        'employee.edit_document' => 'Edit Documents',
        'employee.kpa' => 'Manage KPA',
        'employee.kra' => 'Manage KRA',
    ],

    'Attendance' => [
        'attendance.view_page' => 'View Team Attendance',
        'attendance.view_my' => 'View My Attendance',
        'attendance.import' => 'Import Attendance',
        'attendance.manage' => 'Manage / Edit Attendance',
    ],

    'Leaves' => [
        'leave.apply' => 'Apply for Leave',
        'leave.approve' => 'Approve / Reject Leave',
        'leave.create_type' => 'Create Leave Type',
        'leave.create_policy' => 'Create Leave Policy',
        'leave.view_all' => 'View All Leaves',
    ],

    'Travel & Expense' => [
        'expense.dashboard' => 'View Expense Dashboard',
        'expense.apply' => 'Apply / Claim Expense',
        'expense.approve' => 'Approve / Reject Claims',
        'expense.manage_types' => 'Manage Expense Types',
        'travel_claim.verify' => 'Verify Travel Claims',
        'travel_claim.approve' => 'Approve Travel Claims',
        'travel_claim.pay' => 'Pay Travel Claims',
    ],

    'Payroll' => [
        'payroll.view' => 'View Payroll',
        'payroll.process' => 'Process Payroll',
        'payroll.export' => 'Export Payroll Data',
        'payroll.view_my_slips' => 'View My Payslips',
    ],

    'Recruitment & Onboarding' => [
        'recruitment.view' => 'View Candidates',
        'recruitment.create' => 'Add Candidate',
        'recruitment.edit' => 'Edit Candidate',
        'recruitment.delete' => 'Delete Candidate',
        'recruitment.manage_jobs' => 'Manage Job Postings',
        'onboarding.manage' => 'Manage & Review Onboarding',
    ],

    'Hierarchy & Probation' => [
        'hierarchy.view' => 'View Org Chart',
        'hierarchy.manage' => 'Manage Org Chart',
        'probation.view' => 'View Probation Status',
        'probation.manage' => 'Manage Probation',
        'lifecycle.manage' => 'Manage Employee Lifecycle',
    ],

    'Sales Field Ops' => [
        'sales_ops.view' => 'View Sales Visits',
        'sales_ops.manage' => 'Manage Sales Targets',
    ],

    'Intelligent Vault' => [
        'vault.view' => 'Access Library',
        'vault.upload' => 'Upload Documents',
        'vault.chat' => 'Use AI Bot',
        'vault.train' => 'Train AI Bot',
    ],

    'Asset Management' => [
        'assets.view' => 'View Assets',
        'assets.create' => 'Add New Asset',
        'assets.edit' => 'Edit Asset',
        'assets.delete' => 'Delete Asset',
        'assets.assign' => 'Assign/Revoke Assets',
    ],

    'Monitoring' => [
        'monitoring.live_location' => 'View Live Location',
        'monitoring.timeline' => 'View Time Line',
        'monitoring.card_view' => 'View Card View',
        'sos.manage' => 'Manage SOS Alerts',
    ],

    'Kingo Bingo' => [
        'kingo_bingo.play' => 'Play Kingo Bingo',
        'kingo_bingo.manage' => 'Manage Kingo Bingo Targets',
    ],

    '90-Day Goals' => [
        'goals.view' => 'View 90-Day Goals',
        'goals.manage' => 'Manage 90-Day Goals',
    ],

    'Loans & Advances' => [
        'loans.view_all' => 'View All Loan Requests',
        'loans.approve' => 'Approve / Reject Loans',
    ],

    'Reports' => [
        'reports.view' => 'View Reports',
        'reports.export' => 'Export Reports',
    ],

    'Settings & Administration' => [
        'hr_policies.manage' => 'Manage HR Policies',
        'mind_speak.manage' => 'Manage Mind Speak',
        'approvals.manage' => 'Manage Custom Approvals',
        'roles.manage' => 'Manage Roles & Permissions',
        'audit.view' => 'View Audit Logs',
        'audit.export' => 'Export Audit Logs',
        'settings.manage' => 'Manage General Settings',
    ],

];
