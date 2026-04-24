<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leave_policy_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_policy_profiles', 'deduction_config')) {
                $table->json('deduction_config')->nullable()->after('saturday_off_config');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_policy_profiles', function (Blueprint $table) {
            $table->dropColumn('deduction_config');
        });
    }
};
