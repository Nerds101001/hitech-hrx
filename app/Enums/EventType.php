<?php

namespace App\Enums;

enum EventType: string
{
  case MEETING = 'Meeting';
  case TRAINING = 'Training';
  // case LEAVE = 'Leave'; // Decide if Leave is handled here or separately
  case HOLIDAY = 'Holiday'; // Usually system-generated?
  case DEADLINE = 'Deadline';
  case COMPANY_EVENT = 'Company Event';
  case INTERVIEW = 'Interview';
  case ONBOARDING_SESSION = 'Onboarding Session';
  case PERFORMANCE_REVIEW = 'Performance Review';
  case CLIENT_APPOINTMENT = 'Client Appointment';
  case OTHER = 'Other';

  // Helper for user-friendly labels if needed (e.g., for dropdowns)
  public function label(): string
  {
    // Returns the string value directly, which is already user-friendly
    return $this->value;
  }

  // Optional: Define default colors (can be overridden by user choice)
  public function defaultColor(): string
  {
    return match ($this) {
      self::MEETING => '#007bff',             // Blue
      self::TRAINING => '#ffc107',            // Yellow
      self::HOLIDAY => '#28a745',             // Green
      self::DEADLINE => '#dc3545',            // Red
      self::COMPANY_EVENT => '#17a2b8',       // Teal
      self::INTERVIEW => '#6f42c1',           // Purple
      self::ONBOARDING_SESSION => '#fd7e14',  // Orange
      self::PERFORMANCE_REVIEW => '#20c997',  // Cyan
      self::CLIENT_APPOINTMENT => '#6610f2',  // Indigo
      self::OTHER => '#6c757d',             // Gray
      // self::LEAVE => '#adb5bd',            // Light Gray
    };
  }
}
