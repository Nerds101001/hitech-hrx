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
    Schema::create('activities', function (Blueprint $table) {
      $table->id();

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

      $table->string('ip')->nullable();
      $table->string('address')->nullable();
      $table->boolean('is_mock')->default(false);
      $table->boolean('is_gps_on')->default(false);
      $table->boolean('is_wifi_on')->default(false);
      $table->integer('battery_percentage')->nullable();
      $table->integer('accuracy')->nullable();
      $table->integer('signal_strength')->nullable();
      $table->string('activity')->nullable();
      $table->string('image_url')->nullable();
      $table->boolean('is_offline')->default(false);
      $table->enum('type', ['checked_in', 'travelling', 'still', 'proof_post', 'checked_out']);

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
    Schema::dropIfExists('trackings');
  }
};
