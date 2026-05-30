<?php
$file = 'd:\live_server\app\Http\Controllers\tenant\EmployeeController.php';
$content = file_get_contents($file);

$oldCode = <<<PHP
        // Map DataTables column indexes to actual DB columns (only real columns).
        \$columns = [
          0 => 'first_name', // Column 0 in JS
          1 => 'code',       // Column 1 in JS
          2 => 'team_id',    // Column 2
          3 => 'designation_id', // Column 3
          4 => 'status',      // Column 4
          5 => 'date_of_joining', // Column 5
          6 => 'id',          // Actions (Column 6)
        ];

        \$search = [];

        \$totalDataQuery = User::query();
        if (\$scopedIds) \$totalDataQuery->whereIn('id', \$scopedIds);
        \$totalData = \$totalDataQuery->count();

        \$totalFiltered = \$totalData;

        \$limit = \$request->input('length');
        \$start = \$request->input('start');
        \$orderIndex = (int) \$request->input('order.0.column', 1);
        \$order = \$columns[\$orderIndex] ?? 'id';
        \$dir = strtolower((string) \$request->input('order.0.dir', 'desc'));
        if (!in_array(\$dir, ['asc', 'desc'], true)) {
          \$dir = 'desc';
        }

        \$query = User::with(['team', 'designation', 'roles', 'site']);
        if (\$scopedIds) \$query->whereIn('id', \$scopedIds);

        if (\$request->has('roleFilter') && !empty(\$request->input('roleFilter'))) {
          \$query->whereHas('roles', function (\$q) use (\$request) {
            \$q->where('name', \$request->input('roleFilter'));
          });
        }

        if (\$request->has('teamFilter') && !empty(\$request->input('teamFilter'))) {
          \$query->where('team_id', \$request->input('teamFilter'));
        }

        if (\$request->has('designationFilter') && !empty(\$request->input('designationFilter'))) {
          \$query->where('designation_id', \$request->input('designationFilter'));
        }

        if (\$request->has('statusFilter') && !empty(\$request->input('statusFilter'))) {
          \$query->where('status', \$request->input('statusFilter'));
        }

        if (\$request->has('siteFilter') && !empty(\$request->input('siteFilter'))) {
          \$query->where('site_id', \$request->input('siteFilter'));
        }

        if (!empty(\$request->input('search.value'))) {
          \$search = \$request->input('search.value');
          \$query->where(function (\$q) use (\$search) {
            \$q->where('id', 'LIKE', "%{\$search}%")
              ->orWhere('first_name', 'LIKE', "%{\$search}%")
              ->orWhere('last_name', 'LIKE', "%{\$search}%")
              ->orWhere('phone', 'LIKE', "%{\$search}%")
              ->orWhere('email', 'LIKE', "%{\$search}%")
              ->orWhere('code', 'LIKE', "%{\$search}%");
          });
        }

        \$totalFiltered = \$query->count();
PHP;

$newCode = <<<PHP
        // Map DataTables column indexes to actual DB columns for sorting.
        \$columns = [
          0 => 'users.first_name',
          1 => 'users.code',
          2 => 'departments.name',
          3 => 'designations.name',
          4 => 'users.status',
          5 => 'sites.name',
          6 => 'users.id',
        ];

        \$search = [];

        \$totalDataQuery = User::query();
        if (\$scopedIds) \$totalDataQuery->whereIn('id', \$scopedIds);
        \$totalData = \$totalDataQuery->count();

        \$limit = \$request->input('length');
        \$start = \$request->input('start');
        \$orderIndex = (int) \$request->input('order.0.column', 1);
        \$order = \$columns[\$orderIndex] ?? 'users.id';
        \$dir = strtolower((string) \$request->input('order.0.dir', 'desc'));
        if (!in_array(\$dir, ['asc', 'desc'], true)) {
          \$dir = 'desc';
        }

        \$query = User::with(['team', 'designation', 'roles', 'site', 'department'])
          ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
          ->leftJoin('designations', 'users.designation_id', '=', 'designations.id')
          ->leftJoin('sites', 'users.site_id', '=', 'sites.id')
          ->select('users.*');

        if (\$scopedIds) \$query->whereIn('users.id', \$scopedIds);

        if (\$request->has('roleFilter') && !empty(\$request->input('roleFilter'))) {
          \$query->whereHas('roles', function (\$q) use (\$request) {
            \$q->where('name', \$request->input('roleFilter'));
          });
        }

        if (\$request->has('teamFilter') && !empty(\$request->input('teamFilter'))) {
          \$query->where('users.team_id', \$request->input('teamFilter'));
        }

        if (\$request->has('designationFilter') && !empty(\$request->input('designationFilter'))) {
          \$query->where('users.designation_id', \$request->input('designationFilter'));
        }

        if (\$request->has('statusFilter') && !empty(\$request->input('statusFilter'))) {
          \$query->where('users.status', \$request->input('statusFilter'));
        }

        if (\$request->has('siteFilter') && !empty(\$request->input('siteFilter'))) {
          \$query->where('users.site_id', \$request->input('siteFilter'));
        }

        if (!empty(\$request->input('search.value'))) {
          \$search = \$request->input('search.value');
          \$query->where(function (\$q) use (\$search) {
            \$q->where('users.id', 'LIKE', "%{\$search}%")
              ->orWhere('users.first_name', 'LIKE', "%{\$search}%")
              ->orWhere('users.last_name', 'LIKE', "%{\$search}%")
              ->orWhere('users.phone', 'LIKE', "%{\$search}%")
              ->orWhere('users.email', 'LIKE', "%{\$search}%")
              ->orWhere('users.code', 'LIKE', "%{\$search}%")
              ->orWhere('departments.name', 'LIKE', "%{\$search}%")
              ->orWhere('designations.name', 'LIKE', "%{\$search}%")
              ->orWhere('sites.name', 'LIKE', "%{\$search}%");
          });
        }

        \$totalFiltered = \$query->count();
PHP;

$content = str_replace($oldCode, $newCode, $content);
file_put_contents($file, $content);
echo "Datatable sorting patched successfully!\n";
