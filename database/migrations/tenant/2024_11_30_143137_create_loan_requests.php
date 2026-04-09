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
        Schema::create('loan_requests', function (Blueprint $table) {
          $table->id();
          $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
          $table->decimal('amount', 10, 2);
          $table->decimal('approved_amount', 10, 2)->nullable();
          $table->foreignId('action_taken_by_id')->nullable()->constrained('users')->onDelete('cascade');
          $table->dateTime('action_taken_at')->nullable();
          $table->string('admin_remarks')->nullable();
          $table->string('remarks')->nullable();
          $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');

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
        Schema::dropIfExists('loan_requests');
    }
};
