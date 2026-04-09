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
    Schema::create('payroll_records', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->foreignId('payroll_cycle_id')->constrained('payroll_cycles')->onDelete('cascade');
      $table->string('period'); // e.g., January 2024
      $table->decimal('basic_salary', 10, 2)->default(0);
      $table->decimal('gross_salary', 10, 2)->default(0);
      $table->decimal('net_salary', 10, 2)->default(0);
      $table->decimal('tax_amount', 10, 2)->default(0);
      $table->enum('status', ['pending', 'completed', 'paid', 'cancelled'])->default('pending');

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
    Schema::dropIfExists('payroll_records');
  }
};
