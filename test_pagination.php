<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$crm = new \App\Services\CrmClientService();

echo "=== Testing paginated getCustomers ===\n";
echo "Fetching page 1 with 10 records...\n";

$t1 = microtime(true);
$result = $crm->getCustomers(1, 10);
$elapsed = round(microtime(true) - $t1, 2);

echo "Time: {$elapsed}s\n";
echo "Total in CRM: " . ($result['total'] ?? 'unknown') . "\n";
echo "Received: " . count($result['data'] ?? []) . " records\n";

if (!empty($result['data'])) {
    $first = (array)$result['data'][0];
    echo "\nKeys: " . implode(', ', array_keys($first)) . "\n";
    echo "First: " . json_encode($first, JSON_PRETTY_PRINT) . "\n";
}

$total = (int)($result['total'] ?? 0);
if ($total > 0) {
    $pages = ceil($total / 50);
    echo "\n=== Estimation ===\n";
    echo "Total clients   : {$total}\n";
    echo "Pages @ 50/batch: {$pages}\n";
    echo "Est time (3s/pg): " . round($pages * 3 / 60, 1) . " minutes\n";
    echo "(For full detail: " . round($pages * 50 * 0.5 / 60, 1) . " mins with getCompanyById per client)\n";
}
