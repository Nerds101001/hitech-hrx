<?php
/* Import "Vinoth google sheet.xlsx" into the SALES REPORT (is_locked = true)
   for Vinoth Anand Velusamy. Figures are already in Lakhs.
   Reps assigned by how each is already used across the book:
     Nidhi Sharma #255  -> ccare_id   (240 CCare / 0 New Biz)
     Jasbir Kaur  #476  -> new_biz_id (607 New Biz)
     Dipali Sharma #228 -> new_biz_id (197 New Biz)
   Merge via updateOrCreate — never deletes. Dry-run unless --live. */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\Mail::fake();
use App\Models\User; use App\Models\SalesPipeline; use App\Models\SalesPipelineMonth;
use Illuminate\Support\Facades\DB;

$LIVE = in_array('--live', $argv, true);
$data = json_decode(file_get_contents(__DIR__.'/zz_vinoth.json'), true);
if (!$data) { fwrite(STDERR, "ABORT: data missing\n"); exit(1); }
$admin = User::where('email','ai@rustx.com')->first(); if ($admin) auth()->login($admin);

$sp = User::where('first_name','like','%Vinoth%')->orWhere('last_name','like','%Vinoth%')
        ->orderByRaw('CASE WHEN status = "active" THEN 0 ELSE 1 END')->first();
if (!$sp) { fwrite(STDERR, "ABORT: Vinoth not found\n"); exit(1); }
echo "Salesperson: #{$sp->id} ".trim($sp->first_name.' '.$sp->last_name)."\n";

$REP = ['nidhi'=>['ccare',255], 'jasbir'=>['newbiz',476], 'dipali'=>['newbiz',228]];
$tenantId = SalesPipeline::withoutGlobalScopes()->where('salesperson_id',$sp->id)->value('tenant_id');
$upd=0; $new=0; $cells=0; $unknown=[];

DB::transaction(function() use ($data,$sp,$tenantId,$REP,$LIVE,&$upd,&$new,&$cells,&$unknown){
 foreach ($data as $r) {
    $ccare = null; $newbiz = null;
    if (!empty($r['rep'])) {
        $key = strtolower(trim($r['rep']));
        $hit = null;
        foreach ($REP as $k=>$v) if (str_contains($key,$k)) { $hit=$v; break; }
        if ($hit) { $hit[0]==='ccare' ? $ccare=$hit[1] : $newbiz=$hit[1]; }
        else $unknown[$r['rep']] = true;
    }
    $fields = array_filter([
        'type'                     => $r['type'] ?: 'Customer',
        'product'                  => $r['brand'] ?: null,
        'address'                  => $r['city'] ?: null,
        'total_business_potential' => $r['potential'],
        'ccare_id'                 => $ccare,
        'new_biz_id'               => $newbiz,
    ], fn($v)=>$v !== null && $v !== '');
    $fields['tenant_id'] = $tenantId;

    $exists = SalesPipeline::withoutGlobalScopes()->where('party_name',$r['name'])
        ->where('salesperson_id',$sp->id)->where('is_locked',true)->exists();
    $exists ? $upd++ : $new++;
    if (!$LIVE) continue;

    $p = SalesPipeline::withoutGlobalScopes()->updateOrCreate(
        ['party_name'=>$r['name'],'salesperson_id'=>$sp->id,'is_locked'=>true], $fields);

    $put = function($mk, $vals) use ($p, &$cells) {
        $vals = array_filter($vals, fn($v)=>$v !== null);
        if (!$vals) return;
        $vals['tenant_id'] = $p->tenant_id;
        SalesPipelineMonth::withoutGlobalScopes()->updateOrCreate(
            ['sales_pipeline_id'=>$p->id,'month_year'=>$mk], $vals);
        $cells++;
    };
    $put('2026-07', ['forecast_amount'=>$r['jul_fcst']]);
    $put('2026-06', ['sale_amount'=>$r['jun_sale'], 'pending_amount'=>$r['jun_pend']]);
    $put('2026-05', ['sale_amount'=>$r['may_sale']]);
    $put('2026-04', ['sale_amount'=>$r['apr_sale']]);
 }
});
echo ($LIVE?"*** LIVE — WRITTEN ***":"*** DRY RUN — nothing written ***")."\n";
echo "Sales Report: update {$upd} existing + create {$new} new".($LIVE?"  (+{$cells} month cells)":"")."\n";
echo "is_locked = true (Sales Report only — Sales Pipeline untouched)\n";
if ($unknown) echo "UNRESOLVED reps (left blank): ".implode(', ',array_keys($unknown))."\n";
