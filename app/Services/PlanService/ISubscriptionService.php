<?php

namespace App\Services\PlanService;

use App\Models\Settings;
use App\Models\SuperAdmin\Order;
use App\Models\SuperAdmin\Plan;
use App\Models\SuperAdmin\Subscription;
use DateTime;

interface ISubscriptionService
{
  public function generatePlanExpiryDate(Plan $plan): DateTime;

  public function getPlanTotalAmount(Plan $plan, int $usersCount): float;

  public function getAddUserTotalAmount(int $usersCount): float;

  public function getCurrentPlan(): Plan;

  public function activatePlan(Order $order): void;

  public function renewPlan(Order $order): void;

  public function upgradePlan(Order $order): void;

  public function addUsersToSubscription(Order $order): void;

  public function getRenewalAmount(): float;

  public function getSubscription(): Subscription;

  public function getDifferencePriceForUpgrade(int $newPlanId): float;

  public function processTenantSettingsForAccessRoutesByPlan(): Settings;

  public function refreshPlanAccessForTenants($planId);
}
