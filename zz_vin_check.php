<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User; use App\Models\SalesPipeline; use Illuminate\Support\Facades\DB;
$admin = User::where('email','ai@rustx.com')->first(); if ($admin) auth()->login($admin);

$vin = User::where('first_name','like','%Vinoth%')->orWhere('last_name','like','%Vinoth%')
        ->orderByRaw('CASE WHEN status = "active" THEN 0 ELSE 1 END')->first();
echo $vin ? "Vinoth: #{$vin->id} ".trim($vin->first_name.' '.$vin->last_name)." | ".optional($vin->department)->name." | ".$vin->status->value."\n"
          : "Vinoth NOT FOUND\n";
if ($vin) {
  echo "  existing Sales Report rows: ".SalesPipeline::withoutGlobalScopes()->where('salesperson_id',$vin->id)->where('is_locked',true)->count()."\n";
  echo "  existing Sales Pipeline rows: ".SalesPipeline::withoutGlobalScopes()->where('salesperson_id',$vin->id)->where('is_locked',false)->count()."\n";
}
echo "\n=== reps in the file ===\n";
foreach (['Nidhi','Jasbir','Dipali'] as $r) {
  $u = User::where(fn($q)=>$q->where('first_name','like',"%$r%")->orWhere('last_name','like',"%$r%"))
        ->orderByRaw('CASE WHEN status = "active" THEN 0 ELSE 1 END')->get(['id','first_name','last_name','department_id','status']);
  foreach ($u as $x) {
    $dep = DB::table('departments')->where('id',$x->department_id)->value('name');
    // how is this person already used across the whole book?
    $asCc = SalesPipeline::withoutGlobalScopes()->where('ccare_id',$x->id)->count();
    $asNb = SalesPipeline::withoutGlobalScopes()->where('new_biz_id',$x->id)->count();
    echo "  $r -> #{$x->id} ".trim($x->first_name.' '.$x->last_name)." | dept=".($dep??'-')." | ".$x->status->value." | used as CCare:{$asCc}  NewBiz:{$asNb}\n";
  }
}
