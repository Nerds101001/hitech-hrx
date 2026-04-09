<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\Tenant;

class BaseApiController extends Controller
{
  public function getTenantDomains()
  {

    $tenants = Tenant::where('status', 'active')
      ->with('domains')
      ->get();

    $response = [];
    //Regex to check if the domain is a localhost and removes it
    $regex = '/localhost/';
    foreach ($tenants as $tenant) {

      $domains = $tenant->domains->pluck('domain');
      $finalDomains = [];

      foreach ($domains as $domain) {
        if (!preg_match($regex, $domain)) {
          $finalDomains[] = 'https://' . $domain;
        }
      }

      if (empty($finalDomains)) {
        continue;
      }

      $response[] = [
        'tenantId' => $tenant->id,
        'tenantName' => $tenant->name,
        'domain' => $finalDomains[0],
        'domains' => $finalDomains
      ];
    }

    return Success::response($response);
  }

  public function checkDemoMode()
  {
    return Success::response(env('APP_DEMO'));
  }
}
