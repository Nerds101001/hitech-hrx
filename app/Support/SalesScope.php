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
 *   - admin / hr / sales_ops.view_all → null  (no restriction — see everyone)
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

        if ($user->hasRole(['admin', 'Admin', 'hr', 'HR']) || $user->can('sales_ops.view_all')) {
            return null; // unrestricted
        }

        $ids = collect([$user->id]);

        // Sales manager: their whole team (direct reports). A terminated
        // employee whose reporting_to_id was never cleared must not still
        // count as a live teammate.
        if ($user->hasRole(['manager', 'Manager'])) {
            $ids = $ids->merge(
                User::where('reporting_to_id', $user->id)->notTerminated()->pluck('id')
            );
        }

        // STRICT rule: only the assigned CCARE of a salesperson gets that
        // salesperson's whole book (via cc_salesperson_map). A pure New Biz
        // rep gets NO salesperson-level access — they see only the customers
        // tagged to them, which applyScope adds at row level. The combined
        // "Customer Care + New Biz" department counts as CCare here.
        if (self::isRep($user) && method_exists($user, 'isCcareAgent') && $user->isCcareAgent()) {
            // ONLY the CCare-capacity mappings grant a salesperson's whole book.
            // A combined "Customer Care + New Biz" agent also holds role='newbiz'
            // rows (row-level access, added by applyScope) — those must NOT open
            // the whole book, or she is treated as CCare for salespeople she only
            // does New Biz for. Same termination check as above: a mapping row
            // that outlived the salesperson's employment must not resurrect them.
            $mappedIds = CcSalespersonMap::where('cc_user_id', $user->id)
                ->where('role', 'ccare')
                ->pluck('sales_user_id');
            $ids = $ids->merge(
                User::whereIn('id', $mappedIds)->notTerminated()->pluck('id')
            );
        }

        return $ids->filter()->unique()->values()->all();
    }

    /**
     * May this user search the whole customer database and claim a role on a
     * row outside their normal scope? Deliberately broad — any sales-floor
     * user, not just CCare/New Biz, per explicit product decision: a plain
     * salesperson can claim the Salesperson role on a customer too.
     *
     * Reuses SalesAccessGuard::isBroadSalesUser() — the same predicate that
     * already decides who may reach the Sales Report page at all — rather
     * than defining a second, narrower notion of "sales-floor user." Checked
     * independently inside the search/claim/history endpoints themselves
     * (not relied on via middleware alone), matching how every other
     * mutation on this resource re-validates itself.
     */
    public static function canClaimCustomer($user): bool
    {
        return \App\Http\Middleware\SalesAccessGuard::isBroadSalesUser($user);
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
    /**
     * Who a user is personally responsible for, ignoring the CCare→salesperson
     * map. A CCare rep is mapped to several salespeople, and allowedUserIds()
     * merges those in so the Sales Report shows their whole book — correct
     * there, but on the Sales Pipeline it meant a rep saw every prospect
     * belonging to those salespeople, including ones another rep owns.
     *
     * Managers still get their direct reports; everyone else gets only
     * themselves.
     */
    public static function personalIds($user, $request = null, string $memberParam = 'sp'): ?array
    {
        if (!$user) {
            return [0];
        }
        if ($user->hasRole(['admin', 'Admin', 'hr', 'HR']) || $user->can('sales_ops.view_all')) {
            return null; // unrestricted, same as allowedUserIds()
        }

        $ids = collect([$user->id]);

        // A manager's team is added ONLY when they ask for it. This used to
        // merge every direct report unconditionally and ignore the request
        // entirely, so the My Data / Team switch on the Sales Pipeline did
        // nothing at all: a manager of 20 always saw the whole team's book
        // (1,457 prospects) even with "My Data" selected, instead of the 136
        // they are personally tagged on. viewIds() below already had this
        // behaviour — personalIds() simply never got it.
        if ($user->hasRole(['manager', 'Manager'])) {
            $team = User::where('reporting_to_id', $user->id)->notTerminated()->pluck('id');

            if ($request && $request->filled($memberParam)) {
                // One teammate in focus — but only if they really report to me.
                $member = (int) $request->input($memberParam);
                return $team->contains($member) ? [$member] : [$user->id];
            }
            if ($request === null || $request->boolean('team')) {
                // No request in hand: keep the old, wider behaviour rather than
                // silently narrowing a caller that never opted in.
                $ids = $ids->merge($team);
            }
        }

        return $ids->filter()->unique()->values()->all();
    }

    public static function applyScope($query, $user, $request, array $cols): void
    {
        // 'personal' => true limits the scope to rows the user is themselves
        // tagged on, rather than their mapped salespeople's whole book.
        $ids = ($cols['personal'] ?? false)
            ? self::personalIds($user, $request, $cols['member'] ?? 'sp')
            : self::viewIds($user, $request, $cols['member'] ?? 'sp');
        if ($ids === null) {
            return; // admin/hr — no restriction
        }
        $repCols = $cols['rep'] ?? [];
        $isRep = (self::isRep($user) || $user->hasRole(['manager', 'Manager'])) && !empty($repCols);
        $extra = $cols['extra'] ?? false; // sales_pipelines only: extra-rep tags
        $query->where(function ($q) use ($ids, $isRep, $cols, $repCols, $extra) {
            $q->whereIn($cols['sp'], $ids);
            if ($isRep) {
                foreach ($repCols as $rc) {
                    $q->orWhereIn($rc, $ids);
                }
            }
            if ($extra) {
                // ANY user (salesperson/ccare/newbiz/manager-team) tagged as an
                // additional representative sees the customer too.
                $q->orWhereExists(function ($s) use ($ids) {
                    $s->selectRaw('1')->from('sales_pipeline_extra_reps as er')
                      ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')
                      ->whereIn('er.user_id', $ids);
                });
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
