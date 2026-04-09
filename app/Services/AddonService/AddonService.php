<?php

namespace App\Services\AddonService;

use App\Models\Settings;
use App\Services\PlanService\ISubscriptionService;
use Nwidart\Modules\Facades\Module;

class AddonService implements IAddonService
{

  private ISubscriptionService $subscriptionService;

  function __construct(ISubscriptionService $subscriptionService)
  {
    $this->subscriptionService = $subscriptionService;
  }

  public function getAvailableAddons()
  {
    $settings = Settings::first();

    if (!$settings) {
      return [];
    }

    if (!$settings->accessible_module_routes) {
      $this->subscriptionService->processTenantSettingsForAccessRoutesByPlan();
    }

    return $settings->available_modules;
  }

  /*
   * Check if the addon is enabled
   * @param string $name
   * @param bool $isStandard
   * @return bool
   */

  public function isAddonEnabled(string $name, bool $isStandard = false): bool
  {
    // Temporarily disabled modules as per user request
    $disabledModules = ['AiChat', 'Monitoring', 'Reports', 'ClientVisit', 'SoS', 'Device', 'RegisteredDevices', 'DisabledModule'];
    if (in_array($name, $disabledModules)) {
      return false;
    }

    $settings = Settings::first();
    $addons = $settings->available_modules ?? [];

      if ($isStandard) {
        return in_array($name, $addons);
      }
    $module = Module::find($name);

    $result = ($module != null && $module->isEnabled());

    return $result;
  }
}
