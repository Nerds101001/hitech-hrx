<?php

use App\Enums\TargetStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('sales_target_logs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('sales_target_id')->constrained('sales_targets')->onDelete('cascade');
      $table->date('date'); // Specific date for daily calculation
      $table->decimal('achieved_amount', 10, 2)->default(0); // Amount achieved on the specific day
      $table->decimal('remaining_amount', 10, 2)->default(0); // Remaining amount on the day
      $table->enum('status', [TargetStatus::PENDING->value, TargetStatus::COMPLETED->value,
        TargetStatus::EXPIRED->value])->default(TargetStatus::PENDING->value);

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
    Schema::dropIfExists('sales_target_logs');
  }
};
