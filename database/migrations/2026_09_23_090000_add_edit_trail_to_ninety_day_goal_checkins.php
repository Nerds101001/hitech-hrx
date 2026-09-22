<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A submitted weekly status can now be edited (it used to be permanently locked the moment a
 * status was set). submitted_at/submitted_by_id keep meaning "first reported"; these two columns
 * record the most recent edit, so the history of who said what first is never silently lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ninety_day_goal_checkins', function (Blueprint $t) {
            if (!Schema::hasColumn('ninety_day_goal_checkins', 'edited_at')) {
                $t->timestamp('edited_at')->nullable()->after('submitted_by_id');
            }
            if (!Schema::hasColumn('ninety_day_goal_checkins', 'edited_by_id')) {
                $t->unsignedBigInteger('edited_by_id')->nullable()->after('edited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ninety_day_goal_checkins', function (Blueprint $t) {
            $t->dropColumn(['edited_at', 'edited_by_id']);
        });
    }
};
