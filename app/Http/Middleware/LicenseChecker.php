<?php
namespace App\Http\Middleware;

use App\Services\Activation\IActivationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LicenseChecker
{
  /**
   * Handle an incoming request.
   *
   * Checks the license validity only once per hour.
   *
   * @param \Illuminate\Http\Request $request
   * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response) $next
   * @return mixed
   */
  public function handle(Request $request, Closure $next)
  {
    if (!config('custom.custom.activationService')) {
      return $next($request);
    }

    // Use a cache key to store the license validity.
    $cacheKey = 'license_validity_' . config('app.url'); // or another unique key for your installation

    // Retrieve the validity from cache, or call the activation service if not present.
    if (!Cache::store('file')->has($cacheKey)) {
      /** @var IActivationService $activationService */
      $activationService = app()->make(IActivationService::class);
      $isValid = $activationService->checkValidActivation();
      Log::info('License validation result from api: ' . ($isValid ? 'valid' : 'invalid'));
      Cache::store('file')->put($cacheKey, $isValid, now()->addHour());
    } else {
      $isValid = Cache::store('file')->get($cacheKey);
    }

    if (!$isValid) {
      Cache::store('file')->forget($cacheKey);

      // Log the invalid activation attempt.
      Log::warning('License validation failed for IP: ' . $request->ip());

      //Check if current route is the activation page
      if ($request->routeIs('activation.*')) {
        return $next($request);
      }

      // Redirect to the activation page with an error message.
      return redirect()->route('activation.index')
        ->withErrors(['license' => 'Your license is invalid or has been deactivated. Please activate your copy.']);
    } else {
      Log::info('License validation passed for IP: ' . $request->ip());
    }

    return $next($request);
  }
}
