<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Settings extends Model implements AuditableContract
{
  use Auditable, TenantTrait;

  public $timestamps = false;
  protected $table = 'settings';
  protected $fillable = [
    'default_language',
    'app_name',
    'app_version',
    'country',
    'phone_country_code',
    'default_timezone',
    'currency',
    'currency_symbol',
    'distance_unit',
    'offline_check_time_type',
    'offline_check_time',
    'm_app_version',
    'm_location_update_time_type',
    'm_location_update_interval',
    'privacy_policy_url',
    'verify_client_number',
    'employee_code_prefix',
    'order_prefix',
    'map_provider',
    'map_zoom_level',
    'center_latitude',
    'center_longitude',
    'is_helper_text_enabled',
    'default_password',
    'is_biometric_verification_enabled',
    'is_device_verification_enabled',
    'm_location_distance_filter',
    'map_api_key',
    'available_modules',
    'employees_limit',
    'accessible_module_routes',
    'support_email',
    'support_phone',
    'support_whatsapp',
    'website',
    'is_multiple_check_in_enabled',
    'is_auto_check_out_enabled',
    'company_name',
    'company_logo',
    'company_address',
    'company_phone',
    'company_email',
    'company_website',
    'company_country',
    'company_state',
    'company_city',
    'company_zipcode',
    'company_tax_id',
    'company_reg_no',
    'payroll_frequency',
    'payroll_start_date',
    'payroll_cutoff_date',
    'auto_payroll_processing',
    'branding_type',
    'maps_key',
    'chat_gpt_key',
    'enable_ai_chat_global',
    'enable_ai_for_admin',
    'enable_ai_for_employee_self_service',
    'enable_ai_for_business_intelligence',
    'tenant_id',
  ];

  protected $casts = [
    'offline_check_time' => 'integer',
    'm_location_update_interval' => 'integer',
    'center_latitude' => 'float',
    'center_longitude' => 'float',
    'is_biometric_verification_enabled' => 'boolean',
    'is_helper_text_enabled' => 'boolean',
    'is_device_verification_enabled' => 'boolean',
    'available_modules' => 'json',
    'employees_limit' => 'integer',
    'is_multiple_check_in_enabled' => 'boolean',
    'is_auto_check_out_enabled' => 'boolean',
    'enable_ai_chat_global' => 'boolean',
    'enable_ai_for_admin' => 'boolean',
    'enable_ai_for_employee_self_service' => 'boolean',
    'enable_ai_for_business_intelligence' => 'boolean',
  ];

  //This function is used to clear cache
  protected static function boot()
  {
    parent::boot();
    static::saved(function () {
      Cache::forget('app_settings');
    });
  }

}
