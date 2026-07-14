<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\AttendanceLog;
use App\Models\FormEntry;
use App\Models\LeaveRequest;
use App\Models\ProductOrder;
use App\Models\Settings;
use App\Models\Shift;
use App\Models\UserDevice;
use App\Notifications\Attendance\CheckInOut;
use Carbon\Carbon;
use App\Constants\Constants as AppConstants;
use Exception;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{

  public function getHistory(Request $request)
  {
    $skip = $request->skip;
    $take = $request->take ?? 10;

    $attendances = Attendance::query()
      ->where('user_id', auth()->id())
      ->whereNot('check_out_time', null)
      ->orderBy('created_at', 'desc');

    if ($request->has('startDate')) {
      try {
        $fromDate = Carbon::createFromFormat('d-m-Y', $request->startDate)->format('Y-m-d');
        $attendances = $attendances->whereDate('created_at', '>=', $fromDate);
      } catch (Exception $e) {
        return Error::response('Invalid startDate format. Expected dd-MM-yyyy');
      }
    }

    if ($request->has('endDate')) {
      try {
        $toDate = Carbon::createFromFormat('d-m-Y', $request->endDate)->format('Y-m-d');
        $attendances = $attendances->whereDate('created_at', '<=', $toDate);
      } catch (Exception $e) {
        return Error::response('Invalid endDate format. Expected dd-MM-yyyy');
      }
    }

    $totalCount = $attendances->count();

    $attendances = $attendances->skip($skip)->take($take)->get();

    $finalAttendances = [];
    foreach ($attendances as $attendance) {
      $finalAttendances[] = [
        'date' => $attendance->created_at->format(AppConstants::DateFormat),
        'checkInTime' => $attendance->check_in_time->format(AppConstants::TimeFormat),
        'checkOutTime' => $attendance->check_out_time->format(AppConstants::TimeFormat),
        'totalHours' => round($attendance->check_in_time->diffInHours($attendance->check_out_time), 2),
        'shift' => $attendance->shift->name,
        'status' => $attendance->status,
        'lateReason' => $attendance->late_reason,
        'earlyCheckoutReason' => $attendance->early_checkout_reason,
        'visitCount' => $attendance->visits->count(),
        'ordersCount' => ProductOrder::where('user_id', auth()->user()->id)
          ->whereDate('created_at', $attendance->created_at)
          ->count(),
        'formsSubmissionCount' => FormEntry::where('user_id', auth()->user()->id)
          ->whereDate('created_at', $attendance->created_at)
          ->count(),
        'distanceTravelled' => 0.00
      ];
    }

    return Success::response([
      'totalCount' => $totalCount,
      'values' => $finalAttendances
    ]);
  }

  public function setEarlyCheckoutReason(Request $request)
  {
    $reason = $request->reason;

    if ($reason == null || $reason == '') {
      return Error::response('Reason is required');
    }

    $attendance = Attendance::where('user_id', auth()->user()->id)
      ->whereDate('created_at', Carbon::today())
      ->first();

    if ($attendance == null) {
      return Error::response('Not checked in');
    }

    $attendance->early_checkout_reason = $reason;
    $attendance->save();

    return Success::response('Reason updated successfully');
  }

  public function canCheckOut()
  {
    $attendance = Attendance::where('user_id', auth()->user()->id)
      ->whereDate('created_at', Carbon::today())
      ->first();

    if ($attendance == null) {
      return Error::response('Not checked in');
    }

    $shift = Shift::find(auth()->user()->shift_id);

    if ($shift == null) {
      return Error::response('Shift not found');
    }

    if ($shift->end_time < now()) {
      return Success::response('You can check out');
    } else {
      return Error::response('You can not check out before shift end time');
    }
  }

  public function checkStatus()
  {
    $user = auth()->user();

    $device = UserDevice::where('user_id', $user->id)
      ->first();

    $attendance = Attendance::where('user_id', $user->id)
      ->whereDate('created_at', Carbon::today())
      ->with('attendanceLogs')
      ->first();

    $shift = Shift::find($user->shift_id);

    if($shift == null){
      return Error::response('Shift not found');
    }

    $status = 'new';
    $checkInTime = null;
    $checkOutTime = null;
    $trackedHours = 0.0;
    $travelledDistance = 0.0;

    if ($attendance) {
      if ($attendance->status == 'checked_in') {
        $status = 'checkedin';
        //Check in time only
        $date = strtotime($attendance->check_in_time);
        $checkInTime = date('h:i A', $date);
        //$trackedHours = (now() - $attendance->check_in_time) / 3600;
      } else if ($attendance->status == 'checked_out' && $user->isMultiCheckInOutEnabled()) {
        $status = 'new';
        $checkInTime = null;
        $checkOutTime = null;
      } else {
        $status = 'checkedout';
        //Check in and check out time
        $date = strtotime($attendance->check_in_time);
        $checkInTime = date('h:i A', $date);

        $date = strtotime($attendance->check_out_time);
        $checkOutTime = date('h:i A', $date);
      }
    }

    $isLate = false;

    //Late check
    if ($status == 'new' && now() > $shift->start_time) {
      $isLate = true;
    }

    //Leave Check
    $isOnLeave = false;
    $leave = LeaveRequest::where('user_id', $user->id)
      ->where('status', 'approved')
      ->where('from_date', '<=', Carbon::today())
      ->where('to_date', '>=', Carbon::today())
      ->first();

    if ($leave != null) {
      $isOnLeave = true;
    }

    //Break checking
    $isOnBreak = false;
    $breakStartedAt = '';
    if ($attendance && $attendance->status == 'checked_in') {
      $break = AttendanceBreak::where('attendance_log_id', $attendance->todaysLatestAttendanceLog()->id)
        ->whereNull('end_time')
        ->first();

      if ($break != null) {
        $isOnBreak = true;
        $date = strtotime($break->start_time);
        $breakStartedAt = date('h:i:s A', $date);
      }
    }

    $attendanceType = $this->getAttendanceTypeString($user->attendance_type);

    $shiftStartTime = date('h:i A', strtotime($shift->start_time));
    $shiftEndTime = date('h:i A', strtotime($shift->end_time));

    return Success::response([
      'attendanceType' => $attendanceType == 'site' ? $this->getAttendanceTypeString($user->site->attendance_type) : $attendanceType,
      'userStatus' => $user->status,
      'status' => $status, // 'new', 'present', 'checkedout
      'checkInAt' => $checkInTime,
      'checkOutAt' => $checkOutTime,
      'shiftStartTime' => $shiftStartTime,
      'shiftEndTime' => $shiftEndTime,
      'isLate' => $isLate,
      'isOnBreak' => $isOnBreak,
      'breakStartedAt' => $breakStartedAt,
      'isOnLeave' => $isOnLeave,
      'travelledDistance' => $travelledDistance,
      'trackedHours' => $trackedHours,
      'isSiteEmployee' => $attendanceType == 'site',
      'siteName' => $attendanceType == 'site' ? $user->site->name : '',
      'siteAttendanceType' => $attendanceType == 'site' ? $this->getAttendanceTypeString($user->site->attendance_type) : '',
      'deviceStatus' => $device ? 'active' : 'kill',
    ]);
  }

  private function getAttendanceTypeString($type)
  {
    $attendanceType = 'none';
    if ($type == 'geofence') {
      $attendanceType = 'geofence';
    } else if ($type == 'site') {
      $attendanceType = 'site';
    } else if ($type == 'ip_address') {
      $attendanceType = 'ip';
    } else if ($type == 'qr_code') {
      $attendanceType = 'staticqrcode';
    } else if ($type == 'dynamic_qr') {
      $attendanceType = 'dynamicqrcode';
    }else if($type == 'face_recognition'){
      $attendanceType = 'face';
    }

    return $attendanceType;
  }

  public function checkInOut(Request $request)
  {
    $status = $request->status;
    $latitude = $request->latitude;
    $longitude = $request->longitude;

    if ($status == null) {
      return Error::response('Status is required');
    }

    if ($status != 'checkin' && $status != 'checkout') {
      return Error::response('Invalid status');
    }

    if ($latitude == null || $longitude == null) {
      return Error::response('Location is required');
    }

    if ($status == 'checkin') {
      return $this->checkIn($request);
    } else {
      return $this->checkOut($request);
    }
  }

  private function checkIn($request)
  {
    $user = auth()->user();

    $attendance = Attendance::where('user_id', $user->id)
      ->whereDate('created_at', now())
      ->first();

    if (!$attendance) {
      $attendance = Attendance::create([
        'user_id' => $user->id,
        'check_in_time' => date('Y-m-d H:i:s'),
        'status' => 'checked_in',
        'site_id' => $user->attendance_type == 'site' ? $user->site_id : null,
        'late_reason' => $request->lateReason,
        'shift_id' => $user->shift_id,
        'created_by_id' => $user->id
      ]);
    } else if ($attendance->status == 'checked_out' && $user->isMultiCheckInOutEnabled()) {
      $attendance->status = 'checked_in';
      $attendance->check_in_time = date('Y-m-d H:i:s');
      $attendance->save();
    } else {
      return Error::response('Already done for the day');
    }

    $log = new AttendanceLog();
    $log->attendance_id = $attendance->id;
    $log->shift_id = $user->shift_id;
    $log->type = 'check_in';
    $log->latitude = $request->latitude;
    $log->longitude = $request->longitude;
    $log->created_by_id = $user->id;
    $log->save();


    Activity::create([
      'attendance_log_id' => $log->id,
      'latitude' => $request->latitude,
      'longitude' => $request->longitude,
      'is_mock' => $request->isMock,
      'battery_percentage' => $request->batteryPercentage,
      'is_gps_on' => $request->isGpsOn ?? true,
      'is_wifi_on' => $request->isWifiOn,
      'signal_strength' => $request->signalStrength,
      'type' => 'checked_in',
      'created_by_id' => $user->id,
      'accuracy' => 100,
      'bearing' => $request->bearing,
      'horizontalAccuracy' => $request->horizontalAccuracy,
      'altitude' => $request->altitude,
      'verticalAccuracy' => $request->verticalAccuracy,
      'course' => $request->course,
      'courseAccuracy' => $request->courseAccuracy,
      'speed' => $request->speed,
      'speedAccuracy' => $request->speedAccuracy,
    ]);


    NotificationHelper::notifyAdminHR(new CheckInOut('Attendance Check In', $user->getFullName() . ' has checked in'));


    return Success::response('Checked in successfully');
  }

  private function checkOut($request)
  {
    $user = auth()->user();

    $attendance = Attendance::whereDate('created_at', Carbon::today())
      ->where('user_id', $user->id)
      ->first();

    if (!$attendance) {
      return Error::response('Not checked in');
    }

    if ($attendance->status == 'checked_out') {
      return Error::response('Already checked out');
    }

    //Check Out
    $attendance->status = 'checked_out';
    $attendance->check_out_time = date('Y-m-d H:i:s');
    $attendance->save();


    $log = new AttendanceLog();
    $log->attendance_id = $attendance->id;
    $log->shift_id = $user->shift_id;
    $log->type = 'check_out';
    $log->latitude = $request->latitude;
    $log->longitude = $request->longitude;
    $log->created_by_id = $user->id;
    $log->save();

    Activity::create([
      'attendance_log_id' => $log->id,
      'latitude' => $request->latitude,
      'longitude' => $request->longitude,
      'is_mock' => $request->isMock,
      'battery_percentage' => $request->batteryPercentage,
      'is_gps_on' => $request->isGpsOn ?? true,
      'is_wifi_on' => $request->isWifiOn,
      'signal_strength' => $request->signalStrength,
      'type' => 'checked_out',
      'accuracy' => 100,
      'bearing' => $request->bearing,
      'horizontalAccuracy' => $request->horizontalAccuracy,
      'altitude' => $request->altitude,
      'verticalAccuracy' => $request->verticalAccuracy,
      'course' => $request->course,
      'courseAccuracy' => $request->courseAccuracy,
      'speed' => $request->speed,
      'speedAccuracy' => $request->speedAccuracy
    ]);


    NotificationHelper::notifyAdminHR(new CheckInOut('Attendance Check Out', $user->getFullName() . ' has checked out'));

    return Success::response('Checked out successfully');
  }

  public function statusUpdate(Request $request)
  {
    $status = $request->status;
    $accuracy = $request->accuracy;
    $activity = $request->activity;
    $latitude = $request->latitude;
    $longitude = $request->longitude;
    $isMock = $request->isMock;
    $batteryPercentage = $request->batteryPercentage;
    $isGpsOn = $request->isGPSOn;
    $isWifiOn = $request->isWifiOn;
    $signalStrength = $request->signalStrength;


    if ($status == null) {
      return Error::response('Status is required');
    }

    if (!$latitude || !$longitude) {
      return Error::response('Location is required');
    }

    $attendanceLog = AttendanceLog::where('created_by_id', auth()->id())
      ->whereDate('created_at', Carbon::today())
      ->latest()
      ->first();

    if (!$attendanceLog) {
      return Error::response('Attendance not found');
    }

    if ($attendanceLog->type != 'check_in') {
      return Error::response('You are not checked in');
    }


    Activity::create([
      'uid' => $request->uid,
      'attendance_log_id' => $attendanceLog->id,
      'is_mock' => $isMock,
      'battery_percentage' => $batteryPercentage,
      'is_gps_on' => $isGpsOn,
      'is_wifi_on' => $isWifiOn,
      'signal_strength' => $signalStrength,
      'type' => $status == 'still' ? 'still' : 'travelling',
      'activity' => $activity,
      'accuracy' => $accuracy,
      'latitude' => $latitude,
      'longitude' => $longitude,
      'bearing' => $request->bearing,
      'horizontalAccuracy' => $request->horizontalAccuracy,
      'altitude' => $request->altitude,
      'verticalAccuracy' => $request->verticalAccuracy,
      'course' => $request->course,
      'courseAccuracy' => $request->courseAccuracy,
      'speed' => $request->speed,
      'speedAccuracy' => $request->speedAccuracy,
      'created_by_id' => auth()->id()
    ]);

    return Success::response('Status updated successfully');
  }
}
