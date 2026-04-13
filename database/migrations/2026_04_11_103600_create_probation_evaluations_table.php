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
        Schema::create('probation_evaluations', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('user_id');
            $blueprint->unsignedBigInteger('manager_id')->nullable();
            $blueprint->string('tenant_id')->nullable();
            
            // Evaluation Criteria
            $blueprint->string('job_knowledge')->nullable();
            $blueprint->string('quality_of_work')->nullable();
            $blueprint->string('attendance_punctuality')->nullable();
            $blueprint->string('initiative_reliability')->nullable();
            
            // Textual feedback
            $blueprint->text('overall_performance')->nullable();
            $blueprint->enum('recommendation', ['confirm', 'extend', 'terminate'])->default('confirm');
            $blueprint->integer('extension_months')->nullable();
            $blueprint->text('areas_for_improvement')->nullable();
            $blueprint->text('manager_remarks')->nullable();
            
            // HR Review
            $blueprint->enum('hr_status', ['pending', 'approved', 'rejected'])->default('pending');
            $blueprint->text('hr_remarks')->nullable();
            $blueprint->unsignedBigInteger('reviewed_by_id')->nullable();
            $blueprint->timestamp('reviewed_at')->nullable();
            
            $blueprint->timestamp('submitted_at')->nullable();
            $blueprint->timestamps();

            // Foreign keys - Commented out to resolve production constraint forming issues
            // $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // $blueprint->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
            // $blueprint->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            // Indexes
            $blueprint->index('user_id');
            $blueprint->index('manager_id');
            $blueprint->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('probation_evaluations');
    }
};
