<?php

namespace App\Support;

use App\Models\User;
use App\Models\CcSalespersonMap;
use App\Models\SalesPipeline;

/**
 * SalesScope — single source of truth for "whose data may this user see?"
 * across every Sales field-ops page (Client Master, Visits, Analytics,
 * Attendance, Sales Report, Sales Pipeline, Kingo Bingo).
 *
 * Rules:
 *   - admin / hr            → null  (no restriction — see everyone)
 *   - sales manager         → themselves + their direct reports (reporting_to_id)
 *   - customer care / newbiz→ themselves + their mapped salespersons
 *                             (cc_salesperson_map PLUS any salesperson whose
 *                              pipeline rows already list them as ccare/new_biz)
 *   - salesperson / other   → only themselves
 *
 * The returned list is a set of USER ids. It is used for both user-keyed
 * tables (attendance, visits — column user_id) and salesperson-keyed tables
 * (sales_pipelines, salesperson_targets — column salesperson_id): a
 * salesperson's own id lives in both, a ccare rep's own id simply matches no
 * salesperson rows, so the same set is correct everywhere.
 *
 * null  == unrestricted (admin/HR).
 */
class SalesScope
{
    public static function allowedUserIds($user): ?array
    {
        if (!$user) {
            return [0]; // no user → see nothing
        }

        if ($user->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
            return null; // unrestricted
        }

        $ids = collect([$user->id]);

        // Sales manager: their whole team (direct reports).
        if ($user->hasRole(['manager', 'Manager'])) {
            $ids = $ids->merge(
                User::where('reporting_to_id', $user->id)->pluck('id')
            );
        }

        // Customer Care / New Biz: their mapped salespersons. The map is not
        // reliably maintained on import, so derive from pipeline rows too —
        // otherwise a rep with no map row (e.g. Jasleen) would see nothing.
        $dept = strtolower(optional($user->department)->name ?? '');
        $isCcareOrNewBiz = str_contains($dept, 'customer care')
            || str_contains($dept, 'new biz')
            || str_contains($dept, 'new business');

        if ($isCcareOrNewBiz) {
            $mapped = CcSalespersonMap::where('cc_user_id', $user->id)
                ->pluck('sales_user_id');
            $derived = SalesPipeline::whereNull('deleted_at')
                ->where(function ($q) use ($user) {
                    $q->where('ccare_id', $user->id)
                      ->orWhere('new_biz_id', $user->id);
                })->distinct()->pluck('salesperson_id');
            $ids = $ids->merge($mapped)->merge($derived);
        }

        return $ids->filter()->unique()->values()->all();
    }
}
