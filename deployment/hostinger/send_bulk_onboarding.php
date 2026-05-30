<?php
require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/vendor/autoload.php';
$app = require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Notifications\Onboarding\OnboardingInvite;
use Illuminate\Support\Facades\DB;

$emails = [
    'legal.rustx@gmail.com',
    'rustx.travelling@gmail.com',
];

foreach ($emails as $email) {
    echo "\n--- Processing: {$email} ---\n";

    $user = User::withoutGlobalScopes()
        ->whereRaw('LOWER(email) = ?', [strtolower($email)])
        ->whereNull('deleted_at')
        ->first();

    if (!$user) {
        echo "ERROR: User not found\n";
        continue;
    }

    echo "Found: ID={$user->id}, Name={$user->first_name} {$user->last_name}, Status=" . ($user->status instanceof \UnitEnum ? $user->status->value : $user->status) . "\n";

    // Generate temp password
    $tempPassword = 'Hitech@' . rand(1000, 9999);
    $user->password = bcrypt($tempPassword);
    $user->save();

    try {
        $user->notify(new OnboardingInvite($user, $tempPassword));
        echo "SUCCESS: Onboarding email sent\n";
    } catch (\Exception $e) {
        echo "WARNING: Email failed - " . $e->getMessage() . "\n";
    }

    echo "Credentials => Email: {$user->email} | Password: {$tempPassword}\n";
}

echo "\nDone.\n";
