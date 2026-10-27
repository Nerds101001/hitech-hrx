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
    /**
     * Roles that grant admin dashboard access by name alone, independent of
     * the Spatie permission system. Public so RoleController/the permission
     * editor can explain this on-screen instead of showing a sparse-looking
     * checkbox grid with no context for why it's sparse.
     */
    public const ADMIN_ROLES = ['admin', 'hr', 'accounts', 'manager'];

    /**
     * Permissions that, by themselves, grant admin.guard passage. Public so
     * RoleController can forbid a non-admin from ASSIGNING them (privilege-
     * escalation guard, H-1) — otherwise a manager / roles.manage delegate
     * could self-grant one of these and unlock the entire admin route tree.
     * Keep this list and the check in hasAdminPermissionOverride() in sync.
     */
    public const ELEVATED_PERMISSIONS = [
        'lifecycle.manage',
        'approvals.manage',
        'expense.approve',
        'loans.view_all',
        'loans.approve',
        'onboarding.manage',
        // Added 2026-09: verified via `route:list -v` the same way as the six
        // above - employee.lifecycle_action only sits behind route middleware
        // inside employees/* (terminate, confirm/extend/fail probation,
        // relieve, retire), all within this guard's own route tree. Those
        // controller methods now check it (see EmployeeController), so
        // without it here, granting the permission alone would look like it
        // worked and silently do nothing - blocked at this guard before ever
        // reaching that check.
        'employee.lifecycle_action',
    ];

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

        // Opened to every authenticated user: anyone can assign a task to
        // anyone else, but what they SEE is scoped inside the controller
        // (TaskManagementController::scopeVisible()) - same self-service
        // pattern as my-tasks above, not an admin-data page.
        'task-management',

        // A task's reference image and completion/objection photo. They sit
        // in the admin route group, so the assignee - usually a plain
        // employee - was bounced to the dashboard when opening the image on
        // their own task. TaskController checks Task::canBeViewedBy().
        'tasks/*/attachment',
        'task-updates/*/attachment',

        // Same self-service pattern as my-tasks above, added later and missed
        // off this list each time: each page scopes itself to the signed-in
        // user's own records (MyHolidaysController::index() via
        // Holiday::forUser(), EmployeeLifecycleController::myNotices(), and
        // MyAssetsController's own queries all filter to auth()->id()), so an
        // ordinary employee was silently bounced to the dashboard on their
        // own holiday calendar, their own warnings/complaints, and their own
        // assets instead of seeing the page the sidebar links them to.
        'my-holidays',
        'my-warnings-complaints',
        'my-assets',

        // The actions on that my-warnings-complaints page (acknowledge, post
        // a reply, download the attachment) live under employee-lifecycle/*,
        // not my-warnings-complaints/*, so the entry above never covered
        // them. Each one is already ownership-checked inside
        // EmployeeLifecycleController (user_id/respondent_id === Auth::id(),
        // 403 otherwise) — this guard was simply bouncing a plain 'employee'
        // role to the dashboard before the controller ever ran, with no
        // error shown, so Acknowledge looked like it silently did nothing.
        'employee-lifecycle/warnings/*/attachment',
        'employee-lifecycle/warnings/*/acknowledge',
        'employee-lifecycle/warnings/*/reply',
        'employee-lifecycle/complaints/*/attachment',
        'employee-lifecycle/complaints/*/acknowledge',
        'employee-lifecycle/complaints/*/reply',
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

        // A box ticked on the Roles page (or given as a special permission)
        // opens the pages that check for it, whatever the person's role - see
        // permissionOpens(). Only that page: the rest of the admin area stays
        // closed to them.
        $opensThisPage = self::permissionOpens($request->route(), $user);

        // 'employee' role ALWAYS blocks admin access, regardless of other roles.
        // This prevents accidental department bulk-assignments from leaking data.
        if ($user->hasRole('employee') || $user->hasRole('office_employee')) {
            if (!$this->hasAdminPermissionOverride($user) && !$opensThisPage) {
                return $this->redirectToEmployeeDashboard();
            }
        }

        // Must have at least one legitimate admin role
        if (!$user->hasAnyRole(self::ADMIN_ROLES)) {
            if (!$this->hasAdminPermissionOverride($user) && !$opensThisPage) {
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
        foreach ($user->getAllPermissions() as $permission) {
            if (in_array($permission->name, self::ELEVATED_PERMISSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does a permission this person holds open THIS page? True when the
     * route's own gate (a role_or_permission: or permission: middleware on
     * it or its groups) names a permission they hold.
     *
     * This is what makes the Roles & Permissions page mean what it says for
     * every role - Employee, or a new role made on that page - not only the
     * four ADMIN_ROLES that pass here by name. It is page by page: a route
     * with no permission gate of its own is never opened this way, and the
     * route's own gate still runs after this guard, so the person gets
     * exactly the pages that check for what they were given.
     */
    public static function permissionOpens($route, $user): bool
    {
        if (!$route || !$user || !method_exists($route, 'gatherMiddleware')) {
            return false;
        }
        $held = null;
        foreach ($route->gatherMiddleware() as $m) {
            if (!is_string($m)) {
                continue;
            }
            foreach (['role_or_permission:', 'permission:'] as $prefix) {
                if (!str_starts_with($m, $prefix)) {
                    continue;
                }
                $held ??= $user->getAllPermissions()->pluck('name')->all();
                if (array_intersect(array_map('trim', explode('|', substr($m, strlen($prefix)))), $held)) {
                    return true;
                }
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
