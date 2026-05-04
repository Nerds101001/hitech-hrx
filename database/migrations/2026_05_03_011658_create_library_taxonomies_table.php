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
        Schema::create('library_taxonomies', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['brand', 'category']);
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable(); // Brand ID for Categories
            $table->string('color')->nullable(); // For Brands
            $table->text('description')->nullable(); // For Brands
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('library_taxonomies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_taxonomies');
    }
};
