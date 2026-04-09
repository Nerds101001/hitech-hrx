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
    Schema::create('attendance_logs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('attendance_id')->constrained('attendances')->onDelete('cascade');
      $table->enum('type', ['check_in', 'check_out', 'break_start', 'break_end'])->default('check_in');
      $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');

      $table->decimal('latitude', 10, 8)->nullable();
      $table->decimal('longitude', 11, 8)->nullable();
      $table->decimal('altitude', 10, 2)->nullable();
      $table->decimal('speed', 10, 2)->nullable();
      $table->decimal('speedAccuracy', 10, 2)->nullable();
      $table->decimal('horizontalAccuracy', 10, 2)->nullable();
      $table->decimal('verticalAccuracy', 10, 2)->nullable();
      $table->decimal('course', 10, 2)->nullable();
      $table->decimal('courseAccuracy', 10, 2)->nullable();

      $table->string('address', 1000)->nullable();
      $table->string('notes')->nullable();

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
    Schema::dropIfExists('attendance_logs');
  }
};
