<?php

namespace App\Services\OtaService;

use Illuminate\Support\Collection;

interface IOtaService
{
  public function getAddonsInfo(): Collection;

  public function getAddonVersion(string $uid): string;

  public function isUpdateAvailableForAddon(string $uid, string $version): bool;

  public function isUpdateAvailableForProduct(string $uid, string $version): bool;
  

}
