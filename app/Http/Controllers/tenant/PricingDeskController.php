<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\PricingDeskData;
use App\Support\PricingDeskAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Sales Pricing Desk: price list, discount-approval finder, the product
 * calculators, and (for people with pricing_desk.manage) an edit screen for
 * all of it. The page itself is the vendored tool
 * (resources/views/tenant/pricing-desk/index.blade.php) with its data now
 * read from and saved to the database instead of baked into a static file
 * that had to be hand-edited and re-uploaded to publish a price change.
 */
class PricingDeskController extends Controller
{
    public function index()
    {
        abort_unless(PricingDeskAccess::canView(auth()->user()), 404);

        $row = PricingDeskData::current();

        return view('tenant.pricing-desk.index', [
            'seedJson' => $this->safeJson($row->data),
            'canManage' => PricingDeskAccess::canManage(auth()->user()),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        abort_unless(PricingDeskAccess::canManage(auth()->user()), 403);

        $request->validate([
            'data' => 'required|array',
        ]);
        $data = $request->input('data');

        // Minimal sanity check — the tool's own import function checks the
        // same two keys before trusting a file; mirrored here so a malformed
        // payload can't silently wipe the live price list.
        if (empty($data['vci']) || empty($data['lubes'])) {
            return response()->json(['message' => 'That does not look like a valid price export (missing vci/lubes lists).'], 422);
        }

        PricingDeskData::create([
            'data' => $data,
            'version' => $data['version'] ?? null,
            'updated_by_id' => auth()->id(),
        ]);

        return response()->json(['message' => 'Saved. Every viewer sees this on their next refresh.']);
    }

    /** Escaped so the JSON can sit inside a <script> tag without a stray "</script>" breaking out of it. */
    private function safeJson(array $data): string
    {
        return str_replace('</', '<\/', json_encode($data));
    }
}
