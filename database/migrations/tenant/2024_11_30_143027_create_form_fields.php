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
        Schema::create('form_fields', function (Blueprint $table) {
          $table->id();
          $table->foreignId('form_id')->constrained('forms')->onDelete('cascade');
          $table->integer('order')->default(0);
          $table->enum('field_type', ['text', 'number', 'date', 'time', 'boolean', 'select', 'multiselect', 'url', 'email', 'address']);
          $table->string('label');
          $table->string('placeholder')->nullable();
          $table->boolean('is_required')->default(false);
          $table->integer('min_length')->nullable();
          $table->integer('max_length')->nullable();
          $table->text('default_values')->nullable();
          $table->text('values')->nullable();
          $table->boolean('is_enabled')->default(true);

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
        Schema::dropIfExists('form_fields');
    }
};
