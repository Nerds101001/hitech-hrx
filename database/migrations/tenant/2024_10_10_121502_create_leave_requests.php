<?php

use App\Enums\LeaveRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('leave_requests', function (Blueprint $table) {
      $table->id();
      $table->date('from_date');
      $table->date('to_date');
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('cascade');
      $table->text('document')->nullable();
      $table->string('user_notes', 500)->nullable();
      $table->bigInteger('approved_by_id')->nullable();
      $table->bigInteger('rejected_by_id')->nullable();
      $table->dateTime('approved_at')->nullable();
      $table->dateTime('rejected_at')->nullable();
      $table->enum('status', [LeaveRequestStatus::PENDING->value, LeaveRequestStatus::APPROVED->value,
        LeaveRequestStatus::REJECTED->value, LeaveRequestStatus::CANCELLED->value, LeaveRequestStatus::CANCELLED_BY_ADMIN->value])
        ->default(LeaveRequestStatus::PENDING->value);
      $table->string('approval_notes', 500)->nullable();
      $table->string('notes', 500)->nullable();
      $table->string('cancel_reason', 500)->nullable();
      $table->dateTime('cancelled_at')->nullable();


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
    Schema::dropIfExists('leave_requests');
  }
};
