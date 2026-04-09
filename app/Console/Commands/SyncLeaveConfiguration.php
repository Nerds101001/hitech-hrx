<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeaveType;
use App\Models\LeavePolicyProfile;
use App\Models\LeavePolicyProfileRule;
use Illuminate\Support\Facades\DB;

class SyncLeaveConfiguration extends Command
{
    protected $signature = 'hrx:sync-leave-config';
    protected $description = 'Syncs Leave Types, Profiles, and Rules from Local to Production';

    public function handle()
    {
        $this->info("Starting Leave Configuration Sync (Visibility Patch)...");

        $data = $this->getSyncData();

        DB::beginTransaction();
        try {
            // 1. Sync Leave Types
            $typeMap = [];
            foreach ($data['types'] as $t) {
                unset($t['id'], $t['created_at'], $t['updated_at']);
                
                // FORCE tenant_id to null for production visibility
                $t['tenant_id'] = null;
                
                $type = LeaveType::updateOrCreate(['code' => $t['code']], $t);
                $typeMap[$t['code']] = $type->id;
                $this->line("Synced Leave Type: {$t['name']} ({$t['code']})");
            }

            // 2. Sync Profiles
            $profileMap = [];
            foreach ($data['profiles'] as $p) {
                $oldId = $p['id'];
                unset($p['id'], $p['created_at'], $p['updated_at']);
                
                // FORCE tenant_id to null for production visibility
                $p['tenant_id'] = null;
                
                // For Profiles, strictly match by Name
                $profile = LeavePolicyProfile::updateOrCreate(['name' => $p['name']], $p);
                $profileMap[$oldId] = $profile->id;
                $this->line("Synced Profile: {$p['name']}");
            }

            // 3. Sync Rules
            // First, clear existing rules for the synced profiles
            $syncedProfileIds = array_values($profileMap);
            LeavePolicyProfileRule::whereIn('profile_id', $syncedProfileIds)->delete();

            foreach ($data['rules'] as $r) {
                $sourceTypeCode = collect($data['types'])->firstWhere('id', $r['leave_type_id'])['code'] ?? null;
                
                if (!$sourceTypeCode || !isset($typeMap[$sourceTypeCode])) {
                    $this->error("Skipping rule: Could not match Leave Type ID {$r['leave_type_id']}");
                    continue;
                }

                $newTypeId = $typeMap[$sourceTypeCode];
                $newProfileId = $profileMap[$r['profile_id']] ?? null;

                if (!$newProfileId) {
                    $this->error("Skipping rule: Could not match Profile ID {$r['profile_id']}");
                    continue;
                }

                unset($r['id'], $r['created_at'], $r['updated_at']);
                $r['profile_id'] = $newProfileId;
                $r['leave_type_id'] = $newTypeId;
                
                // FORCE tenant_id to null for production visibility
                $r['tenant_id'] = null;

                // Ensure boolean flags are correctly typed for DB insertion
                $r['is_applicable'] = (bool)$r['is_applicable'];
                $r['is_married_only'] = (bool)($r['is_married_only'] ?? 0);
                $r['is_carry_forward'] = (bool)$r['is_carry_forward'];
                $r['redeem_in_same_month'] = (bool)$r['redeem_in_same_month'];

                LeavePolicyProfileRule::create($r);
            }

            DB::commit();
            $this->info("---------------------------------------");
            $this->info("Leave Configuration Sync Successful!");
            $this->info("Types Synced: " . count($typeMap));
            $this->info("Profiles Synced: " . count($profileMap));
            $this->info("Rules Synced: " . count($data['rules']));
            $this->info("---------------------------------------");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Sync failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function getSyncData()
    {
        return [
            "types" => [
                ["id" => 1, "name" => "Casual Leave", "code" => "CL", "is_paid" => 1],
                ["id" => 2, "name" => "Sick Leave", "code" => "SL", "is_paid" => 1],
                ["id" => 3, "name" => "Paid Leave", "code" => "PL", "is_paid" => 1],
                ["id" => 4, "name" => "Unpaid Leave", "code" => "UL", "is_paid" => 0],
                ["id" => 5, "name" => "Maternity Leave", "code" => "ML", "is_paid" => 1],
                ["id" => 6, "name" => "Paternity Leave", "code" => "PL_PAT", "is_paid" => 1]
            ],
            "profiles" => [
                ["id" => 1, "name" => "Alternate Saturday Off", "description" => "Alternate Saturday off, COFF on non-working days, 2hr Monthly Short Leave. No PL.", "saturday_off_config" => [2, 4]],
                ["id" => 2, "name" => "All Saturday Off", "description" => "All Saturdays off. No Paid Leave. COFF must be redeemed in same month.", "saturday_off_config" => [1, 2, 3, 4, 5]],
                ["id" => 3, "name" => "Regular Leave", "description" => "No Saturday off. 1 PL/month. Carry forward allowed. Tenure-based consecutive rules.", "saturday_off_config" => []],
                ["id" => 4, "name" => "HR Al l Sat Off", "description" => null, "saturday_off_config" => []],
                ["id" => 5, "name" => "Sales", "description" => "Standard rules for sales team with target-based flexibility", "saturday_off_config" => []],
                ["id" => 6, "name" => "Sales (New)", "description" => "Updated Sales rules", "saturday_off_config" => []]
            ],
            "rules" => [
                ["profile_id" => 1, "leave_type_id" => 1, "is_applicable" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0],
                ["profile_id" => 1, "leave_type_id" => 2, "is_applicable" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0],
                ["profile_id" => 1, "leave_type_id" => 3, "is_applicable" => 1, "max_per_month" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0],
                ["profile_id" => 2, "leave_type_id" => 1, "is_applicable" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0],
                ["profile_id" => 2, "leave_type_id" => 2, "is_applicable" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0],
                ["profile_id" => 3, "leave_type_id" => 1, "is_applicable" => 1, "max_per_month" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0],
                ["profile_id" => 3, "leave_type_id" => 2, "is_applicable" => 1, "max_per_month" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0],
                ["profile_id" => 4, "leave_type_id" => 1, "is_applicable" => 1, "max_per_month" => 1, "max_consecutive_days" => 3, "is_carry_forward" => 1, "carry_forward_max_days" => 6, "redeem_in_same_month" => 0],
                ["profile_id" => 4, "leave_type_id" => 2, "is_applicable" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0],
                ["profile_id" => 4, "leave_type_id" => 3, "is_applicable" => 1, "max_per_month" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0],
                ["profile_id" => 4, "leave_type_id" => 5, "is_applicable" => 1, "is_married_only" => 1, "applicable_gender" => "female", "applicable_marital_status" => "married", "wfh_days_entitlement" => 45, "off_days_entitlement" => 45, "is_carry_forward" => 0, "redeem_in_same_month" => 0],
                ["profile_id" => 4, "leave_type_id" => 6, "is_applicable" => 1, "is_married_only" => 1, "applicable_gender" => "male", "applicable_marital_status" => "married", "wfh_days_entitlement" => 3, "off_days_entitlement" => 2, "is_carry_forward" => 0, "redeem_in_same_month" => 0],
                ["profile_id" => 6, "leave_type_id" => 1, "is_applicable" => 1, "max_per_month" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0],
                ["profile_id" => 6, "leave_type_id" => 2, "is_applicable" => 1, "max_consecutive_days" => 1, "short_leave_hours" => 2.00, "short_leave_per_month" => 1, "is_carry_forward" => 0, "redeem_in_same_month" => 0],
                ["profile_id" => 6, "leave_type_id" => 3, "is_applicable" => 1, "max_per_month" => 1, "max_consecutive_days" => 1, "is_carry_forward" => 1, "redeem_in_same_month" => 0]
            ]
        ];
    }
}
