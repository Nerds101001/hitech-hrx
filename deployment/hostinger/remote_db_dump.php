<?php
/**
 * Run ON the production server, uploaded directly into the Laravel app root
 * (same level as this app's own vendor/ and bootstrap/ — NOT deployment/hostinger/),
 * executed via ssh, deleted after. Boots just enough of Laravel to read the
 * already-configured DB credentials from this server's own .env, then shells out
 * to mysqldump with escapeshellarg()'d values — avoids ever needing to type the
 * DB password separately from SSH. Writes a gzipped dump into storage/app/ and
 * prints "OK:<path>" on success.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cfg = config('database.connections.mysql');
$host = $cfg['host'];
$port = $cfg['port'] ?? 3306;
$user = $cfg['username'];
$pass = $cfg['password'];
$name = $cfg['database'];

$stamp = date('Ymd-His');
$outPath = __DIR__ . "/storage/app/hrx_prod_dump_{$stamp}.sql.gz";

$cmd = sprintf(
    'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --quick --routines --triggers --hex-blob %s | gzip > %s',
    escapeshellarg($host),
    escapeshellarg((string) $port),
    escapeshellarg($user),
    escapeshellarg($pass),
    escapeshellarg($name),
    escapeshellarg($outPath)
);

exec($cmd . ' 2>&1', $output, $exitCode);

if ($exitCode !== 0 || !file_exists($outPath)) {
    echo "FAIL: exit={$exitCode}\n" . implode("\n", $output) . "\n";
    exit(1);
}

echo 'OK:' . $outPath . ' (' . round(filesize($outPath) / 1024 / 1024, 2) . " MB)\n";
