<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    if ($this->app->environment('production')) {
      URL::forceScheme('https');
    }
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    Paginator::useBootstrapFive();

    Vite::useStyleTagAttributes(function (?string $src, string $url, ?array $chunk, ?array $manifest) {
      if ($src !== null) {
        return [
          'class' => preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?core)-?.*/i", $src) ? 'template-customizer-core-css' :
            (preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?theme)-?.*/i", $src) ? 'template-customizer-theme-css' : '')
        ];
      }
      return [];
    });

    /**
     * Register Custom Migration Paths
     */
    $this->loadMigrationsFrom([
      database_path() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'tenant'
    ]);

    /**
     * Alias Constants and ModuleConstants for global access
     */
    $constantsPath = app_path('Constants/Constants.php');
    if (is_file($constantsPath)) {
      require_once $constantsPath;
    }
    if (class_exists(\App\Constants\Constants::class) && !class_exists('Constants')) {
      class_alias(\App\Constants\Constants::class, 'Constants');
    }

    $moduleConstantsPath = app_path('Constants/ModuleConstants.php');
    if (is_file($moduleConstantsPath)) {
      require_once $moduleConstantsPath;
    }
    if (class_exists(\App\Constants\ModuleConstants::class) && !class_exists('ModuleConstants')) {
      class_alias(\App\Constants\ModuleConstants::class, 'ModuleConstants');
    }

    /**
     * Ensure $settings is NEVER null for any view (Final defense)
     */
    \Illuminate\Support\Facades\View::composer('*', function ($view) {
      if (!isset($view->settings) || is_null($view->settings)) {
          $s = \App\Models\Settings::first();
          if (!$s) {
              $s = new \App\Models\Settings();
              $s->app_name = config('app.name', 'HRX');
              $s->currency_symbol = '₹';
              $s->currency = 'INR';
              $s->available_modules = json_encode([]);
          }
          $view->with('settings', $s);
      }
    });
  }
}
