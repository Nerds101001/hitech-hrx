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
        Schema::table('leave_policy_profile_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_policy_profile_rules', 'carry_forward_max_days')) {
                $table->unsignedSmallInteger('carry_forward_max_days')->nullable()->after('is_carry_forward');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_policy_profile_rules', function (Blueprint $table) {
            $table->dropColumn('carry_forward_max_days');
        });
    }
};
