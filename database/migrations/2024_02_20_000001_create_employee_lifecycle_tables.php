<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employee_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('previous_designation_id')->nullable()->constrained('designations')->onDelete('set null');
            $table->foreignId('new_designation_id')->nullable()->constrained('designations')->onDelete('set null');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('promotion_type', 50); // merit, seniority, performance
            $table->date('promotion_date');
            $table->decimal('salary_increase', 10, 2)->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('promotion_date');
        });

        Schema::create('employee_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('to_department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('from_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->foreignId('to_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('transfer_date');
            $table->date('effective_date');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('transfer_date');
        });

        Schema::create('employee_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('given_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('warning_type', 50); // verbal, written, performance, attendance
            $table->string('severity', 20); // low, medium, high, critical
            $table->date('warning_date');
            $table->text('description');
            $table->text('action_taken')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'resolved', 'dismissed'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('warning_date');
        });

        Schema::create('employee_resignations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('resignation_date');
            $table->date('last_working_day');
            $table->enum('reason_type', ['voluntary', 'involuntary', 'retirement']);
            $table->text('reason')->nullable();
            $table->text('exit_interview_notes')->nullable();
            $table->boolean('is_rehireable')->default(true);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('resignation_date');
        });

        Schema::create('employee_terminations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('termination_date');
            $table->date('last_working_day');
            $table->enum('termination_type', ['misconduct', 'performance', 'redundancy', 'contract_end']);
            $table->text('reason')->nullable();
            $table->text('termination_notes')->nullable();
            $table->boolean('is_eligible_for_rehire')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('termination_date');
        });

        Schema::create('employee_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complainant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('respondent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('complaint_type', 50); // harassment, discrimination, safety, policy
            $table->string('severity', 20); // low, medium, high, critical
            $table->date('complaint_date');
            $table->text('description');
            $table->text('resolution')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'investigating', 'resolved', 'dismissed'])->default('open');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['complainant_id', 'status']);
            $table->index('complaint_date');
        });

        Schema::create('employee_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('trip_title', 200);
            $table->string('trip_type', 50); // business, training, conference
            $table->date('start_date');
            $table->date('end_date');
            $table->string('destination', 200);
            $table->text('purpose');
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('start_date');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_id')->constrained('users')->onDelete('cascade');
            $table->string('title', 200);
            $table->text('content');
            $table->enum('type', ['general', 'urgent', 'policy', 'holiday', 'event']);
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_acknowledgment')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'start_date']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_promotions');
        Schema::dropIfExists('employee_transfers');
        Schema::dropIfExists('employee_warnings');
        Schema::dropIfExists('employee_resignations');
        Schema::dropIfExists('employee_terminations');
        Schema::dropIfExists('employee_complaints');
        Schema::dropIfExists('employee_trips');
        Schema::dropIfExists('announcements');
    }
};
