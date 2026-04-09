<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_leave_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('leave_type_id');

            // Core availability
            $table->boolean('is_applicable')->default(true)->comment('false = this leave type is disabled for this unit');

            // Quota rules
            $table->unsignedTinyInteger('max_per_month')->nullable()->comment('Max leaves of this type per month; null = unlimited');
            $table->unsignedSmallInteger('max_per_year')->nullable()->comment('Max leaves of this type per year; null = unlimited');

            // Consecutive limit
            $table->unsignedTinyInteger('max_consecutive_days')->nullable()->comment('Max consecutive days in one request; null = unlimited');

            // Short leave settings
            $table->decimal('short_leave_hours', 4, 2)->nullable()->comment('If set, each request counts as X hours (short leave mode)');
            $table->unsignedTinyInteger('short_leave_per_month')->nullable()->comment('Max short leave requests per month');

            // Tenure requirement
            $table->unsignedSmallInteger('tenure_required_months')->nullable()->comment('Min months of employment; null = no requirement');

            // Multi-tenancy
            $table->unsignedBigInteger('tenant_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade');

            // One policy record per unit+leave type
            $table->unique(['site_id', 'leave_type_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_leave_policies');
    }
};
