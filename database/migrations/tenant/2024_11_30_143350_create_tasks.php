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
        Schema::create('tasks', function (Blueprint $table) {
          $table->id();
          $table->string('title');
          $table->text('description')->nullable();
          $table->enum('type', ['open', 'client_based', 'site_based']);
          $table->foreignId('assigned_by_id')->constrained('users')->onDelete('cascade');
          $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
          $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('cascade');
          $table->foreignId('site_id')->nullable()->constrained('sites')->onDelete('cascade');

          $table->decimal('latitude', 10, 8)->nullable();
          $table->decimal('longitude', 11, 8)->nullable();
          $table->integer('max_radius')->default(100);
          $table->dateTime('start_date_time')->nullable();
          $table->dateTime('end_date_time')->nullable();
          $table->dateTime('for_date');
          $table->enum('status', ['new', 'in_progress', 'completed', 'cancelled', 'hold', 'rejected', 'reassigned', 'reopened', 'resolved', 'closed'])->default('new');
          $table->dateTime('due_date')->nullable();
          $table->foreignId('start_form_id')->nullable()->constrained('forms')->onDelete('cascade');
          $table->foreignId('end_form_id')->nullable()->constrained('forms')->onDelete('cascade');

          $table->boolean('is_geo_fence_enabled')->default(false);

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
        Schema::dropIfExists('tasks');
    }
};
