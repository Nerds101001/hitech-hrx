<?php

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
    Schema::create('leave_types', function (Blueprint $table) {
      $table->id();
      $table->string('name', 191);
      $table->string('code', 50)->unique();
      $table->string('notes', 500)->nullable();
      $table->boolean('is_proof_required')->default(false);
      $table->enum('status', [Status::ACTIVE->value, Status::INACTIVE->value])->default(Status::ACTIVE->value);
      
      $table->string('tenant_id', 191)->nullable();
      $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->softDeletes();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('leave_types');
  }
};
