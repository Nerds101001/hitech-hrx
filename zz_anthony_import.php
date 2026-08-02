<?php
/* Import ANTHONY SALE.xlsx into the SALES REPORT (is_locked = true) only.
   Figures already in Lakhs. No reps/city/brand in the file, so those are left
   alone. Merge via updateOrCreate — never deletes. Dry-run unless --live. */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\Mail::fake();
use App\Models\User; use App\Models\SalesPipeline; use App\Models\SalesPipelineMonth;
use Illuminate\Support\Facades\DB;

$LIVE = in_array('--live', $argv, true);
$data = json_decode(file_get_contents(__DIR__.'/zz_anthony.json'), true);
if (!$data) { fwrite(STDERR,"ABORT: data missing\n"); exit(1); }
$admin = User::where('email','ai@rustx.com')->first(); if ($admin) auth()->login($admin);

$cands = User::where('first_name','like','%Anthony%')->orWhere('last_name','like','%Anthony%')
          ->orderByRaw('CASE WHEN status = "active" THEN 0 ELSE 1 END')->get();
echo "Anthony candidates:\n";
foreach ($cands as $c) {
  $rep = SalesPipeline::withoutGlobalScopes()->where('salesperson_id',$c->id)->where('is_locked',true)->count();
  $pip = SalesPipeline::withoutGlobalScopes()->where('salesperson_id',$c->id)->where('is_locked',false)->count();
  echo "  #{$c->id} ".trim($c->first_name.' '.$c->last_name)." | ".optional($c->department)->name." | {$c->status->value} | report={$rep} pipeline={$pip}\n";
}
$sp = $cands->first();
if (!$sp) { fwrite(STDERR,"ABORT: Anthony not found\n"); exit(1); }
echo "\nUsing: #{$sp->id} ".trim($sp->first_name.' '.$sp->last_name)."\n";

$tenantId = SalesPipeline::withoutGlobalScopes()->where('salesperson_id',$sp->id)->value('tenant_id');
$upd=0; $new=0; $cells=0;
DB::transaction(function() use ($data,$sp,$tenantId,$LIVE,&$upd,&$new,&$cells){
 foreach ($data as $r) {
   $fields = array_filter([
       'type'=>$r['type'] ?: 'Customer', 'product'=>$r['brand'] ?: null, 'address'=>$r['city'] ?: null,
       'total_business_potential'=>$r['potential'],
   ], fn($v)=>$v!==null && $v!=='');
   $fields['tenant_id']=$tenantId;
   $ex = SalesPipeline::withoutGlobalScopes()->where('party_name',$r['name'])
        ->where('salesperson_id',$sp->id)->where('is_locked',true)->exists();
   $ex ? $upd++ : $new++;
   if (!$LIVE) continue;
   $p = SalesPipeline::withoutGlobalScopes()->updateOrCreate(
       ['party_name'=>$r['name'],'salesperson_id'=>$sp->id,'is_locked'=>true], $fields);
   $put = function($mk,$vals) use ($p,&$cells) {
       $vals = array_filter($vals, fn($v)=>$v!==null);
       if (!$vals) return;
       $vals['tenant_id']=$p->tenant_id;
       SalesPipelineMonth::withoutGlobalScopes()->updateOrCreate(['sales_pipeline_id'=>$p->id,'month_year'=>$mk],$vals);
       $cells++;
   };
   $put('2026-07', ['sale_amount'=>$r['jul_sale'], 'forecast_amount'=>$r['jul_fcst']]);
   $put('2026-06', ['sale_amount'=>$r['jun_sale']]);
   $put('2026-05', ['sale_amount'=>$r['may_sale']]);
   $put('2026-04', ['sale_amount'=>$r['apr_sale']]);
 }
});
echo ($LIVE?"*** LIVE — WRITTEN ***":"*** DRY RUN — nothing written ***")."\n";
echo "Sales Report: update {$upd} + create {$new}".($LIVE?"  (+{$cells} month cells)":"")."\n";
echo "is_locked = true (Sales Report only — Sales Pipeline untouched)\n";
