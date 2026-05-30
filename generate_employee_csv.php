<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = \App\Models\User::with(['department', 'designation'])->get();

$filename = 'employees_export_' . time() . '.csv';
$fp = fopen(__DIR__ . '/public/' . $filename, 'w');
fputcsv($fp, ['Emp Code', 'Biometric Code', 'Name', 'Department', 'Email', 'Phone', 'Designation', 'DOJ']);

foreach ($users as $user) {
    fputcsv($fp, [
        $user->code,
        $user->biometric_id,
        $user->first_name . ' ' . $user->last_name,
        $user->department ? $user->department->name : 'N/A',
        $user->email,
        $user->phone,
        $user->designation ? $user->designation->name : 'N/A',
        $user->date_of_joining ? \Carbon\Carbon::parse($user->date_of_joining)->format('Y-m-d') : 'N/A'
    ]);
}
fclose($fp);
echo $filename;
