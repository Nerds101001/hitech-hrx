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
            if (!Schema::hasColumn('leave_policy_profile_rules', 'is_married_only')) {
                $table->boolean('is_married_only')->default(false)->after('is_applicable');
            }
            if (!Schema::hasColumn('leave_policy_profile_rules', 'applicable_gender')) {
                $table->enum('applicable_gender', ['all', 'male', 'female'])->default('all')->after('is_married_only');
            }
            if (!Schema::hasColumn('leave_policy_profile_rules', 'applicable_marital_status')) {
                $table->enum('applicable_marital_status', ['all', 'single', 'married'])->default('all')->after('applicable_gender');
            }
            if (!Schema::hasColumn('leave_policy_profile_rules', 'wfh_days_entitlement')) {
                $table->integer('wfh_days_entitlement')->nullable()->after('max_per_year');
            }
            if (!Schema::hasColumn('leave_policy_profile_rules', 'off_days_entitlement')) {
                $table->integer('off_days_entitlement')->nullable()->after('wfh_days_entitlement');
            }
            if (!Schema::hasColumn('leave_policy_profile_rules', 'tenure_consecutive_allowed')) {
                $table->integer('tenure_consecutive_allowed')->nullable()->after('tenure_required_months');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_policy_profile_rules', function (Blueprint $table) {
            $table->dropColumn([
                'is_married_only',
                'applicable_gender',
                'applicable_marital_status',
                'wfh_days_entitlement',
                'off_days_entitlement',
                'tenure_consecutive_allowed'
            ]);
        });
    }
};
