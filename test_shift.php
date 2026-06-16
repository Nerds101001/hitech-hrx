<?php
use App\Models\Shift;
$shift = Shift::find(1);
echo "Shift ID: " . $shift->id . " Start: " . $shift->start_time . " End: " . $shift->end_time . "\n";
