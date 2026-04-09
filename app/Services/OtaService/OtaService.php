<?php

namespace App\Services\OtaService;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class OtaService implements IOtaService
{

  public function getAddonsInfo(): Collection
  {
    $baseUrl = config('variables.pdaUrl');

    $apiUrl = $baseUrl . 'api/v1/core/product/getAddonsInfo';

    //Get request to the api with licenseKey and uid
    $response = Http::get($apiUrl, [
      'licenseKey' => config('variables.licenseKey'),
      'uid' => config('variables.uid')
    ]);

    if ($response->ok()) {
      return collect(json_decode($response->body()));
    }

    return collect([
      'message' => 'Failed to get addons info',
      'status' => $response->status(),
      'data' => $response->body(),
    ]);
  }

  public function getAddonVersion(string $uid): string
  {
    // TODO: Implement getAddonVersion() method.
  }

  public function isUpdateAvailableForAddon(string $uid, string $version): bool
  {
    // TODO: Implement isUpdateAvailableForAddon() method.
  }

  public function isUpdateAvailableForProduct(string $uid, string $version): bool
  {
    // TODO: Implement isUpdateAvailableForProduct() method.
  }
}
