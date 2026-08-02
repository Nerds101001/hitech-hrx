<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Other Reps" and the new "Service Sales" column both tag extra people onto a
 * customer, so they share this table. `type` keeps the two lists apart:
 *   other   — CCare / New Biz people (the existing column)
 *   service — salespersons only (the new Service Sales column)
 * Existing rows are all "other", which preserves today's data exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_pipeline_extra_reps', function (Blueprint $table) {
            $table->string('type', 20)->default('other')->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sales_pipeline_extra_reps', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
