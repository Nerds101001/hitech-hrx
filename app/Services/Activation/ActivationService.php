<?php

namespace App\Services\Activation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

class ActivationService implements IActivationService
{
  protected $baseUrl;

  public function __construct()
  {
    $this->baseUrl = config('variables.creatorUrl');
  }

  public function getActivationStatus(?string $licenseKey = null): bool
  {
    if (!config('custom.custom.activationService')) {
      return true;
    }

    $apiUrl = $this->baseUrl . 'api/v1/core/activation/checkStatus';
    $request = [
      'licenseKey' => $licenseKey ?? config('variables.licenseKey'),
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '?' . http_build_query($request));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      curl_close($ch);
      return false;
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 200;
  }

  public function getActivationInfo(?string $activationCode = null): Collection
  {
    if (!config('custom.custom.activationService')) {
      return collect();
    }

    if(!$activationCode){
      // Get activation code from file
      $file = storage_path('app/activation_code.txt');
      if (file_exists($file)) {
        $activationCode = file_get_contents($file);
      }
    }

    $domain = request()->getSchemeAndHttpHost();

    $apiUrl = $this->baseUrl . 'wp-json/license-activation/v1/activation-status?activation_code=' . $activationCode. '&domain=' . $domain;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      curl_close($ch);
      return collect();
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    Log::info('Activation info response: ' . $response);

    if ($httpCode === 200) {
      return collect(json_decode($response, true));
    }

    return collect(json_decode($response, true));
  }

  /**
   * Collect necessary server information.
   *
   * @return array
   */
  protected function getServerInfo(): array
  {
    return [
      'phpVersion'         => phpversion(),
      'mysqlVersion'       => $this->getMySQLVersion(),
      'domainUrl'          => $this->getDomainUrl(),
      'ipAddress'          => $this->getIpAddress(),
      'hostName'           => gethostname(),
      'serverProtocol'     => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
      'serverSoftware'     => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
      'os'                 => php_uname(),
      'serverTimezone'     => date_default_timezone_get(),
      'serverArchitecture' => php_uname('m'),
      'appVersion'         => config('variables.templateVersion'),
      'appEnvironment'     => config('app.env'),
      'appName'            => config('app.name'),
      //'cpuCores'           => trim(shell_exec('nproc')) ?: 'Unknown',
      'memoryLimit'        => ini_get('memory_limit'),
      'diskTotal'          => disk_total_space("/"),
      'diskFree'           => disk_free_space("/"),
      'serverLocale'       => setlocale(LC_ALL, 0),
      'serverTime'         => date('Y-m-d H:i:s'),
      'userAgent'          => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
      'frameworkVersion'   => app()->version(),
    ];
  }

  /**
   * Activate a license using normal activation.
   *
   * @param string $licenseKey
   * @return Collection
   */
  public function activate(string $licenseKey, string $email): Collection
  {
    try{
      if (!config('custom.custom.activationService')) {
        return collect();
      }

      $apiUrl = $this->baseUrl . 'wp-json/license-activation/v1/activate';

      Log::info('Activating license: ' . $licenseKey);

      $serverInfo = $this->getServerInfo();

      Log::info('Server info: ' . json_encode($serverInfo));

      $domain = request()->getSchemeAndHttpHost();

      $request = [
        'purchase_code' => $licenseKey,
        'activation_type' => 'live',
        'item_id' => config('variables.itemId'),
        'app_version' => config('variables.templateVersion'),
        'email' => $email,
        'domain' => $domain,
        'server_info' => json_encode($serverInfo)
      ];

      Log::info('Activation request: ' . json_encode($request));

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $apiUrl);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

      $response = curl_exec($ch);

      Log::info('Activation response: ' . $response);

      if (curl_errno($ch)) {
        curl_close($ch);
        return collect();
      }
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($httpCode === 200 || $httpCode === 401 || $httpCode === 400) {
        return collect(json_decode($response, true));
      }
      Log::info('Activation failed: ' . $response);
      return collect(json_decode($response, true));
    }catch (\Exception $e){
      Log::error('Activation failed with exception: ' . $e->getMessage());
      return collect();
    }
  }

  /**
   * Activate a license via Envato.
   *
   * Expects the following parameters:
   * - $saleCode: The Envato sale code.
   * - $envatoUsername: The Envato username provided by the client.
   * - $domain: The domain to activate.
   * - $email: The email address to associate with the activation.
   * - $activationType: Either "live" or "localhost" (defaults to "live").
   *
   * Returns a collection with the response from the activation API.
   */
  public function envatoActivate(
    string $licenseKey,
    string $envatoUsername,
    string $email,
  ): Collection {
    if (!config('custom.custom.activationService')) {
      return collect();
    }

    $apiUrl = $this->baseUrl . 'wp-json/license-activation/v1/envato-activate';

    // Collect server information and include it in the request.
    $serverInfo = $this->getServerInfo();

    $domain = request()->getSchemeAndHttpHost();

    // Build the request data.
    $requestData = [
      'purchase_code'         => $licenseKey,
      'user_name'   => $envatoUsername,
      'email'             => $email,
      'item_id' => config('variables.envatoItemId'),
      'app_version' => config('variables.templateVersion'),
      'domain'            => $domain,
      'server_info'       => json_encode($serverInfo)
    ];

    Log::info('Activation request: ' . json_encode($requestData));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);

    Log::info('Activation response: ' . $response);

    if (curl_errno($ch)) {
      curl_close($ch);
      return collect();
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 401 || $httpCode === 400) {
      return collect(json_decode($response, true));
    }

    return collect();
  }

  /**
   * Optionally, retrieve Envato activation details.
   *
   * You could implement this similarly to getActivationInfo() by calling an appropriate endpoint.
   *
   * @param string $saleCode
   * @return Collection
   */
  public function getEnvatoActivationInfo(string $saleCode): Collection
  {
   try{
     if (!config('custom.custom.activationService')) {
       return collect();
     }

     $apiUrl = $this->baseUrl . 'api/v1/core/activation/envato-getInfo';
     $request = [
       'sale_code' => $saleCode,
     ];

     $ch = curl_init();
     curl_setopt($ch, CURLOPT_URL, $apiUrl);
     curl_setopt($ch, CURLOPT_POST, true);
     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

     $response = curl_exec($ch);
     if (curl_errno($ch)) {
       curl_close($ch);
       return collect();
     }
     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
     curl_close($ch);

     if ($httpCode === 200 || $httpCode === 401 || $httpCode === 400|| $httpCode === 403) {
       return collect(json_decode($response, true));
     }

     Log::info('Activation failed: ' . $response);
     return collect(json_decode($response, true));
   }catch (\Exception $e){
      Log::error('Activation failed with exception: ' . $e->getMessage());
      return collect();
   }
  }

  public function deactivate(string $licenseKey): bool
  {
    // Implement deactivation logic if needed.
    return false;
  }

  private function getMySQLVersion(): string
  {
    $pdo = DB::connection()->getPdo();
    return $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
  }

  private function getDomainUrl(): string
  {
    return request()->getSchemeAndHttpHost();
  }

  private function getIpAddress(): string
  {
    return request()->ip();
  }

  /**
   * Compare stored server info with current info.
   *
   * @param array $storedServerInfo
   * @return bool
   */
  public function verifyLicenseLock(array $storedServerInfo): bool
  {
    $currentInfo = $this->getServerInfo();
    // For example, compare domainUrl and ipAddress.
    if ($currentInfo['domainUrl'] !== $storedServerInfo['domainUrl'] ||
      $currentInfo['ipAddress'] !== $storedServerInfo['ipAddress']) {
      return false;
    }
    return true;
  }

  public function checkValidActivation(?string $licenseKey = null): bool
  {
    try {

      if(!$licenseKey){
        // Get activation code from file
        $file = storage_path('app/activation_code.txt');
        if (file_exists($file)) {
          $licenseKey = file_get_contents($file);
        }
      }

      $appVersion = config('variables.templateVersion');

      $domain = request()->getSchemeAndHttpHost();

      $apiUrl = $this->baseUrl . 'wp-json/license-activation/v1/validate-activation?activation_code=' . $licenseKey . '&app_version=' . $appVersion. '&domain=' . $domain;

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $apiUrl );
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $response = curl_exec($ch);

      Log::info('checkValidActivation request: ' . $apiUrl);

      if (curl_errno($ch)) {
        curl_close($ch);
        return false;
      }

      Log::info('checkValidActivation response: ' . $response);

      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($httpCode === 200) {

        Log::info('checkValidActivation response: ' . $response);

        $response = json_decode($response, true);
        return $response['valid'];
      }

      Log::info('checkValidActivation failed: ' . $response);

      return false;
    } catch (\Exception $e) {
      Log::error('checkValidActivation failed: ' . $e->getMessage());
      return false;
    }
  }
}
