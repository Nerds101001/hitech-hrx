<?php
require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/vendor/autoload.php';
$app = require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Notifications\OnboardingInvite;
use Illuminate\Support\Facades\DB;

$email = 'rustx.travelling@gmail.com';

$user = User::withoutGlobalScopes()
    ->whereRaw('LOWER(email) = ?', [strtolower($email)])
    ->first();

if (!$user) {
    echo "ERROR: User {$email} not found\n";
    exit(1);
}

echo "Found user:\n";
echo "  ID: {$user->id}\n";
echo "  Name: {$user->first_name} {$user->last_name}\n";
echo "  Email: {$user->email}\n";
echo "  Status: " . ($user->status instanceof \UnitEnum ? $user->status->value : $user->status) . "\n";

// Generate a temporary password
$tempPassword = 'Hitech@' . rand(1000, 9999);

// Update password
$user->password = bcrypt($tempPassword);
$user->save();

echo "\nGenerated temporary password: {$tempPassword}\n";

// Send onboarding notification
echo "\nAttempting to send onboarding email...\n";
try {
    $user->notify(new OnboardingInvite($user, $tempPassword));
    echo "SUCCESS: Onboarding email sent to {$user->email}\n";
} catch (\Exception $e) {
    echo "WARNING: Email sending failed (sendmail disabled on server)\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\n⚠️  IMPORTANT: Please manually share these credentials with the user:\n";
}

echo "\n=== LOGIN CREDENTIALS ===\n";
echo "Email: {$user->email}\n";
echo "Password: {$tempPassword}\n";
echo "Portal: https://hrx.hitechgroup.in\n";
echo "========================\n";
