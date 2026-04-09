<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('settings', function (Blueprint $table) {

      $table->id();
      $table->string('default_language', 5)->default('en');
      $table->string('app_name')->default('Hi Tech HRX');
      $table->string('app_logo')->nullable();
      $table->string('app_favicon')->nullable();
      $table->string('app_color')->default('#4CAF50');
      $table->string('app_logo_dark')->nullable();
      $table->string('app_logo_light')->nullable();
      $table->string('app_icon')->nullable();
      $table->string('app_description')->nullable();
      $table->string('app_version')->default('4.0.0');

      $table->string('country')->default('USA');
      $table->string('phone_country_code')->default('+1');

      //Attendance
      $table->boolean('is_auto_check_out_enabled')->default(false);
      $table->time('auto_check_out_time')->default('18:00:00');
      $table->boolean('is_multiple_check_in_enabled')->default(true);

      //Time Settings
      $table->string('default_timezone', 50)->default('Asia/Kolkata');
      $table->string('default_date_format', 50)->default('d-m-Y');
      $table->string('default_time_format', 50)->default('h:i A');
      $table->string('default_datetime_format', 50)->default('d-m-Y h:i A');

      $table->string('currency')->default('INR');
      $table->string('currency_symbol')->default('₹');
      $table->enum('distance_unit', ['km', 'miles'])->default('km');
      $table->string('default_password', 191)->default('123456');

      //Checking
      $table->enum('offline_check_time_type', ['minutes', 'seconds'])->default('minutes');
      $table->integer('offline_check_time')->default(900);

      //Mobile App Settings
      $table->string('m_app_version')->default('4.0.0');
      $table->enum('m_location_update_time_type', ['minutes', 'seconds'])->default('seconds');
      $table->integer('m_location_update_interval')->default(5);
      $table->integer('m_location_distance_filter')->default(10);
      $table->string('privacy_policy_url')->default('https://hitechhrx.com/privacy-policy/');

      $table->boolean('verify_client_number')->default(false);

      $table->boolean('is_biometric_verification_enabled')->default(false);
      $table->boolean('is_device_verification_enabled')->default(true);
      $table->string('map_api_key', 2000)->nullable();

      //Maps
      $table->string('map_provider', 191)->default('google');
      $table->string('map_zoom_level', 191)->default('3');
      $table->string('center_latitude', 191)->default('18.418983770139405');
      $table->string('center_longitude', 191)->default('49.67194361588897');
      $table->string('maps_key', 500)->nullable();

      $table->string('chat_gpt_key', 500)->nullable();
      $table->boolean('enable_ai_chat_global')->default(false);

      $table->boolean('enable_ai_for_admin')->default(false);
      $table->boolean('enable_ai_for_employee_self_service')->default(false);
      $table->boolean('enable_ai_for_business_intelligence')->default(false);


      $table->boolean('is_helper_text_enabled')->default(true);

      $table->json('available_modules')->nullable();
      $table->json('accessible_module_routes')->nullable();
      $table->integer('employees_limit')->default(0);

      $table->string('support_email')->nullable();
      $table->string('support_phone')->nullable();
      $table->string('support_whatsapp')->nullable();
      $table->string('website')->nullable();

      //Payroll Settings
      $table->integer('working_days_per_month')->default(26);
      $table->decimal('daily_working_hours', 4, 2)->default(8.0);
      $table->boolean('hourly_rate_calculation')->default(true);
      $table->decimal('overtime_rate', 4, 2)->default(1.5);
      $table->decimal('tax_deduction_percentage', 5, 2)->default(10.0);
      $table->decimal('leave_deduction_per_day', 10, 2)->default(500.00);
      $table->decimal('half_day_deduction', 10, 2)->default(250.00);
      $table->integer('pay_period_start_day')->default(1);
      $table->decimal('attendance_threshold_hours', 4, 2)->default(4.0);
      $table->enum('payroll_frequency', ['monthly', 'bi-weekly', 'weekly', 'daily'])->default('monthly');
      $table->integer('payroll_start_date')->default(1);
      $table->integer('payroll_cutoff_date')->default(31);
      $table->boolean('auto_payroll_processing')->default(false);

      //Company Settings
      $table->string('company_name')->nullable();
      $table->string('company_logo')->nullable();
      $table->string('company_address')->nullable();
      $table->string('company_phone')->nullable();
      $table->string('company_email')->nullable();
      $table->string('company_website')->nullable();
      $table->string('company_country')->nullable();
      $table->string('company_state')->nullable();
      $table->string('company_city')->nullable();
      $table->string('company_zipcode')->nullable();
      $table->string('company_tax_id')->nullable();
      $table->string('company_reg_no')->nullable();

      $table->enum('branding_type', ['logo', 'text', 'both'])->default('both');


      //Prefix and Suffix
      $table->string('employee_code_prefix')->default('EMP')->nullable();
      $table->string('employee_code_suffix')->nullable();

      $table->string('product_code_prefix', 5)->default('PRD')->nullable();
      $table->string('product_code_suffix', 5)->nullable();

      $table->string('category_code_prefix', 5)->default('CAT')->nullable();
      $table->string('category_code_suffix', 5)->nullable();

      $table->string('order_prefix')->default('FM_ORD');
      $table->string('order_suffix')->nullable();

      $table->string('leave_type_code_prefix', 5)->default('LVT')->nullable();
      $table->string('leave_type_code_suffix', 5)->nullable();

      $table->string('expense_type_code_prefix', 5)->default('EXT')->nullable();
      $table->string('expense_type_code_suffix', 5)->nullable();

      $table->string('team_code_prefix', 5)->default('TM')->nullable();
      $table->string('team_code_suffix', 5)->nullable();

      $table->string('location_code_prefix', 5)->default('LOC')->nullable();
      $table->string('location_code_suffix', 5)->nullable();

      $table->string('department_code_prefix', 5)->default('DEP')->nullable();
      $table->string('department_code_suffix', 5)->nullable();

      $table->string('designation_code_prefix', 5)->default('DES')->nullable();
      $table->string('designation_code_suffix', 5)->nullable();

      $table->string('payroll_code_prefix', 5)->default('PAY')->nullable();
      $table->string('payroll_code_suffix', 5)->nullable();

      $table->string('shift_code_prefix', 5)->default('SHF')->nullable();
      $table->string('shift_code_suffix', 5)->nullable();

      $table->string('holiday_code_prefix', 5)->default('HOL')->nullable();
      $table->string('holiday_code_suffix', 5)->nullable();

      $table->string('document_type_code_prefix', 5)->default('PRT')->nullable();
      $table->string('document_type_code_suffix', 5)->nullable();

      $table->string('sales_target_code_prefix', 5)->default('ST')->nullable();
      $table->string('sales_target_code_suffix', 5)->nullable();

      $table->string('tenant_id', 191)->nullable();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('settings');
  }
};
