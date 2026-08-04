<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New Biz 2 — a third, exclusive owner slot alongside ccare_id/new_biz_id,
 * for the customer search-and-claim feature. Deliberately independent of the
 * ccare_id/new_biz_id mutual-exclusivity rule: a row can carry a ccare_id and
 * a new_biz_2_id at once.
 *
 * Not to be confused with the existing "New BIZ2" grid column, which is the
 * sales_pipeline_extra_reps (type='other') shared tag list — that stays a
 * separate, untouched feature.
 *
 * Constrained FK (unlike new_biz_id, which predates this and was left bare):
 * this column starts empty with exactly one writer (the claim endpoint,
 * always a real authenticated user id), so there's no legacy-data reason to
 * skip the constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->foreignId('new_biz_2_id')->nullable()->after('new_biz_id')
                ->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->dropForeign(['new_biz_2_id']);
            $table->dropColumn('new_biz_2_id');
        });
    }
};
