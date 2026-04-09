<?php

namespace App\Enums;

enum TargetStatus: string
{
  case ASSIGNED    = 'assigned';
  case IN_PROGRESS = 'in_progress';
  case UNDER_REVIEW = 'under_review';
  case ACHIEVED    = 'achieved';
  case MISSED      = 'missed';
  case CANCELLED   = 'cancelled';

  // Legacy aliases kept for backward compatibility
  case PENDING     = 'pending';
  case COMPLETED   = 'completed';
  case EXPIRED     = 'expired';

  public function label(): string
  {
    return match($this) {
      self::ASSIGNED     => 'Assigned',
      self::IN_PROGRESS  => 'In Progress',
      self::UNDER_REVIEW => 'Under Review',
      self::ACHIEVED     => 'Achieved',
      self::MISSED       => 'Missed',
      self::CANCELLED    => 'Cancelled',
      self::PENDING      => 'Pending',
      self::COMPLETED    => 'Completed',
      self::EXPIRED      => 'Expired',
    };
  }

  public function badgeClass(): string
  {
    return match($this) {
      self::ASSIGNED     => 'bg-label-secondary',
      self::IN_PROGRESS  => 'bg-label-warning',
      self::UNDER_REVIEW => 'bg-label-info',
      self::ACHIEVED     => 'bg-label-success',
      self::MISSED       => 'bg-label-danger',
      self::CANCELLED    => 'bg-label-dark',
      self::PENDING      => 'bg-label-warning',
      self::COMPLETED    => 'bg-label-success',
      self::EXPIRED      => 'bg-label-danger',
    };
  }
}
