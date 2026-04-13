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
        Schema::table('library_files', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('title');
            $table->text('summary')->nullable()->after('description');
            $table->json('key_properties')->nullable()->after('summary');
            $table->json('hazards')->nullable()->after('key_properties');
            $table->json('usage_instructions')->nullable()->after('hazards');
            $table->json('tags')->nullable()->after('usage_instructions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('library_files', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'summary', 'key_properties', 'hazards', 'usage_instructions', 'tags']);
        });
    }
};
