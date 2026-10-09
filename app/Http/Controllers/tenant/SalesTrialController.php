<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\CcSalespersonMap;
use App\Models\SalesPipeline;
use App\Models\SalesTrial;
use App\Models\SalesTrialImage;
use App\Models\User;
use App\Support\TrialImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Product trials in Sales Field Ops.
 *
 *  - A salesperson lodges a completed trial from scratch (create/store).
 *  - A CCare/NewBiz agent pre-books a trial for a salesperson (bookCreate/bookStore); the
 *    salesperson completes it later with the execution detail + photos (completeForm/complete).
 *
 * The company is always one of that salesperson's own Sales Pipeline / Sales Report rows.
 */
class SalesTrialController extends Controller
{
    private const PHOTO_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360']; // 15 MB raw; compressed after
    private const MIN_PHOTOS = 3;

    // ───────────────────────────────────────────────────────────── list

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = SalesTrial::with(['salesperson:id,first_name,last_name,code', 'bookedBy:id,first_name,last_name', 'images'])
            ->latest('id');

        // Scope: admin/HR/managers see everything; a CCare/NewBiz agent sees their bookings + their
        // mapped salespeople's trials; a salesperson sees their own.
        if (!$this->seesEveryone($user)) {
            $ids = $this->visibleSalespersonIds($user);
            $query->where(function ($q) use ($user, $ids) {
                $q->whereIn('salesperson_id', $ids)->orWhere('booked_by_id', $user->id);
            });
        }

        $stats = [
            'total'     => (clone $query)->count(),
            'booked'    => (clone $query)->where('status', 'booked')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'overdue'   => (clone $query)->where('status', 'completed')->whereDate('next_expected_date', '<', now())->count(),
        ];

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('outcome')) {
            $query->where('current_status', $request->query('outcome'));
        }
        if ($request->boolean('overdue')) {
            $query->where('status', 'completed')->whereDate('next_expected_date', '<', now());
        }
        if ($request->filled('salesperson_id')) {
            // Safe for everyone: the base query is already scoped to who the viewer may see.
            $query->where('salesperson_id', (int) $request->query('salesperson_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('trial_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('trial_date', '<=', $request->query('to'));
        }

        $trials = $query->paginate(20)->withQueryString();

        return view('tenant.sales-trials.index', [
            'trials'         => $trials,
            'filters'        => $request->only(['status', 'outcome', 'overdue', 'salesperson_id', 'from', 'to']),
            'canBook'        => $this->canBook($user),
            'canLodge'       => $this->canLodge($user),
            'isAdmin'        => $this->seesEveryone($user),
            'canDelete'      => $this->canDelete($user),
            'people'         => $this->filterablePeople($user),
            'lodgeCompanies' => $this->companiesFor($user->id),
            'bookSalespeople' => $this->canBook($user) ? $this->bookableSalespeople($user) : collect(),
            'trialStatuses'  => config('trial_status.statuses', []),
            'stats'          => $stats,
        ]);
    }

    public function show($id)
    {
        $trial = SalesTrial::with(['salesperson', 'bookedBy', 'pipeline', 'images', 'statusUpdates.loggedBy:id,first_name,last_name'])->findOrFail($id);
        abort_unless($this->canView(Auth::user(), $trial), 404);

        return view('tenant.sales-trials.show', [
            'trial'            => $trial,
            'isAdmin'          => $this->seesEveryone(Auth::user()),
            'canDelete'        => $this->canDelete(Auth::user()),
            'completeCompanies' => $this->companiesFor($trial->salesperson_id),
            'trialStatuses'    => config('trial_status.statuses', []),
            'canTrack'         => $this->canUpdateStatus(Auth::user(), $trial),
        ]);
    }

    /** Who may add an outcome/status update: the trial's own salesperson, or an admin/HR viewer. */
    private function canUpdateStatus(User $user, SalesTrial $trial): bool
    {
        return $trial->salesperson_id === $user->id || $this->seesEveryone($user);
    }

    /**
     * Record a step on the trial's outcome timeline (Waiting / Retrial / Pass / Fail / Order / …)
     * and roll the headline status + next-expected-date onto the trial for lists and reminders.
     */
    public function addStatus(Request $request, $id)
    {
        $trial = SalesTrial::findOrFail($id);
        $user = Auth::user();
        abort_unless($this->canUpdateStatus($user, $trial), 403);
        // Outcome tracking only begins once the trial has actually been performed.
        if ($trial->status !== 'completed') {
            return back()->with('error', 'Complete the trial in the field before tracking its outcome.');
        }

        $keys = array_keys(config('trial_status.statuses', []));
        $data = $request->validate([
            'status'        => ['required', \Illuminate\Validation\Rule::in($keys)],
            'comment'       => ['nullable', 'string', 'max:2000'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $meta = config('trial_status.statuses.' . $data['status']);
        // Waiting / Retrial must carry the date the next result/attempt is due.
        if (!empty($meta['needs_date']) && empty($data['expected_date'])) {
            return back()->withErrors(['expected_date' => 'Pick the next expected date for "' . $meta['label'] . '".'])->withInput();
        }
        $expected = !empty($meta['needs_date']) ? $data['expected_date'] : null;

        $trial->statusUpdates()->create([
            'tenant_id'     => $trial->tenant_id,
            'status'        => $data['status'],
            'comment'       => $data['comment'] ?? null,
            'expected_date' => $expected,
            'logged_by_id'  => $user->id,
        ]);

        // A terminal status (pass/fail/order/lost) clears the follow-up date.
        $trial->update([
            'current_status'     => $data['status'],
            'next_expected_date' => $expected,
        ]);

        return redirect()->route('sales-visits.trials.show', $trial->id)->with('success', 'Status updated.');
    }

    // ─────────────────────────────────────────────── salesperson lodges new

    public function create()
    {
        // Lodging is a modal on the list; keep this route as a redirect for old links.
        return redirect()->route('sales-visits.trials.index');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($this->canLodge($user), 403);

        $data = $this->validateCompletion($request, $user->id, withSchedule: true);
        $pipeline = $this->pipelineRowFor($user->id, (int) $data['sales_pipeline_id']);

        $trial = SalesTrial::create([
            'tenant_id'         => $user->tenant_id,
            'salesperson_id'    => $user->id,
            'sales_pipeline_id' => $pipeline->id,
            'company_name'      => $pipeline->party_name,
            'booked_by_id'      => null,
            'booked_by_role'    => 'self',
            'status'            => 'completed',
            'current_status'    => config('trial_status.default', 'pending'),
            'trial_date'        => $data['trial_date'],
            'trial_time'        => $data['trial_time'],
            'topic'             => $data['topic'],
            'product'           => $data['product'],
            'description'       => $data['description'],
            'contact_person'    => $data['contact_person'],
            'contact_number'    => $data['contact_number'],
            'result'            => $data['result'],
            'remarks'           => $data['remarks'] ?? null,
            'latitude'          => $data['latitude'] ?? null,
            'longitude'         => $data['longitude'] ?? null,
            'location_accuracy' => $data['location_accuracy'] ?? null,
            'completed_at'      => now(),
        ]);
        $this->storePhotos($trial, $request);

        return redirect()->route('sales-visits.trials.show', $trial->id)->with('success', 'Trial lodged. Now track its outcome below.');
    }

    // ─────────────────────────────────────────────── CCare/NewBiz pre-books

    public function bookCreate()
    {
        // Booking is a modal on the list; keep this route as a redirect for old links.
        return redirect()->route('sales-visits.trials.index');
    }

    /** AJAX: the chosen salesperson's own pipeline/report companies (for the dependent dropdown). */
    public function pipelineCompanies(Request $request)
    {
        $user = Auth::user();
        $salespersonId = (int) $request->query('salesperson_id');
        abort_unless($this->canBookFor($user, $salespersonId) !== null, 403);

        return response()->json($this->companiesFor($salespersonId)->map(fn ($c) => [
            'id'    => $c->id,
            'label' => $c->party_name . ($c->product ? " — {$c->product}" : ''),
        ])->values());
    }

    public function bookStore(Request $request)
    {
        $user = Auth::user();
        abort_unless($this->canBook($user), 403);

        $data = $request->validate([
            'salesperson_id'    => 'required|integer|exists:users,id',
            'sales_pipeline_id' => 'required|integer',
            'trial_date'        => 'required|date',
            'trial_time'        => 'required',
            'topic'             => 'required|string|max:255',
            'product'           => 'required|string|max:255',
            'requirement'       => 'nullable|string|max:5000',
        ]);

        $role = $this->canBookFor($user, (int) $data['salesperson_id']);
        abort_if($role === null, 403, 'You are not mapped to that salesperson.');
        $pipeline = $this->pipelineRowFor((int) $data['salesperson_id'], (int) $data['sales_pipeline_id']);

        $trial = SalesTrial::create([
            'tenant_id'         => $user->tenant_id,
            'salesperson_id'    => (int) $data['salesperson_id'],
            'sales_pipeline_id' => $pipeline->id,
            'company_name'      => $pipeline->party_name,
            'booked_by_id'      => $user->id,
            'booked_by_role'    => $role,
            'status'            => 'booked',
            'trial_date'        => $data['trial_date'],
            'trial_time'        => $data['trial_time'],
            'topic'             => $data['topic'],
            'product'           => $data['product'],
            'requirement'       => $data['requirement'] ?? null,
        ]);

        return redirect()->route('sales-visits.trials.show', $trial->id)->with('success', 'Trial booked for the salesperson.');
    }

    // ─────────────────────────────── salesperson completes a booked trial

    public function completeForm($id)
    {
        // Completion is a modal on the detail page; keep this route as a redirect for old links.
        return redirect()->route('sales-visits.trials.show', $id);
    }

    public function complete(Request $request, $id)
    {
        $trial = SalesTrial::findOrFail($id);
        $user = Auth::user();
        abort_unless($trial->isBooked() && ($trial->salesperson_id === $user->id || $this->seesEveryone($user)), 403);

        $data = $this->validateCompletion($request, $trial->salesperson_id, withSchedule: true);
        $pipeline = $this->pipelineRowFor($trial->salesperson_id, (int) $data['sales_pipeline_id']);

        $trial->update([
            'sales_pipeline_id' => $pipeline->id,
            'company_name'      => $pipeline->party_name,
            'status'            => 'completed',
            'trial_date'        => $data['trial_date'],
            'trial_time'        => $data['trial_time'],
            'topic'             => $data['topic'],
            'product'           => $data['product'],
            'description'       => $data['description'],
            'contact_person'    => $data['contact_person'],
            'contact_number'    => $data['contact_number'],
            'result'            => $data['result'],
            'remarks'           => $data['remarks'] ?? null,
            'latitude'          => $data['latitude'] ?? null,
            'longitude'         => $data['longitude'] ?? null,
            'location_accuracy' => $data['location_accuracy'] ?? null,
            'completed_at'      => now(),
            'current_status'    => $trial->current_status ?: config('trial_status.default', 'pending'),
        ]);
        $this->storePhotos($trial, $request);

        return redirect()->route('sales-visits.trials.show', $trial->id)->with('success', 'Trial completed. Now track its outcome below.');
    }

    public function cancel($id)
    {
        $trial = SalesTrial::findOrFail($id);
        $user = Auth::user();
        abort_unless($trial->isBooked() && ($trial->booked_by_id === $user->id || $this->seesEveryone($user)), 403);
        $trial->update(['status' => 'cancelled']);

        return back()->with('success', 'Trial cancelled.');
    }

    /**
     * Permanently delete a trial — its photos (files + rows) and its outcome
     * timeline (FK cascade). Admin / HR only; destructive and irreversible.
     * Handy for clearing demo/test rows.
     */
    public function destroy($id)
    {
        $trial = SalesTrial::with('images')->findOrFail($id);
        abort_unless($this->canDelete(Auth::user()), 403);

        // Remove the compressed photo files from the public disk, then the rows.
        foreach ($trial->images as $image) {
            if ($image->path) {
                Storage::disk('public')->delete($image->path);
            }
        }
        $trial->images()->delete();
        // sales_trial_status_updates are removed by the FK cascade on delete.
        $company = $trial->company_name;
        $trial->delete();

        return redirect()->route('sales-visits.trials.index')
            ->with('success', 'Trial deleted' . ($company ? ' — ' . $company : '') . '.');
    }

    /** Who may permanently delete a trial: core admins and HR only. */
    private function canDelete(User $user): bool
    {
        return $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR']);
    }

    // ───────────────────────────────────────────────────────────── helpers

    private function validateCompletion(Request $request, int $salespersonId, bool $withSchedule): array
    {
        $rules = [
            'sales_pipeline_id' => 'required|integer',
            'topic'             => 'required|string|max:255',
            'product'           => 'required|string|max:255',
            'description'       => 'required|string|max:5000',
            'contact_person'    => 'required|string|max:255',
            'contact_number'    => 'required|string|max:30',
            'result'            => 'required|string|max:5000',
            'remarks'           => 'nullable|string|max:2000',
            'photos'            => 'required|array|min:' . self::MIN_PHOTOS,
            'photos.*'          => self::PHOTO_RULES,
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180',
            'location_accuracy' => 'nullable|numeric',
        ];
        if ($withSchedule) {
            $rules['trial_date'] = 'required|date';
            $rules['trial_time'] = 'required';
        }
        $data = $request->validate($rules, [
            'photos.min' => 'Please add at least ' . self::MIN_PHOTOS . ' photos of the trial.',
            'photos.required' => 'Please add at least ' . self::MIN_PHOTOS . ' photos of the trial.',
        ]);
        // The company must be one of THIS salesperson's own pipeline/report rows.
        $this->pipelineRowFor($salespersonId, (int) $data['sales_pipeline_id']);

        return $data;
    }

    private function storePhotos(SalesTrial $trial, Request $request): void
    {
        foreach ((array) $request->file('photos', []) as $file) {
            if (!$file) {
                continue;
            }
            $meta = TrialImageOptimizer::store($file);
            SalesTrialImage::create(['sales_trial_id' => $trial->id] + $meta);
        }
    }

    /** A salesperson's own companies from the Sales Pipeline / Sales Report (same table). */
    private function companiesFor(int $salespersonId)
    {
        return SalesPipeline::query()
            ->where(function ($q) use ($salespersonId) {
                $q->where('salesperson_id', $salespersonId)
                  ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('sales_pipeline_extra_reps as er')
                      ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')->where('er.user_id', $salespersonId));
            })
            ->orderBy('party_name')
            ->get(['id', 'party_name', 'product']);
    }

    /** The one pipeline row, confirmed to belong to this salesperson (else 422). */
    private function pipelineRowFor(int $salespersonId, int $pipelineId): SalesPipeline
    {
        $row = $this->companiesFor($salespersonId)->firstWhere('id', $pipelineId);
        abort_if(!$row, 422, 'That company is not in this salesperson\'s Sales Pipeline / Sales Report.');

        return $row;
    }

    /** The salespeople a CCare/NewBiz agent (or admin) may book for. */
    private function bookableSalespeople(User $user)
    {
        if ($this->seesEveryone($user)) {
            return User::whereHas('department', fn ($d) => $d->where('name', 'like', '%sales%')->orWhere('name', 'like', '%export%'))
                ->where('status', \App\Enums\UserAccountStatus::ACTIVE)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'code']);
        }
        $ids = CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id')->unique();

        return User::whereIn('id', $ids)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'code']);
    }

    /** Returns 'ccare'|'newbiz' (or 'self' for an admin) if $user may book for the salesperson, else null. */
    private function canBookFor(User $user, int $salespersonId): ?string
    {
        if ($this->seesEveryone($user)) {
            return 'admin';
        }
        $map = CcSalespersonMap::where('cc_user_id', $user->id)->where('sales_user_id', $salespersonId)->first();

        return $map?->role;
    }

    private function canBook(User $user): bool
    {
        return $this->seesEveryone($user) || CcSalespersonMap::where('cc_user_id', $user->id)->exists();
    }

    private function canLodge(User $user): bool
    {
        // A salesperson with their own pipeline/report rows, or a broad sales user.
        return $this->seesEveryone($user)
            || SalesPipeline::where('salesperson_id', $user->id)->exists()
            || (bool) ($user->can('sales_visits.create') ?? false);
    }

    private function canView(User $user, SalesTrial $trial): bool
    {
        if ($this->seesEveryone($user)) {
            return true;
        }
        if ($trial->booked_by_id === $user->id) {
            return true;
        }

        return in_array($trial->salesperson_id, $this->visibleSalespersonIds($user), true);
    }

    /**
     * Full visibility: sees every salesperson's trials and may book for anyone.
     * By default this is ONLY Admin and HR. Any other role must be granted the
     * granular `sales_trials.view_all` permission in Roles & Permissions — no
     * other role, department or sales permission opens the complete data set.
     * Everyone else (managers, CCare/NewBiz, salespeople) stays scoped via
     * visibleSalespersonIds().
     */
    private function seesEveryone(User $user): bool
    {
        return $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR'])
            || $user->can('sales_trials.view_all');
    }

    /**
     * The salespeople whose trials a non-admin viewer may see: themselves, their direct-report
     * team (a manager sees their team), and the salespeople a CCare/NewBiz agent is mapped to.
     *
     * @return array<int,int>
     */
    private function visibleSalespersonIds(User $user): array
    {
        // Self, plus (where the app supports it) any vacant positions delegated to this
        // account via managerIdentityIds(); the direct-report team of those identities; and
        // the salespeople a CCare/NewBiz agent is mapped to. method_exists keeps this working
        // where managerIdentityIds()/delegation isn't present — it falls back to self only.
        // No "manager" role gate: a non-manager simply has no reports, so the set self-corrects.
        $identities = method_exists($user, 'managerIdentityIds')
            ? collect($user->managerIdentityIds())
            : collect([$user->id]);

        $ids = $identities
            ->merge(User::whereIn('reporting_to_id', $identities)->pluck('id'))
            ->merge(CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id'));

        return $ids->filter()->unique()->values()->all();
    }

    /** The salesperson filter dropdown: everyone (admin) or just the viewer's visible set. */
    private function filterablePeople(User $user)
    {
        $q = User::query()->whereIn('id', SalesTrial::distinct()->pluck('salesperson_id'));
        if (!$this->seesEveryone($user)) {
            $q->whereIn('id', $this->visibleSalespersonIds($user));
        }

        return $q->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
    }
}
