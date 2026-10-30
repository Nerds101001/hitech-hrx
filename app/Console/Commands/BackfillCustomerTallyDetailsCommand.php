<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off catch-up for customers verified against Tally BEFORE the
 * tally_mailing_name/gstin/pan/contact_person/contact_mobile/email/address
 * columns existed (CustomerTallyMappingController::mapTally() only backfills
 * these on NEW verifications going forward). Re-copies them from the current
 * tally_ledgers snapshot for every already-verified customer. Safe to re-run
 * any time Tally's ledger master changes (e.g. a GSTIN gets corrected there)
 * -- always overwrites from the latest snapshot, never hand-edited locally.
 */
class BackfillCustomerTallyDetailsCommand extends Command
{
    protected $signature = 'hrx:backfill-customer-tally-details';
    protected $description = 'Copy GSTIN/PAN/contact/email/address/mailing name from tally_ledgers onto every already-verified customer';

    public function handle(): int
    {
        $ledgersByNorm = DB::table('tally_ledgers')->whereNotNull('normalized_name')->get()
            ->keyBy('normalized_name');

        if ($ledgersByNorm->isEmpty()) {
            $this->warn('tally_ledgers is empty — run hrx:tally-push-ledgers first.');
            return Command::FAILURE;
        }

        $updated = 0;
        $noLedger = 0;

        Customer::where('tally_match_status', Customer::STATUS_VERIFIED)
            ->whereNotNull('tally_matched_name')
            ->chunkById(500, function ($customers) use ($ledgersByNorm, &$updated, &$noLedger) {
                foreach ($customers as $customer) {
                    $ledger = $ledgersByNorm->get($customer->tally_matched_name);
                    if (!$ledger) {
                        $noLedger++;
                        continue;
                    }
                    $customer->update([
                        'tally_mailing_name' => $ledger->mailing_name ?: null,
                        'tally_gstin' => $ledger->gstin ?: null,
                        'tally_pan' => $ledger->pan ?: null,
                        'tally_contact_person' => $ledger->contact_person ?: null,
                        'tally_contact_mobile' => $ledger->contact_mobile ?: null,
                        'tally_email' => $ledger->email ?: null,
                        'tally_address' => $ledger->address ?: null,
                    ]);
                    $updated++;
                }
            });

        $this->info("Updated {$updated} verified customer(s).");
        if ($noLedger > 0) {
            $this->warn("{$noLedger} verified customer(s) had no matching row in tally_ledgers (name may have changed in Tally since verification).");
        }

        return Command::SUCCESS;
    }
}
