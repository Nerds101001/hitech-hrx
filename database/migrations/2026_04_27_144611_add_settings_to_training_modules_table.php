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
        Schema::table('training_modules', function (Blueprint $table) {
            $table->integer('passing_percentage')->default(80)->after('is_assessment_required');
            $table->integer('questions_per_test')->default(5)->after('passing_percentage');
            $table->boolean('show_all_at_once')->default(false)->after('questions_per_test');
        });
    }

    public function down(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->dropColumn(['passing_percentage', 'questions_per_test', 'show_all_at_once']);
        });
    }
};
