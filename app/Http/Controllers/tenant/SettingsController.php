<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\PayrollAdjustment;
use App\Models\Settings;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
  public function index()
  {
    $settings = Settings::first();

    $payrollAdjustments = PayrollAdjustment::where('user_id', null)
      ->get();

    return view('tenant.settings.index', [
      'settings' => $settings,
      'payrollAdjustments' => $payrollAdjustments,
    ]);
  }

  public function updateAiSettings(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    try {
      $validatedData = $request->validate([
        'chat_gpt_key' => 'nullable|string|max:500',
        'enable_ai_chat_global' => 'nullable',
        'enable_ai_for_admin' => 'nullable',
        'enable_ai_for_employee_self_service' => 'nullable',
        'enable_ai_for_business_intelligence' => 'nullable',
      ]);

      $settings = Settings::first();

      $settings->chat_gpt_key = $validatedData['chat_gpt_key'] ?? null;
      $settings->enable_ai_chat_global = $request->has('enable_ai_chat_global');
      $settings->enable_ai_for_admin = $request->has('enable_ai_for_admin');
      $settings->enable_ai_for_employee_self_service = $request->has('enable_ai_for_employee_self_service');
      $settings->enable_ai_for_business_intelligence = $request->has('enable_ai_for_business_intelligence');

      $settings->save();


      return redirect()->back()->with('success', 'AI Settings updated successfully!');
    } catch (Exception $e) {
      Log::error('Error updating AI settings: ' . $e->getMessage());
      return redirect()->back()->with('error', 'Failed to update AI settings.');
    }
  }

  public function addOrUpdatePayrollAdjustment(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $validated = $request->validate([
      'id' => 'nullable|exists:payroll_adjustments,id',
      'adjustmentName' => 'required|string|max:255',
      'adjustmentCode' => 'required|string|max:191',
      'adjustmentType' => 'required|in:benefit,deduction',
      'adjustmentAmount' => 'nullable|numeric|min:0',
      'adjustmentPercentage' => 'nullable|numeric|min:0|max:100',
      'adjustmentNotes' => 'nullable|string|max:1000',
    ]);

    try {
      PayrollAdjustment::updateOrCreate(
        ['id' => $validated['id']],
        [
          'name' => $validated['adjustmentName'],
          'code' => $validated['adjustmentCode'],
          'type' => $validated['adjustmentType'],
          'applicability' => 'global',
          'amount' => $validated['adjustmentAmount'] ?? 0,
          'percentage' => $validated['adjustmentPercentage'],
          'notes' => $validated['adjustmentNotes'],
          'updated_by_id' => auth()->id(),
        ]
      );

      return redirect()->back()->with('success', __('Payroll adjustment saved successfully.'));
    } catch (Exception $e) {
      Log::error('Payroll Adjustment Error: ' . $e->getMessage());
      return redirect()->back()->with('error', __('Failed to save payroll adjustment.'));
    }
  }

  public function deletePayrollAdjustment($id)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $validated = validator(['id' => $id], ['id' => 'required|exists:payroll_adjustments,id'])->validate();

    $payrollAdjustment = PayrollAdjustment::find($validated['id']);

    if ($payrollAdjustment) {
      $payrollAdjustment->delete();
    }

    return redirect()->back()->with('success', 'Payroll adjustment deleted successfully');
  }

  public function updatePayrollSettings(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $request->validate([
      'payrollFrequency' => 'required|in:weekly,bi-weekly,monthly,daily',
      'payrollStartDate' => 'required|numeric|min:1|max:31',
      'payrollCutoffDate' => 'required|numeric|min:1|max:31',
      'autoPayrollProcessing' => 'nullable',
    ]);

    $settings = Settings::first();
    $settings->payroll_frequency = $request->payrollFrequency;
    $settings->payroll_start_date = $request->payrollStartDate;
    $settings->payroll_cutoff_date = $request->payrollCutoffDate;

    if ($request->has('autoPayrollProcessing') && $request->autoPayrollProcessing == 'on') {
      $settings->auto_payroll_processing = true;
    } else {
      $settings->auto_payroll_processing = false;
    }

    $settings->save();

    return redirect()->back()->with('success', 'Settings updated successfully');
  }

  public function updateCompanySettings(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $request->validate([
      'company_name' => 'required',
      'company_logo' => 'nullable|image|max:2048',
      'company_address' => 'nullable',
      'company_phone' => 'nullable',
      'company_email' => 'nullable|email',
      'company_website' => 'nullable',
      'company_country' => 'nullable',
      'company_city' => 'nullable',
      'company_zipcode' => 'nullable',
      'company_state' => 'nullable',
    ]);

    $settings = Settings::first();
    $settings->fill($request->except('company_logo'));

    if ($request->hasFile('company_logo')) {

      if ($settings->company_logo && Storage::disk('public')->exists('images/' . $settings->company_logo)) {
        Storage::disk('public')->delete('images/' . $settings->company_logo);
      }

      Storage::disk('public')->putFileAs('images/', $request->file('company_logo'), 'app_logo.png');
      $settings->company_logo = 'app_logo.png';
    }

    $settings->save();

    return redirect()->back()->with('success', 'Company settings updated successfully');
  }

  public function updateGeneralSettings(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $request->validate([
      'appName' => 'required',
      'country' => 'required',
      'phoneCountryCode' => 'required',
      'currency' => 'required',
      'currencySymbol' => 'required',
      'distanceUnit' => 'required',
      'isHelperTextEnabled' => 'nullable',
    ]);


    $settings = Settings::first();

    if ($settings->app_name != $request->appName) {
      $settings->app_name = $request->appName;
    }

    if ($settings->country != $request->country) {
      $settings->country = $request->country;
    }

    if ($settings->phone_country_code != $request->phoneCountryCode) {
      $settings->phone_country_code = $request->phoneCountryCode;
    }

    if ($settings->currency != $request->currency) {
      $settings->currency = $request->currency;
    }

    if ($settings->currency_symbol != $request->currencySymbol) {
      $settings->currency_symbol = $request->currencySymbol;
    }

    if ($settings->distance_unit != $request->distanceUnit) {
      $settings->distance_unit = $request->distanceUnit;
    }

    if ($request->isHelperTextEnabled == 'on') {
      $settings->is_helper_text_enabled = true;
    } else {
      $settings->is_helper_text_enabled = false;
    }

    $settings->save();

    return redirect()->back()->with('success', 'Settings updated successfully');
  }

  public function updateAppSettings(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $request->validate([
      'mAppVersion' => 'required',
      'locationDistanceFilter' => 'required',
    ]);

    $settings = Settings::first();

    if ($settings->m_app_version != $request->mAppVersion) {
      $settings->m_app_version = $request->mAppVersion;
    }

    if ($settings->m_location_distance_filter != $request->locationDistanceFilter) {
      $settings->m_location_distance_filter = $request->locationDistanceFilter;
    }

    $settings->save();

    return redirect()->back()->with('success', 'Settings updated successfully');
  }

  public function updateTrackingSettings(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $request->validate([
      'offlineCheckTime' => 'required',
    ]);

    $settings = Settings::first();

    if ($settings->offline_check_time != $request->offlineCheckTime) {
      $settings->offline_check_time = $request->offlineCheckTime;
    }

    $settings->save();

    return redirect()->back()->with('success', 'Settings updated successfully');
  }

  public function updateEmployeeSettings(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $request->validate([
      'isBioMetricVerificationEnabled' => 'nullable',
      'isDeviceVerificationEnabled' => 'nullable',
      'defaultPassword' => 'required|min:6',
    ]);

    $settings = Settings::first();

    if ($request->isBioMetricVerificationEnabled == 'on') {
      $settings->is_biometric_verification_enabled = true;
    } else {
      $settings->is_biometric_verification_enabled = false;
    }

    if ($request->isDeviceVerificationEnabled == 'on') {
      $settings->is_device_verification_enabled = true;
    } else {
      $settings->is_device_verification_enabled = false;
    }

    if ($settings->default_password != $request->defaultPassword) {
      $settings->default_password = $request->defaultPassword;
    }

    $settings->save();

    return redirect()->back()->with('success', 'Settings updated successfully');

  }

  public function updateMapSettings(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $request->validate([
      'mapProvider' => 'required',
      'mapApiKey' => 'required',
      'mapZoomLevel' => 'required',
      'centerLatitude' => 'required',
      'centerLongitude' => 'required',
    ]);

    $settings = Settings::first();

    if ($settings->map_provider != $request->mapProvider) {
      $settings->map_provider = $request->mapProvider;
    }

    if ($settings->map_api_key != $request->mapApiKey) {
      $settings->map_api_key = $request->mapApiKey;
    }

    if ($settings->map_zoom_level != $request->mapZoomLevel) {
      $settings->map_zoom_level = $request->mapZoomLevel;
    }

    if ($settings->center_latitude != $request->centerLatitude) {
      $settings->center_latitude = $request->centerLatitude;
    }

    if ($settings->center_longitude != $request->centerLongitude) {
      $settings->center_longitude = $request->centerLongitude;
    }

    $settings->save();

    return redirect()->back()->with('success', 'Settings updated successfully');

  }


  private function setTimeZone($zone)
  {
    /*    // Set Laravel's app timezone
        config(['app.timezone' => $zone]);

        // Set PHP's default timezone
        date_default_timezone_set($zone);
        */

  }
}
