<?php

namespace Database\Seeders;

use App\Models\SalesPipeline;
use Illuminate\Database\Seeder;

/**
 * Tuffpaulin accounts under Manpreet Kaur Kohli (id 251, CCare or New Biz)
 * have no real salesperson assigned -- house/direct accounts. salesperson_id
 * was populated anyway (various people); clear it to match reality.
 */
class FixManpreetKohliTuffpaulinSalespersonSeeder extends Seeder
{
    public function run(): void
    {
        $count = SalesPipeline::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('ccare_id', 251)->orWhere('new_biz_id', 251);
            })
            ->where('product', 'like', '%uff%')
            ->where('is_locked', true)
            ->whereNotNull('salesperson_id')
            ->update(['salesperson_id' => null]);

        $this->command->info("FixManpreetKohliTuffpaulinSalespersonSeeder: cleared salesperson_id on {$count} rows.");
    }
}
