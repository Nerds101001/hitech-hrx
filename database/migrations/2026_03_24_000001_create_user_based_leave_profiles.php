<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Policy Profiles
        Schema::create('leave_policy_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            
            // Saturday config: JSON of [1, 2, 3, 4, 5, 'last', 'all']
            $table->json('saturday_off_config')->nullable(); 
            
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Rules for each Leave Type in a Profile
        Schema::create('leave_policy_profile_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('leave_type_id');

            $table->boolean('is_applicable')->default(true);
            
            $table->unsignedTinyInteger('max_per_month')->nullable()->comment('Monthly quota; null = unlimited');
            $table->unsignedSmallInteger('max_per_year')->nullable()->comment('Annual quota; null = unlimited');
            
            $table->unsignedTinyInteger('max_consecutive_days')->nullable();
            
            // Short leave settings
            $table->decimal('short_leave_hours', 4, 2)->nullable();
            $table->unsignedTinyInteger('short_leave_per_month')->nullable();

            // Carry forward and expiry logic
            $table->boolean('is_carry_forward')->default(false)->comment('If true, unused quota can be used in next months');
            $table->unsignedTinyInteger('expiry_months')->nullable()->comment('How many months after earning it expires; null = no expiry');
            $table->boolean('redeem_in_same_month')->default(false)->comment('If true, must be used within the same month (no carry forward)');

            // Tenure requirement & Tiers
            $table->unsignedSmallInteger('tenure_required_months')->nullable();
            $table->json('tenure_tiers')->nullable()->comment('Tenure-based rules like [ {months: 24, consecutive: 3} ]');

            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'leave_type_id', 'tenant_id'], 'profile_rule_unique');
            $table->foreign('profile_id')->references('id')->on('leave_policy_profiles')->onDelete('cascade');
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade');
        });

        // 3. User Balance tracking (One record per user + leave type)
        // This is where 'Carry Forward' actually accumulates.
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('leave_type_id');
            
            $table->decimal('balance', 8, 2)->default(0);
            $table->decimal('used', 8, 2)->default(0);
            
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'leave_type_id', 'tenant_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade');
        });

        // 4. Link User to Profile
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('leave_policy_profile_id')->nullable()->after('designation_id');
            $table->foreign('leave_policy_profile_id')->references('id')->on('leave_policy_profiles')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['leave_policy_profile_id']);
            $table->dropColumn('leave_policy_profile_id');
        });
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_policy_profile_rules');
        Schema::dropIfExists('leave_policy_profiles');
    }
};
