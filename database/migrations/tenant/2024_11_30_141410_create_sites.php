<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('sites', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('description')->nullable();
      $table->decimal('latitude', 10, 8);
      $table->decimal('longitude', 11, 8);
      $table->integer('radius')->default(100);
      $table->string('address')->nullable();
      $table->enum('status', ['active', 'inactive'])->default('active');
      $table->boolean('is_attendance_enabled')->default(false);
      $table->enum('attendance_type', ['none', 'geofence', 'ip_address', 'static_qr_code', 'dynamic_qr_code', 'site'])->default('none');

      $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');

      $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');


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
    Schema::dropIfExists('sites');
  }
};
