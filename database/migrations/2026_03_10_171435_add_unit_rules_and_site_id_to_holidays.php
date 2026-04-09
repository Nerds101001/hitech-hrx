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
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('is_multiple_check_in_enabled')->default(false)->after('attendance_type');
            $table->boolean('is_auto_check_out_enabled')->default(false)->after('is_multiple_check_in_enabled');
            $table->time('auto_check_out_time')->nullable()->after('is_auto_check_out_enabled');
            $table->boolean('is_biometric_verification_enabled')->default(false)->after('auto_check_out_time');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('notes')->constrained('sites')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'is_multiple_check_in_enabled',
                'is_auto_check_out_enabled',
                'auto_check_out_time',
                'is_biometric_verification_enabled'
            ]);
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropColumn('site_id');
        });
    }
};
