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
        Schema::table('sales_visits', function (Blueprint $table) {
            $table->decimal('started_lat', 10, 7)->nullable()->after('notes');
            $table->decimal('started_lng', 10, 7)->nullable()->after('started_lat');
            $table->dateTime('started_at')->nullable()->after('started_lng');
            $table->unsignedTinyInteger('rating')->nullable()->after('completion_notes');
            $table->text('rating_comment')->nullable()->after('rating');
            $table->string('survey_token')->nullable()->unique()->after('rating_comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_visits', function (Blueprint $table) {
            $table->dropColumn([
                'started_lat',
                'started_lng',
                'started_at',
                'rating',
                'rating_comment',
                'survey_token'
            ]);
        });
    }
};
