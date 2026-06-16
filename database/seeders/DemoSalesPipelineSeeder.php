<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;

class DemoSalesPipelineSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('en_IN');

        $salesUser = User::where('email', 'sales@demo.com')->first();
        $ccareUser = User::where('email', 'ccare@demo.com')->first();

        if (!$salesUser || !$ccareUser) {
            $this->command->error("sales@demo.com or ccare@demo.com not found. Please run DemoAccountsSeeder first.");
            return;
        }

        $stages = ['Lead', 'Contacted', 'Qualified', 'Proposal Sent', 'Negotiation', 'Closed Won', 'Closed Lost'];
        $products = ['Rust-X VCI Paper', 'Zorbit Desiccants', 'VCI Pouches', 'Rust Preventive Oil', 'Shrink Film', 'Export Packaging', 'Aluminium Foil Bags'];

        $months = ['2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08'];

        $tenantId = $salesUser->tenant_id ?? 1;

        for ($i = 0; $i < 20; $i++) {
            $pipeline = SalesPipeline::create([
                'party_name' => $faker->company . ' ' . $faker->companySuffix,
                'salesperson_id' => $salesUser->id,
                'ccare_id' => $ccareUser->id,
                'total_business_potential' => $faker->numberBetween(500000, 5000000),
                'yoy_22_23' => $faker->numberBetween(100000, 1000000),
                'yoy_23_24' => $faker->numberBetween(200000, 2000000),
                'yoy_24_25' => $faker->numberBetween(300000, 3000000),
                'yoy_25_26' => 0, // Current year
                'status_stage' => $faker->randomElement($stages),
                'address' => $faker->city . ', ' . $faker->state,
                'product' => $faker->randomElement($products),
                'tenant_id' => $tenantId,
            ]);

            foreach ($months as $month) {
                $forecast = $faker->numberBetween(50000, 500000);
                $sale = $faker->boolean(70) ? $faker->numberBetween(10000, $forecast) : 0;
                $pending = $forecast - $sale;

                SalesPipelineMonth::create([
                    'sales_pipeline_id' => $pipeline->id,
                    'month_year' => $month,
                    'forecast_amount' => $forecast,
                    'sale_amount' => $sale,
                    'pending_amount' => $pending,
                    'remarks' => $faker->boolean(50) ? 'Follow up needed' : 'On track',
                    'tenant_id' => $tenantId,
                ]);
            }
        }

        $this->command->info("✅ 20 Demo Sales Pipelines and Monthly Forecasts seeded successfully.");
    }
}
