<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalesVisit;
use App\Models\SalesClient;
use App\Models\CcSalespersonMap;
use App\Models\User;
use App\Mail\SalesVisitConfirmation;
use App\Mail\SalesVisitSurveyMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SalesVisitController extends Controller
{
    /**
     * List all visits, filtered by role:
     * - Admin/HR: all visits
     * - CC agent: only their bookings
     * - Salesperson: only their assigned visits
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = SalesVisit::with(['client', 'salesperson', 'ccAgent'])
            ->orderBy('scheduled_at', 'desc');

        // Role-based scoping
        if ($user->hasRole(['admin', 'hr', 'accounts'])) {
            // See all
        } elseif ($user->hasRole('manager')) {
            // Managers see their own as salesperson + any CC bookings they made
            $query->where(function ($q) use ($user) {
                $q->where('salesperson_id', $user->id)
                  ->orWhere('cc_user_id', $user->id);
            });
        } else {
            // CC or salesperson
            $mappedIds = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id')->toArray();
            $allowedSpIds = array_unique(array_merge([$user->id], $mappedIds));

            $query->where(function ($q) use ($user, $allowedSpIds) {
                $q->whereIn('salesperson_id', $allowedSpIds)
                  ->orWhere('cc_user_id', $user->id);
            });
        }

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }
        if ($request->filled('visit_type')) {
            $query->where('visit_type', $request->visit_type);
        }
        if ($request->filled('salesperson_id')) {
            $query->where('salesperson_id', $request->salesperson_id);
        }
        if ($request->filled('cc_user_id')) {
            $query->where('cc_user_id', $request->cc_user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_at', '<=', $request->date_to);
        }

        $visits = $query->paginate(20)->withQueryString();

        // Filter dropdowns — scope salesperson list to what the current user may see
        if ($user->hasRole(['admin', 'hr', 'accounts'])) {
            $salespersons = User::whereHas('roles', fn($q) => $q->whereIn('name', ['employee', 'manager']))->get();
        } else {
            // CC agent: only their mapped salespersons; salesperson: only themselves
            $mappedIds = CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id');
            $salespersons = $mappedIds->isNotEmpty()
                ? User::whereIn('id', $mappedIds)->get()
                : User::where('id', $user->id)->get();
        }
        $ccAgents = User::whereHas('mappedSalespersons')->orWhereHas('roles', fn($q) => $q->whereIn('name', ['hr', 'accounts', 'admin']))->get();

        // Stats for header — scoped to the same records the user can see
        $today = Carbon::today();
        $statsQuery = SalesVisit::query();
        if (!$user->hasRole(['admin', 'hr', 'accounts'])) {
            $mappedIds = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id')->toArray();
            $allowedSpIds = array_unique(array_merge([$user->id], $mappedIds));
            
            $statsQuery->where(function ($q) use ($user, $allowedSpIds) {
                $q->whereIn('salesperson_id', $allowedSpIds)
                  ->orWhere('cc_user_id', $user->id);
            });
        }
        $stats = [
            'total'     => (clone $statsQuery)->count(),
            'today'     => (clone $statsQuery)->whereDate('scheduled_at', $today)->count(),
            'pending'   => (clone $statsQuery)->where('status', 'pending')->count(),
            'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
        ];

        return view('tenant.sales-visits.index', compact('visits', 'salespersons', 'ccAgents', 'stats'))
            ->with('pageConfigs', ['contentLayout' => 'wide']);
    }

    /**
     * Show booking form.
     */
    public function create()
    {
        $user = Auth::user();
        $clients = SalesClient::orderBy('name')->get();

        // CC agent: only their mapped salespersons; admin/hr: all
        if ($user->hasRole(['admin', 'hr', 'accounts'])) {
            $salespersons = User::whereHas('roles', fn($q) => $q->whereIn('name', ['employee', 'manager']))
                ->select('id', 'first_name', 'last_name', 'code')
                ->orderBy('first_name')
                ->get();
        } else {
            $mappedIds = CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id');
            if ($mappedIds->isEmpty()) {
                abort(403, 'You must be assigned as a CC agent to book visits.');
            }
            $salespersons = User::whereIn('id', $mappedIds)
                ->select('id', 'first_name', 'last_name', 'code')
                ->orderBy('first_name')
                ->get();
        }

        return view('tenant.sales-visits.create', compact('clients', 'salespersons'))
            ->with('pageConfigs', ['contentLayout' => 'wide']);
    }

    /**
     * Store a new visit booking.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Authorize CC agent or Admin/HR/Accounts
        if (!$user->hasRole(['admin', 'hr', 'accounts'])) {
            $isMapped = CcSalespersonMap::where('cc_user_id', $user->id)
                ->where('sales_user_id', $request->salesperson_id)
                ->exists();
            if (!$isMapped) {
                return redirect()->back()->with('error', 'You are not authorized to book visits for this salesperson.');
            }
        }

        $validated = $request->validate([
            'client_id'      => 'required|exists:sales_clients,id',
            'salesperson_id' => 'required|exists:users,id',
            'visit_type'     => 'required|in:client_visit,product_trial,order_collection,service_call',
            'scheduled_at'   => 'required|date|after:now',
            'notes'          => 'nullable|string|max:2000',
        ]);

        $visit = SalesVisit::create([
            ...$validated,
            'cc_user_id'       => Auth::id(),
            'status'           => 'pending',
            'tenant_id'        => Auth::user()->tenant_id,
            'razor_blade'      => $request->boolean('razor_blade'),
            'is_new_customer'  => $request->boolean('is_new_customer'),
            'is_upsell'        => $request->boolean('is_upsell'),
            'rate_increase'    => $request->boolean('rate_increase'),
            'is_nif'           => $request->boolean('is_nif'),
            'training_done'    => $request->boolean('training_done'),
            'mom_submitted'    => $request->boolean('mom_submitted'),
            'competitor_insight' => $request->boolean('competitor_insight'),
            'product_idea'     => $request->boolean('product_idea'),
        ]);

        $visit->load(['client', 'salesperson', 'ccAgent']);

        // Send email to salesperson
        try {
            Mail::to($visit->salesperson->email)
                ->queue(new SalesVisitConfirmation($visit, 'salesperson'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Sales visit email (salesperson) failed: ' . $e->getMessage());
        }

        // Send email to client if they have an email
        if ($visit->client->email) {
            try {
                Mail::to($visit->client->email)
                    ->queue(new SalesVisitConfirmation($visit, 'client'));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Sales visit email (client) failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('sales-visits.show', $visit->id)
            ->with('success', 'Visit booked successfully. Confirmation sent to ' . $visit->salesperson->first_name . '.');
    }

    /**
     * Show a single visit's details.
     */
    public function show($id)
    {
        $visit = SalesVisit::with(['client', 'salesperson', 'ccAgent'])->findOrFail($id);
        return view('tenant.sales-visits.show', compact('visit'))
            ->with('pageConfigs', ['contentLayout' => 'wide']);
    }

    /**
     * Salesperson marks visit complete with GPS + optional photo.
     */
    public function complete(Request $request, $id)
    {
        $visit = SalesVisit::findOrFail($id);

        // Only assigned salesperson or admin can complete
        if (Auth::id() !== $visit->salesperson_id && !Auth::user()->hasRole(['admin', 'hr'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'lat'              => 'required|numeric',
            'lng'              => 'required|numeric',
            'completion_notes' => 'nullable|string|max:1000',
            'proof_photo'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $data = [
            'status'           => 'completed',
            'completed_lat'    => $request->lat,
            'completed_lng'    => $request->lng,
            'completed_at'     => now(),
            'completion_notes' => $request->completion_notes,
        ];

        if ($request->hasFile('proof_photo')) {
            $path = $request->file('proof_photo')->store('sales-visit-proofs', 'public');
            $data['proof_photo'] = $path;
        }

        $visit->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Visit marked complete successfully!'
        ]);
    }

    /**
     * Cancel a visit.
     */
    public function cancel($id)
    {
        $visit = SalesVisit::findOrFail($id);

        if ($visit->status === 'completed') {
            return redirect()->back()->with('error', 'Cannot cancel a completed visit.');
        }

        $visit->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', 'Visit cancelled.');
    }

    // =========================================================
    // VERIFICATION (Approve / Reject by CC Agent)
    // =========================================================

    public function verify(Request $request, $id)
    {
        $visit = SalesVisit::findOrFail($id);

        // Allow CC Agents who booked this, or Admin/HR/Managers
        $user = Auth::user();
        if ($visit->cc_user_id !== $user->id && !$user->hasRole(['admin', 'hr', 'manager', 'accounts'])) {
            abort(403, 'Unauthorized to verify this visit.');
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'verification_notes' => 'required_if:action,reject|nullable|string'
        ]);

        if ($validated['action'] === 'approve') {
            $surveyToken = Str::random(32);
            $visit->update([
                'verification_status' => 'approved',
                'verification_notes' => null,
                'survey_token' => $surveyToken,
            ]);

            // Send Survey email to client if they have an email
            if ($visit->client && $visit->client->email) {
                try {
                    Mail::to($visit->client->email)
                        ->queue(new SalesVisitSurveyMail($visit));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Sales visit survey email failed: ' . $e->getMessage());
                }
            }

            return redirect()->back()->with('success', 'Visit verified, approved, and client survey email sent.');
        } else {
            $visit->update([
                'verification_status' => 'rejected',
                'verification_notes' => $validated['verification_notes']
            ]);
            return redirect()->back()->with('error', 'Visit rejected. The salesperson must correct and resubmit.');
        }
    }

    // =========================================================
    // CLIENT MANAGEMENT
    // =========================================================

    public function clientIndex()
    {
        $user = Auth::user();

        // Departments allowed to access Client Master
        // Sales dept IDs: 2 (Sales Department), 26 (Sales)
        // Customer Care: 10, New Biz: 13
        $salesDeptIds   = [2, 26];
        $ccareDeptIds   = [10];
        $newBizDeptIds  = [13];
        $allowedDeptIds = array_merge($salesDeptIds, $ccareDeptIds, $newBizDeptIds);

        $isAdmin = $user->hasRole(['admin', 'hr', 'manager', 'accounts']);
        $inAllowedDept = in_array($user->department_id, $allowedDeptIds);

        // Also allow users who are directly assigned in any CRM mapping role
        $hasAnyMapping = \App\Models\CrmClientMapping::where('salesperson_id', $user->id)
            ->orWhere('ccare_id', $user->id)
            ->orWhere('new_biz_id', $user->id)
            ->orWhere('billing_id', $user->id)
            ->orWhere('finance_id', $user->id)
            ->exists();

        if (!$isAdmin && !$inAllowedDept && !$hasAnyMapping) {
            abort(403, 'You are not authorized to view the Client Master.');
        }

        // Build query — admins see all, dept users see only their clients
        $query = \App\Models\CrmClientMapping::with([
            'salesperson', 'ccare', 'newBiz', 'billing', 'finance',
        ]);

        if (!$isAdmin) {
            // Filter by department role
            if (in_array($user->department_id, $salesDeptIds)) {
                // Sales: see clients where they are salesperson
                $query->where('salesperson_id', $user->id);
            } elseif (in_array($user->department_id, $ccareDeptIds) || in_array($user->department_id, $newBizDeptIds)) {
                // CCare & New Biz: see clients for salespersons they are mapped to
                $mappedIds = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id')->toArray();
                $query->whereIn('salesperson_id', $mappedIds);
            } else {
                // Fallback: any role assignment
                $query->where(function($q) use ($user) {
                    $q->where('salesperson_id', $user->id)
                      ->orWhere('ccare_id', $user->id)
                      ->orWhere('new_biz_id', $user->id)
                      ->orWhere('billing_id', $user->id)
                      ->orWhere('finance_id', $user->id);
                });
            }
        }

        $mappings = $query->orderBy('crm_company_name')->paginate(25);

        // Employees for dropdowns (admins only — dept users don't need to reassign)
        $employees = $isAdmin
            ? \App\Models\User::select('id', 'first_name', 'last_name', 'code')
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get()
                ->map(fn($u) => [
                    'id'   => $u->id,
                    'name' => trim("{$u->first_name} {$u->last_name}") . ($u->code ? " ({$u->code})" : ''),
                ])
            : collect();

        return view('tenant.crm-clients.index', compact('mappings', 'employees'))
            ->with('pageConfigs', ['contentLayout' => 'wide']);
    }

    public function clientStore(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr', 'accounts'])) {
            $hasMappings = CcSalespersonMap::where('cc_user_id', $user->id)->exists();
            if (!$hasMappings) {
                abort(403, 'You are not authorized to add clients.');
            }
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:100',
            'gst_number'     => 'nullable|string|max:20',
            'contact_person' => 'nullable|string|max:255',
        ]);

        SalesClient::create([
            ...$validated,
            'created_by' => Auth::id(),
            'tenant_id'  => Auth::user()->tenant_id,
        ]);

        return redirect()->back()->with('success', 'Client added successfully.');
    }

    public function clientDestroy($id)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr', 'accounts'])) {
            $hasMappings = CcSalespersonMap::where('cc_user_id', $user->id)->exists();
            if (!$hasMappings) {
                abort(403, 'You are not authorized to delete clients.');
            }
        }

        $client = SalesClient::findOrFail($id);
        $client->delete();
        return redirect()->back()->with('success', 'Client removed.');
    }

    // =========================================================
    // CC ↔ SALESPERSON MAPPING
    // =========================================================
    // ANALYTICS & REPORTS
    // =========================================================

    public function reportSummary(Request $request)
    {
        if (!Auth::user()->hasRole(['admin', 'hr', 'manager', 'accounts'])) {
            abort(403);
        }

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $dateTo   = $request->date_to   ? Carbon::parse($request->date_to)->endOfDay()   : Carbon::now()->endOfDay();

        $baseQuery = SalesVisit::whereBetween('scheduled_at', [$dateFrom, $dateTo]);

        // KPI cards
        $kpis = [
            'total'       => (clone $baseQuery)->count(),
            'completed'   => (clone $baseQuery)->where('status', 'completed')->count(),
            'pending'     => (clone $baseQuery)->where('status', 'pending')->count(),
            'cancelled'   => (clone $baseQuery)->where('status', 'cancelled')->count(),
            'trials'      => (clone $baseQuery)->where('visit_type', 'product_trial')->count(),
        ];
        $kpis['completion_rate'] = $kpis['total'] > 0
            ? round(($kpis['completed'] / $kpis['total']) * 100, 1)
            : 0;

        // Per-salesperson breakdown
        $bySalesperson = SalesVisit::with('salesperson')
            ->selectRaw('salesperson_id, count(*) as total,
                sum(case when status="completed" then 1 else 0 end) as completed,
                sum(case when status="pending" then 1 else 0 end) as pending,
                sum(case when status="cancelled" then 1 else 0 end) as cancelled,
                sum(case when visit_type="product_trial" then 1 else 0 end) as trials')
            ->whereBetween('scheduled_at', [$dateFrom, $dateTo])
            ->groupBy('salesperson_id')
            ->orderByDesc('total')
            ->get();

        // Per-CC-agent breakdown
        $byCcAgent = SalesVisit::with('ccAgent')
            ->selectRaw('cc_user_id, count(*) as total,
                sum(case when status="completed" then 1 else 0 end) as completed')
            ->whereBetween('scheduled_at', [$dateFrom, $dateTo])
            ->groupBy('cc_user_id')
            ->orderByDesc('total')
            ->get();

        // Visit type breakdown for chart
        $byType = SalesVisit::selectRaw('visit_type, count(*) as total')
            ->whereBetween('scheduled_at', [$dateFrom, $dateTo])
            ->groupBy('visit_type')
            ->pluck('total', 'visit_type');

        // Daily visits for line chart (last 14 days)
        $dailyVisits = SalesVisit::selectRaw('DATE(scheduled_at) as date, count(*) as total')
            ->whereBetween('scheduled_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Client Feedback data
        $reviewsQuery = SalesVisit::with(['client', 'salesperson'])
            ->whereNotNull('rating')
            ->whereBetween('scheduled_at', [$dateFrom, $dateTo])
            ->orderBy('completed_at', 'desc');

        $reviews = $reviewsQuery->get();
        $avgRating = round($reviewsQuery->avg('rating') ?? 0, 1);
        $ratingCount = $reviewsQuery->count();

        return view('tenant.sales-visits.reports', compact(
            'kpis', 'bySalesperson', 'byCcAgent', 'byType', 'dailyVisits', 'dateFrom', 'dateTo',
            'reviews', 'avgRating', 'ratingCount'
        ))->with('pageConfigs', ['contentLayout' => 'wide']);
    }

    /**
     * Salesperson punches "Meeting Started" with GPS coordinates.
     */
    public function start(Request $request, $id)
    {
        $visit = SalesVisit::findOrFail($id);

        // Only assigned salesperson or admin can start
        if (Auth::id() !== $visit->salesperson_id && !Auth::user()->hasRole(['admin', 'hr'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $visit->update([
            'started_lat' => $request->lat,
            'started_lng' => $request->lng,
            'started_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meeting started successfully!'
        ]);
    }

    /**
     * Show survey form to the client.
     */
    public function showSurvey($token)
    {
        $visit = SalesVisit::with(['client', 'salesperson'])->where('survey_token', $token)->firstOrFail();

        // If survey is already completed, show thank you page
        if ($visit->rating) {
            return view('tenant.sales-visits.survey-thankyou', compact('visit'));
        }

        return view('tenant.sales-visits.survey', compact('visit'));
    }

    /**
     * Submit survey feedback from the client.
     */
    public function submitSurvey(Request $request, $token)
    {
        $visit = SalesVisit::where('survey_token', $token)->firstOrFail();

        if ($visit->rating) {
            return redirect()->back()->with('error', 'Feedback has already been submitted for this visit.');
        }

        $validated = $request->validate([
            'rating'         => 'required|integer|between:1,5',
            'rating_comment' => 'nullable|required_if:rating,1,2,3|string|max:1000',
        ]);

        $visit->update([
            'rating'         => $validated['rating'],
            'rating_comment' => $validated['rating_comment'] ?? null,
        ]);

        return view('tenant.sales-visits.survey-thankyou', compact('visit'));
    }
}
