<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 90-Day Goals, redesigned around the sales team's own tracker sheet.
 *
 *  - ninety_day_rounds: one 13-week goal period per person. It closes by itself
 *    and the next one starts on the following Monday.
 *  - ninety_day_goal_checkins: one row per goal per Monday holding the status,
 *    the comment, when it was submitted and whether it was late.
 *  - ninety_day_goals gains the sheet's columns: the pipeline customer it came
 *    from, brand, the goal in words, and the monthly potential in lakh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ninety_day_rounds')) {
            Schema::create('ninety_day_rounds', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('user_id')->index();
                $t->unsignedInteger('round_no')->default(1);
                $t->date('starts_on');
                $t->date('ends_on');
                $t->string('status', 12)->default('active');   // active | closed
                $t->timestamp('closed_at')->nullable();
                $t->timestamps();
                $t->index(['user_id', 'status']);
            });
        }

        if (!Schema::hasTable('ninety_day_goal_checkins')) {
            Schema::create('ninety_day_goal_checkins', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('goal_id')->index();
                $t->unsignedBigInteger('round_id')->nullable()->index();
                $t->unsignedTinyInteger('week_no');            // 0..12
                $t->date('due_on');                            // the Monday it is due
                $t->string('status', 40)->nullable();          // null until submitted
                $t->text('comment')->nullable();
                $t->timestamp('submitted_at')->nullable();
                $t->unsignedBigInteger('submitted_by_id')->nullable();
                $t->boolean('is_late')->default(false);
                $t->timestamps();
                $t->unique(['goal_id', 'week_no']);
            });
        }

        Schema::table('ninety_day_goals', function (Blueprint $t) {
            if (!Schema::hasColumn('ninety_day_goals', 'round_id')) {
                $t->unsignedBigInteger('round_id')->nullable()->index();
            }
            if (!Schema::hasColumn('ninety_day_goals', 'sales_pipeline_id')) {
                $t->unsignedBigInteger('sales_pipeline_id')->nullable()->index();
            }
            if (!Schema::hasColumn('ninety_day_goals', 'brand')) {
                $t->string('brand', 100)->nullable();
            }
            if (!Schema::hasColumn('ninety_day_goals', 'goal')) {
                $t->string('goal', 255)->nullable();
            }
            if (!Schema::hasColumn('ninety_day_goals', 'monthly_potential')) {
                $t->decimal('monthly_potential', 10, 2)->default(0);   // lakh per month
            }
            if (!Schema::hasColumn('ninety_day_goals', 'closed_at')) {
                $t->timestamp('closed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ninety_day_goals', function (Blueprint $t) {
            foreach (['round_id', 'sales_pipeline_id', 'brand', 'goal', 'monthly_potential', 'closed_at'] as $c) {
                if (Schema::hasColumn('ninety_day_goals', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
        Schema::dropIfExists('ninety_day_goal_checkins');
        Schema::dropIfExists('ninety_day_rounds');
    }
};
