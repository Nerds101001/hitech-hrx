<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$f = fopen(__DIR__ . '/draft_crm_mapping_active_only.csv', 'r');
if (!$f) { die("Could not open CSV file\n"); }
fgetcsv($f); // skip header
$updated = 0;
while (($row = fgetcsv($f)) !== false) {
    $crm_id = trim($row[0] ?? '');
    $hrx_id = trim($row[3] ?? '');
    if ($hrx_id && is_numeric($hrx_id)) {
        \App\Models\User::where('id', $hrx_id)->update(['crm_id' => $crm_id]);
        $updated++;
    }
}
fclose($f);
echo 'Updated ' . $updated . " records.\n";
