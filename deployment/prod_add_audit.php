<?php
/**
 * Adds the Auditable trait to production's own model files.
 *
 * Production ships every model wrapped as
 *
 *   <?php /* Protected by HRX Security *\/ eval(gzinflate(base64_decode('...')));
 *
 * and seven of them contain code that exists nowhere in git. Uploading the
 * local copies would both strip that wrapper from 153 files and silently
 * discard those seven files' prod-only changes. So this works the other way
 * round: it unwraps what is already on the server, makes the one edit, and
 * wraps it again in the identical envelope. Prod's own code stays prod's code.
 *
 * The decoded payload is MINIFIED -- the entire class sits on one line -- so
 * nothing here may anchor on line starts. Every pattern below matches
 * positionally instead, and imports are inserted immediately after the
 * `namespace` statement rather than after "the last top-level use", which is
 * indistinguishable from a trait `use` once the newlines are gone.
 *
 * Dry run by default. --apply writes.
 */

$dir = $argv[1] ?? __DIR__ . '/app/Models';
$apply = in_array('--apply', $argv, true);
$BS = chr(92);

// Tenant extends a Stancl\Tenancy base class that is not installed, so the
// class cannot be loaded at all -- auditing it is impossible, not declined.
$SKIP = ['Tenant'];

$done = [];
$already = [];
$skipped = [];
$failed = [];

foreach (glob(rtrim($dir, '/') . '/*.php') as $file) {
    $name = basename($file, '.php');

    if (in_array($name, $SKIP, true)) {
        $skipped[] = $name;
        continue;
    }

    $raw = file_get_contents($file);
    $wrapped = (bool) preg_match("/eval\(gzinflate\(base64_decode\('([^']+)'\)\)\)/", $raw, $m);
    $src = $wrapped ? "<?php\n" . gzinflate(base64_decode($m[1])) : $raw;

    if (str_contains($src, 'OwenIt' . $BS . 'Auditing' . $BS . 'Auditable')) {
        $already[] = $name;
        continue;
    }

    $eol = str_contains($src, chr(13) . chr(10)) ? chr(13) . chr(10) : chr(10);

    // ---- 1. class declaration: add or extend `implements` ------------------
    // No ^ anchor: minified payloads put this mid-line.
    $classRe = '/\bclass\s+' . preg_quote($name, '/')
        . '(\s+extends\s+[^\s{]+)?(\s+implements\s+([^{]+?))?\s*\{/';
    if (!preg_match($classRe, $src, $cm, PREG_OFFSET_CAPTURE)) {
        $failed[] = "$name (class declaration not matched)";
        continue;
    }

    $extends = $cm[1][0] ?? '';
    $implements = isset($cm[3][0]) && trim($cm[3][0]) !== ''
        ? ' implements ' . trim($cm[3][0]) . ', AuditableContract'
        : ' implements AuditableContract';

    $src = substr_replace(
        $src,
        'class ' . $name . $extends . $implements . ' {',
        $cm[0][1],
        strlen($cm[0][0])
    );

    // ---- 2. imports: immediately after the namespace statement -------------
    // Anchoring on "the last use statement" is unsafe once minified, because a
    // trait `use` inside the class body looks identical to a top-level import.
    if (!preg_match('/\bnamespace\s+[^;]+;/', $src, $nm, PREG_OFFSET_CAPTURE)) {
        $failed[] = "$name (no namespace statement)";
        continue;
    }

    $imports = ' use OwenIt' . $BS . 'Auditing' . $BS . 'Auditable;'
        . ' use OwenIt' . $BS . 'Auditing' . $BS . 'Contracts' . $BS . 'Auditable as AuditableContract;';

    $src = substr_replace($src, $imports, $nm[0][1] + strlen($nm[0][0]), 0);

    // ---- 3. the trait itself ------------------------------------------------
    // Re-find the class: step 2 shifted everything after the namespace.
    if (!preg_match($classRe, $src, $cm2, PREG_OFFSET_CAPTURE)) {
        $failed[] = "$name (class vanished after import insert)";
        continue;
    }
    $bodyAt = $cm2[0][1] + strlen($cm2[0][0]) - 1;   // index of the opening brace

    $after = substr($src, $bodyAt);
    $traitRe = '/\buse\s+([A-Za-z0-9_' . $BS . $BS . ', \r\n\t]+);/';

    if (preg_match($traitRe, $after, $tm, PREG_OFFSET_CAPTURE)) {
        $original = $tm[0][0];
        $src = substr_replace(
            $src,
            substr(rtrim($original), 0, -1) . ', Auditable;',
            $bodyAt + $tm[0][1],
            strlen($original)
        );
    } else {
        $src = substr_replace($src, '{' . $eol . '    use Auditable;' . $eol, $bodyAt, 1);
    }

    // ---- 4. syntax check before anything is written ------------------------
    $tmp = sys_get_temp_dir() . '/audcheck_' . $name . '.php';
    file_put_contents($tmp, $src);
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
    @unlink($tmp);
    if ($rc !== 0) {
        $failed[] = "$name (would not parse: " . trim(implode(' ', $out)) . ')';
        continue;
    }

    // ---- 5. re-wrap exactly as prod had it ---------------------------------
    if ($wrapped) {
        $body = preg_replace('/^<\?php\s*/', '', $src, 1);
        $payload = base64_encode(gzdeflate($body, 9));
        if (gzinflate(base64_decode($payload)) !== $body) {
            $failed[] = "$name (re-wrap round-trip failed)";
            continue;
        }
        $final = "<?php\n/* Protected by HRX Security */\neval(gzinflate(base64_decode('" . $payload . "')));";
    } else {
        $final = $src;
    }

    if ($apply) {
        file_put_contents($file, $final);
    }
    $done[] = $name;
}

printf("%s\n", $apply ? '=== APPLIED ===' : '=== DRY RUN (pass --apply) ===');
printf("patched          : %d\n", count($done));
printf("already auditable: %d\n", count($already));
printf("skipped          : %d (%s)\n", count($skipped), implode(', ', $skipped));
printf("failed           : %d\n", count($failed));
foreach ($failed as $f) {
    echo "   $f\n";
}

exit($failed === [] ? 0 : 1);
