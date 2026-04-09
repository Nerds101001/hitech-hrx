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
        Schema::table('jobs', function (Blueprint $table) {
            $table->string('salary')->nullable()->after('position');
            $table->string('job_type')->nullable()->after('salary'); // e.g., Full-time, Part-time, Contract
            $table->text('benefits')->nullable()->after('requirement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn(['salary', 'job_type', 'benefits']);
        });
    }
};
