<?php

namespace App\Enums;

enum UserShiftStatus : string
{
    Case SCHEDULED = 'scheduled';

    Case ONGOING = 'ongoing';

    Case COMPLETED = 'completed';

    Case CANCELLED = 'cancelled';

    Case MISSED = 'missed';


}
