<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No-Objection Certificates for a leaver.
 *
 * When HR logs a resignation or termination the employee's access is revoked
 * immediately — the exit is real from that moment. Clearance from IT, HR,
 * Finance and Accounts then runs in parallel: one row per department, each
 * carrying its own status and remarks. The exit is only marked fully closed
 * once every row is cleared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_clearances', function (Blueprint $table) {
            $table->id();

            // Which exit this belongs to. Kept as a type + id pair because
            // resignations and terminations are separate tables.
            $table->enum('exit_type', ['resignation', 'termination']);
            $table->unsignedBigInteger('exit_id');

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // it | hr | finance | accounts — keys from config/hr_notifications.
            $table->string('step_key', 40);
            $table->string('label', 80);

            $table->enum('status', ['pending', 'cleared', 'blocked'])->default('pending');
            $table->text('remarks')->nullable();

            $table->foreignId('cleared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();

            $table->string('tenant_id')->nullable();
            $table->timestamps();

            // HR's tracker asks "what is still outstanding for this exit?"
            $table->index(['exit_type', 'exit_id']);
            $table->index(['status', 'tenant_id']);
            $table->unique(['exit_type', 'exit_id', 'step_key'], 'exit_clearance_step_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_clearances');
    }
};
