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
    Schema::create('payroll_cycles', function (Blueprint $table) {
      $table->id();
      $table->string('name'); // e.g., 'January 2024 Payroll'
      $table->string('code')->unique(); // Unique identifier for cycle
      $table->enum('frequency', ['monthly', 'bi-weekly', 'weekly', 'daily'])->default('monthly');
      $table->date('pay_period_start');
      $table->date('pay_period_end');
      $table->date('pay_date'); // Date salary is paid
      $table->enum('status', ['pending', 'processed', 'cancelled', 'completed'])->default('pending');

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
    Schema::dropIfExists('payroll_cycles');
  }
};
