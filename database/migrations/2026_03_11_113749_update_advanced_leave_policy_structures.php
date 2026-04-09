<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Leave Types: Standalone Short Leave Categorization
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('is_short_leave')->default(false)->after('is_proof_required');
        });

        // 2. Sites: Saturday Off Configuration
        Schema::table('sites', function (Blueprint $table) {
            $table->json('saturday_off_config')->nullable()->after('is_biometric_verification_enabled');
        });

        // 3. Unit Leave Policies: Universal Tenure Tiers
        Schema::table('unit_leave_policies', function (Blueprint $table) {
            $table->json('tenure_tiers')->nullable()->after('tenure_required_months');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('is_short_leave');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('saturday_off_config');
        });

        Schema::table('unit_leave_policies', function (Blueprint $table) {
            $table->dropColumn('tenure_tiers');
        });
    }
};
