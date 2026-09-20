<?php

namespace App\Http\Controllers\tenant;

use App\Enums\CrmActivityType;
use App\Http\Controllers\Controller;
use App\Models\CrmClientActivity;
use App\Models\CrmClientContact;
use App\Models\CrmClientMapping;
use App\Models\CrmClientMappingTeamMember;
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
     * True for the people who may see/manage the WHOLE customer book:
     * admin/hr/accounts, or a direct crm_clients.manage grant.
     */
    protected function canManageAll(): bool
    {
        $user = Auth::user();
        return $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'accounts'])
            || $user->can('crm_clients.manage');
    }

    /**
     * Main management page — list mappings the caller is allowed to see.
     *
     * SECURITY: previously this returned EVERY company's CRM mapping
     * (contacts, phone, the internal owner of each account) to any user who
     * cleared sales.guard — i.e. every salesperson could read the whole
     * customer book. Now scoped exactly like SalesVisitController::clientIndex:
     * admin/hr/accounts (or crm_clients.manage) see all; everyone else sees
     * only the accounts tied to the salespeople they're allowed to see
     * (self / their team / their mapped reps) or that they're personally
     * tagged on.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = CrmClientMapping::with(['salesperson', 'ccare', 'newBiz', 'billing', 'finance']);

        $canSeeAll = $this->canManageAll();
        if (!$canSeeAll) {
            $allowed = \App\Support\SalesScope::viewIds($user, $request, 'salesperson_id');
            // Non-privileged callers always get a concrete id list here (viewIds
            // only returns null for admin/hr, already handled above). Restrict to
            // accounts where they hold ANY of the five roles.
            $allowed = $allowed ?? [$user->id];
            $query->where(function ($q) use ($allowed) {
                $q->whereIn('salesperson_id', $allowed)
                  ->orWhereIn('ccare_id', $allowed)
                  ->orWhereIn('new_biz_id', $allowed)
                  ->orWhereIn('billing_id', $allowed)
                  ->orWhereIn('finance_id', $allowed);
            });
        }

        $mappings = $query->orderBy('crm_company_name')->paginate(25)->withQueryString();

        // Reassignment dropdown is only meaningful for the people who may
        // reassign accounts — never expose the full employee list otherwise.
        $employees = $canSeeAll
            ? User::select('id', 'first_name', 'last_name', 'code')
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get()
                ->map(fn($u) => [
                    'id'   => $u->id,
                    'name' => trim("{$u->first_name} {$u->last_name}") . ($u->code ? " ({$u->code})" : ''),
                ])
            : collect();

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
        // IDOR guard: a user may only pull their OWN client list, or — if
        // privileged — anyone's; a manager/rep may pull someone in their
        // allowed set. Previously any sales user could read any colleague's
        // entire client book by enumerating userId.
        $userId = (int) $userId;
        if (!$this->canManageAll() && $userId !== (int) Auth::id()) {
            $allowed = \App\Support\SalesScope::allowedUserIds(Auth::user());
            if ($allowed !== null && !in_array($userId, $allowed, true)) {
                abort(403);
            }
        }

        $mappings = CrmClientMapping::forUser($userId)
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

    /**
     * Same visibility rule as index(): canManageAll() sees anything, everyone
     * else only accounts where they hold one of the 5 roles (their own
     * allowed set, exactly as SalesScope::viewIds computes it for the list).
     */
    protected function canViewMapping(CrmClientMapping $mapping, Request $request): bool
    {
        if ($this->canManageAll()) {
            return true;
        }
        $allowed = \App\Support\SalesScope::viewIds(Auth::user(), $request, 'salesperson_id');
        if ($allowed === null) {
            return true;
        }
        return in_array($mapping->salesperson_id, $allowed, true)
            || in_array($mapping->ccare_id, $allowed, true)
            || in_array($mapping->new_biz_id, $allowed, true)
            || in_array($mapping->billing_id, $allowed, true)
            || in_array($mapping->finance_id, $allowed, true);
    }

    /**
     * Customer 360 — team, contacts, and the call-linked activity feed.
     * Activities are paginated (20/page): a long-lived account can
     * accumulate one row per dispositioned call and this must never become
     * an unpaginated, ever-growing fetch.
     */
    public function show(Request $request, $id)
    {
        $mapping = CrmClientMapping::with(['salesperson', 'ccare', 'newBiz', 'billing', 'finance'])->findOrFail($id);

        if (!$this->canViewMapping($mapping, $request)) {
            abort(403);
        }

        // The Customer Profile is the customer view now. This classic Customer 360 page is kept, reachable
        // with ?classic=1 (the profile's "Classic View" button), in case something is needed that the profile
        // does not cover yet. The redirect comes AFTER the permission check above, so access is unchanged.
        if (!$request->boolean('classic')) {
            return redirect()->route('crm-clients.profile', $mapping->id);
        }

        $contacts = $mapping->contacts()->orderByDesc('is_primary')->orderBy('name')->get();
        $teamByRole = $mapping->teamMembersByRole();
        // The team picker offers everyone active, same pool employeeClients()
        // already treats as fair game for this kind of assignment.
        $teamPickable = User::where('status', 'active')->notTerminated()->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        $activities = $mapping->activities()
            ->with([
                'createdBy:id,first_name,last_name',
                'followUpAssignedTo:id,first_name,last_name',
                'telephonyCall:id,from_number,to_number,duration,vendor_disposition',
            ])
            ->paginate(20)
            ->withQueryString();

        $openFollowUps = $mapping->activities()
            ->where('needs_follow_up', true)
            ->whereNull('follow_up_completed_at')
            ->with('followUpAssignedTo:id,first_name,last_name')
            ->orderBy('follow_up_due_at')
            ->get();

        return view('tenant.crm-clients.show', compact('mapping', 'contacts', 'activities', 'openFollowUps', 'teamByRole', 'teamPickable'));
    }

    /**
     * Contact CRUD -- the customer's own purchase/finance/accounts/admin
     * people. Same visibility gate as show(); the mapping is always reached
     * through its own id, never the contact's, so a contact can't be edited
     * without first proving access to the account it belongs to.
     */
    public function storeContact(Request $request, $mappingId)
    {
        $mapping = CrmClientMapping::findOrFail($mappingId);
        if (!$this->canViewMapping($mapping, $request)) {
            abort(403);
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'role'        => ['required', 'in:purchase,finance,accounts,admin,other'],
            'email'       => ['nullable', 'email', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'is_primary'  => ['nullable', 'boolean'],
            'notes'       => ['nullable', 'string'],
        ]);

        $contact = CrmClientContact::create($data + [
            'crm_client_mapping_id' => $mapping->id,
            'is_primary'            => $request->boolean('is_primary'),
            'created_by_id'         => Auth::id(),
            'updated_by_id'         => Auth::id(),
            'tenant_id'             => $mapping->tenant_id,
        ]);

        return response()->json(['success' => true, 'contact' => $contact]);
    }

    public function updateContact(Request $request, $id)
    {
        $contact = CrmClientContact::with('crmClientMapping')->findOrFail($id);
        if (!$this->canViewMapping($contact->crmClientMapping, $request)) {
            abort(403);
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'role'        => ['required', 'in:purchase,finance,accounts,admin,other'],
            'email'       => ['nullable', 'email', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'is_primary'  => ['nullable', 'boolean'],
            'notes'       => ['nullable', 'string'],
        ]);

        $contact->update($data + [
            'is_primary'    => $request->boolean('is_primary'),
            'updated_by_id' => Auth::id(),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroyContact(Request $request, $id)
    {
        $contact = CrmClientContact::with('crmClientMapping')->findOrFail($id);
        if (!$this->canViewMapping($contact->crmClientMapping, $request)) {
            abort(403);
        }

        $contact->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Internal Team: add a person to a role. A role may hold several people
     * (crm_client_mapping_team) — but the 5 legacy single-FK columns on
     * crm_client_mappings itself (used by telephony call-linking and
     * SalesScope visibility, unrelated to this page) only ever hold one. If
     * this is the first person tagged for a role that legacy column is still
     * empty for, mirror them into it so those other subsystems keep working
     * without needing to learn about this table at all.
     */
    public function addTeamMember(Request $request, $mappingId)
    {
        $mapping = CrmClientMapping::findOrFail($mappingId);
        if (!$this->canViewMapping($mapping, $request)) {
            abort(403);
        }

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:' . implode(',', CrmClientMappingTeamMember::ROLES)],
        ]);

        $member = CrmClientMappingTeamMember::firstOrCreate($data + [
            'crm_client_mapping_id' => $mapping->id,
        ], ['tenant_id' => $mapping->tenant_id]);

        $column = CrmClientMappingTeamMember::PRIMARY_COLUMN[$data['role']] ?? null;
        if ($column && !$mapping->{$column}) {
            $mapping->forceFill([$column => $data['user_id']])->save();
        }

        return response()->json(['success' => true, 'member' => $member->load('user:id,first_name,last_name')]);
    }

    public function removeTeamMember(Request $request, $mappingId, $userId, $role)
    {
        $mapping = CrmClientMapping::findOrFail($mappingId);
        if (!$this->canViewMapping($mapping, $request)) {
            abort(403);
        }

        CrmClientMappingTeamMember::where('crm_client_mapping_id', $mapping->id)
            ->where('user_id', $userId)
            ->where('role', $role)
            ->get()
            ->each->delete();

        // If the removed person was the legacy column's single holder, promote
        // whoever's left in that role (oldest tag first), or clear it if no
        // one else is tagged — the column must never keep pointing at someone
        // who was just removed from the team.
        $column = CrmClientMappingTeamMember::PRIMARY_COLUMN[$role] ?? null;
        if ($column && (int) $mapping->{$column} === (int) $userId) {
            $next = CrmClientMappingTeamMember::where('crm_client_mapping_id', $mapping->id)
                ->where('role', $role)->oldest()->first();
            $mapping->forceFill([$column => $next->user_id ?? null])->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Manual comment on the activity feed (auto-logged calls are created by
     * TelephonyCallService::disposition() / TelephonyWebhookController, not
     * here).
     */
    public function storeActivity(Request $request, $mappingId)
    {
        $mapping = CrmClientMapping::findOrFail($mappingId);
        if (!$this->canViewMapping($mapping, $request)) {
            abort(403);
        }

        $request->validate([
            'body'             => ['required', 'string'],
            'needs_follow_up'  => ['nullable', 'boolean'],
            'follow_up_due_at' => ['nullable', 'date'],
        ]);

        $needsFollowUp = $request->boolean('needs_follow_up');

        $activity = CrmClientActivity::create([
            'crm_client_mapping_id'    => $mapping->id,
            'type'                     => CrmActivityType::COMMENT,
            'body'                     => $request->body,
            'needs_follow_up'          => $needsFollowUp,
            'follow_up_due_at'         => $needsFollowUp ? $request->follow_up_due_at : null,
            'follow_up_assigned_to_id' => $needsFollowUp ? Auth::id() : null,
            'created_by_id'            => Auth::id(),
            'tenant_id'                => $mapping->tenant_id,
        ]);

        return response()->json([
            'success'  => true,
            'activity' => $activity->load(['createdBy:id,first_name,last_name', 'followUpAssignedTo:id,first_name,last_name']),
        ]);
    }

    public function completeFollowUp(Request $request, $id)
    {
        $activity = CrmClientActivity::with('crmClientMapping')->findOrFail($id);
        if (!$this->canViewMapping($activity->crmClientMapping, $request)) {
            abort(403);
        }

        $activity->update(['follow_up_completed_at' => now()]);

        return response()->json(['success' => true]);
    }
}
