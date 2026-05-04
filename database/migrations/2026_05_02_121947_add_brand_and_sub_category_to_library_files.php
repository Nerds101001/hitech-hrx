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
            $table->string('brand')->nullable()->after('title');
            $table->string('sub_category')->nullable()->after('brand');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('library_files', function (Blueprint $table) {
            $table->dropColumn(['brand', 'sub_category']);
        });
    }
};
