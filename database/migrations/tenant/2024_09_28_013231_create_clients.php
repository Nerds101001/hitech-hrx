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
        Schema::create('clients', function (Blueprint $table) {
          $table->id();
          $table->string('name');
          $table->string('email')->unique();
          $table->string('address')->nullable();
          $table->string('phone')->nullable();
          $table->decimal('latitude', 10, 8)->nullable();
          $table->decimal('longitude', 11, 8)->nullable();
          $table->string('contact_person_name')->nullable();
          $table->decimal('radius', 11, 8)->nullable();
          $table->string('city')->nullable();
          $table->string('state')->nullable();
          $table->string('remarks')->nullable();
          $table->string('image_url')->nullable();
          $table->enum('status', ['active', 'inactive'])->default('active');

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
        Schema::dropIfExists('clients');
    }
};
