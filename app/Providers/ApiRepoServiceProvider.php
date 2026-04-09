<?php

namespace App\Providers;

use App\Services\Api\Attendance\AttendanceService;
use App\Services\Api\Attendance\IAttendance;
use App\Services\CommonService\SettingsService\ISettings;
use App\Services\CommonService\SettingsService\SettingsService;
use App\Services\UserService\IUserService;
use App\Services\UserService\UserService;
use Illuminate\Support\ServiceProvider;

class ApiRepoServiceProvider extends ServiceProvider
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
    $this->app->bind(
      IUserService::class,
      UserService::class
    );

    $this->app->bind(
      ISettings::class,
      SettingsService::class
    );

    $this->app->bind(
      IAttendance::class,
      AttendanceService::class
    );


  }
}
