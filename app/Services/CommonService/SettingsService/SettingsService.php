<?php

namespace App\Services\CommonService\SettingsService;

use App\Models\Settings;
use Illuminate\Support\Collection;

class SettingsService implements ISettings
{

  private Settings $settings;

  public function __construct()
  {
    $this->settings = Settings::first();
  }

  public function isDeviceVerificationEnabled(): bool
  {
    return $this->settings->is_device_verification_enabled;
  }


  public function getDocumentTypePrefix(): string
  {
    return $this->settings->document_type_code_prefix;
  }

  public function getAllSettings(): Collection
  {
    return collect($this->settings->toArray());
  }
}
