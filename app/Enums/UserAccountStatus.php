<?php

namespace App\Enums;

enum UserAccountStatus: string
{
  case ACTIVE = 'active';

  case INACTIVE = 'inactive';

  case DELETED = 'deleted';

  case ONBOARDING = 'onboarding';
  case ONBOARDING_SUBMITTED = 'onboarding_submitted';
  case ONBOARDING_REQUESTED = 'onboarding_requested';

  case RETIRED = 'retired';

  case RELIEVED = 'relieved';

  case SUSPENDED = 'suspended';

  case PENDING = 'pending';

  case REJECTED = 'rejected';

  case APPROVED = 'approved';

  case BLOCKED = 'blocked';

  case INVITED = 'invited';

  case REGISTERED = 'registered';

  case TERMINATED = 'terminated'; // Added
  case PROBATION_FAILED = 'probation_failed'; // Added (Optional)

  /**
   * Get the status label in a professional format.
   */
  public function label(): string
  {
      return ucwords(str_replace('_', ' ', $this->value));
  }
}
