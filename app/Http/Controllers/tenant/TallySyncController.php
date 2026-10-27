<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\SalesGroupMapping;

/**
 * "Tally Sales Sync" — supersedes Setu Sales Sync: a direct way to trigger a
 * local-Tally reconciliation for any confirmed salesperson. Mirrors
 * SetuSyncController exactly, except rows are grouped by USER (a salesperson
 * can own more than one confirmed ledger group, e.g. two spellings of their
 * name in Tally) rather than one row per mapping — the sync itself
 * (SalesPipelineController::syncTally()) already pulls every one of a
 * salesperson's confirmed ledger groups in one go.
 */
class TallySyncController extends Controller
{
    public function index()
    {
        $mappings = SalesGroupMapping::where('status', SalesGroupMapping::STATUS_CONFIRMED)
            ->whereNotNull('user_id')
            ->with('user')
            ->orderBy('tally_salesperson')
            ->get()
            ->groupBy('user_id')
            ->map(function ($rows) {
                $user = $rows->first()->user;
                return (object) [
                    'user' => $user,
                    'user_id' => $user->id,
                    'ledger_groups' => $rows->pluck('tally_salesperson')->sort()->values()->all(),
                ];
            })
            ->sortBy(fn ($r) => trim($r->user->first_name . ' ' . $r->user->last_name))
            ->values();

        return view('tenant.tallySync.index', [
            'mappings' => $mappings,
            'pageConfigs' => ['contentLayout' => 'wide'],
        ]);
    }
}
