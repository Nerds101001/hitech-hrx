<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\CrmClientMapping;
use App\Models\SalesPipeline;
use App\Models\User;

/**
 * "CRM Mapping Sync" — a bulk overview for the data reconciliation audit's
 * fix plan step 1 ("give syncCrmMapping a button"). syncCrmMapping()/
 * resolveCrmMapping() (SalesPipelineController) and their UI (window.
 * CrmMappingSync, resources/assets/js/app/crm-mapping-review-common.js,
 * the preview + review modal partials) already existed before this screen -
 * a "Link {salesperson} customers to CRM" item on the Sales Report page's
 * own dropdown, added 2026-08-28. This page reuses that exact driver and
 * those exact modals; it adds only what that entry point doesn't have: every
 * salesperson's unlinked count and cached-candidate count in one list, so
 * running the sync for everyone doesn't mean visiting 59 separate Sales
 * Report pages and finding the button in a dropdown each time - plausibly
 * why the audit still found 0 of 9,946 rows linked over a week after that
 * button shipped.
 *
 * Also surfaces how many CRM companies are cached for each salesperson to
 * match against (crm_client_mappings, any of the 5 role columns) - a
 * salesperson with zero cached companies will sync zero matches no matter
 * how good the name matching is, and that's a different problem (needs
 * step 2's backfill) than one who has candidates but a messy name.
 */
class CrmMappingSyncController extends Controller
{
    public function index()
    {
        $stats = SalesPipeline::selectRaw(
            'salesperson_id, COUNT(*) as total_rows,
             SUM(CASE WHEN is_locked = 1 AND crm_client_mapping_id IS NULL THEN 1 ELSE 0 END) as unlinked_count,
             SUM(CASE WHEN crm_client_mapping_id IS NOT NULL THEN 1 ELSE 0 END) as linked_count'
        )
            ->whereNotNull('salesperson_id')
            ->groupBy('salesperson_id')
            ->get()
            ->keyBy('salesperson_id');

        $userIds = $stats->keys()->all();
        $users = User::whereIn('id', $userIds)->orderBy('first_name')->get()->keyBy('id');

        $rows = $stats->map(function ($stat, $userId) use ($users) {
            $user = $users->get($userId);
            return (object) [
                'user' => $user,
                'total_rows' => (int) $stat->total_rows,
                'unlinked_count' => (int) $stat->unlinked_count,
                'linked_count' => (int) $stat->linked_count,
                // Any of the 5 roles - matches CrmClientMapping::scopeForUser()
                // and what syncCrmMapping() itself candidates against.
                'cached_candidates' => $user ? CrmClientMapping::forUser($user->id)->count() : 0,
            ];
        })
            ->filter(fn ($r) => $r->user !== null)
            ->sortByDesc('unlinked_count')
            ->values();

        return view('tenant.crmMappingSync.index', [
            'rows' => $rows,
            'pageConfigs' => ['contentLayout' => 'wide'],
        ]);
    }
}
