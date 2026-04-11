<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- 1. Checking 'users' Table Schema ---\n";
$columns = Schema::getColumnListing('users');
$requiredFields = [
    'matric_marksheet_no',
    'pan_no',
    'aadhaar_no',
    'intermediate_marksheet_no',
    'graduation_marksheet_no',
    'post_graduation_marksheet_no',
    'experience_certificate_no'
];

foreach ($requiredFields as $field) {
    if (in_array($field, $columns)) {
        echo "[OK] Found field: $field\n";
    } else {
        echo "[MISSING] Field: $field\n";
    }
}

echo "\n--- 2. User Count ---\n";
$userCount = User::count();
echo "Total Users: $userCount\n";

echo "\n--- 3. Document Audit (Top names) ---\n";
// Adjust table name if different. Checking for document_requests or similar.
// Based on EmployeeController it might be DocumentRequest or just files on disk.
// Let's check common tables.
$tables = DB::select('SHOW TABLES');
$tableList = array_map(fn($t) => current((array)$t), $tables);

if (in_array('document_types', $tableList)) {
    echo "Document Types found:\n";
    $types = DB::table('document_types')->select('name', DB::raw('count(*) as count'))
        ->groupBy('name')
        ->get();
    foreach ($types as $type) {
        echo "- {$type->name} ({$type->count})\n";
    }
}

if (in_array('document_requests', $tableList)) {
    echo "\nDocument Requests samples:\n";
    $docs = DB::table('document_requests')
        ->join('document_types', 'document_requests.document_type_id', '=', 'document_types.id')
        ->select('document_types.name', DB::raw('count(*) as count'))
        ->groupBy('document_types.name')
        ->get();
    foreach ($docs as $doc) {
        echo "- {$doc->name}: {$doc->count} uploads\n";
    }
}
