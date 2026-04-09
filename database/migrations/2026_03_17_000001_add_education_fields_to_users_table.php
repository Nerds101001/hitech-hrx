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
        Schema::table('users', function (Blueprint $table) {
            $table->string('highest_qualification')->nullable()->after('onboarding_completed_at');
            
            // Matric
            $table->string('matric_marksheet_no')->nullable()->after('highest_qualification');
            $table->string('matric_university')->nullable()->after('matric_marksheet_no');
            
            // Inter
            $table->string('inter_marksheet_no')->nullable()->after('matric_university');
            $table->string('inter_university')->nullable()->after('inter_marksheet_no');
            
            // Bachelor
            $table->string('bachelor_marksheet_no')->nullable()->after('inter_university');
            $table->string('bachelor_university')->nullable()->after('bachelor_marksheet_no');
            
            // Master
            $table->string('master_marksheet_no')->nullable()->after('bachelor_university');
            $table->string('master_university')->nullable()->after('master_marksheet_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'highest_qualification',
                'matric_marksheet_no', 'matric_university',
                'inter_marksheet_no', 'inter_university',
                'bachelor_marksheet_no', 'bachelor_university',
                'master_marksheet_no', 'master_university'
            ]);
        });
    }
};
