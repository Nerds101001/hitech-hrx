<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer that moves from New Biz to CCare keeps its earlier months under
 * New Biz. ccare_from is the first month (YYYY-MM) that counts as CCare and
 * former_new_biz_id the New Biz person those earlier months belong to. Both
 * stay NULL on every existing row, which keeps today's behaviour for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->string('ccare_from', 7)->nullable()->after('new_biz_2_id');
            $table->unsignedBigInteger('former_new_biz_id')->nullable()->after('ccare_from');
            $table->index('former_new_biz_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->dropIndex(['former_new_biz_id']);
            $table->dropColumn(['ccare_from', 'former_new_biz_id']);
        });
    }
};
