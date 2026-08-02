<?php
require __DIR__.'/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$f = 'C:/Users/DELL/Desktop/ISHAN SIR SALE.xlsx';
$rd = IOFactory::createReader(IOFactory::identify($f));
$rd->setReadDataOnly(true);
$wb = $rd->load($f);
echo "sheets: ".implode(' | ', $wb->getSheetNames())."\n\n";
foreach ($wb->getSheetNames() as $name) {
    $rows = $wb->getSheetByName($name)->toArray(null, true, false, false);
    echo "=== $name === rows=".count($rows)."\n";
    foreach (array_slice($rows, 0, 8) as $i => $r) {
        $cells = array_map(fn($c) => is_null($c) ? '·' : mb_substr(trim((string)$c), 0, 16), array_slice($r, 0, 15));
        echo "  [$i] ".implode(' | ', $cells)."\n";
    }
    echo "\n";
}
