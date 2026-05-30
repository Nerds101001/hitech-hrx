<?php
$file = 'd:\live_server\app\Http\Controllers\tenant\EmployeeController.php';
$content = file_get_contents($file);

$oldCode = <<<PHP
        \$columns = [
          0 => 'users.first_name',
          1 => 'users.code',
          2 => 'departments.name',
          3 => 'designations.name',
          4 => 'users.status',
          5 => 'sites.name',
          6 => 'users.id',
        ];
PHP;

$newCode = <<<PHP
        \$columns = [
          0 => 'users.first_name',
          1 => 'users.code',
          2 => \DB::raw('COALESCE(departments.name, teams.name)'),
          3 => 'designations.name',
          4 => 'users.status',
          5 => 'sites.name',
          6 => 'users.id',
        ];
PHP;

$content = str_replace($oldCode, $newCode, $content);

$oldCodeJoin = <<<PHP
        \$query = User::with(['team', 'designation', 'roles', 'site', 'department'])
          ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
          ->leftJoin('designations', 'users.designation_id', '=', 'designations.id')
          ->leftJoin('sites', 'users.site_id', '=', 'sites.id')
          ->select('users.*');
PHP;

$newCodeJoin = <<<PHP
        \$query = User::with(['team', 'designation', 'roles', 'site', 'department'])
          ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
          ->leftJoin('teams', 'users.team_id', '=', 'teams.id')
          ->leftJoin('designations', 'users.designation_id', '=', 'designations.id')
          ->leftJoin('sites', 'users.site_id', '=', 'sites.id')
          ->select('users.*');
PHP;

$content = str_replace($oldCodeJoin, $newCodeJoin, $content);

$oldCodeSearch = <<<PHP
              ->orWhere('departments.name', 'LIKE', "%{\$search}%")
              ->orWhere('designations.name', 'LIKE', "%{\$search}%")
              ->orWhere('sites.name', 'LIKE', "%{\$search}%");
PHP;

$newCodeSearch = <<<PHP
              ->orWhere('departments.name', 'LIKE', "%{\$search}%")
              ->orWhere('teams.name', 'LIKE', "%{\$search}%")
              ->orWhere('designations.name', 'LIKE', "%{\$search}%")
              ->orWhere('sites.name', 'LIKE', "%{\$search}%");
PHP;

$content = str_replace($oldCodeSearch, $newCodeSearch, $content);

file_put_contents($file, $content);
echo "Sorting coalesced patched successfully!\n";
