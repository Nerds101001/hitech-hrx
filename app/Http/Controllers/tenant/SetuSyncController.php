<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\SetuSalespersonMapping;

/**
 * "Setu Sales Sync" — a direct way to trigger a sync for any confirmed
 * salesperson, without navigating to the Sales Report page and filtering to
 * them via ?sp=. Kept separate from SetuMappingController (which only edits
 * the tally_name -> user_id identity mapping and never triggers a sync);
 * this screen only ever reads confirmed mappings and reuses the existing
 * sales-visits.pipeline.sync-setu endpoint to do the actual work.
 *
 * Note: a bare `setu.manage` permission grant is NOT by itself sufficient to
 * sync anyone but yourself from this screen — SalesPipelineController::
 * syncSetu()'s own authorization check (and the sales.guard middleware in
 * front of it) only recognize the admin/hr/manager roles and
 * sales_report.manage, not setu.manage. That gap pre-dates this screen (a
 * bare setu.manage holder could already edit any mapping's identity via
 * SetuMappingController without being able to sync anyone else either) and
 * is unchanged here — pair setu.manage with one of those roles/permissions
 * if this screen needs to actually sync people other than yourself.
 */
class SetuSyncController extends Controller
{
    public function index()
    {
        $mappings = SetuSalespersonMapping::where('status', SetuSalespersonMapping::STATUS_CONFIRMED)
            ->whereNotNull('user_id')
            ->with('user')
            ->orderBy('tally_name')
            ->get();

        return view('tenant.setuSync.index', [
            'mappings' => $mappings,
            'pageConfigs' => ['contentLayout' => 'wide'],
        ]);
    }
}
