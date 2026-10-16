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

    'Announcements' => [
        'announcements.manage' => 'Manage Announcements',
    ],

    'Employees' => [
        'employee.view' => 'View Employee List',
        'employee.edit' => 'Edit Employee Profile',
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
    ],

    'Attendance' => [
        'attendance.view_page' => 'View Team Attendance',
        'attendance.import' => 'Import Attendance',
        'attendance.manage' => 'Manage / Edit Attendance',
    ],

    'Leaves' => [
        'leave.approve' => 'Approve / Reject Leave',
        'leave.approve.team' => 'Approve Leave — Own Reports Only',
        'leave.approve.all' => 'Approve Leave — Company-wide',
        'leave.create_type' => 'Create Leave Type',
        'leave.create_policy' => 'Create Leave Policy',
        'leave.view_all' => 'View All Leaves',
        'outdoor_duty.approve' => 'Approve Outdoor Duty',
    ],

    'Travel & Expense' => [
        'expense.approve' => 'Approve / Reject Claims',
        'expense.manage_types' => 'Manage Expense Types',
        'travel_claim.verify' => 'Verify Travel Claims',
        'travel_claim.approve' => 'Approve Travel Claims',
        'travel_claim.pay' => 'Pay Travel Claims',
    ],

    'Payroll' => [
        'payroll.view' => 'View Payroll',
        'payroll.process' => 'Process Payroll (incl. Salary Import)',
    ],

    'Recruitment & Onboarding' => [
        'recruitment.view' => 'Access Recruitment (Jobs & Candidates)',
        'onboarding.manage' => 'Manage & Review Onboarding',
        'Manage Job OnBoard' => 'Manage Onboarding Checklist',
    ],

    /*
    | The four modules below are pre-registry permission names (plain
    | English, not dotted keys) still actively checked via
    | Auth::user()->can('...') in Job/JobApplication/JobCategory/
    | JobStage/CustomQuestion/InterviewScheduleController - real,
    | live-enforced access for Admin/HR/Manager that the registry never
    | had an equivalent for. Keys are exactly the strings those
    | controllers check, so they must not be renamed. A handful of
    | sibling names in the same old scheme (e.g. "Delete Job", "Edit Job
    | Application") were checked against the same controllers and found
    | unused - left out on purpose, not missed.
    */
    'Job Postings' => [
        'Manage Job' => 'Manage Job Postings',
        'Create Job' => 'Create Job Posting',
        'Edit Job' => 'Edit Job Posting',
        'Show Job' => 'View Job Posting Details',
    ],

    'Job Applications' => [
        'Manage Job Application' => 'Manage Job Applications',
        'Create Job Application' => 'Create Job Application',
        'Show Job Application' => 'View Job Application Details',
        'Move Job Application' => 'Move Application Between Stages',
        'Add Job Application Skill' => 'Tag Applicant Skills',
        'Add Job Application Note' => 'Add Applicant Notes',
        'Delete Job Application Note' => 'Delete Applicant Notes',
        'Delete Job Application' => 'Delete Job Application',
    ],

    'Job Categories & Stages' => [
        'Manage Job Category' => 'Manage Job Categories',
        'Create Job Category' => 'Create Job Category',
        'Edit Job Category' => 'Edit Job Category',
        'Delete Job Category' => 'Delete Job Category',
        'Manage Job Stage' => 'Manage Job Stages',
        'Create Job Stage' => 'Create Job Stage',
        'Edit Job Stage' => 'Edit Job Stage',
        'Delete Job Stage' => 'Delete Job Stage',
    ],

    'Interview & Screening' => [
        'Manage Custom Question' => 'Manage Screening Questions',
        'Create Custom Question' => 'Create Screening Question',
        'Edit Custom Question' => 'Edit Screening Question',
        'Delete Custom Question' => 'Delete Screening Question',
        'Create Interview Schedule' => 'Schedule Interview',
        'Edit Interview Schedule' => 'Reschedule Interview',
        'Delete Interview Schedule' => 'Cancel Interview',
    ],

    'Hierarchy & Probation' => [
        'hierarchy.view' => 'View Org Chart',
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
        'sales_trials.view_all' => "View All Trials — every salesperson's complete data (default: Admin & HR only)",
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

    'Telephony' => [
        'telephony.reports.own'    => 'View Own Call Reports',
        'telephony.reports.team'   => 'View Team Call Reports',
        'telephony.reports.all'    => 'View All Call Reports',
        'telephony.recording.play' => 'Play Call Recordings',
        'telephony.admin'          => 'Manage Agent Mapping, Webhook Log and Reconciliation',
    ],

    'CCare Attendance' => [
        'cc_attendance.manage' => 'Manage Salesperson Attendance (Full Register, All Submissions, Mark for Anyone)',
    ],

    'Intelligent Vault' => [
        'vault.upload' => 'Upload Documents',
        'vault.chat' => 'Use AI Bot',
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
        'goals.manage' => 'Manage 90-Day Goals',
        'goals.view' => 'View 90-Day Goals (read-only)',
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

    'Pricing Desk' => [
        'pricing_desk.view' => 'View Pricing Desk (price list, discount finder, calculators)',
        'pricing_desk.manage' => 'Edit Pricing Desk Data (products, discount rules, factors)',
    ],

];
