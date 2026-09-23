<?php
/**
 * Post-deploy verification for the audit-coverage work. Run ON the server.
 *
 * Checks behaviour, not file contents: a byte-identical upload proves nothing
 * about whether auditing actually fires, so this writes real records inside a
 * transaction and rolls them back.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use OwenIt\Auditing\Contracts\Auditable;

Mail::fake();   // nothing here may ever send a real message

$BS = chr(92);
$fail = 0;
$ok = static function (string $label, bool $pass, string $detail = '') use (&$fail): void {
    printf("  %-46s %s%s\n", $label, $pass ? 'OK' : '** FAIL **', $detail !== '' ? "  ($detail)" : '');
    if (!$pass) {
        $fail++;
    }
};

echo "=== schema ===\n";

$att = collect(DB::select('SHOW COLUMNS FROM attendances'))->keyBy('Field');
foreach (['notes', 'late_reason', 'early_checkout_reason'] as $c) {
    $ok("attendances.$c is text", str_contains(strtolower($att[$c]->Type ?? ''), 'text'), $att[$c]->Type ?? '?');
}

$aud = collect(DB::select('SHOW COLUMNS FROM audits'))->keyBy('Field');
$ok('audits.created_at is datetime NOT NULL',
    str_contains(strtolower($aud['created_at']->Type ?? ''), 'datetime') && ($aud['created_at']->Null ?? '') === 'NO',
    ($aud['created_at']->Type ?? '?') . ' null=' . ($aud['created_at']->Null ?? '?'));
$ok('audits.auditable_id is varchar',
    str_contains(strtolower($aud['auditable_id']->Type ?? ''), 'char'),
    $aud['auditable_id']->Type ?? '?');

$idx = collect(DB::select('SHOW INDEX FROM audits'))->pluck('Key_name')->unique();
foreach (['audits_user_created_index', 'audits_auditable_created_index', 'audits_created_index'] as $i) {
    $ok("index $i present", $idx->contains($i));
}
$ok('stale index (auditable_type,auditable_id) dropped', !$idx->contains('audits_auditable_type_auditable_id_index'));

$parts = DB::selectOne(
    'SELECT COUNT(*) c FROM information_schema.PARTITIONS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL',
    ['audits']
)->c;
$ok('audits is partitioned', $parts > 1, "$parts partitions");

if (Schema::hasTable('audits_archive')) {
    $ai = collect(DB::select('SHOW INDEX FROM audits_archive'))->pluck('Key_name')->unique();
    $ok('audits_archive has created_at index', $ai->contains('audits_archive_created_index'));
}

echo "\n=== coverage ===\n";
$total = 0;
$audited = 0;
$unloadable = [];
foreach (glob(__DIR__ . '/app/Models/*.php') as $f) {
    $cls = 'App' . $BS . 'Models' . $BS . basename($f, '.php');
    $total++;
    try {
        if (!class_exists($cls)) {
            $unloadable[] = basename($f, '.php');
            continue;
        }
    } catch (\Throwable) {
        $unloadable[] = basename($f, '.php');
        continue;
    }
    if (is_subclass_of($cls, Auditable::class)) {
        $audited++;
    }
}
printf("  models=%d  audited=%d  unloadable=%d (%s)\n", $total, $audited, count($unloadable), implode(', ', $unloadable));
$ok('every loadable model is audited', $audited === $total - count($unloadable));

echo "\n=== auditing actually fires (rolled back) ===\n";
$user = App\Models\User::first();
auth()->login($user);

DB::beginTransaction();
try {
    $a = App\Models\Attendance::create([
        'user_id' => $user->id,
        'check_in_time' => now(),
        'status' => 'Present',
        'notes' => str_repeat('long note that the old varchar(255) would have rejected. ', 30),
    ]);
    $row = DB::table('audits')->where('auditable_type', 'like', '%Attendance')
        ->where('auditable_id', (string) $a->id)->first();
    $ok('attendance write produced an audit row', (bool) $row, $row->event ?? '-');
    $ok('long note stored', mb_strlen((string) $a->fresh()->notes) > 255, mb_strlen((string) $a->fresh()->notes) . ' chars');

    $vals = json_decode($row->new_values ?? '{}', true) ?: [];
    $ok('timestamps excluded from audit values', !isset($vals['updated_at'], $vals['created_at']));
} catch (\Throwable $e) {
    $ok('attendance write', false, $e->getMessage());
} finally {
    DB::rollBack();
}

echo "\n";
printf("%s\n", $fail === 0 ? 'ALL CHECKS PASSED' : "{$fail} CHECK(S) FAILED");
exit($fail === 0 ? 0 : 1);
