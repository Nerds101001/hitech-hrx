<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;

$spreadsheet = IOFactory::load(__DIR__.'/empmap.xlsx');
$sheet = $spreadsheet->getActiveSheet();
$rows  = $sheet->toArray(null, true, true, false);

// Skip header row
array_shift($rows);

$updated   = 0;
$skipped   = 0;
$notFound  = 0;
$sqlLines  = [];
$sqlLines[] = "-- empmap.xlsx → users table update";
$sqlLines[] = "-- Generated: " . date('Y-m-d H:i:s');
$sqlLines[] = "-- Updates: code (emp_id), biometric_id, date_of_joining";
$sqlLines[] = "-- Match key: email";
$sqlLines[] = "";

foreach ($rows as $i => $row) {
    $firstName  = trim((string)($row[0] ?? ''));
    $lastName   = trim((string)($row[1] ?? ''));
    $empId      = trim((string)($row[3] ?? ''));   // Employee ID → code
    $bioId      = trim((string)($row[4] ?? ''));   // Biometric ID
    $email      = strtolower(trim((string)($row[5] ?? '')));
    $doj        = trim((string)($row[8] ?? ''));   // Date of Joining

    if (!$email || !$empId) {
        echo "  [ROW " . ($i+2) . "] SKIP — missing email or emp_id\n";
        $skipped++;
        continue;
    }

    // Parse date (Excel may give m/d/yyyy or d/m/yyyy)
    $dojParsed = null;
    if ($doj) {
        try {
            $ts = strtotime($doj);
            if ($ts) $dojParsed = date('Y-m-d', $ts);
        } catch (\Exception $e) {}
    }

    // Match by email
    $user = DB::table('users')->where('email', $email)->first();

    if (!$user) {
        // Fallback: match by first+last name
        $user = DB::table('users')
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [strtolower($firstName)])
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [strtolower($lastName)])
            ->first();
    }

    if (!$user) {
        echo "  [ROW " . ($i+2) . "] NOT FOUND — {$firstName} {$lastName} <{$email}>\n";
        $notFound++;
        continue;
    }

    // Skip non-numeric or placeholder emp_ids (e.g. "left", "N/A")
    if (!is_numeric($empId)) {
        echo "  [SKIP] {$firstName} {$lastName} — invalid emp_id '{$empId}'\n";
        $skipped++;
        continue;
    }

    // Check if another user already owns this code (would cause unique violation)
    $conflict = DB::table('users')
        ->where('code', $empId)
        ->where('id', '!=', $user->id)
        ->first();

    if ($conflict) {
        echo "  [DUPLICATE] {$firstName} {$lastName} (id={$user->id}) — emp_id={$empId} already owned by id={$conflict->id} ({$conflict->first_name} {$conflict->last_name}). Skipping.\n";
        $skipped++;
        continue;
    }

    // Build update payload
    $updateData = ['code' => $empId, 'biometric_id' => $bioId ?: null];
    if ($dojParsed) $updateData['date_of_joining'] = $dojParsed;

    try {
        DB::table('users')->where('id', $user->id)->update($updateData);
        $dojStr = $dojParsed ?? 'NULL';
        echo "  [OK] {$firstName} {$lastName} (id={$user->id}) → emp_id={$empId}, bio_id={$bioId}, doj={$dojStr}\n";
    } catch (\Exception $e) {
        echo "  [ERROR] {$firstName} {$lastName} (id={$user->id}) — " . $e->getMessage() . "\n";
        $skipped++;
        continue;
    }

    // Also build SQL for live server
    $escapedEmpId = addslashes($empId);
    $escapedBioId = $bioId ? "'" . addslashes($bioId) . "'" : 'NULL';
    $dojSql = $dojParsed ? "'{$dojParsed}'" : 'NULL';
    $escapedEmail = addslashes($email);
    $sqlLines[] = "UPDATE users SET code='{$escapedEmpId}', biometric_id={$escapedBioId}, date_of_joining={$dojSql} WHERE email='{$escapedEmail}';";

    $updated++;
}

echo "\n=============================\n";
echo "Updated : {$updated}\n";
echo "Not found: {$notFound}\n";
echo "Skipped  : {$skipped}\n";

// Write SQL file for live server
$sqlFile = __DIR__ . '/empmap_live_update.sql';
file_put_contents($sqlFile, implode("\n", $sqlLines) . "\n");
echo "\nSQL for live server saved to: empmap_live_update.sql\n";
echo "Run that file on hrx.hitechgroup.in via phpMyAdmin or MySQL CLI.\n";
