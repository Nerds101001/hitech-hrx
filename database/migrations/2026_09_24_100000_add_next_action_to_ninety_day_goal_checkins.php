<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The salesperson's own plan for what happens next on a customer, alongside that week's
 * status/comment — "Share final quotation", "Follow up with MD", etc., plus when they intend to
 * do it. Free text, not a fixed list: the sheet this module follows lets people write anything
 * here, and forcing it into an enum would just lose real detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ninety_day_goal_checkins', function (Blueprint $table) {
            $table->string('next_action', 255)->nullable()->after('comment');
            $table->date('next_follow_up_date')->nullable()->after('next_action');
        });
    }

    public function down(): void
    {
        Schema::table('ninety_day_goal_checkins', function (Blueprint $table) {
            $table->dropColumn(['next_action', 'next_follow_up_date']);
        });
    }
};
