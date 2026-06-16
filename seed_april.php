<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = \App\Models\User::whereIn('id', [504, 507])->get();

foreach ($users as $user) {
    \App\Models\SalespersonTarget::updateOrCreate(
        ['salesperson_id' => $user->id, 'month_year' => '2026-04'],
        ['kingo_target' => 500000, 'bingo_target' => 1000000]
    );
    
    $pipeline = \App\Models\SalesPipeline::where('salesperson_id', $user->id)->first();
    if (!$pipeline) {
        $pipeline = \App\Models\SalesPipeline::create([
            'tenant_id' => $user->tenant_id,
            'salesperson_id' => $user->id,
            'party_name' => 'Seeded Demo Customer',
            'product' => 'Export Packaging',
        ]);
    }
    
    if ($pipeline) {
        \App\Models\SalesPipelineMonth::updateOrCreate(
            ['sales_pipeline_id' => $pipeline->id, 'month_year' => '2026-04'],
            ['sale_amount' => 1200000, 'forecast_amount' => 1200000, 'pending_amount' => 0]
        );
    }
    echo "Seeded for user ID: {$user->id}\n";
}

echo "Seeding complete.\n";
