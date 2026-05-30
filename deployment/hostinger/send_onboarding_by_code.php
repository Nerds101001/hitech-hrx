<?php
require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/vendor/autoload.php';
$app = require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Notifications\Onboarding\OnboardingInvite;

$codes = ['2110', '3196'];

foreach ($codes as $code) {
    echo "\n--- Processing Employee Code: {$code} ---\n";

    $user = User::withoutGlobalScopes()
        ->where('code', $code)
        ->whereNull('deleted_at')
        ->first();

    if (!$user) {
        echo "ERROR: Employee with code {$code} not found\n";
        continue;
    }

    $status = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;
    echo "Found:\n";
    echo "  ID     : {$user->id}\n";
    echo "  Name   : {$user->first_name} {$user->last_name}\n";
    echo "  Email  : {$user->email}\n";
    echo "  Status : {$status}\n";

    // Generate temp password
    $tempPassword = 'Hitech@' . rand(1000, 9999);
    $user->password = bcrypt($tempPassword);
    $user->save();

    try {
        $user->notify(new OnboardingInvite($user, $tempPassword));
        echo "SUCCESS: Onboarding email sent to {$user->email}\n";
    } catch (\Exception $e) {
        echo "WARNING: Email failed - " . $e->getMessage() . "\n";
    }

    echo "Credentials => Email: {$user->email} | Password: {$tempPassword}\n";
}

echo "\nDone.\n";
