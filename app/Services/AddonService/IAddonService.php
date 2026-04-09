<?php

namespace App\Services\AddonService;

interface IAddonService
{
  public function getAvailableAddons();

  public function isAddonEnabled(string $name, bool $isStandard = false): bool;
}
