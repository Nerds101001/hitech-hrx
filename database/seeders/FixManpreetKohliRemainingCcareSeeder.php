<?php

namespace Database\Seeders;

use App\Models\SalesPipeline;
use Illuminate\Database\Seeder;

/**
 * Final 4 accounts still tagged New Biz for Manpreet Kaur Kohli (id 251),
 * all Rust-X. Same move as the Tuffpaulin batch -- CCare, so her whole
 * book shows under one filter regardless of brand.
 */
class FixManpreetKohliRemainingCcareSeeder extends Seeder
{
    public function run(): void
    {
        $count = SalesPipeline::withoutGlobalScopes()
            ->where('new_biz_id', 251)
            ->where('is_locked', true)
            ->update(['ccare_id' => 251, 'new_biz_id' => null]);

        $this->command->info("FixManpreetKohliRemainingCcareSeeder: converted {$count} remaining rows to CCare.");
    }
}
