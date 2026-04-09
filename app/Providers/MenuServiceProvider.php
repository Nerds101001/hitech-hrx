<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap services.
   */
  public function boot(): void
  {
    $verticalMenuJson = file_get_contents(base_path('resources/menu/verticalMenu.json'));
    $verticalMenuData = json_decode($verticalMenuJson);

    $tenantVerticalMenuJson = file_get_contents(base_path('resources/menu/tenantVerticalMenu.json'));
    $tenantVerticalMenuData = json_decode($tenantVerticalMenuJson);

    $horizontalMenuJson = file_get_contents(base_path('resources/menu/horizontalMenu.json'));
    $horizontalMenuData = json_decode($horizontalMenuJson);

    $quickCreateMenuJson = file_get_contents(base_path('resources/menu/quickCreateMenu.json'));
    $quickCreateMenuData = json_decode($quickCreateMenuJson);

    // Share all menuData to all the views
    $this->app->make('view')->share('menuData', [$verticalMenuData, $horizontalMenuData, $quickCreateMenuData, $tenantVerticalMenuData]);
  }
}
