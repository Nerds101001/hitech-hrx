<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$crmEmployeesAll = app(\App\Services\CrmClientService::class)->getEmployees();
$crmEmployees = array_filter($crmEmployeesAll, function($e) { return isset($e['WORKING']) && $e['WORKING'] == 1; });
$hrxUsers = \App\Models\User::where('status', 'active')->get();

$hrxUsersByEmail = [];
$hrxUsersByName = [];
foreach ($hrxUsers as $u) {
    if ($u->email) {
        $hrxUsersByEmail[strtolower(trim($u->email))] = $u;
    }
    $fullName = strtolower(trim($u->first_name . ' ' . $u->last_name));
    $hrxUsersByName[$fullName] = $u;
}

$mapping = [];
foreach ($crmEmployees as $ce) {
    $empCode = $ce['EMP_CODE'] ?? null;
    $empEmail = strtolower(trim($ce['EMAIL_ADD'] ?? ''));
    $empName = strtolower(trim($ce['EMP_NAME'] ?? ''));
    if (!$empCode) continue;
    
    $matchedUser = null;
    $matchMethod = 'None';
    if ($empEmail && isset($hrxUsersByEmail[$empEmail])) {
        $matchedUser = $hrxUsersByEmail[$empEmail];
        $matchMethod = 'Email';
    } elseif ($empName && isset($hrxUsersByName[$empName])) {
        $matchedUser = $hrxUsersByName[$empName];
        $matchMethod = 'Exact Name';
    }
    
    if ($matchedUser) {
        $mapping[] = [
            'crm_id' => $empCode,
            'crm_name' => $ce['EMP_NAME'],
            'crm_email' => $ce['EMAIL_ADD'],
            'hrx_id' => $matchedUser->id,
            'hrx_name' => $matchedUser->first_name . ' ' . $matchedUser->last_name,
            'hrx_email' => $matchedUser->email,
            'method' => $matchMethod
        ];
    } else {
        $mapping[] = [
            'crm_id' => $empCode,
            'crm_name' => $ce['EMP_NAME'],
            'crm_email' => $ce['EMAIL_ADD'],
            'hrx_id' => null,
            'hrx_name' => 'Not Found',
            'hrx_email' => '',
            'method' => 'None'
        ];
    }
}

file_put_contents('d:/live_server/mapping_result.json', json_encode($mapping, JSON_PRETTY_PRINT));
echo "Mapping generated.\n";
