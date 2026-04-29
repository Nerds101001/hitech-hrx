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
        Schema::table('training_questions', function (Blueprint $table) {
            $table->integer('marks')->default(1)->after('correct_option_index');
        });
    }

    public function down(): void
    {
        Schema::table('training_questions', function (Blueprint $table) {
            $table->dropColumn('marks');
        });
    }
};
