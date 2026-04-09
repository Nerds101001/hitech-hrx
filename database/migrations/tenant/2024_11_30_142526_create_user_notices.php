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
        Schema::create('user_notices', function (Blueprint $table) {
          $table->id();
          $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
          $table->foreignId('notice_id')->constrained('notices')->onDelete('cascade');

          $table->string('tenant_id', 191)->nullable();
          $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
          $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
          $table->softDeletes();
          $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notices');
    }
};
