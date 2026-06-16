<?php 
$ccare = App\Models\User::where('email', 'ccare@demo.com')->first(); 
$sales = App\Models\User::whereHas('department', function($q) { $q->where('name', 'like', '%Sales%'); })->first(); 

if ($ccare && $sales) {
    App\Models\SalesPipeline::updateOrCreate(
        ['party_name' => 'Dummy Party', 'salesperson_id' => $sales->id],
        ['ccare_id' => $ccare->id, 'total_business_potential' => 0]
    );
    echo "Created mapping between CCARE (" . $ccare->email . ") and Sales (" . $sales->name . ")\n";
} else {
    echo "Users not found!\n";
}
