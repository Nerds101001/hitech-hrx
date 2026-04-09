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
        Schema::table('users', function   (Blueprint $table) {
          $table->integer('probation_period_months')->nullable()->after('date_of_joining')->comment('Probation duration in months');
          $table->date('probation_end_date')->nullable()->index()->after('probation_period_months');
          $table->timestamp('probation_confirmed_at')->nullable()->after('probation_end_date');
          $table->boolean('is_probation_extended')->default(false)->after('probation_confirmed_at');
          $table->text('probation_remarks')->nullable()->after('is_probation_extended');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('probation_period_months');
            $table->dropColumn('probation_end_date');
            $table->dropColumn('probation_confirmed_at');
            $table->dropColumn('is_probation_extended');
            $table->dropColumn('probation_remarks');
        });
    }
};
