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
    //Get json file from resources/menu
    $menuJson = file_get_contents(base_path('resources/menu/tenantVerticalMenu.json'));

    if(auth()->user()->hasRole('hr')){
      $menuJson = file_get_contents(base_path('resources/menu/horizontalMenu.json'));
    }

    // Decode JSON into an associative array
    $menuData = json_decode($menuJson, true);

    $menuItems = $menuData['menu'];

    $response[] = [];

    //Populate pages
    $pages = [];
    foreach ($menuItems as $item) {
      if (isset($item['menuHeader'])) {
        continue;
      }
      //Check if item has submenu
      if (isset($item['submenu'])) {
        foreach ($item['submenu'] as $subItem) {
          $itemColl = collect($subItem);
          //Remove first / from url
          $url = substr($itemColl->get('url'), 1);
          $pages[] = [
            'name' => $itemColl->get('name'),
            'url' => $url,
            'icon' => $itemColl->get('icon'),
          ];
        }
      } else {
        $itemColl = collect($item);
        //Remove first / from url
        $url = substr($itemColl->get('url'), 1);
        $pages[] = [
          'name' => $itemColl->get('name'),
          'url' => $url,
          'icon' => $itemColl->get('icon'),
        ];
      }
    }

    $response = [
      'pages' => $pages,
    ];

    $users = User::whereNot('id', auth()->user()->id)->get();

    $members = [];
    foreach ($users as $user) {
      $members[] = [
        'name' => $user->getFullName(),
        'subtitle' => $user->email,
        'src' => $user->profile_picture ? asset(Constants::BaseFolderEmployeeProfileWithSlash . $user->profile_picture) : null,
        'initial' => $user->getInitials(),
        'url' => 'employees/view/' . $user->id,
      ];
    }

    $response['members'] = $members;

    return response()->json($response);
  }
}
