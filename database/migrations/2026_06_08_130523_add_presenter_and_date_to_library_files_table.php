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
            $table->unsignedBigInteger('presenter_id')->nullable()->after('created_by_id');
            $table->date('session_date')->nullable()->after('presenter_id');

            $table->foreign('presenter_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('library_files', function (Blueprint $table) {
            $table->dropForeign(['presenter_id']);
            $table->dropColumn(['presenter_id', 'session_date']);
        });
    }
};
