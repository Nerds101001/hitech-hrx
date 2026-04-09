<?php

namespace App\Enums;

enum TerminationType: string {
  case RESIGNATION = 'resignation';
  case TERMINATED_WITH_CAUSE = 'terminated_with_cause';
  case TERMINATED_WITHOUT_CAUSE = 'terminated_without_cause';
  case LAYOFF = 'layoff';
  case RETIREMENT = 'retirement'; // Already handled by RETIRED status? Maybe combine.
  case PROBATION_FAILED = 'probation_failed';
  // ... other types ...
}
