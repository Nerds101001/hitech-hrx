<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product trials in Sales Field Ops. Two entry paths:
 *   - a salesperson lodges a completed trial from scratch (status = completed), or
 *   - a CCare/NewBiz agent pre-books one for a salesperson (status = booked), who then
 *     completes it in the field with the execution detail + photos.
 *
 * The company is always a row from that salesperson's Sales Pipeline / Sales Report
 * (sales_pipelines.party_name) -- never free text -- so a trial ties back to a real customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_trials', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('salesperson_id')->index();          // who conducts/lodges it
            $table->unsignedBigInteger('sales_pipeline_id')->nullable()->index(); // the company (pipeline/report row)
            $table->string('company_name')->nullable();                     // snapshot of party_name

            $table->unsignedBigInteger('booked_by_id')->nullable()->index(); // CCare/NewBiz agent; null = self-lodged
            $table->string('booked_by_role', 12)->nullable();               // ccare | newbiz | self

            $table->string('status', 12)->default('booked')->index();       // booked | completed | cancelled

            // Set at booking (planned) and/or confirmed on completion (actual).
            $table->date('trial_date')->nullable();
            $table->time('trial_time')->nullable();

            $table->string('topic')->nullable();                            // topic / trial name
            $table->string('product')->nullable();                          // free text
            $table->text('description')->nullable();
            $table->text('requirement')->nullable();                        // what CCare/NewBiz noted at booking
            $table->string('contact_person')->nullable();                   // customer representative
            $table->string('contact_number', 30)->nullable();
            $table->text('result')->nullable();
            $table->text('remarks')->nullable();

            // Geo-location captured when the trial is lodged/completed (best-effort).
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->float('location_accuracy')->nullable();                 // metres
            $table->string('location_address')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['salesperson_id', 'status']);
            $table->index('trial_date');
        });

        Schema::create('sales_trial_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_trial_id')->index();
            $table->string('path');                                         // on the 'public' disk
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->timestamps();

            $table->foreign('sales_trial_id')->references('id')->on('sales_trials')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_trial_images');
        Schema::dropIfExists('sales_trials');
    }
};
