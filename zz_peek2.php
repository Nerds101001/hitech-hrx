<?php
require __DIR__.'/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$f = 'C:/Users/DELL/Desktop/ISHAN SIR SALE.xlsx';
$rd = IOFactory::createReader(IOFactory::identify($f)); $rd->setReadDataOnly(true);
$rows = $rd->load($f)->getSheetByName('Sale Report')->toArray(null, true, false, false);
$data = []; $tot = [0,0,0,0];
foreach (array_slice($rows, 1) as $r) {
    $name = trim((string)($r[0] ?? ''));
    if ($name === '' || stripos($name, 'total') === 0) { echo "SKIP row: ".mb_substr($name,0,40)."\n"; continue; }
    $m = [];
    foreach ([1,2,3,4] as $k => $c) {
        $v = $r[$c] ?? null;
        $v = ($v === null || $v === '') ? null : (float)$v;
        $m[$k] = $v; if ($v) $tot[$k] += $v;
    }
    $data[] = ['name' => $name, 'jul' => $m[0], 'jun' => $m[1], 'may' => $m[2], 'apr' => $m[3]];
}
echo "\nrows parsed: ".count($data)."   distinct customers: ".count(array_unique(array_column($data,'name')))."\n";
echo "TOTALS (raw ₹): Jul=".number_format($tot[0])."  Jun=".number_format($tot[1])."  May=".number_format($tot[2])."  Apr=".number_format($tot[3])."\n";
echo "TOTALS (₹ lakh): Jul=".round($tot[0]/1e5,2)."  Jun=".round($tot[1]/1e5,2)."  May=".round($tot[2]/1e5,2)."  Apr=".round($tot[3]/1e5,2)."\n\n";
foreach ($data as $i => $d) printf("%2d. %-46s J=%-10s Ju=%-10s M=%-10s A=%s\n", $i+1, mb_substr($d['name'],0,46), $d['jul']??'-', $d['jun']??'-', $d['may']??'-', $d['apr']??'-');
file_put_contents(__DIR__.'/zz_ishan.json', json_encode($data));
