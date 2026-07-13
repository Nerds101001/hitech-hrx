<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_questions', function (Blueprint $table) {
            $table->string('category')->nullable()->after('correct_option_index');
        });
    }

    public function down(): void
    {
        Schema::table('training_questions', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
