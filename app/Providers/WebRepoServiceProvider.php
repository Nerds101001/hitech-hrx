<?php

namespace App\Providers;

use App\Services\Activation\ActivationService;
use App\Services\Activation\IActivationService;
use App\Services\AddonService\AddonService;
use App\Services\AddonService\IAddonService;
use App\Services\PlanService\ISubscriptionService;
use App\Services\PlanService\SubscriptionService;
use App\Services\Web\NotificationService\INotificationService;
use App\Services\Web\NotificationService\NotificationService;
use Illuminate\Support\ServiceProvider;

class WebRepoServiceProvider extends ServiceProvider
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
      INotificationService::class,
      NotificationService::class
    );

    $this->app->bind(
      ISubscriptionService::class,
      SubscriptionService::class
    );

    $this->app->bind(
      IAddonService::class,
      AddonService::class
    );

    $this->app->bind(
      IActivationService::class,
      ActivationService::class
    );
  }
}
