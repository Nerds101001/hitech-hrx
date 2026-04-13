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
        Schema::create('library_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', ['SDS', 'TDS', 'Video']);
            $table->string('file_path'); // R2 Path
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->boolean('is_public')->default(false); // For SDS
            $table->foreignId('created_by_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('tenant_id')->nullable(); // Multi-tenant support
            $table->timestamps();
        });

        Schema::create('library_file_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('file_id')->constrained('library_files')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_file_permissions');
        Schema::dropIfExists('library_files');
    }
};
