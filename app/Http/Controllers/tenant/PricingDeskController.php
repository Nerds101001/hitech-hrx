<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\PricingDeskData;
use App\Support\PricingDeskAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Sales Pricing Desk: price list, discount-approval finder, product
 * calculators, and — for admins — the price/discount/factor editor (the
 * Admin tab). Viewing is open to PricingDeskAccess::canView()'s full roster
 * (admin/hr by role, anyone else via the pricing_desk.view/manage permission
 * or their department matching config/pricing_desk.php) — back to the same
 * audience as the header's Quick Calculator popup, after a brief Mukul-only
 * restriction earlier in this page's redesign. The Admin tab itself still
 * follows canManage() (admin role or pricing_desk.manage) regardless of who
 * can view the rest of the page, so price/rule editing stays admin-only.
 */
class PricingDeskController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless(PricingDeskAccess::canView($user), 404);

        $row = PricingDeskData::current();

        return view('tenant.pricing-desk.index', [
            'seedJson' => $this->safeJson($row->data),
            'canManage' => PricingDeskAccess::canManage($user),
        ]);
    }

    /**
     * Just what the header's Quick Calculator popup needs (factors + the
     * priced lists it looks rates up against) — not the whole page's markup,
     * so the popup opens instantly instead of loading the full tool. Each
     * price row is trimmed to only the fields the calculators actually read
     * (code/name/spec/unit/dlp/t) — cat/colour/size are price-list-browsing
     * fields this popup never shows, and dropping them roughly halves the
     * payload.
     */
    public function calculatorData(): JsonResponse
    {
        abort_unless(PricingDeskAccess::canView(auth()->user()), 404);

        $data = PricingDeskData::current()->data;
        $trim = fn ($rows) => array_map(fn ($r) => [
            'code' => $r['code'] ?? null, 'name' => $r['name'] ?? null, 'spec' => $r['spec'] ?? null,
            'unit' => $r['unit'] ?? null, 'dlp' => $r['dlp'] ?? null, 't' => $r['t'] ?? [],
        ], $rows ?? []);

        return response()->json([
            'factors' => $data['factors'] ?? [],
            'vci'     => $trim($data['vci'] ?? []),
            'lubes'   => $trim($data['lubes'] ?? []),
            'drbio'   => $trim($data['drbio'] ?? []),
            'kif'     => $trim($data['kif'] ?? []),
            'rules'   => $data['rules'] ?? [],
            'lists'   => $data['lists'] ?? [],
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
