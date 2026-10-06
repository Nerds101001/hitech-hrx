<?php
/**
 * Local half of the live-server preflight: reads the JSON that audit-remote.php printed on the server and says, file
 * by file, how what is LIVE relates to what is in this repository.
 *
 *   php deployment/hostinger/tally/compare-audit.php <live-audit.json> [live-headers.txt]
 *
 * Runs only on this machine. For each audited file it decodes the live source (the server's copy of anything under
 * app/ and routes/ is wrapped by obfuscate.php), then looks for an identical version in this repo -- the working
 * copy, HEAD, then the file's history -- so "live is at commit abc123" is a fact, not a guess. A file that matches
 * nothing is DRIFTED: live carries changes that exist in no commit, and a deploy must not overwrite them blind.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
chdir(dirname(__DIR__, 3));

$jsonPath = $argv[1] ?? null;
if (!$jsonPath || !is_file($jsonPath)) {
    fwrite(STDERR, "usage: php compare-audit.php <live-audit.json> [live-headers.txt]\n");
    exit(1);
}
$r = json_decode(file_get_contents($jsonPath), true);
if (!is_array($r)) {
    fwrite(STDERR, "The audit file is not valid JSON -- the connection probably failed. First lines:\n" . substr(file_get_contents($jsonPath), 0, 600) . "\n");
    exit(1);
}
$headers = isset($argv[2]) && is_file($argv[2]) ? trim(file_get_contents($argv[2])) : '';
$stamp = preg_replace('/^.*live-audit-(.*)\.json$/', '$1', $jsonPath);
$outDir = dirname($jsonPath) . '/live-decoded-' . $stamp;
@mkdir($outDir, 0775, true);

/* ------------------------------------------------------------------ helpers */
function git(array $args): ?string
{
    $p = proc_open(array_merge(['git'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) { return null; }
    $out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return proc_close($p) === 0 ? $out : null;
}
function strip(string $src): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'cmp');
    file_put_contents($tmp, $src);
    $s = php_strip_whitespace($tmp);
    @unlink($tmp);
    return $s;
}
function canon(string $path, string $text, bool $wrapped): string
{
    $isPhp = str_ends_with($path, '.php') && !str_ends_with($path, '.blade.php');
    if ($wrapped && $isPhp) { $text = strip($text); }          // the server's copy was built from php_strip_whitespace()
    $text = str_replace("\r", '', $text);
    if ($isPhp) { $text = preg_replace('/^<\?php\s*/', '', $text, 1); }
    return rtrim($text) . "\n";
}
/**
 * php_strip_whitespace() folds a whole file onto one line, which makes a line diff useless. Put a line break after
 * every ; { } -- by token, so a ';' inside a string is left alone -- purely so differences can be seen and counted.
 */
function pretty(string $php): string
{
    $out = '';
    foreach (array_slice(token_get_all("<?php " . $php), 1) as $t) {
        if (is_array($t)) { $out .= $t[1]; continue; }
        $out .= $t . (in_array($t, [';', '{', '}'], true) ? "\n" : '');
    }
    return preg_replace("/\n{2,}/", "\n", $out);
}
function lineDistance(string $a, string $b, bool $splitStatements): int
{
    $x = explode("\n", $splitStatements ? pretty($a) : $a); $y = explode("\n", $splitStatements ? pretty($b) : $b);
    return count(array_diff($x, $y)) + count(array_diff($y, $x));
}
function line(string $s = ''): void { echo $s, "\n"; }

/* ------------------------------------------------------------------ 1. the server */
line('LIVE SERVER AUDIT  --  ' . ($r['meta']['generated_at'] ?? '?') . '  (read-only; nothing was changed)');
line(str_repeat('=', 78));
$s = $r['server'] ?? [];
line(sprintf('PHP %s (%s) | LiteSpeed: %s | disk free %s GB | memory_limit %s | post_max_size %s',
    $r['meta']['php_version'] ?? '?', $r['meta']['php_sapi'] ?? '?', !empty($s['litespeed_present']) ? 'yes' : 'no',
    $s['disk_free_gb'] ?? '?', $s['cli_limits']['memory_limit'] ?? '?', $s['cli_limits']['post_max_size'] ?? '?'));
line('PHP extensions found: ' . implode(', ', $s['extensions'] ?? []));

line();
line('--- .env on live (secrets are shown only as set / not set) ---');
foreach ($r['env']['values'] ?? [] as $k => $v) { line(sprintf('  %-22s %s', $k, $v)); }
foreach ($r['env']['tally_keys'] ?? [] as $k => $v) { line(sprintf('  %-26s %s', $k, $v)); }
$cs = $r['env']['values']['CACHE_STORE'] ?? $r['env']['values']['CACHE_DRIVER'] ?? '(default: database/file)';
line("  -> the push guards use the file store explicitly, so CACHE_STORE=$cs does not weaken them.");
if (($r['env']['values']['APP_DEBUG'] ?? '') === 'true') { line('  !! APP_DEBUG=true on live -- error pages would show code and settings.'); }

line();
line('--- caches ---');
$c = $r['cache_state'] ?? [];
line('  config cached: ' . (!empty($c['config_cached']) ? 'YES  (a new config file / env value is invisible until config is rebuilt)' : 'no'));
line('  routes cached: ' . (!empty($c['routes_cached']) ? 'YES  (new routes are invisible until the route cache is rebuilt)' : 'no'));
line('  storage/logs writable: ' . (!empty($c['storage_logs_writable']) ? 'yes' : 'NO') . ' | cache dir writable: ' . (!empty($c['cache_dir_writable']) ? 'yes' : 'NO'));

/* ------------------------------------------------------------------ 2. database */
line();
line('--- database ---');
$db = $r['db'] ?? [];
if (empty($db['connected'])) {
    line('  could NOT connect: ' . ($db['error'] ?? 'unknown'));
} else {
    line('  MySQL ' . $db['server_version']);
    $t = $db['tally_tables'] ?? [];
    line('  tally tables present: ' . ($t ? '' : 'NONE'));
    foreach ($t as $name => $rows) { line(sprintf('      %-28s %s rows', $name, number_format($rows))); }
    $need = ['tally_imports', 'tally_invoices', 'tally_invoice_lines', 'tally_sale_orders', 'tally_outstanding', 'tally_sale_lines', 'tally_push_runs', 'tally_deliveries', 'tally_parties', 'tally_receipts'];
    $missing = array_values(array_diff($need, array_keys($t)));
    line('  tally tables still to create: ' . ($missing ? implode(', ', $missing) : 'none'));
    line('  other tables the Tally pages lean on: ' . implode(', ', array_map(fn ($k, $v) => $k . ($v ? ' (yes)' : ' (MISSING)'), array_keys($db['other_tables_present']), $db['other_tables_present'])));
    $m = $db['migrations'];
    line(sprintf('  migrations: %d ran, last batch %s; tally-related already ran: %s', $m['ran_total'], $m['last_batch'], $m['tally_related_ran'] ? implode(', ', $m['tally_related_ran']) : 'none'));
    line('  last 10 that ran: ' . implode(', ', $m['last_10_ran']));
    $pending = $m['pending_on_disk'];
    $tallyPending = array_filter($pending, fn ($x) => stripos($x, 'tally') !== false);
    $foreign = array_diff($pending, $tallyPending);
    line('  PENDING on disk (a bare `artisan migrate` would run all of these): ' . ($pending ? count($pending) : 'none'));
    foreach ($pending as $p) { line('      ' . $p . (in_array($p, $foreign, true) ? '   <-- NOT Tally: must not be dragged in' : '')); }
    if ($foreign) { line('  => deploy Tally migrations with --path=<file> only, never a bare migrate.'); }
    line('  admin accounts on live: ' . implode(', ', array_column($db['admin_users'], 'email')));
    line('  viewer account ai@rustx.com exists on live: ' . ($db['viewer_account'] ? 'yes (id ' . $db['viewer_account'][0]['id'] . ')' : 'NO -- nobody could see Tally'));
}

/* ------------------------------------------------------------------ 3. files */
line();
line('--- files: live versus this repository ---');
line('   = same as   OK     : identical to a git version (shown) -- safe to replace / patch');
line('   ABSENT             : not on live yet (a normal "new file")');
line('   DRIFTED            : live holds changes found in NO git version -- must be reviewed before touching');
line();

$summary = ['same' => 0, 'absent' => 0, 'drifted' => 0, 'unreadable' => 0];
$drifts = [];
foreach ($r['files'] as $path => $f) {
    $tag = $f['group'] === 'shared' ? 'shared' : 'tally ';
    $tracked = git(['ls-files', '--error-unmatch', $path]) !== null;
    if (!$f['exists']) {
        $summary['absent']++;
        line(sprintf('  [%s] ABSENT     %s%s', $tag, $path, $tracked ? '' : '  (not tracked locally either)'));
        continue;
    }
    if ($f['wrapped'] === null || !$f['src_gz_b64']) {
        $summary['unreadable']++;
        line(sprintf('  [%s] UNREADABLE %s  (obfuscated, but the payload could not be decoded)', $tag, $path));
        continue;
    }
    $live = gzinflate(base64_decode($f['src_gz_b64']));
    $safe = $outDir . '/' . $path;
    @mkdir(dirname($safe), 0775, true);
    file_put_contents($safe, $live);
    $liveCanon = canon($path, $live, (bool) $f['wrapped']);
    $isWrapped = $f['wrapped'] ? 'wrapped' : 'plain  ';

    // candidate versions, most relevant first: working copy, HEAD, then history
    $cands = [];
    if (is_file($path)) { $cands['working copy (uncommitted)'] = file_get_contents($path); }
    $head = git(['show', "HEAD:$path"]);
    if ($head !== null) { $cands['HEAD'] = $head; }
    $log = git(['log', '--format=%h|%ad|%s', '--date=short', '-n', '60', '--', $path]);
    foreach (array_filter(explode("\n", (string) $log)) as $i => $row) {
        [$sha, $date, $subject] = explode('|', $row, 3);
        $blob = git(['show', "$sha:$path"]);
        if ($blob !== null) { $cands["$sha $date " . mb_substr($subject, 0, 60)] = $blob; }
    }

    $match = null; $best = null; $bestDist = PHP_INT_MAX; $bestCanon = '';
    foreach ($cands as $label => $text) {
        $cc = canon($path, $text, (bool) $f['wrapped']);
        if ($cc === $liveCanon) { $match = $label; break; }
        $d = lineDistance($liveCanon, $cc, (bool) $f['wrapped']);
        if ($d < $bestDist) { $bestDist = $d; $best = $label; $bestCanon = $cc; }
    }

    if ($match !== null) {
        $summary['same']++;
        line(sprintf('  [%s] = OK       %s  [%s]  == %s', $tag, $path, $isWrapped, $match));
    } else {
        $summary['drifted']++;
        $drifts[] = $path;
        line(sprintf('  [%s] DRIFTED    %s  [%s]  closest git version: %s (%s line(s) differ)', $tag, $path, $isWrapped, $best ?? 'none', $best ? $bestDist : '-'));
        if ($best !== null) {
            $a = tempnam(sys_get_temp_dir(), 'a'); $b = tempnam(sys_get_temp_dir(), 'b');
            file_put_contents($a, $f['wrapped'] ? pretty($bestCanon) : $bestCanon);
            file_put_contents($b, $f['wrapped'] ? pretty($liveCanon) : $liveCanon);
            $diff = shell_exec('diff -u ' . escapeshellarg($a) . ' ' . escapeshellarg($b));
            @unlink($a); @unlink($b);
            file_put_contents($safe . '.drift.diff', "--- git: $best\n+++ live\n" . preg_replace('/^(---|\+\+\+).*\n/m', '', (string) $diff));
        }
    }
}

/* ------------------------------------------------------------------ 4. things live has that git does not */
line();
line('--- Tally-flavoured files that exist on live but are not in the audited list ---');
foreach ($r['live_only_tally_files'] ?? [] as $x) { line(sprintf('  %-70s %s bytes  %s', $x['path'], $x['size'], $x['mtime'])); }
if (empty($r['live_only_tally_files'])) { line('  none'); }
line('  routes/ on live: ' . implode(', ', $r['routes_dir'] ?? []));

/* ------------------------------------------------------------------ 5. web server / CDN */
line();
line('--- how the site is fronted ---');
$cf = (bool) preg_match('/cf-ray|server:\s*cloudflare/i', $headers);
line($headers !== '' ? preg_replace('/^/m', '  ', $headers) : '  (no headers captured)');
line('  => ' . ($cf
    ? 'BEHIND CLOUDFLARE: the office address arrives in CF-Connecting-IP, and Cloudflare\'s ranges must be the trusted proxies.'
    : 'NOT behind Cloudflare (as far as the headers show): the connecting address IS the caller\'s address -- the simple setup.'));
$ht = $r['htaccess'] ?? [];
line('  public/.htaccess: ' . (!empty($ht['present']) ? (!empty($ht['has_tally_rule']) ? 'already has a tally-push rule' : 'present, no tally rule yet') : 'MISSING'));

/* ------------------------------------------------------------------ 6. verdict */
line();
line(str_repeat('=', 78));
line(sprintf('SUMMARY: %d identical to git | %d absent on live | %d DRIFTED | %d unreadable', $summary['same'], $summary['absent'], $summary['drifted'], $summary['unreadable']));
if ($drifts) {
    line();
    line('DRIFTED files -- live differs from every git version. Diffs are saved next to the decoded copies:');
    foreach ($drifts as $d) { line("  $outDir/$d.drift.diff"); }
    $shown = 0;
    foreach ($drifts as $d) {
        $p = "$outDir/$d.drift.diff";
        if (!is_file($p) || $shown >= 6) { continue; }
        $shown++;
        line();
        line("  >> $d");
        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($p)), fn ($l) => preg_match('/^[+-]/', $l) && !preg_match('/^(\+\+\+|---)/', $l)));
        foreach (array_slice($lines, 0, 25) as $l) { line('     ' . mb_substr($l, 0, 150)); }
        if (count($lines) > 25) { line('     ... ' . (count($lines) - 25) . ' more changed line(s) in the .drift.diff file'); }
    }
    line();
    line('Nothing is to be deployed until each drifted file is understood: either its live-only change is carried into');
    line('the new version, or the file is left alone and the Tally change is made in a different place.');
} else {
    line('No drift: every audited file that exists on live matches a known git version.');
}
