<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Registers every granular permission wired up across the app's
 * per-capability access control pass (attendance, sales field ops,
 * employees, masters, operations, assets, vault, system utilities,
 * performance) and grants each to the roles that already have the
 * equivalent access today, purely so the role editor UI reflects
 * reality — the actual routes/controllers pass via hasRole() OR-checks
 * regardless of these grants. Idempotent — safe to re-run anywhere.
 *
 * Permissions intentionally granted to NO role (goals.manage,
 * scorecards.manage, kpi.manage, settings.manage): these routes remain
 * admin-only by explicit decision — the permission exists only so ONE
 * specific person can be individually delegated access without widening
 * the role-based default.
 */
class CcAttendanceSalesOpsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // role => [permission names already matching that role's current access]
        $grants = [
            'admin' => [],
            'hr' => [],
            'accounts' => [],
            'manager' => [],
        ];

        // Already-fixed modules (earlier commits) — kept here for idempotency.
        $adminHr = [
            'cc_attendance.manage', 'sales_ops.view_all', 'reports.view', 'reports.export',
            'roles.manage', 'kingo_bingo.manage',
        ];

        // Modules gated role:admin|hr|accounts|manager (Masters, Operations,
        // Departments/Designations/Teams, Leave Requests, Payroll register).
        $adminHrAccountsManager = [
            'shifts.manage', 'holidays.manage', 'document_types.manage', 'ipgroup.manage',
            'geofencegroup.manage', 'qrgroup.manage', 'units.manage',
            'departments.manage', 'designations.manage', 'teams.manage',
            'attendance.view_page', 'attendance.manage', 'attendance.import',
            'visits.manage', 'tasks.manage',
            'leave.approve', 'leave.view_all', 'outdoor_duty.approve',
            'leave.create_type', 'leave.create_policy', 'expense.manage_types',
            'payroll.view', 'payroll.process',
        ];

        // Employees module — gated role:admin|hr|accounts.
        $adminHrAccounts = [
            'employee.view', 'employee.edit', 'employee.view_banking',
            'employee.view_document', 'employee.edit_document', 'employee.kpa', 'employee.kra',
            'employee.delete', 'employee.reset_password', 'employee.manage_leave_balance',
            'employee.manage_payroll_adjustment', 'employee.manage_device',
            'employee.lifecycle_action', 'sales_ops.manage',
        ];

        // Stricter admin|hr-only modules.
        $adminHrOnly = [
            'document_request.manage', 'hr_policies.manage', 'audit.view',
            'vault.chat', 'vault.upload',
            'chat.access', 'probation.view', 'probation.manage',
        ];

        // Asset Management / System Utilities — gated role:admin|hr|manager.
        $adminHrManager = [
            'assets.view', 'assets.create', 'assets.edit', 'assets.delete', 'assets.assign',
            'assets.manage_categories', 'device.manage', 'biometric.manage', 'account.manage',
        ];

        // Sales Field Ops sub-pages — sales.guard already covers manager/
        // accounts/department members structurally; admin/hr granted here
        // for UI consistency.
        $salesFieldOps = [
            'sales_visits.view', 'sales_visits.create', 'sales_visits.manage', 'sales_visits.verify',
            'client_master.view', 'client_master.manage',
            'sales_report.view', 'sales_report.manage', 'sales_report.import',
            'sales_pipeline.view', 'sales_targets.manage', 'sales_analytics.view',
            'crm_clients.manage', 'kingo_bingo.play',
        ];

        foreach (array_merge($adminHr, $adminHrAccountsManager, $adminHrOnly, $salesFieldOps) as $p) {
            $grants['admin'][] = $p;
            $grants['hr'][] = $p;
        }
        foreach ($adminHrAccountsManager as $p) {
            $grants['accounts'][] = $p;
            $grants['manager'][] = $p;
        }
        foreach ($adminHrAccounts as $p) {
            $grants['admin'][] = $p;
            $grants['hr'][] = $p;
            $grants['accounts'][] = $p;
        }
        foreach ($adminHrManager as $p) {
            $grants['admin'][] = $p;
            $grants['hr'][] = $p;
            $grants['manager'][] = $p;
        }

        $allPermissions = array_unique(array_merge(...array_values($grants)));
        foreach ($allPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Permissions that exist but are deliberately granted to no role —
        // admin-only routes preserved as-is, available only for individual
        // per-user delegation via "User Special Permissions."
        foreach (['goals.manage', 'scorecards.manage', 'kpi.manage', 'settings.manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach ($grants as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            if ($role && $permissionNames) {
                $role->givePermissionTo(array_unique($permissionNames));
            }
        }
    }
}
