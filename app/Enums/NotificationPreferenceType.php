<?php

namespace App\Enums;

enum NotificationPreferenceType: string
{
  case LEAVE_REQUEST = 'leave_request';
  case EXPENSE_REQUEST = 'expense_request';
  case LOAN_REQUEST = 'loan_request';
  case DOCUMENT_REQUEST = 'document_request';
  case GPS_ALERT = 'gps_alert';
  case ATTENDANCE_ALERT = 'attendance_alert';
  case LOW_BATTERY_ALERT = 'low_battery_alert';

  /**
   * Get all preference types.
   */
  public static function all(): array
  {
    return [
      self::LEAVE_REQUEST,
      self::EXPENSE_REQUEST,
      self::LOAN_REQUEST,
      self::DOCUMENT_REQUEST,
      self::GPS_ALERT,
      self::ATTENDANCE_ALERT,
      self::LOW_BATTERY_ALERT,
    ];
  }
}
