<?php
echo implode(PHP_EOL, array_map(function(\) { return array_values((array)\)[0]; }, \DB::select('SHOW TABLES')));
