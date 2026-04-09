<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('payroll_adjustment_logs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('payroll_record_id')->constrained('payroll_records')->onDelete('cascade');
      $table->foreignId('payroll_adjustment_id')->nullable()->constrained('payroll_adjustments')->onDelete('set null');
      $table->string('name');
      $table->string('code', 191)->nullable();
      $table->enum('type', ['deduction', 'benefit']);
      $table->enum('applicability', ['global', 'employee']);
      $table->decimal('amount', 10, 2)->default(0);
      $table->decimal('percentage', 5, 2)->nullable();
      $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Specific employee
      $table->string('log_message')->nullable(); // Reason for change or context

      $table->string('tenant_id', 191)->nullable();
      $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->softDeletes();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('payroll_adjustment_logs');
  }
};
