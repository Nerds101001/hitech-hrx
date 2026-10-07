<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the "add your 10 customers" reminder was emailed for this round, so it
 * fires once per deadline. Cleared when an admin reopens the window (a new
 * deadline re-arms the reminder). NULL on every existing row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ninety_day_rounds', function (Blueprint $table) {
            $table->timestamp('completion_reminder_sent_at')->nullable()->after('customer_window_until');
        });
    }

    public function down(): void
    {
        Schema::table('ninety_day_rounds', function (Blueprint $table) {
            $table->dropColumn('completion_reminder_sent_at');
        });
    }
};
