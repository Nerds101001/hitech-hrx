<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\SalesPipeline;
use App\Support\NameMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time (re-runnable) backfill: groups every locked Sales Report row by
 * App\Support\NameMatcher::normalize(party_name), creates one Customer per
 * group, and links every row in that group to it via customer_id.
 *
 * Deliberately additive and idempotent: party_name is never read from again
 * once a row already has a customer_id (so re-running never re-groups
 * something a human has since split back apart), and matching on
 * normalized_name means re-running after new rows appear just links the new
 * ones without creating duplicate Customer rows.
 */
class BackfillCustomersCommand extends Command
{
    protected $signature = 'hrx:backfill-customers {--dry-run : Report what would happen without writing anything}';
    protected $description = 'Group Sales Report customers by normalized name into the canonical customers table';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = SalesPipeline::where('is_locked', true)
            ->whereNull('customer_id')
            ->get(['id', 'party_name', 'tenant_id']);

        if ($rows->isEmpty()) {
            $this->info('Nothing to backfill — every locked row already has a customer_id.');
            return Command::SUCCESS;
        }

        $this->info("Rows to link: {$rows->count()}");

        $groups = $rows->groupBy(fn ($r) => NameMatcher::normalize($r->party_name));
        $this->info("Distinct customers to create/reuse: {$groups->count()}");

        if ($dryRun) {
            $multi = $groups->filter(fn ($g) => $g->pluck('party_name')->unique()->count() > 1);
            $this->line("Of those, {$multi->count()} group(s) merge 2+ different spellings:");
            foreach ($multi->take(15) as $norm => $group) {
                $this->line('  ' . $group->pluck('party_name')->unique()->implode(' | '));
            }
            if ($multi->count() > 15) {
                $this->line('  ... and ' . ($multi->count() - 15) . ' more');
            }
            $this->warn('Dry run — nothing written.');
            return Command::SUCCESS;
        }

        $linked = 0;
        $created = 0;
        DB::transaction(function () use ($groups, &$linked, &$created) {
            foreach ($groups as $norm => $group) {
                if ($norm === '') {
                    continue; // a blank/unnormalizable name has nothing to group on
                }
                // Longest original spelling as the canonical display name —
                // usually the least-abbreviated one.
                $canonical = $group->pluck('party_name')->sortByDesc(fn ($n) => mb_strlen($n))->first();

                $customer = Customer::firstOrCreate(
                    ['normalized_name' => $norm],
                    ['canonical_name' => $canonical, 'tenant_id' => $group->first()->tenant_id]
                );
                if ($customer->wasRecentlyCreated) {
                    $created++;
                }

                SalesPipeline::whereIn('id', $group->pluck('id'))->update(['customer_id' => $customer->id]);
                $linked += $group->count();
            }
        });

        $this->info("Done. Created {$created} customer(s), linked {$linked} row(s).");
        return Command::SUCCESS;
    }
}
