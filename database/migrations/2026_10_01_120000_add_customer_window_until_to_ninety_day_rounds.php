<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin/HR can reopen (or set) a round's window to add target customers from
 * Program Details. When set it replaces the usual 48 hours from enrolment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ninety_day_rounds', 'customer_window_until')) {
            Schema::table('ninety_day_rounds', function (Blueprint $table) {
                $table->dateTime('customer_window_until')->nullable()->after('closed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ninety_day_rounds', 'customer_window_until')) {
            Schema::table('ninety_day_rounds', function (Blueprint $table) {
                $table->dropColumn('customer_window_until');
            });
        }
    }
};
