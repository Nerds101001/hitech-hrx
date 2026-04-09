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
    Schema::create('device_status_logs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('device_id')->constrained('user_devices')->onDelete('cascade');
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->string('device_type');
      $table->string('brand');
      $table->string('board');
      $table->string('sdk_version');
      $table->string('model');
      $table->string('token');
      $table->string('app_version')->nullable();
      $table->integer('battery_percentage')->default(0);
      $table->boolean('is_charging')->default(false);
      $table->boolean('is_online')->default(0);
      $table->boolean('is_gps_on')->default(0);
      $table->boolean('is_wifi_on')->default(0);
      $table->boolean('is_mock')->default(0);
      $table->integer('signal_strength')->default(0);
      $table->string('ip_address')->nullable();
      $table->string('address')->nullable();

      //Location Info
      $table->decimal('latitude', 10, 8);
      $table->decimal('longitude', 11, 8);
      $table->decimal('bearing', 11, 8)->nullable();

      $table->decimal('horizontalAccuracy', 11, 8)->nullable();

      $table->decimal('altitude', 11, 8)->nullable();
      $table->decimal('verticalAccuracy', 11, 8)->nullable();

      $table->decimal('course', 11, 8)->nullable();
      $table->decimal('courseAccuracy', 11, 8)->nullable();

      $table->decimal('speed', 11, 8)->nullable();
      $table->decimal('speedAccuracy', 11, 8)->nullable();
      //Location Info End

      //Offline indicators
      $table->string('uid', 500)->nullable();
      $table->boolean('is_offline_record')->default(false);
      $table->string('offline_created_at', 50)->nullable();

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
    Schema::dropIfExists('device_update_logs');
  }
};
