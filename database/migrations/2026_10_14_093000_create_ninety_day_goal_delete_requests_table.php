<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sales team cannot remove a customer themselves (its history stays on record for
 * achievement/KPI integrity) — they can only ask. One row per ask: who asked, why, and
 * admin/HR's decision. Only admin/HR ever actually deletes the goal, on approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ninety_day_goal_delete_requests')) {
            return;
        }
        Schema::create('ninety_day_goal_delete_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->unsignedBigInteger('goal_id')->index();
            $t->unsignedBigInteger('requested_by_id');
            $t->text('reason')->nullable();
            $t->string('status', 12)->default('pending');   // pending | approved | rejected
            $t->unsignedBigInteger('decided_by_id')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->text('admin_remarks')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ninety_day_goal_delete_requests');
    }
};
