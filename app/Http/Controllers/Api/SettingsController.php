<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use ModuleConstants;
use Nwidart\Modules\Facades\Module;

class SettingsController extends Controller
{
  public function getAppSettings()
  {
    $settings = Settings::first();

    $response = [
      'appVersion' => $settings->app_version,
      'locationUpdateIntervalType' => $settings->m_location_update_time_type == 'seconds' ? 's' : 'm',
      'locationUpdateInterval' => $settings->m_location_update_interval,
      'locationDistanceFilter' => $settings->m_location_update_interval,
      'privacyPolicyUrl' => $settings->privacy_policy_url,
      'currency' => $settings->currency,
      'currencySymbol' => $settings->currency_symbol,
      'distanceUnit' => $settings->distance_unit,
      'countryPhoneCode' => $settings->phone_country_code,
      'supportEmail' => $settings->support_email,
      'supportPhone' => $settings->support_phone,
      'supportWhatsapp' => $settings->support_whatsapp,
      'website' => $settings->website,
      'companyName' => $settings->company_name,
      'companyLogo' => $settings->company_logo ? asset('images/' . $settings->company_logo) : null,
      'companyAddress' => $settings->company_address,
      'companyPhone' => $settings->company_phone,
      'companyEmail' => $settings->company_email,
      'companyWebsite' => $settings->company_website,
      'companyCountry' => $settings->company_country,
      'companyState' => $settings->company_state,
    ];
    return Success::response($response);
  }

  public function getModuleSettings()
  {
    $response = [
      'isExpenseModuleEnabled' => true,
      'isLeaveModuleEnabled' => true,
      'isChatModuleEnabled' => true,
      'isClientVisitModuleEnabled' => true,
      'isSosModuleEnabled' => true,
      'isBiometricVerificationModuleEnabled' => false,
      'isProductModuleEnabled' => Module::has(ModuleConstants::PRODUCT_ORDER) && Module::isEnabled(ModuleConstants::PRODUCT_ORDER),
      'isTaskModuleEnabled' => Module::has(ModuleConstants::TASK_SYSTEM) && Module::isEnabled(ModuleConstants::TASK_SYSTEM),
      'isNoticeModuleEnabled' => Module::has(ModuleConstants::NOTICE_BOARD) && Module::isEnabled(ModuleConstants::NOTICE_BOARD),
      'isDynamicFormModuleEnabled' => Module::has(ModuleConstants::DYNAMIC_FORMS) && Module::isEnabled(ModuleConstants::DYNAMIC_FORMS),
      'isDocumentModuleEnabled' => Module::has(ModuleConstants::DOCUMENT) && Module::isEnabled(ModuleConstants::DOCUMENT),
      'isLoanModuleEnabled' => Module::has(ModuleConstants::LOAN_MANAGEMENT) && Module::isEnabled(ModuleConstants::LOAN_MANAGEMENT),
      'isAiChatModuleEnabled' => Module::has(ModuleConstants::AI_CHATBOT) && Module::isEnabled(ModuleConstants::AI_CHATBOT),
      'isPaymentCollectionModuleEnabled' => Module::has(ModuleConstants::PAYMENT_COLLECTION) && Module::isEnabled(ModuleConstants::PAYMENT_COLLECTION),
      'isGeofenceModuleEnabled' => Module::has(ModuleConstants::GEOFENCE) && Module::isEnabled(ModuleConstants::GEOFENCE),
      'isIpBasedAttendanceModuleEnabled' => Module::has(ModuleConstants::IP_ADDRESS_ATTENDANCE) && Module::isEnabled(ModuleConstants::IP_ADDRESS_ATTENDANCE),
      'isUidLoginModuleEnabled' => Module::has(ModuleConstants::UID_LOGIN) && Module::isEnabled(ModuleConstants::UID_LOGIN),
      'isOfflineTrackingModuleEnabled' => Module::has(ModuleConstants::OFFLINE_TRACKING) && Module::isEnabled(ModuleConstants::OFFLINE_TRACKING),
      'isQrCodeAttendanceModuleEnabled' => Module::has(ModuleConstants::QR_ATTENDANCE) && Module::isEnabled(ModuleConstants::QR_ATTENDANCE),
      'isDynamicQrCodeAttendanceEnabled' => Module::has(ModuleConstants::DYNAMIC_QR_ATTENDANCE) && Module::isEnabled(ModuleConstants::DYNAMIC_QR_ATTENDANCE),
      'isBreakModuleEnabled' => Module::has(ModuleConstants::BREAK) && Module::isEnabled(ModuleConstants::BREAK),
      'isSiteModuleEnabled' => Module::has(ModuleConstants::SITE_ATTENDANCE) && Module::isEnabled(ModuleConstants::SITE_ATTENDANCE),
      'isDataImportExportModuleEnabled' => Module::has(ModuleConstants::DATA_IMPORT_EXPORT) && Module::isEnabled(ModuleConstants::DATA_IMPORT_EXPORT),
      'isPayrollModuleEnabled' => Module::has(ModuleConstants::PAYROLL) && Module::isEnabled(ModuleConstants::PAYROLL),
      'isSalesTargetModuleEnabled' => Module::has(ModuleConstants::SALES_TARGET) && Module::isEnabled(ModuleConstants::SALES_TARGET),
      'isDigitalIdCardModuleEnabled' => Module::has(ModuleConstants::DIGITAL_ID_CARD) && Module::isEnabled(ModuleConstants::DIGITAL_ID_CARD),
      'isApprovalModuleEnabled' => Module::has(ModuleConstants::APPROVALS) && Module::isEnabled(ModuleConstants::APPROVALS),
      'isRecruitmentModuleEnabled' => Module::has(ModuleConstants::RECRUITMENT) && Module::isEnabled(ModuleConstants::RECRUITMENT),
      'isCalendarModuleEnabled' => Module::has(ModuleConstants::CALENDAR) && Module::isEnabled(ModuleConstants::CALENDAR),
    ];
    return Success::response($response);
  }
}
