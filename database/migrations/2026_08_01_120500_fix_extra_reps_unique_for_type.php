<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spx_unique was (sales_pipeline_id, user_id), which predates the `type` split.
 * With Other Reps and Service Sales sharing this table, the same person may
 * legitimately appear in BOTH columns on one customer — the old index rejected
 * that with a duplicate-key error. Widen it to include `type`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_pipeline_extra_reps', function (Blueprint $table) {
            $table->dropUnique('spx_unique');
            $table->unique(['sales_pipeline_id', 'user_id', 'type'], 'spx_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sales_pipeline_extra_reps', function (Blueprint $table) {
            $table->dropUnique('spx_unique');
            $table->unique(['sales_pipeline_id', 'user_id'], 'spx_unique');
        });
    }
};
