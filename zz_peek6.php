<?php
require __DIR__.'/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$f = 'C:/Users/DELL/Desktop/ANTHONY SALE.xlsx';
$rd = IOFactory::createReader(IOFactory::identify($f)); $rd->setReadDataOnly(true);
$rows = $rd->load($f)->getSheetByName('Sheet1')->toArray(null, true, false, false);
$out = [];
foreach (array_slice($rows, 1) as $r) {
    $name = trim((string)($r[1] ?? ''));
    if ($name === '' || strcasecmp($name,'TOTAL') === 0) { echo "SKIP: '$name'\n"; continue; }
    $g = fn($i) => (($r[$i] ?? null) === null || trim((string)$r[$i]) === '') ? null : trim((string)$r[$i]);
    $n = fn($i) => (($r[$i] ?? null) === null || trim((string)$r[$i]) === '') ? null : (float)$r[$i];
    $out[] = ['name'=>$name,'potential'=>$n(2),'rep'=>$g(3),'city'=>$g(4),'brand'=>$g(5),'type'=>$g(6),
              'jul_fcst'=>$n(7),'jul_sale'=>$n(8),'jun_sale'=>$n(9),'may_sale'=>$n(10),'apr_sale'=>$n(11)];
}
echo "\ncustomers: ".count($out)."   duplicate names: ";
$c = array_filter(array_count_values(array_column($out,'name')), fn($x)=>$x>1);
echo ($c ? json_encode($c) : "none")."\n\n";
foreach ($out as $i=>$d) printf("%2d. %-30s pot=%-5s rep=%-8s city=%-8s brand=%-8s type=%-8s Jfc=%-5s Jul=%-5s Jun=%-5s May=%-5s Apr=%s\n",
  $i+1, mb_substr($d['name'],0,30), $d['potential']??'-', $d['rep']??'-', $d['city']??'-', $d['brand']??'-', $d['type']??'-',
  $d['jul_fcst']??'-', $d['jul_sale']??'-', $d['jun_sale']??'-', $d['may_sale']??'-', $d['apr_sale']??'-');
$t=['jul_sale'=>0,'jun_sale'=>0,'may_sale'=>0,'apr_sale'=>0];
foreach ($out as $d) foreach ($t as $k=>$v) $t[$k] += $d[$k] ?? 0;
echo "\nTOTALS: Jul=".round($t['jul_sale'],2)."  Jun=".round($t['jun_sale'],2)."  May=".round($t['may_sale'],2)."  Apr=".round($t['apr_sale'],2)."\n";
file_put_contents(__DIR__.'/zz_anthony.json', json_encode($out));
