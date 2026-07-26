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
        'employee.delete' => 'Delete Employee',
        'employee.reset_password' => 'Reset Employee Password / Security',
        'employee.manage_leave_balance' => 'Adjust Leave Balance',
        'employee.manage_payroll_adjustment' => 'Manage Payroll Adjustments',
        'employee.manage_device' => 'Allot / Remove Devices',
        'employee.lifecycle_action' => 'Suspend / Relieve / Retire / Terminate',
        'employee.manage_hr_location' => 'Manage HR Location Assignment',
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
        'outdoor_duty.approve' => 'Approve Outdoor Duty',
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
        'payroll.process' => 'Process Payroll (incl. Salary Import)',
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
        'sales_ops.view_all' => 'View All Sales/CCare Data (Unrestricted, cross-cutting)',
        'sales_ops.manage' => "Manage Employees' Sales Targets (from Employee profile)",
        'sales_visits.view' => 'View Field Visits',
        'sales_visits.create' => 'Book a Field Visit',
        'sales_visits.manage' => 'Edit / Cancel Field Visits',
        'sales_visits.verify' => 'Verify Completed Visits',
        'client_master.view' => 'View Client Master',
        'client_master.manage' => 'Add / Remove Clients',
        'sales_report.view' => 'View Sales Report',
        'sales_report.manage' => 'Edit Sales Report Entries',
        'sales_report.import' => 'Import Sales Report',
        'sales_pipeline.view' => 'View Sales Pipeline',
        'sales_targets.manage' => 'Set / Import Sales Targets',
        'sales_analytics.view' => 'View Sales Analytics & Reports',
        'crm_clients.manage' => 'Manage CRM Client Mappings',
    ],

    'CCare Attendance' => [
        'cc_attendance.manage' => 'Manage Salesperson Attendance (Full Register, All Submissions, Mark for Anyone)',
    ],

    'Intelligent Vault' => [
        'vault.view' => 'Access Library',
        'vault.upload' => 'Upload Documents',
        'vault.chat' => 'Use AI Bot',
        'vault.train' => 'Train AI Bot',
        'vault.delete' => 'Delete Library Items',
        'vault.manage_taxonomy' => 'Manage Library Taxonomy',
    ],

    'Asset Management' => [
        'assets.view' => 'View Assets',
        'assets.create' => 'Add New Asset',
        'assets.edit' => 'Edit Asset',
        'assets.delete' => 'Delete Asset',
        'assets.assign' => 'Assign/Revoke Assets',
        'assets.manage_categories' => 'Manage Asset Categories',
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

    'Performance' => [
        'goals.view' => 'View 90-Day Goals',
        'goals.manage' => 'Manage 90-Day Goals',
        'scorecards.view' => 'View Scorecards',
        'scorecards.manage' => 'Generate / Review Scorecards',
        'kpi.manage' => 'Manage KPI / KRA (DWM)',
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

    'Configuration Masters' => [
        'shifts.manage' => 'Manage Shifts',
        'holidays.manage' => 'Manage Holidays',
        'document_types.manage' => 'Manage Document Types',
        'ipgroup.manage' => 'Manage IP Groups',
        'geofencegroup.manage' => 'Manage Geofence Groups',
        'qrgroup.manage' => 'Manage QR Code Groups',
        'units.manage' => 'Manage Units / Sites',
        'departments.manage' => 'Manage Departments',
        'designations.manage' => 'Manage Designations',
        'teams.manage' => 'Manage Teams',
    ],

    'Operations' => [
        'visits.manage' => 'Manage Visit Log (HR view)',
        'tasks.manage' => 'Manage Tasks',
        'document_request.manage' => 'Manage Document Requests',
        'probation.view' => 'View Probation Status',
        'probation.manage' => 'Manage Probation',
    ],

    'System Utilities' => [
        'device.manage' => 'Manage Device Status',
        'biometric.manage' => 'Manage Biometric Devices',
        'account.manage' => 'Manage User Accounts (Suspend/Delete/Reset)',
        'chat.access' => 'Access Admin Chat',
    ],

    'Training' => [
        'training.manage' => 'Manage Training Portal',
    ],

];
