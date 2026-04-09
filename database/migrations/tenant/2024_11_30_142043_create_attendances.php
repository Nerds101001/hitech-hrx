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
    Schema::create('attendances', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->dateTime('check_in_time')->nullable();
      $table->dateTime('check_out_time')->nullable();
      $table->string('late_reason')->nullable();
      $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('cascade');
      $table->string('early_checkout_reason')->nullable();

      $table->decimal('working_hours', 15, 2)->default(0);
      $table->decimal('late_hours', 15, 2)->default(0);
      $table->decimal('early_hours', 15, 2)->default(0);
      $table->decimal('overtime_hours', 15, 2)->default(0);

      $table->string('notes')->nullable();

      $table->enum('status', ['checked_in', 'checked_out', 'auto_checked_out', 'present', 'absent', 'leave', 'half-Day']);

      $table->foreignId('site_id')->nullable()->constrained('sites')->onDelete('set null');

      $table->foreignId('approved_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->dateTime('approved_at')->nullable();

      $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->string('tenant_id', 191)->nullable();
      $table->softDeletes();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('attendances');
  }
};
