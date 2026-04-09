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
        // 1. Finalize LeaveTypes Master-Switches
        if (Schema::hasTable('leave_types')) {
            Schema::table('leave_types', function (Blueprint $table) {
                // Add missing switches if they don't exist
                if (!Schema::hasColumn('leave_types', 'is_paid')) {
                    $table->boolean('is_paid')->default(true)->after('id');
                }
                if (!Schema::hasColumn('leave_types', 'is_proof_required')) {
                    $table->boolean('is_proof_required')->default(false)->after('notes');
                }
                if (!Schema::hasColumn('leave_types', 'is_short_leave')) {
                    $table->boolean('is_short_leave')->default(false)->after('is_proof_required');
                }
                if (!Schema::hasColumn('leave_types', 'is_carry_forward')) {
                    $table->boolean('is_carry_forward')->default(true)->after('is_short_leave');
                }
                if (!Schema::hasColumn('leave_types', 'is_split_entitlement')) {
                    $table->boolean('is_split_entitlement')->default(false)->after('is_carry_forward');
                }
                if (!Schema::hasColumn('leave_types', 'is_consecutive_allowed')) {
                    $table->boolean('is_consecutive_allowed')->default(false)->after('is_split_entitlement');
                }
                if (!Schema::hasColumn('leave_types', 'is_strict_rules')) {
                    $table->boolean('is_strict_rules')->default(false)->after('is_consecutive_allowed');
                }

                // Drop redundant detail columns (moved to Policy Rules)
                $columnsToRemove = [];
                if (Schema::hasColumn('leave_types', 'wfh_days_entitlement')) $columnsToRemove[] = 'wfh_days_entitlement';
                if (Schema::hasColumn('leave_types', 'off_days_entitlement')) $columnsToRemove[] = 'off_days_entitlement';
                if (Schema::hasColumn('leave_types', 'applicable_gender')) $columnsToRemove[] = 'applicable_gender';
                if (Schema::hasColumn('leave_types', 'applicable_marital_status')) $columnsToRemove[] = 'applicable_marital_status';

                if (!empty($columnsToRemove)) {
                    $table->dropColumn($columnsToRemove);
                }
            });
        }

        // 2. Finalize LeavePolicyProfileRules Eligibility
        if (Schema::hasTable('leave_policy_profile_rules')) {
            Schema::table('leave_policy_profile_rules', function (Blueprint $table) {
                if (!Schema::hasColumn('leave_policy_profile_rules', 'applicable_gender')) {
                    $table->enum('applicable_gender', ['all', 'male', 'female'])->default('all')->after('is_applicable');
                }
                if (!Schema::hasColumn('leave_policy_profile_rules', 'applicable_marital_status')) {
                    $table->enum('applicable_marital_status', ['all', 'single', 'married'])->default('all')->after('applicable_gender');
                }
                if (!Schema::hasColumn('leave_policy_profile_rules', 'tenure_consecutive_allowed')) {
                    $table->integer('tenure_consecutive_allowed')->nullable()->after('tenure_required_months');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('leave_types')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->integer('wfh_days_entitlement')->nullable();
                $table->integer('off_days_entitlement')->nullable();
                $table->enum('applicable_gender', ['all', 'male', 'female'])->default('all');
                $table->enum('applicable_marital_status', ['all', 'single', 'married'])->default('all');
            });
        }

        if (Schema::hasTable('leave_policy_profile_rules')) {
            Schema::table('leave_policy_profile_rules', function (Blueprint $table) {
                $table->dropColumn(['applicable_gender', 'applicable_marital_status', 'tenure_consecutive_allowed']);
            });
        }
    }
};
