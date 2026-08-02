<?php
/* Import ISHAN SIR SALE.xlsx into the SALES REPORT (is_locked = true) for
   Ishan Sikka. No representative is set (ccare_id / new_biz_id stay null).
   Figures in the file are raw rupees; the app stores Lakhs, so /100000.
   Merge via updateOrCreate — never deletes. Dry-run unless --live. */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\Mail::fake();
use App\Models\User; use App\Models\SalesPipeline; use App\Models\SalesPipelineMonth;
use Illuminate\Support\Facades\DB;

$LIVE = in_array('--live', $argv, true);
$data = json_decode(file_get_contents(__DIR__.'/zz_ishan.json'), true);
if (!$data) { fwrite(STDERR, "ABORT: data file missing\n"); exit(1); }

$admin = User::where('email','ai@rustx.com')->first(); if ($admin) auth()->login($admin);

$sp = User::where(fn($q)=>$q->where('first_name','like','%Ishan%'))
          ->where(fn($q)=>$q->where('last_name','like','%Sikka%')->orWhere('first_name','like','%Sikka%'))
          ->orderByRaw('CASE WHEN status = "active" THEN 0 ELSE 1 END')->first();
if (!$sp) { fwrite(STDERR, "ABORT: Ishan Sikka not found\n"); exit(1); }
echo "Salesperson: #{$sp->id} ".trim($sp->first_name.' '.$sp->last_name)." (".optional($sp->department)->name.")\n";

$tenantId = SalesPipeline::withoutGlobalScopes()->where('salesperson_id',$sp->id)->value('tenant_id');
$months = ['jul'=>'2026-07','jun'=>'2026-06','may'=>'2026-05','apr'=>'2026-04'];
$upd=0; $new=0; $cells=0; $sum=[];

DB::transaction(function() use ($data,$sp,$tenantId,$months,$LIVE,&$upd,&$new,&$cells,&$sum){
  foreach ($data as $row) {
    $exists = SalesPipeline::withoutGlobalScopes()
        ->where('party_name',$row['name'])->where('salesperson_id',$sp->id)->where('is_locked',true)->first();
    $exists ? $upd++ : $new++;
    if (!$LIVE) {
      foreach ($months as $k=>$mk) if ($row[$k]) $sum[$mk] = ($sum[$mk] ?? 0) + $row[$k]/100000;
      continue;
    }
    $p = SalesPipeline::withoutGlobalScopes()->updateOrCreate(
        ['party_name'=>$row['name'], 'salesperson_id'=>$sp->id, 'is_locked'=>true],
        ['type'=>'Customer', 'product'=>'Dr.Bio', 'tenant_id'=>$tenantId]   // no representative
    );
    foreach ($months as $k=>$mk) {
      if ($row[$k] === null) continue;
      SalesPipelineMonth::withoutGlobalScopes()->updateOrCreate(
          ['sales_pipeline_id'=>$p->id, 'month_year'=>$mk],
          ['sale_amount'=>round($row[$k]/100000, 2), 'tenant_id'=>$p->tenant_id]);
      $cells++; $sum[$mk] = ($sum[$mk] ?? 0) + $row[$k]/100000;
    }
  }
});
echo ($LIVE ? "*** LIVE — WRITTEN ***" : "*** DRY RUN — nothing written ***")."\n";
echo "Sales Report: update {$upd} existing + create {$new} new".($LIVE ? "  (+{$cells} month cells)" : "")."\n";
echo "Representative: none (ccare_id / new_biz_id left null)   Brand: Dr.Bio   is_locked: true (Sales Report only)\n";
ksort($sum);
foreach ($sum as $mk=>$v) echo "   $mk : ".number_format($v,2)." L\n";
