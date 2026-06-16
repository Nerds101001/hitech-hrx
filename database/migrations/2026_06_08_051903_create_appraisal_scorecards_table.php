<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('appraisal_scorecards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('appraisal_cycle_id');
            
            // Raw calculated values
            $table->decimal('revenue_achieved', 15, 2)->default(0);
            $table->decimal('revenue_target', 15, 2)->default(0);
            $table->decimal('pipeline_value', 15, 2)->default(0);
            $table->decimal('yoy_growth_percentage', 8, 2)->default(0);
            $table->integer('demos_conducted')->default(0);
            $table->decimal('new_customer_revenue_percentage', 5, 2)->default(0);
            $table->decimal('ninety_day_goal_achievement', 5, 2)->default(0);
            $table->integer('sales_pitch_score_raw')->default(0); // out of 20
            $table->integer('learn_hitech_programs')->default(0);
            $table->integer('seminars_conducted')->default(0);
            $table->integer('competitor_insights_count')->default(0);
            $table->integer('product_ideas_count')->default(0);
            $table->decimal('cost_of_selling_percentage', 5, 2)->default(0);
            $table->decimal('cross_sell_dominant_percentage', 5, 2)->default(0);
            $table->decimal('market_share_percentage', 5, 2)->default(0);
            $table->decimal('attrition_rate', 5, 2)->default(0);
            
            // Scored points
            $table->integer('revenue_score')->default(0);
            $table->integer('pipeline_score')->default(0);
            $table->integer('yoy_growth_score')->default(0);
            $table->integer('demos_score')->default(0);
            $table->integer('new_customer_score')->default(0);
            $table->integer('ninety_day_goal_score')->default(0);
            $table->integer('sales_pitch_score')->default(0);
            $table->integer('learn_hitech_score')->default(0);
            $table->integer('seminars_score')->default(0);
            $table->integer('competitor_insights_score')->default(0);
            $table->integer('product_ideas_score')->default(0);
            $table->integer('cost_of_selling_score')->default(0);
            $table->integer('cross_sell_score')->default(0);
            $table->integer('market_share_score')->default(0);
            $table->integer('attrition_penalty')->default(0);
            
            $table->integer('total_score')->default(0);
            $table->string('grade')->nullable(); // e.g., "C - Meets Expectation"
            
            // Manager Review Section
            $table->text('manager_comments')->nullable();
            $table->string('review_frequency')->nullable(); // Quarterly, Half-Yearly, Annual
            $table->string('role_recommendation')->nullable(); // Continue, Expand, Promotion, PIP
            $table->string('training_requirement')->nullable(); // Product, Sales, Tech, None
            
            $table->string('status')->default('draft'); // draft, published
            
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('appraisal_cycle_id')->references('id')->on('appraisal_cycles')->onDelete('cascade');

        });
    }

    public function down()
    {
        Schema::dropIfExists('appraisal_scorecards');
    }
};
