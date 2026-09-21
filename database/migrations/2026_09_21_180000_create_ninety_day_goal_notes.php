<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comments on a week of a 90-Day Goal, written by the salesperson or by an admin/manager.
 * They sit beside the status comment and are never edited or removed by the sales team.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ninety_day_goal_notes')) {
            return;
        }
        Schema::create('ninety_day_goal_notes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->unsignedBigInteger('checkin_id')->index();
            $t->unsignedBigInteger('goal_id')->index();
            $t->unsignedTinyInteger('week_no');
            $t->unsignedBigInteger('user_id')->index();   // who wrote it
            $t->text('body');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ninety_day_goal_notes');
    }
};
