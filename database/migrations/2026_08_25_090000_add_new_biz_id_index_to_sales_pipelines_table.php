<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Its siblings (salesperson_id, ccare_id, new_biz_2_id) all already have
 * one; new_biz_id was the one left unindexed despite SalesScope::applyScope()
 * filtering on it exactly the same way (whereIn($repCols, $ids), which
 * includes both ccare_id and new_biz_id) for every CCare/New Biz rep's
 * row-level visibility check on this ~10k-row table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->index('new_biz_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->dropIndex(['new_biz_id']);
        });
    }
};
