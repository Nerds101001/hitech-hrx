<?php

/*
 * Print a PHP file as its token stream, with comments and whitespace removed.
 *
 *   php php-tokens.php <file>
 *
 * Used by deploy-telephony.sh to compare an obfuscated file on production
 * against the plain source here. A checksum cannot do that job: obfuscation
 * strips comments and collapses whitespace by definition, so the bytes always
 * differ even when the code is identical. Comparing tokens answers the
 * question that actually matters -- has anyone changed the CODE?
 */

$file = $argv[1] ?? null;

if (!$file || !is_file($file)) {
    fwrite(STDERR, "usage: php-tokens.php <file>\n");
    exit(1);
}

$src = file_get_contents($file);

if (!str_starts_with(ltrim($src), '<?php')) {
    $src = "<?php\n" . $src;
}

$out = [];

foreach (token_get_all($src) as $token) {
    if (is_array($token)) {
        if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) {
            continue;
        }
        $out[] = $token[1];
        continue;
    }

    $out[] = $token;
}

echo implode('|', $out);
