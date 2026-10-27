<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\TallyInvoice;
use App\Support\NameMatcher;
use Illuminate\Console\Command;

/**
 * Marks each Customer 'suggested' when its normalized_name has an exact
 * match among Tally parties with recent invoiced sales, or 'similar' when
 * the closest it has is a fuzzy (>=80%) candidate — never 'verified', that
 * only happens when a human confirms it
 * (CustomerTallyMappingController::mapTally()). Mirrors
 * MatchCustomersToSetuCommand exactly, but against the app's own local
 * tally_invoices instead of the external Setu cache. Re-running never
 * touches a customer a human has already verified, and never demotes an
 * existing 'suggested'/'similar' back down.
 */
class MatchCustomersToTallyCommand extends Command
{
    protected $signature = 'hrx:match-customers-tally';
    protected $description = 'Flag customers against local Tally sales: exact party-name match = "suggested", fuzzy-only = "similar"';

    private const FUZZY_FLOOR = 80.0;
    private const RECENT_MONTHS = 6;

    public function handle(): int
    {
        $since = now()->subMonths(self::RECENT_MONTHS - 1)->startOfMonth();

        $tallyByNorm = TallyInvoice::customerSales()
            ->whereNotNull('normalized_name')
            ->where('invoice_date', '>=', $since)
            ->selectRaw('normalized_name, party_name')
            ->distinct()
            ->get()
            ->groupBy('normalized_name')
            ->map(fn ($rows) => $rows->sortByDesc(fn ($r) => mb_strlen($r->party_name ?? ''))->first()->party_name);

        if ($tallyByNorm->isEmpty()) {
            $this->warn('No recent Tally invoices found — nothing to match against.');
            return Command::FAILURE;
        }

        $suggested = 0;
        Customer::whereIn('tally_match_status', [Customer::STATUS_NONE, Customer::STATUS_SIMILAR])
            ->chunkById(500, function ($customers) use ($tallyByNorm, &$suggested) {
                foreach ($customers as $customer) {
                    $match = $tallyByNorm->get($customer->normalized_name);
                    if ($match !== null) {
                        $customer->update([
                            'tally_match_status' => Customer::STATUS_SUGGESTED,
                            'tally_matched_name' => $customer->normalized_name,
                        ]);
                        $suggested++;
                    }
                }
            });
        $this->info("Newly suggested (exact match): {$suggested}");

        $pool = $tallyByNorm->map(fn ($name, $norm) => ['label' => $name, 'normalized_name' => $norm])->values()->all();
        $similar = 0;
        $remaining = Customer::where('tally_match_status', Customer::STATUS_NONE)->count();
        $bar = $this->output->createProgressBar($remaining);
        $bar->start();

        Customer::where('tally_match_status', Customer::STATUS_NONE)
            ->chunkById(200, function ($customers) use ($pool, &$similar, $bar) {
                foreach ($customers as $customer) {
                    $matches = NameMatcher::bestMatches($customer->canonical_name, $pool, self::FUZZY_FLOOR, 1);
                    if ($matches) {
                        $customer->update(['tally_match_status' => Customer::STATUS_SIMILAR]);
                        $similar++;
                    }
                    $bar->advance();
                }
            });
        $bar->finish();
        $this->newLine();
        $this->info("Newly flagged similar (fuzzy match, needs review): {$similar}");

        return Command::SUCCESS;
    }
}
