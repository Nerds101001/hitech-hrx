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
            return $this->redirectToEmployeeDashboard();
        }

        // Must have at least one legitimate admin role
        if (!$user->hasAnyRole(self::ADMIN_ROLES)) {
            // No recognised role at all — send to employee dashboard safely
            return $this->redirectToEmployeeDashboard();
        }

        return $next($request);
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
