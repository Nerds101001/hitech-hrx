<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Services\TallyGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The ONE write path from HRX into Tally: Credit & Contacts lets someone correct a customer's contact person
 * or mobile number, and push that one change back to the ledger it came from. Everything else Sales Sources
 * does with Tally is read-only (see App\Services\TallyGateway's own doc comment).
 *
 * Deliberately admin-only (stricter than the rest of Sales Sources, which is admin/HR/accounts) -- this is the
 * one place in the app that can alter the company's live accounting data, not just read from it.
 *
 * Two-step by design: preview() never touches Tally, only shows what would change against what tally_parties
 * already has cached; update() is the only method that actually calls TallyGateway::updateLedgerContact(). A
 * client is expected to call preview() first and only call update() once a person has confirmed what they saw.
 */
class TallyContactUpdateController extends Controller
{
    private function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole(['admin', 'Admin', 'super_admin']), 403);
        abort_unless(\App\Http\Middleware\RestrictTallyAccess::permits($user), 404);   // the named viewer(s) only
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'normalized_name' => ['required', 'string'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_mobile' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /** What's currently on file (tally_parties, last synced from Tally) vs what would be sent -- no Tally call. */
    public function preview(Request $request): JsonResponse
    {
        $this->authorizeAccess();
        $data = $this->validated($request);

        $party = DB::table('tally_parties')->where('normalized_name', $data['normalized_name'])->first();
        if (!$party) {
            return response()->json(['ok' => false, 'error' => 'This customer is not in the last Tally party sync -- refusing to guess its exact ledger name.'], 404);
        }

        return response()->json([
            'ok' => true,
            'party_name' => $party->party_name,
            'current' => ['contact_person' => $party->contact_person, 'contact_mobile' => $party->contact_mobile],
            'proposed' => ['contact_person' => $data['contact_person'] ?? null, 'contact_mobile' => $data['contact_mobile'] ?? null],
            'unchanged' => (string) $party->contact_person === (string) ($data['contact_person'] ?? '')
                && (string) $party->contact_mobile === (string) ($data['contact_mobile'] ?? ''),
        ]);
    }

    /** Actually writes to Tally, then updates the local cache to match what Tally confirmed back. */
    public function update(Request $request): JsonResponse
    {
        $this->authorizeAccess();
        $data = $this->validated($request);

        $party = DB::table('tally_parties')->where('normalized_name', $data['normalized_name'])->first();
        if (!$party) {
            return response()->json(['ok' => false, 'error' => 'This customer is not in the last Tally party sync -- refusing to guess its exact ledger name.'], 404);
        }

        $url = (string) config('services.tally.write_gateway_url');
        $company = (string) config('services.tally.write_gateway_company');
        if ($url === '' || $company === '') {
            return response()->json(['ok' => false, 'error' => 'Writing to Tally is not configured yet (TALLY_WRITE_GATEWAY_URL / TALLY_WRITE_GATEWAY_COMPANY are blank). This is deliberate until the write path has been tested live.'], 422);
        }

        $gateway = new TallyGateway($url, $company, (int) config('services.tally.gateway_timeout', 30));
        try {
            $gateway->assertCompany();
            $confirmed = $gateway->updateLedgerContact($party->party_name, $data['contact_person'] ?? null, $data['contact_mobile'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }

        DB::table('tally_parties')->where('normalized_name', $data['normalized_name'])->update([
            'contact_person' => $confirmed['contact_person'],
            'contact_mobile' => $confirmed['contact_mobile'],
            'synced_at' => now(),
        ]);

        return response()->json(['ok' => true, 'party_name' => $party->party_name, 'confirmed' => $confirmed]);
    }
}
