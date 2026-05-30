<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Models\Designation;
use App\Enums\UserAccountStatus;
use Illuminate\Support\Facades\Hash;

class DebugController extends Controller
{
    /**
     * Create or update a test user with onboarding status.
     * Used for local onboarding flow testing.
     *
     * @return string
     */
    public function createTestUser()
    {
        $user = User::updateOrCreate(
            ['email' => 'onboarding_test@example.com'],
            [
                'name' => 'Trial User',
                'first_name' => 'Trial',
                'last_name' => 'User',
                'personal_email' => 'onboarding_test@example.com',
                'password' => Hash::make('password123'),
                'status' => UserAccountStatus::ONBOARDING,
                'code' => 'OB-TEST',
            ]
        );

        if ($user) {
            $user->syncRoles(['employee']);
            return "Created";
        }
        return "Failed";
    }

    /**
     * Retrieve diagnostic onboarding metadata for debugging.
     *
     * @return array
     */
    public function debugOnboardingData()
    {
        return [
           'roles' => Role::get(),
           'departments' => Department::get(),
           'designations' => Designation::get(),
           'auth_tenant' => auth()->user()->tenant_id ?? 'no auth'
        ];
    }
}
