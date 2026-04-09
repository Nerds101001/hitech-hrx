<?php

namespace App\Http\Middleware;

use App\Services\AddonService\IAddonService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AddonCheckMiddleware
{

  /**
   * Handle an incoming request.
   *
   * @param Closure(Request): (Response) $next
   */
  public function handle(Request $request, Closure $next): Response
  {
  /*  if (tenancy()->initialized && auth()->check()) {

      Log::info('AddonCheckMiddleware: Checking addon permission');
      $addonService = app(IAddonService::class);

      if ($request->header('addon') != null) {
        $addon = $request->header('addon');
        if (!$addonService->isAddonEnabled($addon)) {
          return redirect()->route('accessDenied')->with('error', 'You do not have permission to access this page');
        }
      } else {
        Log::info('AddonCheckMiddleware: No addon header found');
      }
    }*/

    return $next($request);
  }
}
