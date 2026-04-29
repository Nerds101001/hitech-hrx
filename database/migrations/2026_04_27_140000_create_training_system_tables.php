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
        // 1. Training Phases (e.g. Phase 1, Phase 2)
        Schema::create('training_phases', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Training Modules (Items within a phase)
        Schema::create('training_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained('training_phases')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('content_type', ['policy', 'catalog', 'video', 'document']);
            $table->longText('content_body')->nullable(); // For interactive reader text
            $table->string('content_url')->nullable(); // For PDF or Video URLs
            $table->integer('estimated_time_minutes')->default(0);
            $table->integer('order')->default(0);
            $table->boolean('is_assessment_required')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Training Questions (Dynamic Assessment)
        Schema::create('training_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('training_modules')->onDelete('cascade');
            $table->text('question');
            $table->json('options'); // JSON array of options
            $table->integer('correct_option_index'); // 0-based index
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });

        // 4. User Training Progress
        Schema::create('user_training_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('training_modules')->onDelete('cascade');
            $table->enum('status', ['locked', 'available', 'in_progress', 'completed'])->default('locked');
            $table->decimal('assessment_score', 5, 2)->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'module_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_training_progress');
        Schema::dropIfExists('training_questions');
        Schema::dropIfExists('training_modules');
        Schema::dropIfExists('training_phases');
    }
};
