<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesperson_targets', function (Blueprint $table) {
            // Third target tier, sits above KINGO. Mirrors kingo_target/bingo_target.
            $table->decimal('super_kingo_target', 15, 2)->default(0)->after('month_year');
        });
    }

    public function down(): void
    {
        Schema::table('salesperson_targets', function (Blueprint $table) {
            $table->dropColumn('super_kingo_target');
        });
    }
};
