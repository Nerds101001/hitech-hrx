<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * status_remarks was varchar(255), but it holds free-text notes typed by
     * reps and they routinely run longer -- a 277-char remark made the pipeline
     * import fail with SQLSTATE[22001]. PipelineImportController assigns the
     * raw value with no length guard, so the in-app importer hits this too.
     *
     * TEXT matches sales_pipeline_months.remarks, which is already TEXT.
     * Widening never truncates existing rows and the column is not indexed.
     */
    public function up(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->text('status_remarks')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->string('status_remarks', 255)->nullable()->change();
        });
    }
};
