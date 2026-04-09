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
        Schema::create('task_updates', function (Blueprint $table) {
          $table->id();
          $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
          $table->text('comment')->nullable();
          $table->decimal('latitude', 10, 8)->nullable();
          $table->decimal('longitude', 11, 8)->nullable();
          $table->string('address')->nullable();
          $table->text('file_url')->nullable();
          $table->boolean('is_admin')->default(false);
          $table->foreignId('form_entry_id')->nullable()->constrained('form_entries')->onDelete('cascade');
          $table->enum('update_type', ['comment', 'location', 'image', 'document', 'hold', 'un_hold', 'start', 'complete', 'form']);

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
        Schema::dropIfExists('task_updates');
    }
};
