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
    Schema::create('payroll_adjustments', function (Blueprint $table) {
      $table->id();
      $table->string('name'); // e.g., "Health Insurance", "Late Penalty"
      $table->string('code', 191)->nullable();
      $table->enum('type', ['deduction', 'benefit'])->default('deduction');
      $table->enum('applicability', ['global', 'employee'])->default('global'); // global = all, employee = specific
      $table->decimal('amount', 10, 2)->default(0);
      $table->decimal('percentage', 5, 2)->nullable(); // Optional percentage-based adjustments
      $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade'); // If specific to an employee
      $table->foreignId('payroll_record_id')->nullable()->constrained('payroll_records')->onDelete('cascade');

      $table->string('notes', 191)->nullable();

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
    Schema::dropIfExists('payroll_adjustments');
  }
};
