<?php

/*
 * Obfuscate a single PHP file into a staging copy, the same way
 * deployment/hostinger/obfuscate.php does for a whole tree.
 *
 *   php obfuscate-one.php <source> <destination>
 *
 * Used by deploy-telephony.sh for files that ship OBFUSCATED on production.
 * Uploading a plain-text copy of one of those would silently strip the
 * protection from a file that somebody deliberately obfuscated -- a decision
 * that is not the deploy script's to reverse.
 *
 * Also does the reverse: `--decode <file>` prints the original source, which
 * the deploy uses to compare production's real content against the baseline
 * before it overwrites anything. Decoding, never eval'ing: the payload comes
 * off a server and running it here would execute whatever it contains.
 */

if (($argv[1] ?? '') === '--decode') {
    $src = file_get_contents($argv[2]);

    if (!preg_match('/base64_decode\(\s*[\'"]([A-Za-z0-9+\/=\s]+)[\'"]\s*\)/', $src, $m)) {
        // Not obfuscated. Emit it unchanged so the caller can compare either way.
        echo $src;
        exit(0);
    }

    $raw = base64_decode(preg_replace('/\s+/', '', $m[1]), true);
    $out = @gzinflate($raw) ?: @gzuncompress($raw) ?: @gzdecode($raw);

    if ($out === false) {
        fwrite(STDERR, "could not inflate the payload\n");
        exit(1);
    }

    echo str_starts_with(ltrim($out), '<?php') ? $out : "<?php\n" . $out;
    exit(0);
}

[$script, $source, $destination] = $argv + [null, null, null];

if (!$source || !$destination || !is_file($source)) {
    fwrite(STDERR, "usage: obfuscate-one.php <source> <destination>\n");
    fwrite(STDERR, "       obfuscate-one.php --decode <file>\n");
    exit(1);
}

$content = php_strip_whitespace($source);

if (str_contains($content, 'eval(gzinflate')) {
    fwrite(STDERR, "already obfuscated: $source\n");
    exit(1);
}

$code = preg_replace('/^<\?php\s*/i', '', $content);
$encoded = base64_encode(gzdeflate($code, 9));

@mkdir(dirname($destination), 0775, true);
file_put_contents(
    $destination,
    "<?php\n/* Protected by HRX Security */\neval(gzinflate(base64_decode('$encoded')));\n"
);

// Prove it round-trips before the deploy trusts it. An obfuscated file that
// does not inflate back to working source is a 500 on every page that touches
// it, and the failure would only surface in production.
$check = @gzinflate(base64_decode($encoded));

if ($check === false || trim($check) !== trim($code)) {
    unlink($destination);
    fwrite(STDERR, "round-trip check FAILED for $source\n");
    exit(1);
}

printf("obfuscated %s -> %s (%d bytes)\n", basename($source), $destination, filesize($destination));
