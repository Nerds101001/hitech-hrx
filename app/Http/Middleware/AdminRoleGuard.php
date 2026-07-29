<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdminRoleGuard — Hard security gate for all admin/HR routes.
 *
 * STRICT RULE: No user may access admin routes unless they have EXACTLY
 * one of these roles: admin, hr, accounts, manager.
 *
 * The 'employee' role ALWAYS overrides and forces redirect to the employee
 * dashboard — even if 'accounts' was accidentally assigned alongside it.
 *
 * This middleware is the last line of defence: even if a controller has a bug,
 * this gate prevents admin data from leaking to regular employees.
 */
class AdminRoleGuard
{
    /** Roles that grant admin dashboard access */
    private const ADMIN_ROLES = ['admin', 'hr', 'accounts', 'manager'];

    /**
     * Routes that employees are allowed to access even inside the admin route group.
     * These are self-service endpoints (own profile, own profile update, logout).
     */
    private const EMPLOYEE_ALLOWED_PATHS = [
        'employees/myProfile',
        'employees/update-my-profile',
        'employees/changeEmployeeProfilePicture',
        'auth/logout',
        'lang',
        'work-control',

        // Personal, per-user pages that the sidebar shows to everyone. Each one
        // authorises inside its own controller and only ever renders that
        // user's own items, so they must not be bounced to the employee
        // dashboard — an employee is often a leaver's reporting manager, and
        // the NOC they owe is signed here.
        'my-tasks',
        'announcements',
        'exit-clearance',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Not logged in — redirect to login
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        // Allow self-service routes for all authenticated users
        foreach (self::EMPLOYEE_ALLOWED_PATHS as $allowedPath) {
            if ($request->is($allowedPath) || $request->is($allowedPath . '/*')) {
                return $next($request);
            }
        }

        // 'employee' role ALWAYS blocks admin access, regardless of other roles.
        // This prevents accidental department bulk-assignments from leaking data.
        if ($user->hasRole('employee') || $user->hasRole('office_employee')) {
            if (!$this->hasAdminPermissionOverride($user)) {
                return $this->redirectToEmployeeDashboard();
            }
        }

        // Must have at least one legitimate admin role
        if (!$user->hasAnyRole(self::ADMIN_ROLES)) {
            if (!$this->hasAdminPermissionOverride($user)) {
                // No recognised role at all — send to employee dashboard safely
                return $this->redirectToEmployeeDashboard();
            }
        }

        return $next($request);
    }

    /**
     * Check if user possesses any administrative permission override.
     *
     * ALLOWLIST, not blocklist: this used to be "any permission NOT on a
     * short safe list grants override," which meant every one of the ~50
     * module-scoped permission checkboxes in config/permissions_registry.php
     * (most of them dead/unwired — see the 2026-07-26 permission audit) would
     * silently unlock the entire admin.guard route tree for a plain-role
     * employee the moment anyone ticked that box in the role editor. Verified
     * via `php artisan route:list -v` that only these permissions actually
     * sit behind route middleware that lives inside this guard's route tree
     * (employee-lifecycle, approvals, and the loan/expense/onboarding
     * "restricted operations" group) — holding one of them must let the user
     * past this gate so that middleware gets to run. Every other permission,
     * including cc_attendance.manage / sales_ops.view_all and anything added
     * to the registry later, must NEVER by itself grant admin.guard passage.
     */
    private function hasAdminPermissionOverride($user): bool
    {
        $elevatedPermissions = [
            'lifecycle.manage',
            'approvals.manage',
            'expense.approve',
            'loans.view_all',
            'loans.approve',
            'onboarding.manage',
        ];

        foreach ($user->getAllPermissions() as $permission) {
            if (in_array($permission->name, $elevatedPermissions, true)) {
                return true;
            }
        }

        return false;
    }

    private function redirectToEmployeeDashboard(): Response
    {
        // Redirect to the employee-specific dashboard route
        if (request()->expectsJson()) {
            return response()->json(['error' => 'Access denied. Insufficient privileges.'], 403);
        }

        return redirect()->route('user.dashboard.index');
    }
}
