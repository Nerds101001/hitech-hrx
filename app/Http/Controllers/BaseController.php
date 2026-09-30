<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Activation\IActivationService;
use Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BaseController extends Controller
{

  public function accessDenied()
  {
    $pageConfigs = ['myLayout' => 'blank', 'displayCustomizer' => false];
    return view('access-denied', ['pageConfigs' => $pageConfigs]);
  }

  public function Index()
  {
    $activationService = app()->make(IActivationService::class);
    $licenseInfo = $activationService->getActivationInfo();


    if(isset($licenseInfo) && isset($licenseInfo['success']) && $licenseInfo['success'] && !auth()->check()) {
      return redirect()->route('login');
    }


    return view('activation.index',[
      'licenseInfo' => $licenseInfo,
    ]);
  }

  public function activate(Request $request)
  {
    $request->validate([
      'licenseKey' => 'required|string',
      'email' => 'required|email'
    ]);

    $licenseKey = $request->input('licenseKey');

    $activationService = app()->make(IActivationService::class);

    $email = $request->input('email', '');

    if ($request->filled('envato_username')) {
      $envatoUsername = $request->input('envato_username');
      $result = $activationService->envatoActivate($licenseKey, $envatoUsername,$email);
    } else {
      $result = $activationService->activate($licenseKey,$email);
    }

    if ($result->get('success')) {
      $activationCode = $result->get('activation_code');

      $file = storage_path('app/activation_code.txt');
      file_put_contents($file, $activationCode);

      $cacheKey = 'license_validity_' . config('app.url');
      Cache::store('file')->put($cacheKey, true, now()->addHour());

      return redirect()->route('activation.index')->with('message', 'Activation successful.');
    } else {
      $errorMsg = $result->get('message') ?? 'Activation failed. Please try again.';
      return redirect()->route('activation.index')->with('error', $errorMsg);
    }
  }

  public function getSearchDataAjax()
  {
    $user = auth()->user();
    $userId = $user->id;
    $role = $user->hasRole('hr') ? 'hr' : 'other';
    $cacheKey = "global_search_data_{$userId}_{$role}";

    return Cache::remember($cacheKey, 3600, function () use ($user) {
      //Get json file from resources/menu
      $menuJson = file_get_contents(base_path('resources/menu/tenantVerticalMenu.json'));

      if ($user->hasRole('hr')) {
        $menuJson = file_get_contents(base_path('resources/menu/horizontalMenu.json'));
      }

      // Decode JSON into an associative array
      $menuData = json_decode($menuJson, true);
      $menuItems = $menuData['menu'];
      $pages = [];

      // Same visibility rule the sidebar itself applies (see
      // verticalMenu.blade.php) — a menu entry can carry its own 'roles',
      // 'permission' or 'department' gate, and a submenu child can carry
      // another copy of the same on top of its parent's. Search was reading
      // this same JSON but skipping all of it, so every admin/HR-only and
      // department-only page — Admin Dashboard, Agent Mapping, Telephony
      // Analytics — was searchable and (via Nav::allows below) sometimes
      // actually openable by anyone signed in.
      $isAdminUser = $user->hasRole(['Admin', 'admin', 'super_admin', 'ADMIN', 'HR', 'hr']);
      $hasAnyPermission = function ($permission) use ($user) {
        foreach ((array) $permission as $p) {
          if ($user->can($p)) return true;
        }
        return false;
      };
      $passesGate = function (array $item) use ($user, $isAdminUser, $hasAnyPermission) {
        // Audience-only links (same "hideForRoles" rule as the sidebar).
        if (isset($item['hideForRoles'])
            && array_intersect(array_map('strtolower', $user->roles->pluck('name')->toArray()), array_map('strtolower', (array) $item['hideForRoles']))) {
          return false;
        }
        if (isset($item['roles'])) {
          $ur = array_map('strtolower', $user->roles->pluck('name')->toArray());
          $rr = array_map('strtolower', (array) $item['roles']);
          if (empty(array_intersect($ur, $rr))
              && !\App\Support\Nav::grantedByPermission(\App\Support\Nav::menuUrls($item), $user)) return false;
        }
        if (isset($item['permission']) && !$isAdminUser) {
          if (!$hasAnyPermission($item['permission'])) return false;
        }
        if (isset($item['department']) && !$isAdminUser) {
          if (!$user->department || stripos($user->department->name, $item['department']) === false) return false;
        }
        return true;
      };

      //Populate pages
      foreach ($menuItems as $item) {
        if (isset($item['menuHeader']) || !$passesGate($item)) {
          continue;
        }
        //Check if item has submenu
        if (isset($item['submenu'])) {
          foreach ($item['submenu'] as $subItem) {
            if (!$passesGate($subItem)) continue;
            $url = substr($subItem['url'] ?? '', 1);
            if ($url !== '' && !\App\Support\Nav::allows($url, $user)) continue;
            $pages[] = [
              'name' => $subItem['name'] ?? null,
              'url' => $url,
              'icon' => $subItem['icon'] ?? null,
            ];
          }
        } else {
          $url = substr($item['url'] ?? '', 1);
          if ($url !== '' && !\App\Support\Nav::allows($url, $user)) continue;
          $pages[] = [
            'name' => $item['name'] ?? null,
            'url' => $url,
            'icon' => $item['icon'] ?? null,
          ];
        }
      }

      // Members are only searchable by whoever can actually open an employee
      // profile — the same role_or_permission gate routes/tenant.php puts on
      // the whole employees/* route group (it doesn't vary per target id, so
      // one check covers the list). Everyone else gets no results instead of
      // the entire company directory — matching the 403 they'd already get
      // from clicking through to employees/view/{id} directly.
      $members = [];
      if (\App\Support\Nav::allows('employees/view/' . $user->id, $user)) {
        $users = User::whereNot('id', $user->id)->where('status', \App\Enums\UserAccountStatus::ACTIVE)->get();
        foreach ($users as $u) {
          $members[] = [
            'name' => $u->getFullName(),
            'subtitle' => $u->email,
            'src' => $u->profile_picture ? asset(\Constants::BaseFolderEmployeeProfileWithSlash . $u->profile_picture) : null,
            'initial' => $u->getInitials(),
            'url' => 'employees/view/' . $u->id,
          ];
        }
      }

      return [
        'pages' => $pages,
        'members' => $members,
      ];
    });
  }

}
