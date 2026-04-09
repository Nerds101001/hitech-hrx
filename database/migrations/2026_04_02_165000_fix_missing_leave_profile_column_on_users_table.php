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
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'leave_policy_profile_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('leave_policy_profile_id')->nullable()->after('designation_id');
                
                // Only add foreign key if the profiles table actually exists
                if (Schema::hasTable('leave_policy_profiles')) {
                    $table->foreign('leave_policy_profile_id')
                          ->references('id')
                          ->on('leave_policy_profiles')
                          ->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'leave_policy_profile_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['leave_policy_profile_id']);
                $table->dropColumn('leave_policy_profile_id');
            });
        }
    }
};
