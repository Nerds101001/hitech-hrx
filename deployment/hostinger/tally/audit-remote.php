<?php
/**
 * READ-ONLY audit of the live HRX server, run before anything Tally-related is deployed.
 *
 *   cd <app root> && php < audit-remote.php > report.json        (audit-live.sh does exactly this over SSH)
 *
 * What it does NOT do: write, delete, upload, migrate, clear a cache, or run any of the app's own code. It does not
 * boot Laravel; it reads files, decodes the "Protected by HRX Security" wrapper WITHOUT eval'ing it (the payload is a
 * gzipped, base64 string), and runs SELECT/SHOW queries through plain PDO using the credentials in .env.
 *
 * What it does not reveal: no .env value is printed except a short list of non-secret settings (environment, debug,
 * cache/session/queue/mail driver). Every secret is reported only as "set" or "not set". The database password is
 * used to connect and never leaves this process.
 *
 * Output is one JSON document: facts about the server, a fingerprint of every file that matters to the Tally
 * go-live, and the DECODED source of each of them, so the real live code can be compared line by line with git.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');
$root = getcwd();

/* ---------------------------------------------------------------- the files that matter */
$tallyOwned = [
    'app/Console/Commands/ImportTallyFilesCommand.php',
    'app/Console/Commands/TallyPushCommand.php',
    'app/Console/Commands/TallyPartyPushCommand.php',
    'app/Services/TallyGateway.php',
    'app/Models/TallyInvoice.php',
    'app/Models/TallyInvoiceLine.php',
    'app/Models/TallySaleLine.php',
    'database/migrations/2026_09_19_200000_create_tally_tables.php',
    'database/migrations/2026_09_19_210000_create_tally_sale_lines_table.php',
    'database/migrations/2026_09_30_120000_add_tally_sale_to_salesperson_targets.php',
    'app/Http/Controllers/Api/TallyPushController.php',
    'app/Http/Controllers/tenant/SalesAnalyticsController.php',
    'app/Http/Controllers/tenant/SalesSourcesController.php',
    'app/Http/Controllers/tenant/TallyContactUpdateController.php',
    'app/Http/Middleware/RestrictTallyAccess.php',
    'app/Http/Middleware/VerifyTallyPushSignature.php',
    'app/Services/CompanySales.php',
    'app/Services/CrmCustomerResolver.php',
    'app/Services/TallyAnalytics.php',
    'app/Support/TallyTotals.php',
    'app/Support/TallyFileReader.php',
    'app/Support/Fmt.php',
    'app/Support/NameMatcher.php',
    'app/Support/ProductGroup.php',
    'config/tally_push.php',
    'routes/tally_push.php',
    'database/migrations/2026_09_28_120000_create_tally_push_runs_table.php',
    'database/migrations/2026_09_28_130000_create_tally_deliveries_table.php',
    'database/migrations/2026_09_28_140000_create_tally_parties_table.php',
    'database/migrations/2026_09_29_100000_create_tally_receipts_table.php',
    'resources/views/tenant/sales-sources/layout.blade.php',
    'resources/views/tenant/sales-sources/compare.blade.php',
    'resources/views/tenant/sales-sources/company.blade.php',
    'resources/views/tenant/sales-sources/finance.blade.php',
    'resources/views/tenant/sales-sources/orders.blade.php',
    'resources/views/tenant/sales-sources/outstanding.blade.php',
    'resources/views/tenant/sales-sources/parties.blade.php',
    'resources/views/tenant/sales-sources/team.blade.php',
    'resources/views/tenant/sales-sources/unmapped.blade.php',
    'resources/views/tenant/sales-sources/analytics.blade.php',
    'resources/views/tenant/sales-sources/setu.blade.php',
    'resources/views/tenant/sales-sources/partials/company-sales.blade.php',
    'resources/views/tenant/sales-sources/partials/contact-update-modal.blade.php',
    'resources/views/tenant/sales-sources/partials/dispatch-modal.blade.php',
    'resources/views/tenant/sales-sources/partials/invoice-modal.blade.php',
];
$shared = [   // files other features also live in: only a few lines of each would change
    'routes/api.php',
    'routes/console.php',
    'routes/tenant.php',
    'bootstrap/app.php',
    'app/Providers/AppServiceProvider.php',
    'app/Support/Nav.php',
    'app/Models/TallyImport.php',
    'app/Models/TallyOutstanding.php',
    'app/Models/TallySaleOrder.php',
    'app/Models/SetuCustomer.php',
    'app/Models/Customer.php',
    'app/Http/Controllers/tenant/CustomerProfileController.php',
    'resources/menu/tenantVerticalMenu.json',
    'public/.htaccess',
];

/* ---------------------------------------------------------------- helpers */
/** Same wrapper handling as deployment/hostinger/obfuscate-one.php --decode: decode, never eval. */
function unwrap(string $raw): array
{
    if (!str_contains($raw, 'eval(gzinflate(base64_decode(') && !str_contains($raw, 'Protected by HRX Security')) {
        return [$raw, false];
    }
    if (!preg_match('/base64_decode\(\s*[\'"]([A-Za-z0-9+\/=\s]+)[\'"]\s*\)/', $raw, $m)) {
        return [$raw, null];
    }
    $bin = base64_decode(preg_replace('/\s+/', '', $m[1]), true);
    $out = $bin === false ? false : (@gzinflate($bin) ?: @gzuncompress($bin) ?: @gzdecode($bin));
    if ($out === false || $out === null || $out === '') {
        return [$raw, null];   // wrapped but could not be unwrapped
    }
    return [str_starts_with(ltrim($out), '<?php') ? $out : "<?php\n" . $out, true];
}

/** One normal form for comparing live against git: LF endings, no leading <?php tag, one trailing newline. */
function norm(string $path, string $text): string
{
    $text = str_replace("\r", '', $text);
    if (str_ends_with($path, '.php') && !str_ends_with($path, '.blade.php')) {
        $text = preg_replace('/^<\?php\s*/', '', $text, 1);
    }
    return rtrim($text) . "\n";
}

function envFile(string $root): array
{
    $vars = [];
    $path = $root . '/.env';
    if (!is_file($path)) {
        return $vars;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $line = ltrim(str_replace("\xEF\xBB\xBF", '', $line));
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $v = substr($v, 1, -1);
        } else {
            $v = trim(preg_replace('/\s+#.*$/', '', $v));
        }
        $vars[trim($k)] = $v;
    }
    return $vars;
}

/* ---------------------------------------------------------------- the report */
$report = ['meta' => [
    'generated_at' => date('c'), 'php_version' => PHP_VERSION, 'php_sapi' => PHP_SAPI, 'app_root' => $root,
    'host' => gethostname(), 'read_only' => true,
]];

// ---- server facts
$report['server'] = [
    'litespeed_present' => is_dir('/usr/local/lsws'),
    'cli_limits' => ['memory_limit' => ini_get('memory_limit'), 'max_execution_time' => ini_get('max_execution_time'),
                     'post_max_size' => ini_get('post_max_size'), 'upload_max_filesize' => ini_get('upload_max_filesize')],
    'disk_free_gb' => round(@disk_free_space($root) / 1073741824, 1),
    'extensions' => array_values(array_intersect(['pdo_mysql', 'mbstring', 'openssl', 'curl', 'zip', 'gd', 'fileinfo'], get_loaded_extensions())),
];

// ---- environment (values only for non-secret settings)
$env = envFile($root);
$plain = ['APP_ENV', 'APP_DEBUG', 'APP_URL', 'CACHE_STORE', 'CACHE_DRIVER', 'SESSION_DRIVER', 'QUEUE_CONNECTION', 'MAIL_MAILER', 'LOG_CHANNEL', 'DB_CONNECTION', 'DB_HOST', 'DB_DATABASE'];
$flags = ['TALLY_PUSH_ENABLED', 'TALLY_PUSH_API_KEY', 'TALLY_PUSH_SECRETS', 'TALLY_PUSH_ALLOWED_IPS', 'TALLY_VIEWER_EMAILS', 'TALLY_EXPORT_DIR', 'TALLY_WRITE_GATEWAY_URL', 'TALLY_GATEWAY_URL'];
$report['env'] = ['file_present' => is_file($root . '/.env'), 'values' => [], 'tally_keys' => []];
foreach ($plain as $k) {
    if (isset($env[$k])) { $report['env']['values'][$k] = $k === 'DB_HOST' || $k === 'DB_DATABASE' ? '(set)' : $env[$k]; }
}
foreach ($flags as $k) {
    $report['env']['tally_keys'][$k] = !isset($env[$k]) ? 'not set' : ($env[$k] === '' ? 'set but empty' : ($k === 'TALLY_VIEWER_EMAILS' || $k === 'TALLY_PUSH_ENABLED' ? $env[$k] : 'set'));
}

// ---- caches that decide whether a deploy needs extra steps
$cache = $root . '/bootstrap/cache';
$report['cache_state'] = [
    'config_cached' => is_file($cache . '/config.php'),
    'routes_cached' => (bool) glob($cache . '/routes*.php'),
    'events_cached' => is_file($cache . '/events.php'),
    'storage_logs_writable' => is_writable($root . '/storage/logs'),
    'cache_dir_writable' => is_writable($root . '/storage/framework/cache/data'),
];

// ---- database (SELECT / SHOW only, plain PDO)
$db = ['connected' => false];
try {
    if (!empty($env['DB_DATABASE'])) {
        $dsn = 'mysql:host=' . ($env['DB_HOST'] ?? '127.0.0.1') . ';port=' . ($env['DB_PORT'] ?? '3306') . ';dbname=' . $env['DB_DATABASE'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 15]);
        $db['connected'] = true;
        $db['server_version'] = $pdo->query('SELECT VERSION()')->fetchColumn();
        $db['tally_tables'] = [];
        foreach ($pdo->query("SHOW TABLES LIKE 'tally%'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $db['tally_tables'][$t] = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $t) . '`')->fetchColumn();
        }
        $db['other_tables_present'] = [];
        foreach (['audits', 'crm_client_mappings', 'crm_client_contacts', 'crm_client_activities', 'sales_pipeline_months', 'customers', 'setu_customers', 'users'] as $t) {
            $db['other_tables_present'][$t] = (bool) $pdo->query("SHOW TABLES LIKE '$t'")->fetchColumn();
        }
        $ran = $pdo->query('SELECT migration, batch FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
        $onDisk = array_map(fn ($f) => basename($f, '.php'), glob($root . '/database/migrations/*.php'));
        sort($onDisk);
        $pending = array_values(array_diff($onDisk, array_keys($ran)));
        $db['migrations'] = [
            'ran_total' => count($ran), 'last_batch' => $ran ? max($ran) : null,
            'last_10_ran' => array_slice(array_keys($ran), -10),
            'tally_related_ran' => array_values(array_filter(array_keys($ran), fn ($m) => stripos($m, 'tally') !== false)),
            'pending_on_disk' => $pending,
        ];
        $db['admin_users'] = $pdo->query("SELECT u.id, u.email FROM users u JOIN model_has_roles mr ON mr.model_id = u.id AND mr.model_type LIKE '%User' JOIN roles r ON r.id = mr.role_id WHERE r.name IN ('admin','Admin','super_admin') ORDER BY u.id")->fetchAll(PDO::FETCH_ASSOC);
        $db['viewer_account'] = $pdo->query("SELECT id, email FROM users WHERE LOWER(email) = 'ai@rustx.com'")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $db['error'] = 'no DB_DATABASE in .env';
    }
} catch (Throwable $e) {
    $db['error'] = get_class($e) . ': ' . substr($e->getMessage(), 0, 160);   // never includes credentials
}
$report['db'] = $db;

// ---- fingerprints and DECODED sources
$report['files'] = [];
foreach (array_merge($tallyOwned, $shared) as $rel) {
    $abs = $root . '/' . $rel;
    $entry = ['group' => in_array($rel, $shared, true) ? 'shared' : 'tally', 'exists' => is_file($abs)];
    if ($entry['exists']) {
        $raw = file_get_contents($abs);
        [$decoded, $wrapped] = unwrap($raw);
        $entry += [
            'size' => strlen($raw), 'mtime' => date('c', filemtime($abs)), 'wrapped' => $wrapped,
            'md5_norm' => $wrapped === null ? null : md5(norm($rel, $decoded)),
            'src_gz_b64' => $wrapped === null ? null : base64_encode(gzdeflate($decoded, 9)),
        ];
    }
    $report['files'][$rel] = $entry;
}

// ---- anything Tally-flavoured on the server that is NOT in the list above (live-only files)
$known = array_flip(array_merge($tallyOwned, $shared));
$found = [];
$walk = function (string $dir) use (&$walk, &$found, $root, $known) {
    foreach (@scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') { continue; }
        $p = $dir . '/' . $f;
        if (is_dir($p)) {
            if (!in_array($f, ['vendor', 'node_modules', 'storage', '.git'], true)) { $walk($p); }
        } elseif (stripos($f, 'tally') !== false || stripos($p, 'sales-sources') !== false) {
            $rel = ltrim(substr($p, strlen($root)), '/');
            if (!isset($known[$rel])) { $found[] = ['path' => $rel, 'size' => filesize($p), 'mtime' => date('c', filemtime($p))]; }
        }
    }
};
foreach (['app', 'routes', 'config', 'database', 'resources', 'public'] as $top) { $walk($root . '/' . $top); }
$report['live_only_tally_files'] = $found;
$report['routes_dir'] = array_map('basename', glob($root . '/routes/*.php'));

// ---- the web server rules in public/.htaccess (not secret) and whether a tally rule is already there
$ht = $root . '/public/.htaccess';
$report['htaccess'] = is_file($ht) ? ['present' => true, 'has_tally_rule' => stripos(file_get_contents($ht), 'tally-push') !== false, 'text' => file_get_contents($ht)] : ['present' => false];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
