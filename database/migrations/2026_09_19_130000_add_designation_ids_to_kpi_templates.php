<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Narrow a template to specific designations within its department.
 *
 * A template used to reach EVERY active person in its department. The
 * Production scorecard is a plant-level sheet owned by the managers, not by
 * all 25 people on the floor, so a template can now carry a list of
 * designation ids: NULL keeps the old "whole department" behaviour, a list
 * restricts the audience to those designations (the General Manager is left
 * out simply by not being on it).
 *
 * Stored as JSON text, read back as an array by the model cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('kpi_templates', 'designation_ids')) {
            Schema::table('kpi_templates', function (Blueprint $t) {
                $t->text('designation_ids')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kpi_templates', 'designation_ids')) {
            Schema::table('kpi_templates', function (Blueprint $t) {
                $t->dropColumn('designation_ids');
            });
        }
    }
};
