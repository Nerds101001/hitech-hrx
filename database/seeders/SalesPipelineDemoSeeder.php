<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use Carbon\Carbon;

class SalesPipelineDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $salespersonId = 490; // Test Salesperson
        $newBizId = 454; // Sonam Bhardwaj
        $ccareId = 489; // Test CCAgent

        // Delete previous demo pipelines
        SalesPipeline::whereIn('party_name', ['Demo Pipeline (Ready to Convert)', 'Demo Pipeline (Already Converted)'])->delete();

        // 1. Pipeline that is 1 month away from converting
        $pipe1 = SalesPipeline::create([
            'party_name' => 'Demo Pipeline (Ready to Convert)',
            'salesperson_id' => $salespersonId,
            'new_biz_id' => $newBizId,
            'ccare_id' => $ccareId,
            'status_stage' => 'Order',
            'type' => 'New Biz',
            'total_business_potential' => 100000,
            'address' => 'Demo Address 1',
            'product' => 'Demo Product A',
            'is_locked' => true // so it shows up in Sales Report immediately
        ]);

        // Add 4 months of sales
        $currentDate = Carbon::now()->subMonths(4);
        for ($i = 0; $i < 4; $i++) {
            SalesPipelineMonth::create([
                'sales_pipeline_id' => $pipe1->id,
                'month_year' => $currentDate->format('Y-m'),
                'sale_amount' => 5000,
                'forecast_amount' => 5000,
            ]);
            $currentDate->addMonth();
        }

        // 2. Pipeline that has already converted
        $pipe2 = SalesPipeline::create([
            'party_name' => 'Demo Pipeline (Already Converted)',
            'salesperson_id' => $salespersonId,
            'new_biz_id' => $newBizId,
            'ccare_id' => $ccareId,
            'status_stage' => 'Order',
            'type' => 'CCare',
            'converted_from_newbiz' => true,
            'total_business_potential' => 250000,
            'address' => 'Demo Address 2',
            'product' => 'Demo Product B',
            'is_locked' => true // so it shows up in Sales Report immediately
        ]);

        // Add 5 months of sales
        $currentDate = Carbon::now()->subMonths(5);
        for ($i = 0; $i < 5; $i++) {
            SalesPipelineMonth::create([
                'sales_pipeline_id' => $pipe2->id,
                'month_year' => $currentDate->format('Y-m'),
                'sale_amount' => 10000,
                'forecast_amount' => 10000,
            ]);
            $currentDate->addMonth();
        }
    }
}
