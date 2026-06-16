<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\CrmClientMapping;
use App\Models\User;
use App\Services\CrmClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmClientMappingController extends Controller
{
    protected CrmClientService $crm;

    public function __construct(CrmClientService $crm)
    {
        $this->crm = $crm;
    }

    /**
     * Return the current tenant ID, falling back to the user's own ID as a string
     * in local/dev environments where tenant_id may not be set on the user.
     */
    protected function tenantId(): ?string
    {
        return Auth::user()->tenant_id ?: null;
    }

    /**
     * Main management page — list all mappings.
     */
    public function index(Request $request)
    {
        $mappings = CrmClientMapping::with(['salesperson', 'ccare', 'newBiz', 'billing', 'finance'])
            ->orderBy('crm_company_name')
            ->paginate(25);

        // All active HRX employees for the dropdowns
        $employees = User::select('id', 'first_name', 'last_name', 'code')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get()
            ->map(fn($u) => [
                'id'   => $u->id,
                'name' => trim("{$u->first_name} {$u->last_name}") . ($u->code ? " ({$u->code})" : ''),
            ]);

        return view('tenant.crm-clients.index', compact('mappings', 'employees'));
    }

    /**
     * AJAX: search CRM API for companies.
     */
    public function searchCrm(Request $request)
    {
        $query = trim($request->get('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = $this->crm->searchCustomers($query);

        // Format for select2 / JS dropdown
        $formatted = collect($results)->map(fn($c) => [
            'id'    => $c['Comp_Code'] ?? $c['comp_code'] ?? null,
            'text'  => $c['Comp_Name'] ?? $c['comp_name'] ?? 'Unknown',
            'city'  => $c['City'] ?? '',
            'state' => $c['State'] ?? '',
            'stage' => $c['Stages'] ?? '',
        ])->filter(fn($c) => $c['id'] !== null)->values();

        return response()->json($formatted);
    }

    /**
     * Save a new mapping.
     */
    public function store(Request $request)
    {
        $request->validate([
            'crm_company_code' => 'required|integer',
            'crm_company_name' => 'required|string|max:255',
            'salesperson_id'   => 'nullable|exists:users,id',
            'ccare_id'         => 'nullable|exists:users,id',
            'new_biz_id'       => 'nullable|exists:users,id',
            'billing_id'       => 'nullable|exists:users,id',
            'finance_id'       => 'nullable|exists:users,id',
        ]);

        $tenantId = $this->tenantId();

        // Build match criteria — if tenant_id is null (local dev), match on crm_company_code only
        $match = $tenantId
            ? ['tenant_id' => $tenantId, 'crm_company_code' => $request->crm_company_code]
            : ['crm_company_code' => $request->crm_company_code];

        CrmClientMapping::updateOrCreate(
            $match,
            [
                'crm_company_name' => $request->crm_company_name,
                'crm_city'         => $request->crm_city,
                'crm_state'        => $request->crm_state,
                'crm_stage'        => $request->crm_stage,
                'salesperson_id'   => $request->salesperson_id ?: null,
                'ccare_id'         => $request->ccare_id ?: null,
                'new_biz_id'       => $request->new_biz_id ?: null,
                'billing_id'       => $request->billing_id ?: null,
                'finance_id'       => $request->finance_id ?: null,
                'notes'            => $request->notes,
                'created_by_id'    => Auth::id(),
                'updated_by_id'    => Auth::id(),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Client mapping saved successfully.']);
    }

    /**
     * Update an existing mapping.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'salesperson_id' => 'nullable|exists:users,id',
            'ccare_id'       => 'nullable|exists:users,id',
            'new_biz_id'     => 'nullable|exists:users,id',
            'billing_id'     => 'nullable|exists:users,id',
            'finance_id'     => 'nullable|exists:users,id',
        ]);

        $mapping = CrmClientMapping::findOrFail($id);
        $mapping->update([
            'salesperson_id' => $request->salesperson_id ?: null,
            'ccare_id'       => $request->ccare_id ?: null,
            'new_biz_id'     => $request->new_biz_id ?: null,
            'billing_id'     => $request->billing_id ?: null,
            'finance_id'     => $request->finance_id ?: null,
            'notes'          => $request->notes,
            'updated_by_id'  => Auth::id(),
        ]);

        // Refresh CRM data from API
        if ($fresh = $this->crm->getCustomerById($mapping->crm_company_code)) {
            $mapping->update([
                'crm_city'  => $fresh['City'] ?? $mapping->crm_city,
                'crm_state' => $fresh['State'] ?? $mapping->crm_state,
                'crm_stage' => $fresh['Stages'] ?? $mapping->crm_stage,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Mapping updated.']);
    }

    /**
     * Delete a mapping.
     */
    public function destroy($id)
    {
        CrmClientMapping::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Mapping removed.']);
    }

    /**
     * STEP 1 — Sync status: returns total expected + already synced count.
     * GET /crm-clients/sync-status?mode=incremental
     *
     * mode=full        → fetch all customers (fresh)
     * mode=incremental → only fetch customers NOT yet in DB
     */
    public function syncStatus(Request $request)
    {
        $perPage   = 10; // 10 per batch = ~10 detail API calls = fast
        $mode      = $request->get('mode', 'full');
        $alreadySynced = \App\Models\CrmClientMapping::count();

        return response()->json([
            'success'       => true,
            'total'         => 0,   // discovered live as pages come in
            'per_page'      => $perPage,
            'total_pages'   => 0,   // JS loops until done=true
            'already_synced'=> $alreadySynced,
            'mode'          => $mode,
        ]);
    }

    /**
     * STEP 2 — Sync one page of CRM clients into HRX.
     * POST /crm-clients/sync-page  { page: N, mode: 'full'|'incremental' }
     *
     * INCREMENTAL mode (default):
     *  - Skips companies already in crm_client_mappings (only adds NEW ones)
     *  - Much faster — no detail API calls for existing records
     *
     * FULL mode:
     *  - Re-fetches detail for every company (refreshes team assignments)
     *
     * Batch size: 10 companies per page to keep each request under 30 seconds.
     *
     * CRM API field names (confirmed from live API):
     *  - REPRESENTATIVE = Sales person name
     *  - NEWBIZ_CODE / NEWBIZ_NAME = New Biz (code + name)
     *  - CCarePersonName, BillingPersonName, FinancePersonName = names only
     *  - PHONE1, EMAIL1, ADD1, ADD2, CITY, STATE, PIN = contact/address
     */
    public function syncPage(Request $request)
    {
        set_time_limit(0); // no PHP limit — web server timeout is the real cap

        $page      = (int) $request->get('page', 1);
        $perPage   = 5;   // 5 per batch × ~6s each = ~30s per request (safe)
        $mode      = $request->get('mode', 'incremental');
        $tenantId  = $this->tenantId();

        // ── Lookup Map 1: CRM EMP_CODE → HRX user_id (via users.crm_id) ──────
        // This is the PRIMARY resolution method since users have crm_id set.
        $crmCodeToHrxId = \App\Models\User::whereNotNull('crm_id')
            ->pluck('id', 'crm_id')
            ->toArray();

        // ── Lookup Map 2: CRM employee name → CRM EMP_CODE (cached 2h) ────────
        // Used to resolve names (REPRESENTATIVE, CCarePersonName etc.) → EMP_CODE
        // → then find HRX user via Map 1.
        $crmNameToCode = $this->crm->getCrmNameToCodeMap();

        // ── Lookup Map 3: HRX fullname → user_id (fallback) ───────────────────
        $hrxNameToId = \App\Models\User::select('id', 'first_name', 'last_name')
            ->where('status', 'active')
            ->get()
            ->mapWithKeys(fn($u) => [strtolower(trim("{$u->first_name} {$u->last_name}")) => $u->id])
            ->toArray();

        /**
         * Resolve a CRM person name → HRX user_id.
         * Chain: name → CRM EMP_CODE (employee list) → HRX user (crm_id)
         * Fallback: direct name match on HRX users table
         */
        $resolveByName = function (?string $name) use ($crmNameToCode, $crmCodeToHrxId, $hrxNameToId): ?int {
            if (!$name) return null;
            $key = strtolower(trim($name));
            // Primary: name → code → HRX user
            $crmCode = $crmNameToCode[$key] ?? null;
            if ($crmCode && isset($crmCodeToHrxId[$crmCode])) {
                return $crmCodeToHrxId[$crmCode];
            }
            // Fallback: direct HRX name match
            return $hrxNameToId[$key] ?? null;
        };

        // ── Incremental: which codes are already in DB? ────────────────────────
        $existingCodes = ($mode === 'incremental')
            ? \App\Models\CrmClientMapping::pluck('crm_company_code')->flip()->toArray()
            : [];

        // ── Fetch this page of customers from CRM list ─────────────────────────
        $result    = $this->crm->getCustomers($page, $perPage);
        $customers = $result['data'] ?? [];
        $total     = (int) ($result['total'] ?? 0);

        $synced  = 0;
        $skipped = 0;

        foreach ($customers as $c) {
            $compCode = $c['Comp_Code'] ?? $c['comp_code'] ?? $c['COMP_CODE'] ?? null;
            if (!$compCode) { $skipped++; continue; }

            $compName = $c['Comp_Name'] ?? $c['comp_name'] ?? $c['COMP_NAME'] ?? 'Unknown';
            $city     = $c['City']      ?? $c['city']      ?? null;
            $state    = $c['State']     ?? $c['state']     ?? null;
            $stage    = $c['Stages']    ?? $c['stages']    ?? null;

            // ── INCREMENTAL: skip companies already fully synced ───────────────
            if ($mode === 'incremental' && isset($existingCodes[$compCode])) {
                // Just keep stage + name current from list (no detail API call)
                \App\Models\CrmClientMapping::where('crm_company_code', $compCode)
                    ->update(['crm_company_name' => $compName, 'crm_stage' => $stage]);
                $skipped++;
                continue;
            }

            // ── FULL / NEW: call getCompanyById for team + contact ────────────
            $phone = $email = $address = $contact = null;
            $salesId = $ccareId = $newBizId = $billingId = $financeId = null;

            $detail = $this->crm->getCustomerById($compCode);

            if ($detail) {
                $compName = $detail['COMP_NAME'] ?? $compName;
                $city     = $detail['CITY']      ?? $city;
                $state    = $detail['STATE']      ?? $state;
                $stage    = $detail['STAGES']     ?? $stage;

                $add1    = trim($detail['ADD1'] ?? '');
                $add2    = trim($detail['ADD2'] ?? '');
                $pin     = trim($detail['PIN']  ?? '');
                $address = implode(', ', array_filter([$add1, $add2, $city, $state, $pin])) ?: null;

                $phone   = $detail['PHONE1'] ?? null;
                $email   = $detail['EMAIL1'] ?? $detail['EMAIL2'] ?? null;
                $contact = $detail['ContactPerson'] ?? null;

                // ── Resolve team roles ────────────────────────────────────────
                // NEWBIZ has an actual CRM EMP_CODE — use it directly
                $newBizCode = (int) ($detail['NEWBIZ_CODE'] ?? 0);
                $newBizId   = ($newBizCode && isset($crmCodeToHrxId[$newBizCode]))
                    ? $crmCodeToHrxId[$newBizCode]
                    : $resolveByName($detail['NEWBIZ_NAME'] ?? null);

                $salesId   = $resolveByName($detail['REPRESENTATIVE']    ?? null);
                $ccareId   = $resolveByName($detail['CCarePersonName']   ?? null);
                $billingId = $resolveByName($detail['BillingPersonName'] ?? null);
                $financeId = $resolveByName($detail['FinancePersonName'] ?? null);
            }

            // ── Upsert into crm_client_mappings ───────────────────────────────
            $crmMatch = $tenantId
                ? ['tenant_id' => $tenantId, 'crm_company_code' => $compCode]
                : ['crm_company_code' => $compCode];

            \App\Models\CrmClientMapping::updateOrCreate($crmMatch, [
                'crm_company_name' => $compName,
                'crm_city'         => $city,
                'crm_state'        => $state,
                'crm_stage'        => $stage,
                'email'            => $email,
                'phone'            => $phone,
                'address'          => $address,
                'contact_person'   => $contact,
                'salesperson_id'   => $salesId,
                'ccare_id'         => $ccareId,
                'new_biz_id'       => $newBizId,
                'billing_id'       => $billingId,
                'finance_id'       => $financeId,
                'updated_by_id'    => Auth::id(),
            ]);

            // ── Also upsert into sales_clients ────────────────────────────────
            $ownerId = $salesId ?? $ccareId ?? $newBizId ?? Auth::id();
            $scMatch = $tenantId
                ? ['tenant_id' => $tenantId, 'crm_company_code' => $compCode]
                : ['crm_company_code' => $compCode];
            \App\Models\SalesClient::updateOrCreate($scMatch, [
                'name'           => $compName,
                'city'           => $city,
                'address'        => $address,
                'phone'          => $phone,
                'email'          => $email,
                'contact_person' => $contact,
                'created_by'     => $ownerId,
            ]);

            $synced++;
        }

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : null;

        return response()->json([
            'success'     => true,
            'page'        => $page,
            'synced'      => $synced,
            'skipped'     => $skipped,
            'total'       => $total,
            'total_pages' => $totalPages,
            'done'        => (count($customers) < $perPage),
            'message'     => "Page {$page}: synced {$synced} clients.",
        ]);
    }

    /**
     * Legacy full auto-sync (kept for backward compat — now delegates to syncPage logic).
     */
    public function autoSync(Request $request)
    {
        return $this->syncPage($request->merge(['page' => 1]));
    }

    /**
     * DEBUG — shows raw CRM API responses for 1 company.
     * GET /crm-clients/debug-crm?comp_code=1920
     * Shows: list fields, detail fields, employee map, resolved user IDs.
     */
    public function debugCrm(Request $request)
    {
        set_time_limit(60);

        // 1. Get first company from list (or use provided comp_code)
        $compCode = $request->get('comp_code');
        $listSample = null;

        if (!$compCode) {
            $result   = $this->crm->getCustomers(1, 3);
            $list     = $result['data'] ?? [];
            $listSample = $list;
            $compCode = $list[0]['Comp_Code'] ?? $list[0]['comp_code'] ?? $list[0]['COMP_CODE'] ?? null;
        }

        // 2. Get full detail for that company
        $detail = $compCode ? $this->crm->getCustomerById($compCode) : null;

        // 3. Get employee name→code map
        $nameMap = $this->crm->getCrmNameToCodeMap();

        // 4. Get HRX crm_id→user map
        $crmCodeToHrxId = \App\Models\User::whereNotNull('crm_id')
            ->select('id', 'first_name', 'last_name', 'crm_id')
            ->get()
            ->map(fn($u) => ['hrx_id' => $u->id, 'name' => $u->first_name.' '.$u->last_name, 'crm_id' => $u->crm_id])
            ->toArray();

        // 5. Try resolving the team names from the detail
        $resolved = [];
        if ($detail) {
            $fields = [
                'REPRESENTATIVE'    => 'salesperson',
                'NEWBIZ_NAME'       => 'new_biz',
                'CCarePersonName'   => 'ccare',
                'BillingPersonName' => 'billing',
                'FinancePersonName' => 'finance',
                'NEWBIZ_CODE'       => 'new_biz_code (direct)',
            ];
            foreach ($fields as $crmField => $role) {
                $val = $detail[$crmField] ?? null;
                $crmCode = $crmField === 'NEWBIZ_CODE' ? (int)($val ?? 0) : ($nameMap[strtolower(trim((string)$val))] ?? null);
                $hrxUserId = null;
                foreach ($crmCodeToHrxId as $u) {
                    if ((string)$u['crm_id'] === (string)$crmCode) { $hrxUserId = $u; break; }
                }
                $resolved[$role] = [
                    'crm_value'  => $val,
                    'crm_code'   => $crmCode,
                    'hrx_user'   => $hrxUserId,
                ];
            }
        }

        return response()->json([
            'comp_code_used'    => $compCode,
            'list_sample'       => $listSample,
            'detail_raw'        => $detail,
            'employee_map_count'=> count($nameMap),
            'employee_map_sample'=> array_slice($nameMap, 0, 10, true),
            'hrx_users_with_crm_id' => $crmCodeToHrxId,
            'team_resolution'   => $resolved,
        ], 200, [], JSON_PRETTY_PRINT);
    }


    /**
     * AJAX: get all mappings for a specific HRX employee (for profile tab).
     */
    public function employeeClients($userId)
    {
        $mappings = CrmClientMapping::forUser((int) $userId)
            ->with([
                'salesperson:id,first_name,last_name',
                'ccare:id,first_name,last_name',
                'newBiz:id,first_name,last_name',
                'billing:id,first_name,last_name',
                'finance:id,first_name,last_name',
            ])
            ->orderBy('crm_company_name')
            ->get()
            ->map(function ($m) use ($userId) {
                // Determine this user's role on the account
                $roles = [];
                if ($m->salesperson_id == $userId) $roles[] = 'Salesperson';
                if ($m->ccare_id       == $userId) $roles[] = 'CCare';
                if ($m->new_biz_id     == $userId) $roles[] = 'New Biz';
                if ($m->billing_id     == $userId) $roles[] = 'Billing';
                if ($m->finance_id     == $userId) $roles[] = 'Finance';

                return [
                    'id'               => $m->id,
                    'crm_company_code' => $m->crm_company_code,
                    'crm_company_name' => $m->crm_company_name,
                    'crm_city'         => $m->crm_city,
                    'crm_state'        => $m->crm_state,
                    'crm_stage'        => $m->crm_stage,
                    'email'            => $m->email,
                    'phone'            => $m->phone,
                    'role'             => implode(', ', $roles) ?: null,
                    'salesperson'      => $m->salesperson ? $m->salesperson->first_name . ' ' . $m->salesperson->last_name : null,
                    'ccare'            => $m->ccare       ? $m->ccare->first_name       . ' ' . $m->ccare->last_name       : null,
                    'new_biz'          => $m->newBiz      ? $m->newBiz->first_name      . ' ' . $m->newBiz->last_name      : null,
                    'billing'          => $m->billing     ? $m->billing->first_name     . ' ' . $m->billing->last_name     : null,
                    'finance'          => $m->finance     ? $m->finance->first_name     . ' ' . $m->finance->last_name     : null,
                    'notes'            => $m->notes,
                ];
            });

        return response()->json($mappings);
    }
}
