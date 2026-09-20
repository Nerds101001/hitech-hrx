<?php

namespace App\Console\Commands;

use App\Models\CrmClientMapping;
use App\Models\CrmClientMappingTeamMember;
use Illuminate\Console\Command;

/**
 * One-time (re-runnable, idempotent) backfill: copies each CrmClientMapping's
 * 5 existing single-person role columns into crm_client_mapping_team, so the
 * new multi-person team card starts out showing exactly what the old
 * single-slot card showed — nothing is lost, and nothing on
 * crm_client_mappings itself is touched.
 */
class BackfillCrmClientTeamCommand extends Command
{
    protected $signature = 'hrx:backfill-crm-team';
    protected $description = 'Copy existing salesperson/ccare/new_biz/billing/finance assignments into crm_client_mapping_team';

    public function handle(): int
    {
        $created = 0;
        CrmClientMapping::query()->chunkById(500, function ($mappings) use (&$created) {
            foreach ($mappings as $mapping) {
                foreach (CrmClientMappingTeamMember::PRIMARY_COLUMN as $role => $column) {
                    $userId = $mapping->{$column};
                    if (!$userId) {
                        continue;
                    }
                    $member = CrmClientMappingTeamMember::firstOrCreate([
                        'crm_client_mapping_id' => $mapping->id,
                        'user_id' => $userId,
                        'role' => $role,
                    ], ['tenant_id' => $mapping->tenant_id]);
                    if ($member->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        });

        $this->info("Backfilled {$created} team assignment(s).");
        return Command::SUCCESS;
    }
}
