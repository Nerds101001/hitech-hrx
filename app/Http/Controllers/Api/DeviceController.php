<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\DeviceStatusLog;
use App\Models\Settings;
use App\Models\UserDevice;
use App\Notifications\GPSAlert;
use App\Notifications\LowBatteryAlert;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
  public function checkDevice(Request $request)
  {
    $deviceId = $request->deviceId;
    $deviceType = $request->deviceType;

    if (!$deviceId || !$deviceType) {
      return Error::response('Device id or device type is missing');
    }

    // Device verification check
    $isDeviceVerificationEnabled = Settings::first()->is_device_verification_enabled;


    // Fetch user's registered device
    $userDevice = UserDevice::where('user_id', auth()->id())->first();

    // If the user has a registered device
    if ($userDevice) {
      if ($userDevice->device_id === $deviceId && $userDevice->device_type === $deviceType) {
        return Success::response('Device verified successfully');
      }

      if (!$isDeviceVerificationEnabled) {
        return Success::response('Device verification is disabled');
      }

      return Error::response('This account is already registered with another device');
    }

    if (!$isDeviceVerificationEnabled) {
      return Success::response('Device verification is disabled');
    }

    // Check if the device is already registered with another user
    $isDeviceRegistered = UserDevice::where('device_id', $deviceId)
      ->where('device_type', $deviceType)
      ->exists();

    if ($isDeviceRegistered) {
      return Error::response('This device is already registered with another user');
    }

    return Success::response('not registered');
  }

  public function registerDevice(Request $request)
  {
    $deviceId = $request->deviceId;
    $deviceType = $request->deviceType;
    $brand = $request->brand;
    $board = $request->board;
    $sdkVersion = $request->sdkVersion;
    $model = $request->model;

    if (!$deviceId) {
      return Error::response('Device id is required');
    }

    if (!$deviceType) {
      return Error::response('Device type is required');
    }

    if (!(strtolower($deviceType) == 'android' || strtolower($deviceType) == 'ios')) {
      return Error::response('Invalid device type');
    }

    $oldDevice = UserDevice::where('user_id', auth()->id())
      ->first();

    if ($oldDevice) {
      $oldDevice->delete();
    }

    $device = new UserDevice();
    $device->user_id = auth()->user()->id;
    $device->device_id = $deviceId;
    $device->device_type = $deviceType;
    $device->brand = $brand;
    $device->board = $board;
    $device->sdk_version = $sdkVersion;
    $device->model = $model;
    $device->app_version = $request->appVersion;
    $device->token = '';
    $device->latitude = 0;
    $device->longitude = 0;
    $device->save();

    return Success::response('Device registered successfully');
  }

  public function messagingToken(Request $request)
  {

    $token = $request->Token;
    $deviceType = $request->DeviceType;

    if ($token == null) {
      return Error::response('Token is required');
    }

    if ($deviceType == null) {
      return Error::response('Device type is required');
    }

    $device = UserDevice::where('user_id', auth()->user()->id)
      ->first();

    if ($device == null) {
      return Error::response('Device not registered');
    }
    $device->token = $token;
    $device->save();

    return Success::response('Token saved successfully');
  }

  public function updateDeviceStatus(Request $request)
  {

    $batteryPercentage = $request->batteryPercentage;
    $isGpsOn = $request->isGPSOn;
    $isWifiOn = $request->isWifiOn;
    $isMock = $request->isMock ?? false;
    $signalStrength = $request->signalStrength;
    $latitude = $request->latitude;
    $longitude = $request->longitude;

    $device = UserDevice::where('user_id', auth()->id())
      ->first();

    if ($device == null) {
      return Error::response('Device not registered');
    }

    if (!$latitude || !$longitude) {
      return Error::response('Location is required');
    }

    $device->battery_percentage = $batteryPercentage;
    $device->is_gps_on = $isGpsOn;
    $device->is_wifi_on = $isWifiOn;
    $device->is_mock = $isMock;
    $device->signal_strength = $signalStrength;
    $device->latitude = $latitude;
    $device->longitude = $longitude;
    $device->bearing = $request->bearing;
    $device->is_charging = $request->isCharging;
    $device->horizontalAccuracy = $request->horizontalAccuracy;
    $device->altitude = $request->altitude;
    $device->verticalAccuracy = $request->verticalAccuracy;
    $device->course = $request->course;
    $device->courseAccuracy = $request->courseAccuracy;
    $device->speed = $request->speed;
    $device->speedAccuracy = $request->speedAccuracy;
    $device->save();

    $this->createDeviceLog($device, $request->uid);

    if (!$isGpsOn) {
      NotificationHelper::notifyAdminHR(new GPSAlert($device->user->getFullName(), 'off'));
    }

    // if ($batteryPercentage < 20) {
    //   NotificationHelper::notifyAdminHR(new LowBatteryAlert($device->user->getFullName(), $batteryPercentage));
    // }

    return Success::response('Status updated successfully');
  }

  private function createDeviceLog(UserDevice $device, $uid = null)
  {
    $attendanceLog = AttendanceLog::where('created_by_id', $device->user_id)
      ->latest()
      ->first();

    if (!$attendanceLog) {
      return Error::response('Attendance not found');
    }

    if ($attendanceLog->type != 'check_in') {
      return Error::response('You are not checked in');
    }

    $deviceLog = new DeviceStatusLog();
    $deviceLog->uid = $uid;
    $deviceLog->attendance_log_id = $attendanceLog->id;
    $deviceLog->device_id = $device->id;
    $deviceLog->user_id = $device->user_id;
    $deviceLog->device_type = $device->device_type;
    $deviceLog->brand = $device->brand;
    $deviceLog->board = $device->board;
    $deviceLog->sdk_version = $device->sdk_version;
    $deviceLog->model = $device->model;
    $deviceLog->token = $device->token;
    $deviceLog->app_version = $device->app_version;

    $deviceLog->battery_percentage = $device->battery_percentage;
    $deviceLog->is_gps_on = $device->is_gps_on;
    $deviceLog->is_wifi_on = $device->is_wifi_on;
    $deviceLog->is_mock = $device->is_mock;
    $deviceLog->signal_strength = $device->signal_strength;
    $deviceLog->address = $device->address;
    $deviceLog->is_charging = $device->is_charging;

    //Location Info
    $deviceLog->latitude = $device->latitude;
    $deviceLog->longitude = $device->longitude;
    $deviceLog->bearing = $device->bearing;
    $deviceLog->horizontalAccuracy = $device->horizontalAccuracy;
    $deviceLog->altitude = $device->altitude;
    $deviceLog->verticalAccuracy = $device->verticalAccuracy;
    $deviceLog->course = $device->course;
    $deviceLog->courseAccuracy = $device->courseAccuracy;
    $deviceLog->speed = $device->speed;
    $deviceLog->speedAccuracy = $device->speedAccuracy;
    $deviceLog->created_by_id = auth()->id();
    //Location Info End
    $deviceLog->save();
  }

  public function validateDevice(Request $request)
  {
    $deviceId = $request->deviceId;
    $deviceType = $request->deviceType;

    if (!$deviceId || !$deviceType) {
      return Error::response('Device id or device type is missing');
    }

    $userDevice = UserDevice::where('user_id', auth()->id())->first();

    // If the user has a registered device
    if ($userDevice) {
      if ($userDevice->device_id === $deviceId && $userDevice->device_type === $deviceType) {
        return Success::response('Valid');
      }
    }

    return Error::response('Invalid');
  }

}
