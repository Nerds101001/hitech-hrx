<?php

/**
 * Hitech HRX - Comprehensive Email Test Utility
 * Dispatches all major notification templates to a target email.
 */

// 1. Manual Class Loading (Since we are running outside the web context)
use App\Models\User;
use App\Notifications\Auth\OtpNotification;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Notifications\Auth\SecurityAlertNotification;
use App\Notifications\Auth\UnlockAccountNotification;
use App\Notifications\Onboarding\OnboardingInvite;
use App\Notifications\Onboarding\OnboardingStatusChanged;
use App\Notifications\Onboarding\PortalLaunchNotification;
use Illuminate\Support\Facades\Notification;

// 2. Boostrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 3. Configuration
$testEmail = 'csenerds@gmail.com';
$dummyUser = User::where('status', 'active')->first() ?? User::first();

if (!$dummyUser) {
    echo "CRITICAL: No users found in database to use as sender context. Aborting.\n";
    exit(1);
}

// Force test email as the recipient target in the dummy user object for templates that use $notifiable->email
$dummyUser->email = $testEmail;
if (empty($dummyUser->first_name)) {
  $dummyUser->first_name = 'CS';
  $dummyUser->last_name = 'Nerds';
}

echo "====================================================\n";
echo " Hitech HRX - Production Email Verification Utility \n";
echo "====================================================\n";
echo "Target Recipient: $testEmail\n";
echo "Context User ID: {$dummyUser->id}\n";
echo "----------------------------------------------------\n";

$types = [
    'Welcome / Onboarding Invite' => new OnboardingInvite($dummyUser, 'Hitech@2026!'),
    'Portal Launch Announcement'   => new PortalLaunchNotification($dummyUser, 'Hitech@2026!'),
    'Onboarding - Resubmission'    => new OnboardingStatusChanged($dummyUser, 'resubmission', 'Please re-upload your PAN card; the previous image was blurred.'),
    'Onboarding - Approved'        => new OnboardingStatusChanged($dummyUser, 'approved'),
    'Account Unlock / Reset'      => new UnlockAccountNotification(url('/password/reset/sample-token')),
    'Security Alert'               => new SecurityAlertNotification([
                                        'reason' => 'Multiple Failed Login Attempts',
                                        'email' => $testEmail,
                                        'ip' => '122.161.50.144',
                                        'action' => 'Temporary Account Lock'
                                      ]),
    'OTP Verification'             => new OtpNotification('658942'),
    'Password Changed'             => new PasswordChangedNotification('Hitech@2026!')
];

$successCount = 0;
foreach ($types as $name => $notification) {
    try {
        echo "Dispatching: [$name]... ";
        $dummyUser->notify($notification);
        echo "SENT ✅\n";
        $successCount++;
    } catch (\Exception $e) {
        echo "FAILED ❌ ({$e->getMessage()})\n";
    }
}

echo "----------------------------------------------------\n";
echo "Summary: $successCount / " . count($types) . " emails dispatched.\n";
echo "Please check your inbox: $testEmail\n";
echo "====================================================\n";
