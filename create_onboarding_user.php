<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\User;
use App\Enums\UserAccountStatus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

$email = 'tester.onboarding@hitech.com';
$user = User::updateOrCreate(
    ['email' => $email],
    [
        'first_name' => 'Onboarding',
        'last_name' => 'Tester',
        'phone' => '9999999999',
        'password' => Hash::make('password'),
        'status' => UserAccountStatus::ONBOARDING,
        'email_verified_at' => now(),
        'date_of_joining' => now(),
        'code' => 'ONB-001',
    ]
);
$user->syncRoles(['field_employee']);
echo "User Created with ID: " . $user->id . "\n";
