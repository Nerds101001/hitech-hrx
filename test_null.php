<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
$col = DB::select('SHOW COLUMNS FROM attendances WHERE Field = "check_in_time"');
print_r($col);
