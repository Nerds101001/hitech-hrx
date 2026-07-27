<?php

namespace Database\Seeders;

use App\Models\SalesPipeline;
use Illuminate\Database\Seeder;

/**
 * Tuffpaulin accounts handed to Manpreet Kaur Kohli were tagged New Biz
 * (new_biz_id=251) instead of CCare -- splitting her book across two
 * separate filters. Move them to CCare so one filter shows everything.
 */
class FixManpreetKohliTuffpaulinCcareSeeder extends Seeder
{
    public function run(): void
    {
        $count = SalesPipeline::withoutGlobalScopes()
            ->where('new_biz_id', 251)
            ->where(function ($q) {
                $q->whereNull('ccare_id')->orWhere('ccare_id', '!=', 251);
            })
            ->where('product', 'like', '%uff%')
            ->where('is_locked', true)
            ->update(['ccare_id' => 251, 'new_biz_id' => null]);

        $this->command->info("FixManpreetKohliTuffpaulinCcareSeeder: converted {$count} rows from New Biz to CCare.");
    }
}
