<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\SetuCustomer;
use App\Support\NameMatcher;
use Illuminate\Console\Command;

/**
 * Marks each Customer 'suggested' when its normalized_name has an exact
 * match in the setu_customers cache, or 'similar' when the closest it has is
 * a fuzzy (>=80%) candidate — never 'verified', that only happens when a
 * human confirms it (CustomerSetuMappingController::mapSetu()). Re-running
 * never touches a customer a human has already verified, and never demotes
 * an existing 'suggested'/'similar' back down — a customer can only move
 * none -> similar -> suggested (exact beats fuzzy, found any time later),
 * never backwards, so nobody's mid-review status flips under them.
 */
class MatchCustomersToSetuCommand extends Command
{
    protected $signature = 'hrx:match-customers-setu';
    protected $description = 'Flag customers against the Setu cache: exact name match = "suggested", fuzzy-only = "similar"';

    /** Same floor the per-customer nearest-matches popup uses. */
    private const FUZZY_FLOOR = 80.0;

    public function handle(): int
    {
        $setuByNorm = SetuCustomer::pluck('name', 'normalized_name');
        if ($setuByNorm->isEmpty()) {
            $this->warn('setu_customers is empty — run hrx:sync-setu-customers first.');
            return Command::FAILURE;
        }

        // Pass 1: exact normalized-name match -> 'suggested'. Runs over both
        // 'none' and 'similar' so a customer already flagged similar still
        // gets promoted the moment an exact spelling shows up in Setu.
        $suggested = 0;
        Customer::whereIn('setu_match_status', [Customer::STATUS_NONE, Customer::STATUS_SIMILAR])
            ->chunkById(500, function ($customers) use ($setuByNorm, &$suggested) {
                foreach ($customers as $customer) {
                    $match = $setuByNorm->get($customer->normalized_name);
                    if ($match !== null) {
                        $customer->update([
                            'setu_match_status' => Customer::STATUS_SUGGESTED,
                            'setu_matched_name' => $match,
                        ]);
                        $suggested++;
                    }
                }
            });
        $this->info("Newly suggested (exact match): {$suggested}");

        // Pass 2: whatever's still 'none' gets one fuzzy pass against the
        // whole Setu pool. This is an O(remaining customers x Setu pool)
        // similar_text scan -- fine for a console command with no request
        // timeout, but not something to run inline on a page load.
        $pool = $setuByNorm->map(fn($name) => ['label' => $name])->values()->all();
        $similar = 0;
        $remaining = Customer::where('setu_match_status', Customer::STATUS_NONE)->count();
        $bar = $this->output->createProgressBar($remaining);
        $bar->start();

        Customer::where('setu_match_status', Customer::STATUS_NONE)
            ->chunkById(200, function ($customers) use ($pool, &$similar, $bar) {
                foreach ($customers as $customer) {
                    $matches = NameMatcher::bestMatches($customer->canonical_name, $pool, self::FUZZY_FLOOR, 1);
                    if ($matches) {
                        $customer->update(['setu_match_status' => Customer::STATUS_SIMILAR]);
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
