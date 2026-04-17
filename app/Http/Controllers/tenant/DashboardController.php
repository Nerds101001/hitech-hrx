<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\LeaveRequestStatus;
use App\Enums\Status;
use App\Enums\UserAccountStatus;
use App\Helpers\TrackingHelper;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\DeviceStatusLog;
use App\Models\DocumentRequest;
use App\Models\ExpenseRequest;
use App\Models\FormEntry;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LoanRequest;
use App\Models\ProductOrder;
use App\Models\Settings;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Visit;
use App\Constants\Constants as AppConstants;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobStage;
use App\Models\Announcement;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
  public function index()
  {
    // Redirect all dashboard requests to the unified UserDashboardController
    return redirect()->route('user.dashboard.index');
  }

  public function getRecentActivities()
  {
    $activities = collect();

    // Fetch Orders
    $orders = ProductOrder::with('user')
      ->select('id', 'order_no', 'user_id', 'created_at')
      ->latest('created_at')
      ->limit(10)
      ->get()
      ->map(function ($order) {
      return [
      'id' => $order->id,
      'title' => $order->order_no,
      'created_at_human' => $order->created_at->diffForHumans(),
      'created_at' => $order->created_at,
      'type' => 'Order',
      'user' => $order->user ? $order->user->getUserForProfile() : 'N/A'
      ];
    });

    // Fetch Visits
    $visits = Visit::with('client')
      ->with('createdBy')
      ->select('id', 'client_id', 'created_by_id', 'created_at')
      ->latest('created_at')
      ->limit(10)
      ->get()
      ->map(function ($visit) {
      return [
      'id' => $visit->id,
      'title' => $visit->client->name ?? 'No Client Name',
      'created_at_human' => $visit->created_at->diffForHumans(),
      'created_at' => $visit->created_at,
      'type' => 'Visit',
      'user' => $visit->createdBy ? $visit->createdBy->getUserForProfile() : 'N/A'
      ];
    });

    // Fetch Form Submissions
    $forms = FormEntry::with('form')
      ->with('user')
      ->select('id', 'form_id', 'created_at')
      ->latest('created_at')
      ->limit(10)
      ->get()
      ->map(function ($form) {
      return [
      'id' => $form->id,
      'title' => $form->form->name ?? 'No Form Name',
      'created_at_human' => $form->created_at->diffForHumans(),
      'created_at' => $form->created_at,
      'type' => 'Form Submission'
      ];
    });

    // Fetch Tasks
    $tasks = Task::with('user')
      ->select('id', 'title', 'user_id', 'created_at')
      ->latest('created_at')
      ->limit(10)
      ->get()
      ->map(function ($task) {
      return [
      'id' => $task->id,
      'title' => $task->title,
      'created_at_human' => $task->created_at->diffForHumans(),
      'created_at' => $task->created_at,
      'type' => 'Task',
      'user' => $task->user ? $task->user->getUserForProfile() : 'N/A'
      ];
    });

    // Merge all collections and sort them by created_at
    $activities = $activities
      ->merge($orders)
      ->merge($visits)
      ->merge($forms)
      ->merge($tasks)
      ->sortByDesc('created_at')
      ->take(10)
      ->values();

    return Success::response($activities);
  }

  public function liveLocationView()
  {
    return view('tenant.dashboard.live_location_view', [
      'pageConfigs' => ['contentLayout' => 'wide']
    ]);
  }

  public function liveLocationAjax()
  {

    try {

      $todayAttendances = Attendance::with('user.userDevice')
        ->whereDate('created_at', '>=', now())
        ->with('user')->with('user.userDevice')
        ->get();


      $response = [];

      $settings = Settings::first();

      $trackingHelper = new TrackingHelper();
      foreach ($todayAttendances as $attendance) {

        if ($attendance->user->userDevice == null) {
          continue;
        }


        $status = 'offline';
        //  ? $status = 'online' : $status = 'offline';
        if ($trackingHelper->isUserOnline($attendance->user->userDevice->updated_at)) {
          $status = 'online';
        }

        $response[] = [
          'id' => $attendance->user_id,
          'name' => $attendance->user->getFullName(),
          'initials' => $attendance->user->getInitials(),
          'code' => $attendance->user->code,
          'profilePicture' => $attendance->user->getProfilePicture(),
          'designation' => $attendance->user->designation ? $attendance->user->designation->name : 'N/A',
          'latitude' => $attendance->user->userDevice->latitude,
          'longitude' => $attendance->user->userDevice->longitude,
          'status' => $status,
          'updatedAt' => $attendance->user->userDevice->updated_at->diffForHumans(),
          'type' => $settings->offline_check_time_type,
          'time' => $settings->offline_check_time,
        ];
      }

      return response()->json($response);
    }
    catch (Exception $e) {
      Log::error($e->getMessage());
      return response()->json($e->getMessage());
    }
  }

  public function cardView()
  {
    $teamsList = Team::where('status', 'active')
      ->get();

    $attendances = Attendance::whereDate('created_at', now())
      ->with('attendanceLogs')
      ->get();

    $trackingHelper = new TrackingHelper();

    $users = User::where('status', '=', 'active')
      ->where('team_id', '!=', null)
      ->where('shift_id', '!=', null)
      ->get();

    $userDevices = UserDevice::whereIn('user_id', $users->pluck('id'))
      ->get();

    $teams = [];
    foreach ($teamsList as $team) {

      $user = $users->where('team_id', '=', $team->id);

      $teamAttendances = $attendances->whereIn('user_id', $user->pluck('id'));

      $cardItems = [];

      foreach ($teamAttendances as $attendance) {

        $device = $userDevices
          ->where('user_id', '=', $attendance->user_id)
          ->first();

        if ($device == null || $attendance->attendanceLogs->count() == 0 || $attendance->isCheckedOut()) {
          continue;
        }


        $attendanceLogIds = $attendance->attendanceLogs->pluck('id');

        $isOnline = $trackingHelper->isUserOnline($device->updated_at);

        $visitsCount = Visit::whereIn('attendance_log_id', $attendanceLogIds)
          ->count();
        $ordersCount = ProductOrder::whereIn('attendance_log_id', $attendanceLogIds)
          ->count();

        $formsFilled = FormEntry::where('user_id', $attendance->user_id)
          ->whereDate('created_at', now())
          ->count();


        $cardItems[] = [
          'id' => $attendance->user->id,
          'name' => $attendance->user->getFullName(),
          'initials' => $attendance->user->getInitials(),
          'profilePicture' => $attendance->user->getProfilePicture(),
          'employeeCode' => $attendance->user->code,
          'phoneNumber' => $attendance->user->phone,
          'batteryLevel' => $device->battery_percentage,
          'isGpsOn' => $device->is_gps_on,
          'isWifiOn' => $device->is_wifi_on,
          'updatedAt' => $device->updated_at->diffForHumans(),
          'isOnline' => $isOnline,
          'teamId' => $attendance->user->team_id,
          'teamName' => $team->name,
          'attendanceInAt' => $attendance->check_in_time,
          'attendanceOutAt' => $attendance->check_out_time,
          'latitude' => $device->latitude,
          'longitude' => $device->longitude,
          'address' => $device->address,
          'visitsCount' => $visitsCount,
          'ordersCount' => $ordersCount,
          'formsFilled' => $formsFilled,
          'attendanceDuration' => $attendance->check_in_time && $attendance->check_out_time ? 
          $attendance->check_in_time->diff($attendance->check_out_time)->format('%H:%I:%S') : 'N/A',
        ];
      }

      if ($user->count() > 0) {

        $teams[] = [
          'id' => $team->id,
          'name' => $team->name,
          'totalEmployees' => $user->count(),
          'cardItems' => $cardItems,
        ];
      }
    }
    return view('tenant.dashboard.card_view', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'teams' => $teams
    ]);
  }

  public function cardViewAjax()
  {
    $teamsList = Team::where('status', '=', 'active')
      ->get();


    $attendances = Attendance::whereDate('created_at', '=', now())
      ->get();

    $trackingHelper = new TrackingHelper();

    $users = User::where('status', '=', 'active')
      ->where('team_id', '!=', null)
      ->where('shift_id', '!=', null)
      ->get();

    $userDevices = UserDevice::whereIn('user_id', $users->pluck('id'))
      ->get();

    $cardItems = [];
    foreach ($teamsList as $team) {

      $user = $users->where('team_id', '=', $team->id);

      $teamAttendances = $attendances->whereIn('user_id', $user->pluck('id'));


      foreach ($teamAttendances as $attendance) {

        $device = $userDevices
          ->where('user_id', '=', $attendance->user_id)
          ->first();

        if ($device == null) {
          continue;
        }

        $isOnline = $trackingHelper->isUserOnline($device->updated_at);

        $visitsCount = Visit::where('attendance_id', '=', $attendance->id)
          ->whereDate('created_at', '=', now())
          ->count();

        $cardItems[] = [
          'id' => $attendance->user->id,
          'name' => $attendance->user->first_name . ' ' . $attendance->user->last_name,
          'phoneNumber' => $attendance->user->phone_number,
          'batteryLevel' => $device->battery_percentage,
          'isGpsOn' => $device->is_gps_on,
          'isWifiOn' => $device->is_wifi_on,
          'updatedAt' => $device->updated_at->diffForHumans(),
          'isOnline' => $isOnline,
          'teamId' => $attendance->user->team_id,
          'teamName' => $team->name,
          'attendanceInAt' => $attendance->check_in_time,
          'attendanceOutAt' => $attendance->check_out_time ?? '',
          'latitude' => $device->latitude,
          'longitude' => $device->longitude,
          'address' => $device->address,
          'visitsCount' => $visitsCount,
        ];
      }
    }

    return response()->json($cardItems);
  }

  public function timelineView()
  {
    $employees = User::where('status', UserAccountStatus::ACTIVE)
      ->get();

    return view('tenant.dashboard.timeline_view', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'employees' => $employees
    ]);
  }

  public function getDeviceLocationAjax($userId, $date, $attendanceLogId = null)
  {
    $logs = DeviceStatusLog::query()
      ->where('user_id', $userId)
      ->whereDate('created_at', $date)
      ->orderBy('created_at', 'asc');

    if ($attendanceLogId && $attendanceLogId != 'null') {
      $attendanceLog = AttendanceLog::find($attendanceLogId);
      $nextCheckOutLog = AttendanceLog::where('attendance_id', $attendanceLog->attendance_id)
        ->where('created_at', '>', $attendanceLog->created_at)
        ->where('type', 'check_out')
        ->first();

      if (!$attendanceLog) {
        return Error::response('Attendance log not found');
      }

      if ($nextCheckOutLog) {
        $logs = $logs->where('created_at', '>=', $attendanceLog->created_at)
          ->where('created_at', '<=', $nextCheckOutLog->created_at);
      }
      else {
        $logs->where('created_at', '>=', $attendanceLog->created_at);
      }
    }

    $logs = $logs->get();

    $trackingHelper = new TrackingHelper();

    $filteredLogs = $trackingHelper->getFilteredLocationPoints($logs);

    $response = [];

    foreach ($filteredLogs['filteredPoints'] as $log) {
      $response[] = [
        'latitude' => $log->latitude,
        'longitude' => $log->longitude,
        'address' => $log->address,
        'created_at' => $log->created_at->format(AppConstants::TimeFormat),
      ];
    }

    $result = [
      'logs' => $response,
      'rawLogs' => $logs,
      'totalTravelledDistance' => $filteredLogs['totalTravelledDistance'],
      'averageTravelledSpeed' => $filteredLogs['averageTravelledSpeed'],
    ];

    return Success::response($result);
  }

  public function getAttendanceLogAjax($userId, $date)
  {
    $attendance = Attendance::where('user_id', $userId)
      ->whereDate('created_at', $date)
      ->first();

    if (!$attendance) {
      return Success::response([]);
    }

    $attendanceLogs = AttendanceLog::where('attendance_id', $attendance->id)
      ->where('type', 'check_in')
      ->orderBy('created_at', 'asc')
      ->get();

    $attendanceLogs = $attendanceLogs->map(function ($log) {
      return [
      'id' => $log->id,
      'latitude' => $log->latitude,
      'longitude' => $log->longitude,
      'address' => $log->address,
      'created_at' => $log->created_at->format(AppConstants::TimeFormat),
      ];
    });

    return Success::response($attendanceLogs);
  }

  public function getActivityAjax($userId, $date, $attendanceLogId = null)
  {
    $employeeId = $userId;

    $trackingHelper = new TrackingHelper();

    $attendance = Attendance::where('user_id', $employeeId)
      ->whereDate('created_at', $date)
      ->first();

    if (!$attendance) {
      return Success::response([]);
    }

    $activities = [];
    if ($attendanceLogId && $attendanceLogId != 'null') {
      $attendanceLog = AttendanceLog::find($attendanceLogId);
      if (!$attendanceLog) {
        return Success::response([]);
      }

      $nextCheckOutLog = AttendanceLog::where('attendance_id', $attendance->id)
        ->where('created_at', '>', $attendanceLog->created_at)
        ->where('type', 'check_out')
        ->first();

      if (!$nextCheckOutLog) {
        $activities = Activity::where('created_at', '>=', $attendanceLog->created_at)
          ->where('created_by_id', $employeeId)
          ->get();
      }
      else {
        //Filter activities from this log to next log created_at
        $activities = Activity::where('created_at', '>=', $attendanceLog->created_at)
          ->where('created_at', '<=', $nextCheckOutLog->created_at)
          ->where('created_by_id', $employeeId)
          ->get();
      }
    }
    else {

      $activities = Activity::whereDate('created_at', $date)
        ->where('created_by_id', $employeeId)
        ->get();
    }


    if ($activities->count() == 0) {
      return Success::response([]);
    }

    //$activities = $activities->where('accuracy', '>', 20)->toArray();

    //return Success::response($activities);

    $filteredTrackings = $trackingHelper->getFilteredDataV2($activities);

    $timeLineItems = [];

    for ($i = 0; $i < count($filteredTrackings); $i++) {

      $elapseTime = "0";

      $tracking = $filteredTrackings[$i];
      $nextTracking = null;
      if ($tracking->type == 'checked_in') {
        if ($i < count($filteredTrackings) - 1 && count($filteredTrackings) != 1) {
          $nextTracking = $filteredTrackings[$i + 1];
          $elapseTime = $tracking->created_at->diff($nextTracking->created_at)->format('%H:%I:%S');
        }
        else {
          $elapseTime = '0';
        }
        $timeLineItems[] = [
          'id' => $tracking->id,
          'type' => 'checkIn',
          'accuracy' => $tracking->accuracy,
          'activity' => $tracking->activity,
          'batteryPercentage' => $tracking->battery_percentage,
          'isGPSOn' => $tracking->is_gps_on,
          'isWifiOn' => $tracking->is_wifi_on,
          'latitude' => $tracking->latitude,
          'longitude' => $tracking->longitude,
          'address' => $tracking->address,
          'signalStrength' => $tracking->signal_strength,
          'trackingType' => $tracking->type,
          'startTime' => $tracking->created_at->format('h:i A'),
          'endTime' => $nextTracking != null ? $nextTracking->created_at->format('h:i A') : $tracking->created_at->format('h:i A'),
          'elapseTime' => $elapseTime,
        ];
        continue;
      }

      if ($tracking->type == 'checked_out') {
        $elapseTime = $tracking->created_at->format('%H:%I:%S');

        $timeLineItems[] = [
          'id' => $tracking->id,
          'type' => 'checkOut',
          'accuracy' => $tracking->accuracy,
          'activity' => $tracking->activity,
          'batteryPercentage' => $tracking->battery_percentage,
          'isGPSOn' => $tracking->is_gps_on,
          'isWifiOn' => $tracking->is_wifi_on,
          'latitude' => $tracking->latitude,
          'longitude' => $tracking->longitude,
          'address' => $tracking->address,
          'signalStrength' => $tracking->signal_strength,
          'trackingType' => $tracking->type,
          'startTime' => $elapseTime,
          'endTime' => $tracking->created_at->format('h:i A'),
          'elapseTime' => $elapseTime,
        ];
        continue;
      }

      $nextTracking = null;

      if ($i + 1 < count($filteredTrackings)) {
        $nextTracking = $filteredTrackings[$i + 1];
        $elapseTime = $tracking->created_at->diff($nextTracking->created_at)->format('%H:%I:%S');
      }
      else {
        $elapseTime = $tracking->created_at->format('%H:%I:%S');
      }

      switch ($tracking->activity) {
        case 'ActivityType.STILL':
          $timeLineItems[] = [
            'id' => $tracking->id,
            'type' => 'still',
            'accuracy' => $tracking->accuracy ?? 0,
            'activity' => $tracking->activity,
            'batteryPercentage' => $tracking->battery_percentage,
            'isGPSOn' => $tracking->is_gps_on,
            'isWifiOn' => $tracking->is_wifi_on,
            'latitude' => $tracking->latitude,
            'longitude' => $tracking->longitude,
            'address' => $tracking->address,
            'signalStrength' => $tracking->signal_strength,
            'trackingType' => $tracking->type,
            'startTime' => $tracking->created_at->format('h:i A'),
            'endTime' => $nextTracking != null ? $nextTracking->created_at->format('h:i A') : $tracking->created_at->format('h:i A'),
            'elapseTime' => $elapseTime,
          ];
          break;
        case 'ActivityType.WALKING':
          $timeLineItems[] = [
            'id' => $tracking->id,
            'type' => 'walk',
            'accuracy' => $tracking->accuracy ?? 0,
            'activity' => $tracking->activity,
            'batteryPercentage' => $tracking->battery_percentage,
            'isGPSOn' => $tracking->is_gps_on,
            'isWifiOn' => $tracking->is_wifi_on,
            'latitude' => $tracking->latitude,
            'longitude' => $tracking->longitude,
            'address' => $tracking->address,
            'signalStrength' => $tracking->signal_strength,
            'trackingType' => $tracking->type,
            'startTime' => $tracking->created_at->format('h:i A'),
            'endTime' => $nextTracking ? $nextTracking->created_at->format('h:i A') : $tracking->created_at->format('h:i A'),
            'elapseTime' => $elapseTime,
          ];
          break;
        default:

          $distance = 0;
          if ($i + 1 < count($filteredTrackings)) {
            $nextTracking = $filteredTrackings[$i + 1];
          }


          $timeLineItems[] = [
            'id' => $tracking->id,
            'type' => 'vehicle',
            'accuracy' => $tracking->accuracy ?? 0,
            'activity' => $tracking->activity,
            'batteryPercentage' => $tracking->battery_percentage,
            'isGPSOn' => $tracking->is_gps_on,
            'isWifiOn' => $tracking->is_wifi_on,
            'latitude' => $tracking->latitude,
            'longitude' => $tracking->longitude,
            'address' => $tracking->address,
            'signalStrength' => $tracking->signal_strength,
            'trackingType' => $tracking->type,
            'startTime' => $tracking->created_at->format('h:i A'),
            'endTime' => $nextTracking ? $nextTracking->created_at->format('h:i A') : $tracking->created_at->format('h:i A'),
            'elapseTime' => $elapseTime,
            'distance' => $distance,
          ];
          break;
      }
    }


    return Success::response($timeLineItems);
  }

  public function getStatsForTimeLineAjax($userId, $date, $attendanceLogId = null)
  {
    $attendance = Attendance::where('user_id', $userId)
      ->whereDate('created_at', $date)
      ->with('attendanceLogs')
      ->first();

    if (!$attendance) {
      return Error::response([]);
    }

    $attendanceLogIds = $attendance->attendanceLogs->pluck('id');


    if ($attendanceLogId && $attendanceLogId != 'null') {
      $attendanceLogIds = [$attendanceLogId];
    }

    $visits = Visit::whereIn('attendance_log_id', $attendanceLogIds)
      ->get();

    $visits = $visits->map(function ($visit) {
      return [
      'id' => $visit->id,
      'latitude' => $visit->latitude,
      'longitude' => $visit->longitude,
      'address' => $visit->address,
      'img_url' => asset('storage/' . AppConstants::BaseFolderVisitImages . $visit->img_url),
      'created_at' => $visit->created_at->format(AppConstants::TimeFormat),
      'client_name' => $visit->client->name,
      ];
    });

    $breaks = AttendanceBreak::whereIn('attendance_log_id', $attendanceLogIds)
      ->get();

    $breaks = $breaks->map(function ($break) {
      return [
      'id' => $break->id,
      'start_time' => $break->start_time->format(AppConstants::TimeFormat),
      'end_time' => $break->end_time ? $break->end_time->format(AppConstants::TimeFormat) : null,
      'duration' => $break->end_time ? $break->start_time->diff($break->end_time)->format('%H:%I:%S') : null,
      'created_at' => $break->created_at->format(AppConstants::TimeFormat),
      ];
    });

    $orders = ProductOrder::whereIn('attendance_log_id', $attendanceLogIds)
      ->get();

    $orders = $orders->map(function ($order) {
      return [
      'id' => $order->id,
      'order_number' => $order->order_no,
      'total_amount' => $order->total,
      'status' => $order->status,
      'total_items' => $order->orderLines->count(),
      'created_at' => $order->created_at->format(AppConstants::TimeFormat),
      'user_remarks' => $order->user_remarks,
      ];
    });

    return Success::response([
      'userId' => $userId,
      'date' => $date,
      'attendanceDuration' => $attendance->check_in_time && $attendance->check_out_time ? 
      $attendance->check_in_time->diff($attendance->check_out_time)->format('%H:%I:%S') : 'N/A',
      'attendanceLogId' => $attendanceLogId,
      'name' => $attendance->user->getFullName(),
      'code' => $attendance->user->code,
      'designation' => $attendance->user->designation ? $attendance->user->designation->name : 'N/A',
      'visits' => $visits,
      'breaks' => $breaks,
      'orders' => $orders,
      'visitsCount' => $visits->count(),
      'breaksCount' => $breaks->count(),
      'ordersCount' => $orders->count(),
    ]);
  }

  public function getDepartmentPerformanceAjax()
  {
    $departments = Department::where('status', Status::ACTIVE)
      ->with('designations')
      ->with('designations.users')
      ->get();

    $departmentPerformance = [];

    foreach ($departments as $department) {
      $departmentPerformance[] = [
        'id' => $department->id,
        'name' => $department->name,
        'code' => $department->code,
        'totalEmployees' => $department->designations->sum(function ($designation) {
        return $designation->users->count();
      }),
        'totalPresentEmployees' => $department->designations->sum(function ($designation) {
        return $designation->users->sum(function ($user) {
            return Attendance::where('user_id', $user->id)->whereDate('created_at', now())->count();
          }
          );
        }),
        'totalAbsentEmployees' => $department->designations->sum(function ($designation) {
        return $designation->users->count() - $designation->users->sum(function ($user) {
            return Attendance::where('user_id', $user->id)->whereDate('created_at', now())->count();
          }
          );
        }),
      ];
    }

    return Success::response($departmentPerformance);
  }

}
