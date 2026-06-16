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
            $table->boolean('razor_blade')->default(false)->after('completion_notes');
            $table->boolean('is_new_customer')->default(false)->after('razor_blade');
            $table->boolean('is_upsell')->default(false)->after('is_new_customer');
            $table->boolean('rate_increase')->default(false)->after('is_upsell');
            $table->boolean('is_nif')->default(false)->after('rate_increase');
            $table->boolean('training_done')->default(false)->after('is_nif');
            $table->boolean('mom_submitted')->default(false)->after('training_done');
            $table->boolean('competitor_insight')->default(false)->after('mom_submitted');
            $table->boolean('product_idea')->default(false)->after('competitor_insight');
        });
    }

    public function down(): void
    {
        Schema::table('sales_visits', function (Blueprint $table) {
            $table->dropColumn([
                'razor_blade', 'is_new_customer', 'is_upsell', 'rate_increase',
                'is_nif', 'training_done', 'mom_submitted', 'competitor_insight', 'product_idea',
            ]);
        });
    }
};
