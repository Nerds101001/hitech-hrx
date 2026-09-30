<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A real, optional planning date the salesperson (or admin) sets when adding/editing a
 *  customer — shown on the round detail page. Nullable: most existing goals never had one. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ninety_day_goals', function (Blueprint $table) {
            $table->date('expected_order_date')->nullable()->after('monthly_potential');
        });
    }

    public function down(): void
    {
        Schema::table('ninety_day_goals', function (Blueprint $table) {
            $table->dropColumn('expected_order_date');
        });
    }
};
