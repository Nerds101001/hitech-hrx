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
 * Rules (salesperson-LEVEL access — see a salesperson's whole book):
 *   - admin / hr            → null  (no restriction — see everyone)
 *   - sales manager         → themselves + their direct reports (reporting_to_id)
 *   - customer care / newbiz→ themselves + salespersons EXPLICITLY mapped to
 *                             them in cc_salesperson_map (their coordinator book)
 *   - salesperson / other   → only themselves
 *
 * NOTE: a CCare/New Biz rep also sees the individual customer rows they are
 * assigned to (ccare_id / new_biz_id = them) even when the salesperson is not
 * in their map — but that is ROW-level and handled by applyScope(), not here.
 * We deliberately do NOT grant a rep the salesperson's whole book just because
 * they appear on a few of its rows (that over-shared every rep's view).
 *
 * The returned list is a set of USER ids. null == unrestricted (admin/HR).
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

        // Customer Care / New Biz: only salespersons EXPLICITLY mapped to them.
        // (Row-level access to their own assigned customers is added separately
        // by applyScope.)
        if (self::isRep($user)) {
            $ids = $ids->merge(
                CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id')
            );
        }

        return $ids->filter()->unique()->values()->all();
    }

    /** True when the user is a Customer Care / New Biz representative. */
    public static function isRep($user): bool
    {
        if (!$user) return false;
        $dept = strtolower(optional($user->department)->name ?? '');
        return str_contains($dept, 'customer care')
            || str_contains($dept, 'new biz')
            || str_contains($dept, 'new business');
    }

    /**
     * applyScope — apply row visibility to a query on a sales row table.
     * Salesperson-level access (viewIds) OR, for a CCare/New Biz rep, the rows
     * assigned to them. For a CCare/New Biz MANAGER the rep set is their whole
     * team (viewIds already returns self / self+team / a chosen member based on
     * the manager toggle), so a manager sees every customer assigned to the
     * reps reporting to them — and only their team's, not the whole company.
     *
     *   $cols = ['sp' => 'salesperson_id', 'rep' => ['ccare_id','new_biz_id'],
     *            'member' => 'sp']   // 'member' = manager's teammate filter key
     */
    public static function applyScope($query, $user, $request, array $cols): void
    {
        $ids = self::viewIds($user, $request, $cols['member'] ?? 'sp');
        if ($ids === null) {
            return; // admin/hr — no restriction
        }
        $repCols = $cols['rep'] ?? [];
        $isRep = (self::isRep($user) || $user->hasRole(['manager', 'Manager'])) && !empty($repCols);
        $query->where(function ($q) use ($ids, $isRep, $cols, $repCols) {
            $q->whereIn($cols['sp'], $ids);
            if ($isRep) {
                foreach ($repCols as $rc) {
                    $q->orWhereIn($rc, $ids);
                }
            }
        });
    }

    /**
     * viewIds — the ids to actually SHOW on a list page, applying the sales
     * manager's default-to-own behaviour (mirrors the Kingo Bingo scorecard):
     *   - admin/hr                    → null (everyone)
     *   - manager, default            → only themselves
     *   - manager + ?team=1           → their whole team
     *   - manager + member selected   → just that member (if on their team)
     *   - ccare/newbiz/salesperson    → their normal allowed set (unchanged)
     *
     * $memberParam is the request key that carries a chosen teammate's id
     * (e.g. 'sp' on Sales Report/Pipeline, 'salesperson_id' on Visits).
     */
    public static function viewIds($user, $request, string $memberParam = 'sp'): ?array
    {
        $allowed = self::allowedUserIds($user);
        if ($allowed === null) {
            return null; // admin/hr — no restriction
        }

        $isManager = $user->hasRole(['manager', 'Manager']);
        if (!$isManager || count($allowed) <= 1) {
            return $allowed; // salesperson / ccare / newbiz — unchanged
        }

        // Manager: a specific teammate takes priority, else team vs. own default.
        if ($request->filled($memberParam)) {
            $member = (int) $request->input($memberParam);
            return in_array($member, $allowed) ? [$member] : [$user->id];
        }
        if ($request->boolean('team')) {
            return $allowed;
        }
        return [$user->id];
    }
}
