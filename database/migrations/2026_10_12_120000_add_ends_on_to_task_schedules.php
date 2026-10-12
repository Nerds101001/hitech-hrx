<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional end date for a recurring task schedule. Without one a schedule
 * runs forever (until someone stops it by hand); with one it stops creating
 * occurrences after the date, and the daily run deactivates it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('task_schedules', 'ends_on')) {
                $table->date('ends_on')->nullable()->after('day_of_month');
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('task_schedules', 'ends_on')) {
                $table->dropColumn('ends_on');
            }
        });
    }
};
