<?php

/*
 * Runs ON THE PRODUCTION SERVER. Inserts the two things that cannot be a
 * simple append: the middleware alias and schedule entry in bootstrap/app.php,
 * and the Telephony group in the vertical menu.
 *
 * Written in PHP rather than sed because a botched regex on bootstrap/app.php
 * takes the entire site down, and PHP lets us verify the result parses before
 * committing to it.
 *
 * Every edit is:
 *   - idempotent: running twice changes nothing the second time
 *   - verified:   the file is syntax-checked (PHP) or re-parsed (JSON) BEFORE
 *                 it replaces the original, and abandoned if it fails
 *   - minimal:    an insert next to an existing anchor, nothing else moves
 *
 * Deliberately does NOT touch prod's own content. bootstrap/app.php and the
 * menu are both on an older baseline than git and carry production-only state;
 * uploading the local copies would silently roll production backwards.
 */

/**
 * The Telephony menu, as one list.
 *
 * Shared so that creating the group and topping up a group that already exists
 * cannot drift apart -- which is exactly how a screen ends up deployed but
 * unreachable from the menu.
 *
 * @return array<int,array<string,mixed>>
 */
function telephonySubmenu(): array
{
    $admin = ['permission' => ['telephony.admin'], 'roles' => ['admin', 'hr']];

    return [
        ['url' => '/user/telephony/my-calls', 'name' => 'Calls', 'slug' => 'user.telephony.reports.calls'],
        ['url' => '/user/telephony/productivity', 'name' => 'Productivity', 'slug' => 'user.telephony.reports.productivity'],
        ['url' => '/user/telephony/analytics', 'name' => 'Analytics', 'slug' => 'user.telephony.reports.analytics'],
        ['url' => '/user/telephony/admin/connection', 'name' => 'Connection', 'slug' => 'user.telephony.admin.connection'] + $admin,
        ['url' => '/user/telephony/admin/webhook-log', 'name' => 'Webhook Log', 'slug' => 'user.telephony.admin.webhook-log'] + $admin,
        ['url' => '/user/telephony/admin/agent-map', 'name' => 'Agent Mapping', 'slug' => 'user.telephony.admin.agent-map'] + $admin,
        ['url' => '/user/telephony/admin/exceptions', 'name' => 'Exceptions', 'slug' => 'user.telephony.admin.exceptions'] + $admin,
        ['url' => '/user/telephony/admin/reconciliation', 'name' => 'Reconciliation', 'slug' => 'user.telephony.admin.reconciliation'] + $admin,
    ];
}

$root = __DIR__;
$ok = true;

function report(string $file, string $message): void
{
    printf("  %-38s %s\n", $file, $message);
}

/**
 * Write $content to $file only if it survives a syntax check.
 */
function safeWrite(string $file, string $content, bool $isPhp): bool
{
    $tmp = $file . '.patch-tmp';
    file_put_contents($tmp, $content);

    if ($isPhp) {
        exec(PHP_BINARY . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            unlink($tmp);
            report(basename($file), 'ABORTED — patched file does not parse');

            return false;
        }
    } else {
        if (json_decode(file_get_contents($tmp)) === null) {
            unlink($tmp);
            report(basename($file), 'ABORTED — patched JSON does not parse');

            return false;
        }
    }

    rename($tmp, $file);

    return true;
}

// ─────────────────────────────────────────────────────── bootstrap/app.php ──

$file = $root . '/bootstrap/app.php';
$s = file_get_contents($file);

if (str_contains($s, 'telephony.signed')) {
    report('bootstrap/app.php', 'already patched');
} else {
    $before = $s;

    // 1. The middleware alias. Anchored on the closing of the alias array.
    if (preg_match("/('sales\.guard'\s*=>\s*\\\\App\\\\Http\\\\Middleware\\\\SalesAccessGuard::class,)/", $s, $m)) {
        $s = str_replace(
            $m[1],
            $m[1] . "\n      'telephony.signed' => \\App\\Http\\Middleware\\VerifyTelephonySignature::class,",
            $s
        );
    } elseif (preg_match("/(\\\$middleware->alias\(\[)/", $s, $m)) {
        // Fall back to the top of the array if that specific alias is absent.
        $s = str_replace(
            $m[1],
            $m[1] . "\n      'telephony.signed' => \\App\\Http\\Middleware\\VerifyTelephonySignature::class,",
            $s
        );
    } else {
        report('bootstrap/app.php', 'ABORTED — no alias array found');
        $ok = false;
        $s = $before;
    }

    // 2. The nightly reconciliation.
    if ($s !== $before && preg_match("/(->withSchedule\(function\s*\([^)]*\)\s*\{)/", $s, $m)) {
        $s = str_replace(
            $m[1],
            $m[1] . "\n    \$schedule->command('telephony:reconcile')->dailyAt('02:00')->withoutOverlapping();",
            $s
        );
    }

    if ($s !== $before) {
        if (safeWrite($file, $s, true)) {
            report('bootstrap/app.php', '+ telephony.signed alias, + nightly reconcile');
        } else {
            $ok = false;
        }
    }
}

// ─────────────────────────────────────────────────── the vertical menu ──────

$file = $root . '/resources/menu/tenantVerticalMenu.json';

if (!is_file($file)) {
    report('tenantVerticalMenu.json', 'not found — skipped');
} else {
    $raw = file_get_contents($file);

    $doc = json_decode($raw, true);

    if (!isset($doc['menu']) || !is_array($doc['menu'])) {
        report('tenantVerticalMenu.json', 'ABORTED — unexpected shape');
        $ok = false;
    } else {
        // Find an existing Telephony group rather than testing whether one
        // exists at all. On a re-deploy the group is already there and it is
        // the individual sub-items that may be missing -- guarding on the
        // group alone means a newly added screen is reachable only by URL.
        $existingIndex = null;
        foreach ($doc['menu'] as $i => $item) {
            if (($item['name'] ?? null) === 'Telephony') {
                $existingIndex = $i;
                break;
            }
        }

        if ($existingIndex !== null) {
            $have = array_column($doc['menu'][$existingIndex]['submenu'] ?? [], 'slug');
            $added = [];

            foreach (telephonySubmenu() as $item) {
                if (!in_array($item['slug'], $have, true)) {
                    $doc['menu'][$existingIndex]['submenu'][] = $item;
                    $doc['menu'][$existingIndex]['slug'][] = $item['slug'];
                    $added[] = $item['name'];
                }
            }

            if ($added === []) {
                report('tenantVerticalMenu.json', 'already complete');
            } else {
                $encoded = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (safeWrite($file, $encoded, false)) {
                    report('tenantVerticalMenu.json', '+ ' . implode(', ', $added));
                } else {
                    $ok = false;
                }
            }
        } else {
            $entry = [
                'name' => 'Telephony',
                'icon' => 'menu-icon bx bx-phone-call',
                'slug' => array_column(telephonySubmenu(), 'slug'),
                'submenu' => telephonySubmenu(),
            ];

            // Put it next to Payment Collections if that exists (Sales &
            // Operations, where the call-centre floor already looks), else at
            // the end rather than guessing.
            $at = count($doc['menu']);
            foreach ($doc['menu'] as $i => $item) {
                if (($item['name'] ?? null) === 'Payment Collections') {
                    $at = $i + 1;
                    break;
                }
            }

            $countBefore = count($doc['menu']);
            array_splice($doc['menu'], $at, 0, [$entry]);

            if (count($doc['menu']) !== $countBefore + 1) {
                report('tenantVerticalMenu.json', 'ABORTED — insert changed the wrong number of entries');
                $ok = false;
            } else {
                $encoded = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                if (safeWrite($file, $encoded, false)) {
                    report('tenantVerticalMenu.json', "+ Telephony group (at position {$at})");
                } else {
                    $ok = false;
                }
            }
        }
    }
}

if (!$ok) {
    fwrite(STDERR, "\n  ONE OR MORE PATCHES FAILED — run REVERT.sh\n");
    exit(1);
}
