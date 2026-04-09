<?php

namespace App\Services\Activation;

use Illuminate\Support\Collection;

interface IActivationService
{
  public function checkValidActivation(?string $licenseKey = null): bool;

  public function getActivationStatus(?string $licenseKey = null): bool;

  public function getActivationInfo(?string $activationCode = null): Collection;

  public function activate(string $licenseKey, string $email): Collection;

  public function envatoActivate(
    string $licenseKey,
    string $envatoUsername,
    string $email,
  ): Collection;

  public function getEnvatoActivationInfo(string $saleCode): Collection;

  public function deactivate(string $licenseKey): bool;

  public function verifyLicenseLock(array $storedServerInfo): bool;
}
