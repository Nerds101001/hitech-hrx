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
        ->where(function ($query) {
            $query->whereDate('check_in_time', Carbon::today())
                  ->orWhere(function ($q2) {
                      $q2->whereNull('check_in_time')
                         ->whereDate('created_at', Carbon::today());
                  });
        })
        ->first();

      $attendanceLog = $todayAttendance ? AttendanceLog::where('attendance_id', $todayAttendance->id)->latest()->first() : null;

      if (!$todayAttendance || !$attendanceLog) {
        $now = now();
        $isLatePunch = $now->hour >= 14; // Auto-detect missed morning punch if after 2:00 PM
        
        $calc = $this->calculateDayStatus($user, $now, $isLatePunch ? null : $now, $isLatePunch ? $now : null);

        if ($todayAttendance) {
          // Update the leave-created attendance record with actual check-in time
          if ($isLatePunch) {
              $todayAttendance->check_out_time = $now;
              $todayAttendance->status = Attendance::STATUS_ABSENT; // Missed morning punch
              $logType = AttendanceLogType::CHECK_OUT;
          } else {
              $todayAttendance->check_in_time = $now;
              $todayAttendance->status = $calc['status'];
              $logType = AttendanceLogType::CHECK_IN;
          }
          
          $todayAttendance->is_policy_late = $calc['is_policy_late'] ?? false;
          $todayAttendance->save();

          $this->takeAttendanceLog($todayAttendance->id, $logType, $data);
          return Success::response($isLatePunch ? 'Checked Out (Missed Morning Punch)' : 'Checked In');
        } else {
          //Fresh check in for the day
          $attendance = new Attendance();
          $attendance->user_id = $userId;
          if ($isLatePunch) {
              $attendance->check_in_time = null;
              $attendance->check_out_time = $now;
              $attendance->status = Attendance::STATUS_ABSENT; // Missed morning punch
              $logType = AttendanceLogType::CHECK_OUT;
          } else {
              $attendance->check_in_time = $now;
              $attendance->status = $calc['status'];
              $logType = AttendanceLogType::CHECK_IN;
          }
          $attendance->shift_id = $user->shift_id;
          
          $attendance->is_policy_late = $calc['is_policy_late'] ?? false;
          $attendance->leave_request_id = $calc['leave_request_id'] ?? null;
          $attendance->created_by_id = auth()->id();
          $attendance->tenant_id = $user->tenant_id;
          $attendance->save();

          $this->takeAttendanceLog($attendance->id, $logType, $data);
          return Success::response($isLatePunch ? 'Checked Out (Missed Morning Punch)' : 'Checked In');
        }
      } else {
        // $attendanceLog already fetched at the top of this method — reuse it, no duplicate query needed
        if ($attendanceLog->type == AttendanceLogType::CHECK_IN) {
          $now = now();
          //Check out
          if (!$isMultiCheckInOutEnabled) {
            $todayAttendance->check_out_time = $now;
            
            // Recalculate status with Check-Out
            $calc = $this->calculateDayStatus($user, $now, $todayAttendance->check_in_time, $now);
            $todayAttendance->status = $calc['status'];
            $todayAttendance->is_policy_late = $calc['is_policy_late'] ?? false;
            
            if ($todayAttendance->leave_request_id) {
                $calc['leave_request_id'] = $todayAttendance->leave_request_id;
            }
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

  protected static $halfDayLeaveCache = [];
  protected static $shortLeaveCache = [];
  protected static $shortLeaveTypeCache = null;
  protected static $shortLeaveTypeFetched = false;

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

    $cacheKey = $user->id . '_' . $dateStr;
    if (!array_key_exists($cacheKey, self::$halfDayLeaveCache)) {
        self::$halfDayLeaveCache[$cacheKey] = \App\Models\LeaveRequest::where('user_id', $user->id)
            ->whereDate('from_date', $dateStr)
            ->where('is_half_day', true)
            ->where('status', \App\Enums\LeaveRequestStatus::APPROVED)
            ->first();
    }
    $approvedHalfDayLeave = self::$halfDayLeaveCache[$cacheKey];

    if ($user->shift && $checkIn) {
        $shift = $user->shift;
        $shiftStartStr = Carbon::parse($shift->start_time)->toTimeString();
        $shiftEndStr = Carbon::parse($shift->end_time)->toTimeString();
        $shiftStartTime = Carbon::parse($dateStr . ' ' . $shiftStartStr);
        $shiftEndTime = Carbon::parse($dateStr . ' ' . $shiftEndStr);
        $originalShiftMinutes = $shiftStartTime->diffInMinutes($shiftEndTime);
        $totalShiftMinutes = $originalShiftMinutes;

        if ($approvedHalfDayLeave) {
            $totalShiftMinutes = $originalShiftMinutes / 2;
            if ($approvedHalfDayLeave->half_day_session === 'first_half') {
                // Morning session leave, late if afternoon check-in is past midpoint + 15 mins
                $midpointTime = $shiftStartTime->copy()->addMinutes($originalShiftMinutes / 2);
                if ($checkIn->gt($midpointTime->copy()->addMinutes(15))) {
                    $isLate = true;
                }
            } else {
                // Afternoon session leave, morning check-in is normal
                if ($shift->is_flexible && $shift->flex_end_time) {
                    $flexEndStr = Carbon::parse($shift->flex_end_time)->toTimeString();
                    $flexEndTime = Carbon::parse($dateStr . ' ' . $flexEndStr);
                    if ($checkIn->gt($flexEndTime)) {
                        $isLate = true;
                    }
                } else {
                    if ($checkIn->gt($shiftStartTime->copy()->addMinutes(15))) {
                        $isLate = true;
                    }
                }
            }
        } else {
            // Standard Shift logic with 15 mins grace (Master setting)
            if ($shift->is_flexible && $shift->flex_end_time) {
                $flexEndStr = Carbon::parse($shift->flex_end_time)->toTimeString();
                $flexEndTime = Carbon::parse($dateStr . ' ' . $flexEndStr);
                if ($checkIn->gt($flexEndTime)) {
                    $isLate = true;
                }
            } else {
                if ($checkIn->gt($shiftStartTime->copy()->addMinutes(15))) {
                    $isLate = true;
                }
            }
        }

        // --- Handle Short Leave Waiver & Required Hours ---
        if (!self::$shortLeaveTypeFetched) {
            self::$shortLeaveTypeCache = \App\Models\LeaveType::where('is_short_leave', true)->first();
            self::$shortLeaveTypeFetched = true;
        }
        $shortLeaveType = self::$shortLeaveTypeCache;

        if ($shortLeaveType) {
            if (!array_key_exists($cacheKey, self::$shortLeaveCache)) {
                self::$shortLeaveCache[$cacheKey] = \App\Models\LeaveRequest::where('user_id', $user->id)
                    ->where('from_date', $dateStr)
                    ->where('leave_type_id', $shortLeaveType->id)
                    ->where('status', \App\Enums\LeaveRequestStatus::APPROVED)
                    ->first();
            }
            $approvedShortLeave = self::$shortLeaveCache[$cacheKey];

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
            
            // Standard required minutes for Full Day (8 hours = 480 mins)
            $requiredMinutes = 480 - $shortLeaveMinutes;
            
            // Standard grace minutes for Full Day (7 hours 45 mins = 465 mins)
            $graceMinutes = 465 - $shortLeaveMinutes;

            if ($minutesWorked < ($requiredMinutes / 2)) {
                // Working less than half the required time is considered Absent
                $status = Attendance::STATUS_ABSENT;
                
                // Revert short leave consumption if marked absent
                if ($approvedShortLeave) {
                    $approvedShortLeave->status = \App\Enums\LeaveRequestStatus::REJECTED;
                    $approvedShortLeave->approval_notes = "System: Short leave reverted because required working hours were not met.";
                    $approvedShortLeave->save();
                    
                    $isPolicyLate = false; // Reset late waiver
                    $isLate = true; // User is late again because short leave is voided
                }
            } else {
                if ($approvedHalfDayLeave) {
                    $isHalfDay = false;
                    $isLate = false;
                    $status = Attendance::STATUS_PRESENT;
                } else {
                    if ($minutesWorked >= $graceMinutes) {
                        // They worked the required >= 7 hours 45 minutes
                        
                        // Check if they punched in after 11:00 AM
                        $elevenAM = Carbon::parse($dateStr . ' 11:00:00');
                        if ($checkIn->gt($elevenAM)) {
                            // Punched in after 11:00 AM -> Force Half-Day (Orange)
                            $isHalfDay = true;
                        } else {
                            // Valid Full Day
                            $isHalfDay = false;
                        }
                    } else {
                        // They worked between 4 hours and 7 hours 45 mins
                        $isHalfDay = true;
                    }
                    
                    $status = $isHalfDay ? Attendance::STATUS_HALF_DAY : Attendance::STATUS_PRESENT;
                }
            }
        }
    } else if (!$checkIn && $checkOut) {
        // Only a checkout exists -> they missed the morning punch -> Absent
        $status = Attendance::STATUS_ABSENT;
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
