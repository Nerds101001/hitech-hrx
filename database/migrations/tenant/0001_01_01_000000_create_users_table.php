<?php

use App\Enums\Gender;
use App\Enums\Language;
use App\Enums\SalaryType;
use App\Enums\UserAccountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('users', function (Blueprint $table) {

      $table->id();
      $table->string('tenant_id', 191)->nullable();
      $table->string('first_name', 50)->nullable();
      $table->string('last_name', 50)->nullable();
      $table->string('user_name')->nullable()->unique();
      $table->string('name', 100)->nullable();

      $table->text('profile_picture')->nullable();
      $table->timestamp('email_verified_at')->nullable();
      $table->timestamp('phone_verified_at')->nullable();
      $table->timestamp('last_login')->nullable();
      $table->string('password');

      //Personal Info
      $table->string('code', 50)->unique();
      $table->string('email', 100)->unique();
      $table->string('phone', 20)->unique()->nullable();
      $table->string('address', 1000)->nullable();
      $table->string('alternate_number')->nullable();
      $table->date('dob')->nullable();
      $table->enum('gender', [Gender::MALE->value, Gender::FEMALE->value, Gender::OTHER->value])->default(Gender::MALE->value);

      //Employment Info
      $table->date('date_of_joining')->nullable();
      $table->decimal('base_salary', 10, 2)->nullable();
      $table->decimal('hourly_rate', 10, 2)->nullable();
      $table->decimal('overtime_rate', 10, 2)->nullable();
      $table->enum('salary_type', [SalaryType::HOURLY->value, SalaryType::DAILY->value, SalaryType::MONTHLY->value,
        SalaryType::CONTRACT->value, SalaryType::COMMISSION->value])->default(SalaryType::MONTHLY->value);

      $table->decimal('primary_sales_target', 10, 2)->nullable();
      $table->decimal('secondary_sales_target', 10, 2)->nullable();
      $table->decimal('available_leave_count', 10, 2)->nullable();

      //Attendance Info
      $table->enum('attendance_type', ['open', 'qr_code', 'dynamic_qr', 'geofence', 'ip_address', 'site', 'face_recognition'])->default('open');

      $table->enum('status', [
        UserAccountStatus::ACTIVE->value, UserAccountStatus::INACTIVE->value, UserAccountStatus::PENDING->value, UserAccountStatus::DELETED->value,
        UserAccountStatus::APPROVED->value, UserAccountStatus::BLOCKED->value, UserAccountStatus::ONBOARDING->value, UserAccountStatus::RETIRED->value, UserAccountStatus::SUSPENDED->value,
        UserAccountStatus::REJECTED->value, UserAccountStatus::INVITED->value, UserAccountStatus::REGISTERED->value, UserAccountStatus::RELIEVED->value])->default(UserAccountStatus::ACTIVE->value);

      $table->timestamp('relieved_at')->nullable();
      $table->string('relieved_reason')->nullable();

      $table->timestamp('onboarding_at')->nullable();
      $table->timestamp('onboarding_completed_at')->nullable();

      $table->timestamp('retired_at')->nullable();
      $table->string('retired_reason')->nullable();


      $table->string('tax_no', 191)->nullable();
      $table->enum('language', [Language::ENGLISH->value, Language::ARABIC->value])->default(Language::ENGLISH->value);

      $table->boolean('is_sa_user')->default(false);

      //Foreign Keys
      $table->foreignId('reporting_to_id')->nullable()->constrained('users')->onDelete('set null');

      $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->softDeletes();
      $table->rememberToken();
      $table->timestamps();
    });

    Schema::create('password_reset_tokens', function (Blueprint $table) {
      $table->string('email')->primary();
      $table->string('token');
      $table->timestamp('created_at')->nullable();
    });

    Schema::create('sessions', function (Blueprint $table) {
      $table->string('id')->primary();
      $table->foreignId('user_id')->nullable()->index();
      $table->string('ip_address', 45)->nullable();
      $table->text('user_agent')->nullable();
      $table->longText('payload');
      $table->integer('last_activity')->index();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('users');
    Schema::dropIfExists('password_reset_tokens');
    Schema::dropIfExists('sessions');
  }
};
