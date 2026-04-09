<?php

namespace App\Services\CommonService\SettingsService;

use Illuminate\Support\Collection;

interface ISettings
{
  public function isDeviceVerificationEnabled(): bool;

  public function getDocumentTypePrefix(): string;

  public function getAllSettings(): Collection;
}
