<?php

/*
|--------------------------------------------------------------------------
| Permissions a built-in role already has by NAME in code
|--------------------------------------------------------------------------
|
| Most enforcement points read "role OR permission" - e.g.
| hasRole(['admin','hr']) || can('loans.approve'), or route middleware
| role_or_permission:admin|hr|loans.approve. So an HR user passes those
| checks whether or not the HR role holds the permission row, and the
| Roles & Permissions matrix used to show those boxes unticked.
|
| A permission is listed under a role here ONLY if every place in app/,
| routes/, resources/menu and resources/views that checks it already lets
| that role in by name (sidebar menu gates bypass for admin/hr only, so a
| menu-checked permission is never listed for manager/accounts). Storing
| these rows is therefore a no-op for access - it only makes the matrix
| show the truth. BackfillBuiltInRolePermissionsSeeder writes them
| (add-only); the role editor shows them ticked and locked, and never
| strips them on save, because unticking would not remove the role-name
| access anyway.
|
| admin is not listed: Gate::before (AppServiceProvider) grants it every
| ability, so the matrix shows all boxes ticked and locked for it without
| storing anything.
|
| If you change a hasRole()/role_or_permission check to drop a role, remove
| the permission from that role's list here too.
|
*/

return [

    'hr' => [
        'employee.view',
        'employee.kra',
        'employee.delete',
        'employee.reset_password',
        'employee.manage_leave_balance',
        'employee.manage_payroll_adjustment',
        'employee.lifecycle_action',
        'attendance.view_page',
        'attendance.import',
        'attendance.manage',
        'leave.create_type',
        'leave.create_policy',
        'leave.view_all',
        'outdoor_duty.approve',
        'expense.approve',
        'expense.manage_types',
        'payroll.view',
        'payroll.process',
        'probation.manage',
        'lifecycle.manage',
        'sales_ops.manage',
        'telephony.recording.play',
        'assets.view',
        'assets.create',
        'assets.edit',
        'assets.delete',
        'assets.assign',
        'assets.manage_categories',
        'monitoring.live_location',
        'monitoring.timeline',
        'monitoring.card_view',
        'sos.manage',
        'loans.view_all',
        'loans.approve',
        'hr_policies.manage',
        'mind_speak.manage',
        'approvals.manage',
        'audit.view',
        'shifts.manage',
        'holidays.manage',
        'document_types.manage',
        'ipgroup.manage',
        'geofencegroup.manage',
        'qrgroup.manage',
        'units.manage',
        'departments.manage',
        'designations.manage',
        'teams.manage',
        'visits.manage',
        'document_request.manage',
        'device.manage',
        'account.manage',
        'chat.access',
    ],

    'manager' => [
        'attendance.view_page',
        'attendance.import',
        'attendance.manage',
        'leave.create_type',
        'leave.view_all',
        'outdoor_duty.approve',
        'expense.manage_types',
        'telephony.recording.play',
        'assets.create',
        'assets.delete',
        'assets.manage_categories',
        'monitoring.live_location',
        'monitoring.timeline',
        'monitoring.card_view',
        'shifts.manage',
        'holidays.manage',
        'document_types.manage',
        'ipgroup.manage',
        'geofencegroup.manage',
        'qrgroup.manage',
        'visits.manage',
        'device.manage',
        'account.manage',
    ],

    'accounts' => [
        'employee.view',
        'employee.kra',
        'employee.delete',
        'employee.reset_password',
        'employee.manage_leave_balance',
        'employee.manage_payroll_adjustment',
        'attendance.view_page',
        'attendance.import',
        'attendance.manage',
        'leave.create_type',
        'leave.view_all',
        'outdoor_duty.approve',
        'expense.manage_types',
        'payroll.view',
        'payroll.process',
        'sales_ops.manage',
        'monitoring.live_location',
        'monitoring.timeline',
        'monitoring.card_view',
        'shifts.manage',
        'holidays.manage',
        'document_types.manage',
        'ipgroup.manage',
        'geofencegroup.manage',
        'qrgroup.manage',
        'visits.manage',
    ],

];
