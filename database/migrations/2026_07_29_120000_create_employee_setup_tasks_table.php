<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the attendance setup accounts must complete for every approved
 * joiner: record the employee ID and, unless their department is exempt,
 * enrol them on a biometric device.
 *
 * One open row per employee drives both the login popup and the dashboard
 * task card, and is closed when the details are submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_setup_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // What this particular employee still needs. Biometric is waived
            // for field departments (sales and similar); the employee ID is
            // always required.
            $table->boolean('needs_emp_id')->default(true);
            $table->boolean('needs_biometric')->default(true);

            $table->enum('status', ['pending', 'completed'])->default('pending');

            // What accounts actually recorded, kept for audit even though the
            // values are also written onto the user record.
            $table->string('emp_id_value')->nullable();
            $table->string('biometric_id_value')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->string('tenant_id')->nullable();
            $table->timestamps();

            // The popup asks "is anything still pending?" on every accounts
            // login, so that lookup must be cheap.
            $table->index(['status', 'tenant_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_setup_tasks');
    }
};
