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
    Schema::create('visits', function (Blueprint $table) {
      $table->id();
      $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
      $table->string('remarks')->nullable();
      $table->text('img_url')->nullable();
      $table->decimal('latitude', 10, 8)->nullable();
      $table->decimal('longitude', 11, 8)->nullable();
      $table->string('address')->nullable();
      $table->dateTime('start_at')->nullable();
      $table->dateTime('end_at')->nullable();

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
    Schema::dropIfExists('visits');
  }
};
