<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Enums\UserAccountStatus;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $email = 'mukul@rustx.com';
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update([
                'password' => 'Mukul@6589',
                'status' => UserAccountStatus::ACTIVE,
                'locked_until' => null,
                'login_attempts' => 0
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed for a pure data fix migration
    }
};
