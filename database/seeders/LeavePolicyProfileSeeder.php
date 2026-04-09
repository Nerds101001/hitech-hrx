<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use App\Models\LeavePolicyProfile;
use App\Models\LeavePolicyProfileRule;
use Illuminate\Database\Seeder;

class LeavePolicyProfileSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Core Leave Types
        $types = [
            ['name' => 'Monthly Paid Leave', 'code' => 'PL', 'is_paid' => true, 'is_short_leave' => false, 'is_carry_forward' => true],
            ['name' => 'Compensatory Off', 'code' => 'COFF', 'is_paid' => true, 'is_short_leave' => false, 'is_carry_forward' => true],
            ['name' => 'Short Leave', 'code' => 'SL', 'is_paid' => true, 'is_short_leave' => true, 'is_carry_forward' => false],
            ['name' => 'Unpaid Leave', 'code' => 'LWP', 'is_paid' => false, 'is_short_leave' => false, 'is_carry_forward' => false],
        ];

        $typeIds = [];
        foreach ($types as $t) {
            $type = LeaveType::updateOrCreate(['code' => $t['code']], $t);
            $typeIds[$t['code']] = $type->id;
        }

        // --- PROFILE 1: Alternate Saturday Off ---
        $altSat = LeavePolicyProfile::updateOrCreate(['name' => 'Alternate Saturday Off'], [
            'description' => 'Alternate Saturday off, COFF on non-working days, 2hr Monthly Short Leave. No PL.',
            'saturday_off_config' => [2, 4], // 2nd and 4th Saturday
        ]);

        // COFF Rule
        LeavePolicyProfileRule::updateOrCreate(['profile_id' => $altSat->id, 'leave_type_id' => $typeIds['COFF']], [
            'is_applicable' => true,
            'is_carry_forward' => true,
        ]);
        // Short Leave Rule
        LeavePolicyProfileRule::updateOrCreate(['profile_id' => $altSat->id, 'leave_type_id' => $typeIds['SL']], [
            'is_applicable' => true,
            'short_leave_hours' => 2,
            'short_leave_per_month' => 1,
            'is_carry_forward' => false,
        ]);

        // --- PROFILE 2: All Saturday off ---
        $allSat = LeavePolicyProfile::updateOrCreate(['name' => 'All Saturday off'], [
            'description' => 'All Saturdays off. No Paid Leave. COFF must be redeemed in same month.',
            'saturday_off_config' => [1, 2, 3, 4, 5],
        ]);

        // COFF Rule (Redeem in same month)
        LeavePolicyProfileRule::updateOrCreate(['profile_id' => $allSat->id, 'leave_type_id' => $typeIds['COFF']], [
            'is_applicable' => true,
            'is_carry_forward' => false,
            'redeem_in_same_month' => true,
        ]);

        // --- PROFILE 3: Regular Leave ---
        $regular = LeavePolicyProfile::updateOrCreate(['name' => 'Regular Leave'], [
            'description' => 'No Saturday off. 1 PL/month. Carry forward allowed. Tenure-based consecutive rules.',
            'saturday_off_config' => [],
        ]);

        // PL Rule
        LeavePolicyProfileRule::updateOrCreate(['profile_id' => $regular->id, 'leave_type_id' => $typeIds['PL']], [
            'is_applicable' => true,
            'max_per_month' => 1,
            'is_carry_forward' => true,
            'max_consecutive_days' => 1, // Default max 1 if tenure < 2 years
            'tenure_tiers' => [
                ['months' => 5, 'consecutive' => 2],  // After 5 months, can take 2 consecutive
                ['months' => 24, 'consecutive' => 3], // After 2 years (24 months), can take 3 consecutive
            ]
        ]);

        $this->command->info('Leave Policy Profiles seeded successfully!');
    }
}
