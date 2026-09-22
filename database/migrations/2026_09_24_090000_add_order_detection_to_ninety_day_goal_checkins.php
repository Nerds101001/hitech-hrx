<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order status is never typed by the salesperson — it is detected from the real Sales Report
 * (sales_pipeline_months.sale_amount) the moment a checkin week's ensureCheckins() runs:
 *   - the goal's first-ever month with a real sale auto-fills that week's status to
 *     "Regular Order", unambiguously (auto_filled = true);
 *   - a later month's sale, for a goal that already has one, is a repeat/upsell — the system
 *     does not silently mark it; it flags needs_upsell_confirm and waits for a person to
 *     confirm (upsell_confirmed) before the status is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ninety_day_goal_checkins', function (Blueprint $table) {
            $table->boolean('auto_filled')->default(false)->after('is_late');
            $table->boolean('needs_upsell_confirm')->default(false)->after('auto_filled');
            $table->boolean('upsell_confirmed')->nullable()->after('needs_upsell_confirm');
        });
    }

    public function down(): void
    {
        Schema::table('ninety_day_goal_checkins', function (Blueprint $table) {
            $table->dropColumn(['auto_filled', 'needs_upsell_confirm', 'upsell_confirmed']);
        });
    }
};
