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
    Schema::create('payslips', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->foreignId('payroll_record_id')->constrained('payroll_records')->onDelete('cascade');
      $table->string('code')->unique();
      $table->decimal('basic_salary', 10, 2)->default(0);
      $table->decimal('total_deductions', 10, 2)->default(0);
      $table->decimal('total_benefits', 10, 2)->default(0);
      $table->decimal('net_salary', 10, 2)->default(0);
      $table->enum('status', ['generated', 'delivered', 'archived'])->default('generated');
      $table->text('notes')->nullable();

      $table->integer('total_worked_days')->default(0);
      $table->integer('total_absent_days')->default(0);
      $table->integer('total_leave_days')->default(0);
      $table->integer('total_late_days')->default(0);
      $table->integer('total_early_checkout_days')->default(0);
      $table->integer('total_overtime_days')->default(0);
      $table->integer('total_holidays')->default(0);
      $table->integer('total_weekends')->default(0);
      $table->integer('total_working_days')->default(0);

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
    Schema::dropIfExists('payslips');
  }
};
