<?php

use App\Enums\IncentiveType;
use App\Enums\TargetStatus;
use App\Enums\TargetType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('sales_targets', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->enum('target_type', [TargetType::DAILY->value, TargetType::WEEKLY->value, TargetType::MONTHLY->value,
        TargetType::QUARTERLY->value, TargetType::HALF_YEARLY->value, TargetType::YEARLY->value])
        ->default(TargetType::MONTHLY->value);

      $table->date('start_date')->nullable();
      $table->date('end_date')->nullable();
      $table->date('expiry_date')->nullable();

      $table->integer('period')->default(1);
      $table->decimal('target_amount', 10, 2)->default(0);
      $table->decimal('achieved_amount', 10, 2)->default(0);
      $table->decimal('remaining_amount', 10, 2)->default(0);


      $table->decimal('incentive_amount', 10, 2)->default(0);
      $table->decimal('incentive_percentage', 10, 2)->default(0);
      $table->enum('incentive_type', [IncentiveType::FIXED->value, IncentiveType::PERCENTAGE->value,
        IncentiveType::NONE->value])->default(IncentiveType::NONE->value);

      $table->date('last_evaluated_date')->nullable();

      $table->enum('status', [TargetStatus::PENDING->value, TargetStatus::COMPLETED->value,
        TargetStatus::EXPIRED->value])->default(TargetStatus::PENDING->value);

      $table->text('description')->nullable();
      $table->text('notes')->nullable();

      $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->string('tenant_id', 191)->nullable();
      $table->softDeletes();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('sales_targets');
  }
};
