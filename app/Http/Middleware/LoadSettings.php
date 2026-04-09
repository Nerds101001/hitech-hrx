<?php

namespace App\Http\Middleware;

use App\Models\Settings;
use App\Models\SuperAdmin\SaSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class LoadSettings
{
  /**
   * Handle an incoming request.
   *
   * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    // Try to get from cache first
    $settings = Cache::get('app_settings');

    if (!is_object($settings)) {
      $settings = Settings::first();
      
      // Fallback if DB is empty
      if (!$settings) {
        $settings = new Settings();
        $settings->app_name = config('app.name', 'HRX');
        $settings->currency_symbol = '₹';
        $settings->currency = 'INR';
      } else {
        // Only cache if we have a valid database object
        Cache::put('app_settings', $settings, 60 * 60);
      }
    }

    view()->share('settings', $settings);

    return $next($request);
  }
}
