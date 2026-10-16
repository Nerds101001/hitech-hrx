<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SalesAccessGuard — restricts Sales field-ops / pipeline / performance pages
 * to people who actually work with sales:
 *   - admin / hr / manager / accounts roles, OR
 *   - employees whose department is Sales, Customer Care, or New Biz, OR
 *   - a user directly granted sales_ops.view_all (broad, cross-cutting
 *     module access), OR one of the per-sub-page permissions
 *     (sales_visits.*, client_master.*, sales_report.*, sales_pipeline.view,
 *     sales_targets.manage, sales_analytics.view, crm_clients.manage,
 *     kingo_bingo.play) — this only gets them PAST this outer gate; each
 *     controller still checks its own specific permission so a narrow grant
 *     (e.g. sales_report.view only) doesn't open every sales sub-page. See
 *     SalesAccessGuard::isBroadSalesUser() — the "already had full access
 *     to everything under this gate" check every controller uses to decide
 *     whether its own narrower per-page check even needs to run.
 *
 * Everyone else (regular non-sales employees) is redirected away, so sales
 * performance data is no longer visible to the whole company.
 */
class SalesAccessGuard
{
    /** The per-sub-page permissions that grant entry to this section alone. */
    public const SUB_PAGE_PERMISSIONS = [
        'sales_visits.view', 'sales_visits.create', 'sales_visits.manage', 'sales_visits.verify',
        'client_master.view', 'client_master.manage',
        'sales_report.view', 'sales_report.manage', 'sales_report.import',
        'sales_pipeline.view', 'sales_targets.manage', 'sales_analytics.view',
        'crm_clients.manage', 'kingo_bingo.play',
        // Pricing Desk: its own access logic (PricingDeskAccess) also allows a
        // department default, but Billing / AI & IT aren't part of this
        // section's broad department check above, so someone there needs
        // one of these granted (Role Matrix or Special Permissions) to get
        // past this outer gate at all.
        'pricing_desk.view', 'pricing_desk.manage',
    ];

    /**
     * True for the broad, pre-existing accessors of this whole section
     * (role, department, or the unrestricted sales_ops.view_all grant).
     * Every sub-page controller uses this as the "current access,
     * unchanged" branch before falling back to its own specific permission.
     */
    public static function isBroadSalesUser($user): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->hasAnyRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager', 'accounts'])
            || $user->can('sales_ops.view_all')) {
            return true;
        }
        $dept = strtolower(optional($user->department)->name ?? '');
        return $dept && (str_contains($dept, 'sales')
                       || str_contains($dept, 'customer care')
                       || str_contains($dept, 'new biz')
                       || str_contains($dept, 'new business')
                       || str_contains($dept, 'export'));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        if (self::isBroadSalesUser($user) || $user->hasAnyPermission(self::SUB_PAGE_PERMISSIONS)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Access denied. Sales access only.'], 403);
        }
        return redirect()->route('user.dashboard.index')
            ->with('error', 'You do not have access to the Sales section.');
    }
}
