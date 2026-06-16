<?php
use Illuminate\Support\Facades\Schema;
$columns = Schema::getColumnListing('attendances');
foreach ($columns as $col) {
    echo "$col\n";
}
