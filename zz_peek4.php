<?php
require __DIR__.'/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$f = 'C:/Users/DELL/Desktop/Vinoth google sheet.xlsx';
$rd = IOFactory::createReader(IOFactory::identify($f)); $rd->setReadDataOnly(true);
$rows = $rd->load($f)->getSheetByName('Sheet1')->toArray(null, true, false, false);
echo "FULL HEADERS:\n";
foreach ($rows[0] as $i => $h) if ($h !== null && trim((string)$h) !== '') echo "  [$i] ".trim((string)$h)."\n";
echo "\nALL DATA ROWS:\n";
$out = [];
foreach (array_slice($rows, 1) as $r) {
    $name = trim((string)($r[1] ?? ''));
    if ($name === '' || strcasecmp($name, 'TOTAL') === 0) { echo "  SKIP: '$name'\n"; continue; }
    $g = fn($i) => (($r[$i] ?? null) === null || trim((string)$r[$i]) === '') ? null : trim((string)$r[$i]);
    $n = fn($i) => (($r[$i] ?? null) === null || trim((string)$r[$i]) === '') ? null : (float)$r[$i];
    $out[] = ['name'=>$name,'potential'=>$n(2),'rep'=>$g(3),'city'=>$g(4),'brand'=>$g(5),'type'=>$g(6),
              'jul_fcst'=>$n(7),'jun_sale'=>$n(8),'jun_pend'=>$n(9),'may_sale'=>$n(10),'apr_sale'=>$n(11)];
    printf("  %-22s pot=%-5s rep=%-8s city=%-10s brand=%-8s type=%-10s Jfc=%-5s Jun=%-5s JunP=%-5s May=%-5s Apr=%s\n",
      mb_substr($name,0,22), $n(2)??'-', $g(3)??'-', $g(4)??'-', $g(5)??'-', $g(6)??'-', $n(7)??'-', $n(8)??'-', $n(9)??'-', $n(10)??'-', $n(11)??'-');
}
echo "\ncustomers: ".count($out)."\n";
file_put_contents(__DIR__.'/zz_vinoth.json', json_encode($out));
