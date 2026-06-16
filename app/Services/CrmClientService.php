<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CrmClientService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected string $cacheKey = 'crm_api_token';

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) config('services.crm.base_url', ''), '/');
        $this->username = (string) config('services.crm.username', '');
        $this->password = (string) config('services.crm.password', '');
    }

    /**
     * Get (or refresh) the CRM API Bearer token.
     */
    public function getToken(): ?string
    {
        return Cache::remember($this->cacheKey, now()->addHours(8), function () {
            return $this->fetchToken();
        });
    }

    /**
     * Force a fresh token (call when API returns 401).
     */
    public function refreshToken(): ?string
    {
        Cache::forget($this->cacheKey);
        return $this->getToken();
    }

    protected function fetchToken(): ?string
    {
        try {
            $response = Http::timeout(10)
                ->post("{$this->baseUrl}/api/Authentication/dologin", [
                    'username' => $this->username,
                    'password' => $this->password,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                // CRM API returns token in 'TokenKey' field
                return $data['TokenKey'] ?? $data['token'] ?? $data['Token'] ?? $data['access_token'] ?? null;
            }

            Log::error('CRM API login failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        } catch (\Exception $e) {
            Log::error('CRM API login exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Make an authenticated GET request to the CRM API.
     */
    protected function get(string $endpoint, array $params = []): ?array
    {
        $token = $this->getToken();
        if (!$token) return null;

        try {
            $response = Http::timeout(15)
                ->withToken($token)
                ->get("{$this->baseUrl}/{$endpoint}", $params);

            if ($response->status() === 401) {
                // Token expired — refresh and retry once
                $token = $this->refreshToken();
                $response = Http::timeout(15)
                    ->withToken($token)
                    ->get("{$this->baseUrl}/{$endpoint}", $params);
            }

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('CRM API GET failed', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Make an authenticated POST request to the CRM API.
     */
    protected function post(string $endpoint, array $body = [], int $timeout = 30): ?array
    {
        $token = $this->getToken();
        if (!$token) return null;

        try {
            $response = Http::timeout($timeout)
                ->withToken($token)
                ->post("{$this->baseUrl}/{$endpoint}", $body);

            if ($response->status() === 401) {
                $token = $this->refreshToken();
                $response = Http::timeout($timeout)
                    ->withToken($token)
                    ->post("{$this->baseUrl}/{$endpoint}", $body);
            }

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('CRM API POST failed', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fetch paginated customer list.
     * Uses 60s timeout since this is a large dataset operation.
     */
    public function getCustomers(int $pageNo = 1, int $pageSize = 50, string $search = ''): array
    {
        if ($search) {
            $items = $this->searchCustomers($search);
            return ['data' => $items, 'total' => count($items)];
        }

        // CRM API often returns ALL records at once (ignoring pageSize).
        // Use 5-minute timeout to handle the large ~11MB response.
        $result = $this->post('api/Customer/SearchCRMCLientList', [
            'searchText' => '',
            'pageNo'     => $pageNo,
            'pageSize'   => $pageSize,
        ], 300); // 5-minute timeout — CRM returns ~11MB for full list

        if (!$result) return ['data' => [], 'total' => 0];

        // CRM wraps data in 'Data' key.
        // NOTE: 'Result' = status code (1 = success), NOT record count.
        // Real total may be in TotalCount, TotalRecords, Count, or similar.
        // We check all known fields; if none is a real count (>1 on page 1),
        // fall back to count of returned items — the JS loop uses `done` flag anyway.
        $items = $result['Data'] ?? $result['data'] ?? (isset($result[0]) ? $result : []);

        // Try to find the real total — skip 'Result' (it's always 1 = success code)
        $total = (int) (
            $result['TotalCount']   ??
            $result['totalCount']   ??
            $result['TotalRecords'] ??
            $result['totalRecords'] ??
            $result['Total']        ??
            $result['Count']        ??
            $result['count']        ??
            0
        );

        // If we still have no total, use the item count (the JS `done` flag will stop the loop correctly)
        if ($total === 0) {
            $total = count($items);
        }

        return ['data' => $items, 'total' => $total];
    }

    /**
     * Search customers by text.
     */
    public function searchCustomers(string $query): array
    {
        $result = $this->post('api/Customer/SearchCRMCLientList', [
            'searchText' => $query,
            'pageNo'     => 1,
            'pageSize'   => 30,
        ]);

        if (!$result) return [];

        // CRM returns flat array of objects with Comp_Code, Comp_Name, City, State, Stages
        return $result['Data'] ?? $result['data'] ?? (isset($result[0]) ? $result : []);
    }

    /**
     * Get all active CRM employees
     */
    public function getEmployees(): array
    {
        $result = $this->get('api/Employee/GetEmployeeList');
        return $result['data'] ?? $result['Data'] ?? [];
    }

    /**
     * Build a lookup map: lowercase employee name → CRM EMP_CODE
     * Used to resolve person names in customer detail to CRM employee codes.
     *
     * CRM API returns names (not codes) for Sales, CCare, Billing, Finance.
     * This map lets us go: "John Smith" → 1234 (EMP_CODE) → HRX user via crm_id.
     *
     * Cached for 2 hours so we don't call GetEmployeeList on every sync batch.
     */
    public function getCrmNameToCodeMap(): array
    {
        return Cache::remember('crm_emp_name_to_code', now()->addHours(2), function () {
            $employees = $this->getEmployees();
            $map = [];
            foreach ($employees as $emp) {
                $code = $emp['EMP_CODE'] ?? $emp['emp_code'] ?? $emp['Emp_Code'] ?? null;
                $name = $emp['EMP_NAME'] ?? $emp['emp_name'] ?? $emp['Emp_Name'] ?? null;
                if ($code && $name) {
                    $map[strtolower(trim($name))] = (int) $code;
                    // Also store first-name-only variant for partial matching
                    $firstName = strtolower(explode(' ', trim($name))[0]);
                    if (!isset($map[$firstName])) {
                        $map[$firstName] = (int) $code;
                    }
                }
            }
            Log::info('CRM name→code map built', ['total_employees' => count($employees), 'map_entries' => count($map)]);
            return $map;
        });
    }

    /**
     * Get a single customer by CompCode.
     */
    public function getCustomerById(string $compCode): ?array
    {
        $token = $this->getToken();
        if (!$token) return null;

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(8)
                ->withToken($token)
                ->get("{$this->baseUrl}/api/Customer/getCompanyById/{$compCode}");

            if ($response->status() === 401) {
                $token = $this->refreshToken();
                $response = \Illuminate\Support\Facades\Http::timeout(8)
                    ->withToken($token)
                    ->get("{$this->baseUrl}/api/Customer/getCompanyById/{$compCode}");
            }

            if (!$response->successful()) return null;

            $json = $response->json();

            // CRM wraps the actual company data inside a "Data" key:
            // { "Data": { "COMP_NAME": ..., "REPRESENTATIVE": ... }, "StatusCode": 200, ... }
            return $json['Data'] ?? $json['data'] ?? $json;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("CRM getCustomerById failed for {$compCode}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Get all customers for a specific employee (by CRM emp code).
     */
    public function getCustomersByEmployee(string $empCode): array
    {
        $result = $this->post('api/Customer/getEmpAllClientList', [
            'empCode' => $empCode,
        ]);

        if (!$result) return [];
        return $result['data'] ?? $result['Data'] ?? (isset($result[0]) ? $result : []);
    }
}
