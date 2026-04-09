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
        Schema::create('geofence_locations', function (Blueprint $table) {
          $table->id();
          $table->string('name');
          $table->string('description')->nullable();
          $table->decimal('latitude', 10, 8);
          $table->decimal('longitude', 11, 8);
          $table->integer('radius')->default(100);
          $table->boolean('is_enabled')->default(true);
          $table->foreignId('geofence_group_id')->constrained('geofence_groups')->onDelete('cascade');

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
        Schema::dropIfExists('geofence_locations');
    }
};
