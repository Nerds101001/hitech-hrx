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
        Schema::table('shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('shifts', 'is_flexible')) {
                $table->boolean('is_flexible')->default(false)->after('shift_type');
                $table->time('flex_start_time')->nullable()->after('is_flexible');
                $table->time('flex_end_time')->nullable()->after('flex_start_time');
                $table->integer('min_working_hours')->default(8)->after('flex_end_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            if (Schema::hasColumn('shifts', 'is_flexible')) {
                $table->dropColumn(['is_flexible', 'flex_start_time', 'flex_end_time', 'min_working_hours']);
            }
        });
    }
};
