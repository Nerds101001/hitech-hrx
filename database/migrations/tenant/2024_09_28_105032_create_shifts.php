<?php

use App\Enums\ShiftType;
use App\Enums\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('shifts', function (Blueprint $table) {
      $table->id();
      $table->string('name', 191);
      $table->string('code', 50)->unique();
      $table->string('notes', 500)->nullable();

      // Shift Timing
      $table->dateTime('start_date');
      $table->dateTime('end_date')->nullable();
      $table->time('start_time');
      $table->time('end_time');

      // Weekly Schedule
      $table->boolean('sunday')->default(false);
      $table->boolean('monday')->default(false);
      $table->boolean('tuesday')->default(false);
      $table->boolean('wednesday')->default(false);
      $table->boolean('thursday')->default(false);
      $table->boolean('friday')->default(false);
      $table->boolean('saturday')->default(false);

      // Shift Attributes
      $table->boolean('is_infinite')->default(false)->comment('If the shift is infinite, then end_date will be null');
      $table->decimal('over_time_threshold', 8, 2)->default(0)->comment('Threshold for overtime in minutes');
      $table->boolean('is_default')->default(false)->comment('If the shift is default, then it will be assigned to all employees by default');
      $table->boolean('is_over_time_enabled')->default(false)->comment('If the shift is over time enabled, then it will be considered for over time calculation');
      $table->boolean('is_break_enabled')->default(false)->comment('If the shift is break enabled, then it will be considered for break calculation');
      $table->decimal('break_time', 8, 2)->default(0)->nullable()->comment('Break time in minutes');

      $table->enum('shift_type', [ShiftType::REGULAR->value, ShiftType::NIGHT->value])->default(ShiftType::REGULAR->value);
      $table->enum('status', [Status::ACTIVE->value, Status::INACTIVE->value, Status::DELETED->value])->default(Status::ACTIVE->value);

      $table->string('timezone')->default('UTC');

      $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->string('tenant_id', 191)->nullable();
      $table->softDeletes();
      $table->timestamps();

      $table->index('is_default');
      $table->index('status');
      $table->index('shift_type');
    });

  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('shifts');
  }
};
