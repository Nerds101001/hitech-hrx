<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->float('carry_forward_last_year')->default(0)->after('used');
            $table->float('accrued_this_year')->default(0)->after('carry_forward_last_year');
        });
    }

    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropColumn(['carry_forward_last_year', 'accrued_this_year']);
        });
    }
};
