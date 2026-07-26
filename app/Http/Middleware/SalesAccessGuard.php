<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SalesAccessGuard — restricts Sales field-ops / pipeline / performance pages
 * to people who actually work with sales:
 *   - admin / hr / manager / accounts roles, OR
 *   - a user directly granted sales_ops.view_all (module-scoped admin access
 *     without promoting them to a full admin/hr/manager role), OR
 *   - employees whose department is Sales, Customer Care, or New Biz.
 *
 * Everyone else (regular non-sales employees) is redirected away, so sales
 * performance data is no longer visible to the whole company.
 */
class SalesAccessGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        if ($user->hasAnyRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager', 'accounts'])
            || $user->can('sales_ops.view_all')) {
            return $next($request);
        }

        $dept = strtolower(optional($user->department)->name ?? '');
        if ($dept && (str_contains($dept, 'sales')
                   || str_contains($dept, 'customer care')
                   || str_contains($dept, 'new biz')
                   || str_contains($dept, 'new business'))) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Access denied. Sales access only.'], 403);
        }
        return redirect()->route('user.dashboard.index')
            ->with('error', 'You do not have access to the Sales section.');
    }
}
