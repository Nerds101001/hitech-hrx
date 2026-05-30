<?php
$file = 'd:\live_server\app\Http\Controllers\tenant\EmployeeController.php';
$content = file_get_contents($file);

// 1. In create()
$content = str_replace(
    "      'ccUsers' => \$ccUsers,",
    "      'ccareUsers' => \App\Models\User::with(['designation', 'department'])->whereHas('department', function(\$q) { \$q->whereIn('name', ['CCARE', 'Customer Care']); })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->orderBy('first_name')->get(),\n      'newbizUsers' => \App\Models\User::with(['designation', 'department'])->whereHas('department', function(\$q) { \$q->whereIn('name', ['New Biz', 'New Business Department']); })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->orderBy('first_name')->get(),",
    $content
);

// Remove the old ccUsers query in create()
$oldCcUsersQueryCreate = <<<PHP
    \$ccUsers = User::with(['designation', 'department'])
        ->whereHas('department', function(\$q) {
            \$q->whereIn('name', ['CCARE', 'Customer Care', 'New Biz', 'New Business Department']);
        })->where('status', UserAccountStatus::ACTIVE)->orderBy('first_name')->get();
PHP;
$content = str_replace($oldCcUsersQueryCreate, '', $content);

// 2. In index()
$oldCcUsersQueryIndex = <<<PHP
      'ccUsers' => User::with(['designation', 'department'])
            ->whereHas('department', function(\$q) {
                \$q->where('name', 'like', '%CCARE%')->orWhere('name', 'like', '%Customer Care%');
            })->orderBy('first_name')->get(),
PHP;
$newCcUsersQueryIndex = <<<PHP
      'ccareUsers' => User::with(['designation', 'department'])->whereHas('department', function(\$q) { \$q->whereIn('name', ['CCARE', 'Customer Care']); })->whereIn('status', [\App\Enums\UserAccountStatus::ACTIVE, 'active', 'ACTIVE'])->orderBy('first_name')->get(),
      'newbizUsers' => User::with(['designation', 'department'])->whereHas('department', function(\$q) { \$q->whereIn('name', ['New Biz', 'New Business Department']); })->whereIn('status', [\App\Enums\UserAccountStatus::ACTIVE, 'active', 'ACTIVE'])->orderBy('first_name')->get(),
PHP;
$content = str_replace($oldCcUsersQueryIndex, $newCcUsersQueryIndex, $content);

// 3. In view()
$oldCcUsersQueryView = <<<PHP
    \$ccUsers = User::with(['designation', 'department'])
        ->whereHas('department', function(\$q) {
            \$q->whereIn('name', ['CCARE', 'Customer Care', 'New Biz', 'New Business Department']);
        })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->orderBy('first_name')->get();

    \$currentCcMapping = \App\Models\CcSalespersonMap::where('sales_user_id', \$user->id)->first();
PHP;

$newCcUsersQueryView = <<<PHP
    \$ccareUsers = User::with(['designation', 'department'])->whereHas('department', function(\$q) { \$q->whereIn('name', ['CCARE', 'Customer Care']); })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->orderBy('first_name')->get();
    \$newbizUsers = User::with(['designation', 'department'])->whereHas('department', function(\$q) { \$q->whereIn('name', ['New Biz', 'New Business Department']); })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->orderBy('first_name')->get();
    
    \$currentMappings = \App\Models\CcSalespersonMap::with('ccUser.department')->where('sales_user_id', \$user->id)->get();
    \$currentCcareId = null;
    \$currentNewbizId = null;
    foreach (\$currentMappings as \$map) {
        \$dName = strtolower(\$map->ccUser->department->name ?? '');
        if (str_contains(\$dName, 'ccare') || str_contains(\$dName, 'customer care')) {
            \$currentCcareId = \$map->cc_user_id;
        } elseif (str_contains(\$dName, 'new biz') || str_contains(\$dName, 'new business')) {
            \$currentNewbizId = \$map->cc_user_id;
        }
    }
PHP;
$content = str_replace($oldCcUsersQueryView, $newCcUsersQueryView, $content);

$content = str_replace(
    "        'ccUsers' => \$ccUsers,\n        'currentCcMapping' => \$currentCcMapping",
    "        'ccareUsers' => \$ccareUsers,\n        'newbizUsers' => \$newbizUsers,\n        'currentCcareId' => \$currentCcareId,\n        'currentNewbizId' => \$currentNewbizId",
    $content
);

// 4. store() mapping insert
$oldStoreMapping = <<<PHP
      // CC Agent Assignment for Sales/New Biz
      if (\$request->filled('cc_agent_id')) {
          \App\Models\CcSalespersonMap::updateOrCreate(
              ['sales_user_id' => \$user->id],
              ['cc_user_id' => \$request->input('cc_agent_id')]
          );
      }
PHP;
$newStoreMapping = <<<PHP
      // CC Agent Assignment for Sales/New Biz
      if (\$request->filled('ccare_agent_id')) {
          \App\Models\CcSalespersonMap::create(['sales_user_id' => \$user->id, 'cc_user_id' => \$request->input('ccare_agent_id')]);
      }
      if (\$request->filled('newbiz_agent_id')) {
          \App\Models\CcSalespersonMap::create(['sales_user_id' => \$user->id, 'cc_user_id' => \$request->input('newbiz_agent_id')]);
      }
PHP;
$content = str_replace($oldStoreMapping, $newStoreMapping, $content);

// 5. update() mapping update
$oldUpdateMapping = <<<PHP
      // CC Agent Assignment for Sales/New Biz
      if (\$request->has('cc_agent_id')) {
          \$ccAgentId = \$request->input('cc_agent_id');
          if (empty(\$ccAgentId)) {
              \App\Models\CcSalespersonMap::where('sales_user_id', \$user->id)->delete();
          } else {
              \App\Models\CcSalespersonMap::updateOrCreate(
                  ['sales_user_id' => \$user->id],
                  ['cc_user_id' => \$ccAgentId]
              );
          }
      }
PHP;
$newUpdateMapping = <<<PHP
      // CC Agent Assignment for Sales/New Biz
      if (\$request->has('ccare_agent_id') || \$request->has('newbiz_agent_id')) {
          \App\Models\CcSalespersonMap::where('sales_user_id', \$user->id)->delete();
          if (\$request->filled('ccare_agent_id')) {
              \App\Models\CcSalespersonMap::create(['sales_user_id' => \$user->id, 'cc_user_id' => \$request->input('ccare_agent_id')]);
          }
          if (\$request->filled('newbiz_agent_id')) {
              \App\Models\CcSalespersonMap::create(['sales_user_id' => \$user->id, 'cc_user_id' => \$request->input('newbiz_agent_id')]);
          }
      }
PHP;
$content = str_replace($oldUpdateMapping, $newUpdateMapping, $content);

// 6. Validations
$content = str_replace("'cc_agent_id' => 'nullable|exists:users,id',", "'ccare_agent_id' => 'nullable|exists:users,id',\n        'newbiz_agent_id' => 'nullable|exists:users,id',", $content);

file_put_contents($file, $content);
echo "EmployeeController patched successfully!\n";
