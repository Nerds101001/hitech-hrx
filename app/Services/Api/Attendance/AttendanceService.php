<?php

namespace App\Services\Api\Attendance;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\AttendanceLogType;
use App\Http\Requests\Api\Attendance\CheckInOutRequest;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AttendanceService implements IAttendance
{

  public function checkInOut(CheckInOutRequest $data): JsonResponse
  {
    try {

      $userId = auth()->id();
      $user = auth()->user();

      $isMultiCheckInOutEnabled = $this->isMultiCheckInOutEnabled();

      $todayAttendance = Attendance::where('user_id', $userId)
        ->whereDate('check_in_time', Carbon::today())
        ->first();

      if (!$todayAttendance) {
        $now = now();
        // Calculate status using central logic
        $calc = $this->calculateDayStatus($user, $now, $now, null);

        //Fresh check in for the day
        $attendance = new Attendance();
        $attendance->user_id = $userId;
        $attendance->check_in_time = $now;
        $attendance->shift_id = $user->shift_id;
        $attendance->status = $calc['status'];
        $attendance->is_policy_late = $calc['is_policy_late'] ?? false;
        $attendance->leave_request_id = $calc['leave_request_id'] ?? null;
        $attendance->created_by_id = auth()->id();
        $attendance->tenant_id = $user->tenant_id;
        $attendance->save();

        $this->takeAttendanceLog($attendance->id, AttendanceLogType::CHECK_IN, $data);
        return Success::response('Checked In');
      } else {
        $attendanceLog = AttendanceLog::where('attendance_id', $todayAttendance->id)->latest()->first();
        if ($attendanceLog->type == AttendanceLogType::CHECK_IN) {
          $now = now();
          //Check out
          if (!$isMultiCheckInOutEnabled) {
            $todayAttendance->check_out_time = $now;
            
            // Recalculate status with Check-Out
            $calc = $this->calculateDayStatus($user, $now, $todayAttendance->check_in_time, $now);
            $todayAttendance->status = $calc['status'];
            $todayAttendance->is_policy_late = $calc['is_policy_late'] ?? false;
            $todayAttendance->leave_request_id = $calc['leave_request_id'] ?? null;

            $todayAttendance->save();
          }
          $this->takeAttendanceLog($todayAttendance->id, AttendanceLogType::CHECK_OUT, $data);
          return Success::response('Checked Out');
        } else {

          if (!$isMultiCheckInOutEnabled) {
            return Error::response('You have already checked out');
          }

          //Check in
          $this->takeAttendanceLog($todayAttendance->id, AttendanceLogType::CHECK_IN, $data);
          return Success::response('Checked In again');
        }
      }
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return Error::response('Something went wrong, please try again');
    }
  }

  /**
   * Centralized logic to calculate daily attendance status.
   * Shared by real-time Check-In/Out and Biometric Import.
   */
  public function calculateDayStatus(\App\Models\User $user, $date, ?Carbon $checkIn, ?Carbon $checkOut): array
  {
    $date = $date instanceof Carbon ? $date : Carbon::parse($date);
    $dateStr = $date->toDateString();
    
    $isWorkingDay = \App\Services\LeavePolicyService::isWorkingDay($user, $date);
    if (!$isWorkingDay) {
        return ['status' => Attendance::STATUS_PRESENT, 'is_late' => false]; // weekends/holidays are always green if they log in
    }

    $status = Attendance::STATUS_PRESENT;
    $isHalfDay = false;
    $isLate = false;
    $isPolicyLate = false;
    $approvedShortLeave = null;

    if ($user->shift && $checkIn) {
        $shift = $user->shift;
        $shiftStartTime = Carbon::parse($dateStr . ' ' . $shift->start_time);
        $shiftEndTime = Carbon::parse($dateStr . ' ' . $shift->end_time);
        $totalShiftMinutes = $shiftStartTime->diffInMinutes($shiftEndTime);

        // Standard Shift logic with 15 mins grace (Master setting)
        if ($shift->is_flexible && $shift->flex_end_time) {
            $flexEndTime = Carbon::parse($dateStr . ' ' . $shift->flex_end_time);
            if ($checkIn->gt($flexEndTime)) {
                $isLate = true;
            }
        } else {
            if ($checkIn->gt($shiftStartTime->copy()->addMinutes(15))) {
                $isLate = true;
            }
        }

        // --- Handle Short Leave Waiver & Required Hours ---
        $shortLeaveType = \App\Models\LeaveType::where('is_short_leave', true)->first();
        if ($shortLeaveType) {
            $approvedShortLeave = \App\Models\LeaveRequest::where('user_id', $user->id)
                ->where('from_date', $dateStr)
                ->where('leave_type_id', $shortLeaveType->id)
                ->where('status', \App\Enums\LeaveRequestStatus::APPROVED)
                ->first();

            if ($approvedShortLeave) {
                if ($isLate) {
                    $isLate = false;
                    $isPolicyLate = true; // Waved due to approved short leave
                }
            }
        }

        // --- Required Hours Calculation ---
        if ($checkIn && $checkOut) {
            $minutesWorked = $checkIn->diffInMinutes($checkOut);
            $shortLeaveMinutes = $approvedShortLeave ? ($approvedShortLeave->duration_hours * 60) : 0;
            $requiredMinutes = $totalShiftMinutes - $shortLeaveMinutes;

            // If user doesn't complete the rest of the hours, mark as ABSENT
            if ($minutesWorked < $requiredMinutes) {
                $status = Attendance::STATUS_ABSENT;
                
                // Revert short leave consumption if marked absent
                if ($approvedShortLeave) {
                    // Logic to ensure it's not consumed: 
                    // We can either set the leave request to REJECTED/CANCELLED or 
                    // just note it. Since the booted() method handles balance on status change, 
                    // we'll change status to REJECTED with a system note.
                    $approvedShortLeave->status = \App\Enums\LeaveRequestStatus::REJECTED;
                    $approvedShortLeave->approval_notes = "System: Short leave reverted because required working hours were not met.";
                    $approvedShortLeave->save();
                    
                    $isPolicyLate = false; // Reset late waiver
                    $isLate = true; // User is late again because short leave is voided
                }
            } else {
                if ($minutesWorked >= 480) { // 8 hours (Full Day)
                    $isHalfDay = false;
                    $isLate = false;
                } elseif ($minutesWorked < 465) {
                    $isHalfDay = true;
                }
                $status = $isHalfDay ? Attendance::STATUS_HALF_DAY : Attendance::STATUS_PRESENT;
            }
        }
    }

    return [
        'status' => $status,
        'is_late' => $isLate,
        'is_policy_late' => $isPolicyLate,
        'leave_request_id' => $approvedShortLeave ? $approvedShortLeave->id : null
    ];
  }

  private function isMultiCheckInOutEnabled(): bool
  {
    return auth()->user()->isMultiCheckInOutEnabled();
  }

  private function takeAttendanceLog(int $attendanceId, AttendanceLogType $type, CheckInOutRequest $data): void
  {
    //Take Attendance Log
    AttendanceLog::create([
      'attendance_id' => $attendanceId,
      'created_by_id' => auth()->id(),
      'type' => $type,
      'latitude' => $data['latitude'],
      'longitude' => $data['longitude'],
      'altitude' => $data['altitude'],
      'speed' => $data['speed'] ?? null,
      'horizontalAccuracy' => $data['horizontalAccuracy'] ?? null,
      'verticalAccuracy' => $data['verticalAccuracy'] ?? null,
      'course' => $data['course'] ?? null,
      'courseAccuracy' => $data['courseAccuracy'] ?? null,
      'speedAccuracy' => $data['speedAccuracy'] ?? null,
      'address' => $data['address'] ?? null,
      'tenant_id' => auth()->user()->tenant_id,
    ]);
  }

  public function getStatus(): JsonResponse
  {
    $user = auth()->user();

    $shiftInfo = [
      'shift_start' => Carbon::parse($user->shift->start_time)->format('h:i A'),
      'shift_end' => Carbon::parse($user->shift->end_time)->format('h:i A'),
    ];

    $response = [];

    $isMultiCheckInOutEnabled = $this->isMultiCheckInOutEnabled();

    $response['shiftInfo'] = $shiftInfo;
    $response['isMultiCheckInOutEnabled'] = $isMultiCheckInOutEnabled;

    if ($user->isOnLeave()) {

      return Success::response(['status' => 'on leave']);

    } else {

      $attendance = Attendance::where('user_id', $user->id)
        ->whereDate('check_in_time', Carbon::today())
        ->first();

      if (!$attendance) {
        $response['status'] = 'new';
      } else {

        if ($isMultiCheckInOutEnabled) {
          $attendanceLogs = AttendanceLog::where('attendance_id', $attendance->id)->latest()->first();

          if ($attendanceLogs->type == AttendanceLogType::CHECK_IN) {
            $response['status'] = 'checked in';
          } else {
            $response['status'] = 'checked out';
          }

        } else {
          //Single check in out
          if ($attendance->check_out_time) {
            $response['status'] = 'checked out';
          } else {
            $response['status'] = 'checked in';
          }
        }
      }


      return Success::response($response);
    }
  }

  public function isCheckedIn(): bool
  {
    $attendance = Attendance::where('user_id', auth()->id())
      ->whereDate('check_in_time', Carbon::today())
      ->first();

    if (!$attendance) {
      return false;
    }

    $attendanceLog = AttendanceLog::where('attendance_id', $attendance->id)->latest()->first();

    return $attendanceLog->type == AttendanceLogType::CHECK_IN;
  }
}
