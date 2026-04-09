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
        Schema::create('form_entry_fields', function (Blueprint $table) {
          $table->id();
          $table->foreignId('form_entry_id')->constrained('form_entries')->onDelete('cascade');
          $table->foreignId('form_field_id')->constrained('form_fields')->onDelete('cascade');
          $table->text('value')->nullable();

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
        Schema::dropIfExists('form_entry_fields');
    }
};
