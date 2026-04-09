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
          $table->date('exit_date')->nullable()->index()->after('retired_reason');
          $table->string('exit_reason')->nullable()->after('exit_date');
          $table->string('termination_type')->nullable()->index()->after('exit_reason')->comment('e.g., resignation, terminated, layoff, retired, probation_failed'); // Consider Enum
          $table->date('last_working_day')->nullable()->after('termination_type');
          $table->boolean('is_eligible_for_rehire')->nullable()->default(true)->after('last_working_day');
          $table->integer('notice_period_days')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('exit_date');
            $table->dropColumn('exit_reason');
            $table->dropColumn('termination_type');
            $table->dropColumn('last_working_day');
            $table->dropColumn('is_eligible_for_rehire');
            $table->dropColumn('notice_period_days');
        });
    }
};
