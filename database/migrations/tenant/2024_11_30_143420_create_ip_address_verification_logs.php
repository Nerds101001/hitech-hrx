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
        Schema::create('ip_address_verification_logs', function (Blueprint $table) {
          $table->id();
          $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
          $table->string('ip');
          $table->boolean('is_verified')->default(false);
          $table->dateTime('verified_at')->nullable();
          $table->string('reason')->nullable();
          $table->foreignId('site_id')->nullable()->constrained('sites')->onDelete('cascade');
          $table->foreignId('ip_address_group_id')->nullable()->constrained('ip_address_groups')->onDelete('cascade');

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
        Schema::dropIfExists('ip_address_verification_logs');
    }
};
