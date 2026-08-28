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
        // SECURITY (QA fix): this migration hardcoded a plaintext password for
        // mukul@rustx.com and re-applied it (with an account unlock) on every
        // fresh deploy. Neutralized to a no-op.
        // ACTION REQUIRED: rotate that account's password out-of-band; the old
        // value was committed to version control and must be considered leaked.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed for a pure data fix migration
    }
};
