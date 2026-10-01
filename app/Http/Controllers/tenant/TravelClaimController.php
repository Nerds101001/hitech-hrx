<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TravelClaim;
use App\Models\TravelClaimItem;
use App\Models\TravelClaimAdvance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class TravelClaimController extends Controller
{
    /**
     * Personal vehicles reimbursed per kilometre. Anything not listed here is
     * reimbursed at the fare the user enters. 'Bike'/'Car' are the legacy
     * spellings kept so old rows keep costing the same.
     *
     * The matching table in resources/views/tenant/travel-claims/form.blade.php
     * must be changed with this one — the grid recalculates client-side.
     */
    public const PER_KM_RATES = [
        'Personal Bike' => 4.00,
        'Bike'          => 4.00,
        'Personal Car'  => 9.50,
        'Car'           => 9.50,
        'Electric Car'  => 3.00,
    ];

    /** Currencies a day's expenses can be recorded in - INR unless picked otherwise. */
    public const CURRENCIES = ['INR', 'USD', 'EUR', 'AED'];

    /**
     * Modes that carry an odometer: the personal vehicles above plus the
     * company's own bike and car. These are the only rows that require meter
     * readings, and the only ones that make the month's odometer photos
     * mandatory.
     */
    public const METERED_MODES = [
        'Personal Bike', 'Bike', 'Personal Car', 'Car', 'Official Bike', 'Official Car', 'Electric Car',
    ];

    private static function isMeteredMode(?string $mode): bool
    {
        return $mode !== null && in_array($mode, self::METERED_MODES, true);
    }

    /**
     * An untouched number box posts an empty string, and MySQL will not accept
     * '' for a decimal column — it rejected the whole INSERT, which took the
     * entire save down with it. Blank means "not recorded", so: null.
     */
    private static function decimalOrNull($value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    /**
     * Does this row actually claim travel on the vehicle?
     *
     * Distance and the conveyance amount worked out from it are the only two
     * things meter readings feed, so a row claiming neither has no use for
     * them. Both are checked because the per-km modes compute conveyance from
     * the distance while the official vehicles have the amount typed in.
     */
    private static function claimsVehicleTravel(array $item): bool
    {
        return floatval($item['distance_km'] ?? 0) > 0
            || floatval($item['conveyance_amount'] ?? 0) > 0;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->hasRole(['admin', 'Admin', 'super_admin']) || $user->can('travel_claim.verify')) {
            $claims = TravelClaim::with(['user', 'items', 'attachments'])
                ->where(function($q) use ($user) {
                    $q->where('status', '!=', 'draft')
                      ->orWhere('user_id', $user->id);
                })
                ->orderBy('created_at', 'desc')->get();
            return view('tenant.travel-claims.verify', compact('claims'));
        } elseif ($user->hasRole(['accounts', 'Accounts']) || $user->can('travel_claim.approve')) {
            $claims = TravelClaim::with('user')->whereIn('status', ['verified', 'approved', 'paid'])->orderBy('created_at', 'desc')->get();
            return view('tenant.travel-claims.accounts', compact('claims'));
        } else {
            $claims = TravelClaim::with('items')->where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
            return view('tenant.travel-claims.index', compact('claims'));
        }
    }

    public function create()
    {
        /* Show any draft already in progress so people resume it instead of
           unknowingly starting a second one for the same month. */
        $openDrafts = TravelClaim::where('user_id', auth()->id())
            ->where('status', 'draft')
            ->withCount('items')
            ->orderByDesc('updated_at')
            ->get();

        return view('tenant.travel-claims.form', [
            'attachmentsByCategory' => collect(),
            'openDrafts' => $openDrafts,
        ]);
    }

    // Explicitly nullable: PHP 8.4 deprecates inferring it from the default.
    public function store(Request $request, ?TravelClaim $existingClaim = null)
    {
        // Pre-filter empty items submitted by the grid
        if ($request->has('items') && is_array($request->items)) {
            $filteredItems = [];
            foreach ($request->items as $idx => $item) {
                $hasFile = $request->hasFile("items.{$idx}.photo") ||
                           $request->hasFile("items.{$idx}.petrol_slip") ||
                           $request->hasFile("items.{$idx}.toll_proof") ||
                           $request->hasFile("items.{$idx}.additional_food_proof") ||
                           $request->hasFile("items.{$idx}.special_approval_proof") ||
                           $request->hasFile("items.{$idx}.transport_proof") ||
                           $request->hasFile("items.{$idx}.bills_proof") ||
                           $request->hasFile("items.{$idx}.freight_proof") ||
                           $request->hasFile("items.{$idx}.courier_proof") ||
                           $request->hasFile("items.{$idx}.auto_taxi_proof");

                // A row counts as filled on ANY of its own fields. Meter
                // readings, distance, the typed conveyance amount and the
                // customer name used to be ignored here, so a row carrying
                // only those was dropped without a word.
                $hasValue = !empty($item['mode_of_travel']) ||
                            !empty($item['to_location']) ||
                            !empty($item['from_location']) ||
                            !empty($item['party_visited']) ||
                            !empty($item['remarks']) ||
                            (isset($item['start_meter']) && $item['start_meter'] !== '') ||
                            (isset($item['end_meter']) && $item['end_meter'] !== '') ||
                            (isset($item['distance_km']) && floatval($item['distance_km']) > 0) ||
                            (isset($item['conveyance_amount']) && floatval($item['conveyance_amount']) > 0) ||
                            (isset($item['food_allowance']) && floatval($item['food_allowance']) > 0) ||
                            (isset($item['lodging_amount']) && floatval($item['lodging_amount']) > 0) ||
                            (isset($item['courier_amount']) && floatval($item['courier_amount']) > 0) ||
                            (isset($item['other_amount']) && floatval($item['other_amount']) > 0) ||
                            (isset($item['toll_amount']) && floatval($item['toll_amount']) > 0) ||
                            (isset($item['additional_food_amount']) && floatval($item['additional_food_amount']) > 0) ||
                            (isset($item['special_approval_amount']) && floatval($item['special_approval_amount']) > 0) ||
                            (isset($item['transport_amount']) && floatval($item['transport_amount']) > 0) ||
                            (isset($item['bills_amount']) && floatval($item['bills_amount']) > 0) ||
                            (isset($item['freight_amount']) && floatval($item['freight_amount']) > 0) ||
                            (isset($item['auto_taxi_amount']) && floatval($item['auto_taxi_amount']) > 0) ||
                            !empty($item['existing_photo']) ||
                            !empty($item['existing_petrol_slip']) ||
                            !empty($item['existing_toll_proof']) ||
                            !empty($item['existing_additional_food_proof']) ||
                            !empty($item['existing_special_approval_proof']) ||
                            !empty($item['existing_transport_proof']) ||
                            !empty($item['existing_bills_proof']) ||
                            !empty($item['existing_freight_proof']) ||
                            !empty($item['existing_courier_proof']) ||
                            !empty($item['existing_auto_taxi_proof']);

                if ($hasFile || $hasValue) {
                    $filteredItems[$idx] = $item;
                }
            }
            $request->merge(['items' => $filteredItems]);
        }

        // Background autosave is always a draft — it must never submit a claim.
        $isDraft = $request->input('action') === 'draft' || $request->boolean('autosave');

        $request->validate([
            // Must be a real 'YYYY-MM' value: the <input type="month"> falls back to a
            // plain text box in browsers that don't support the picker (older Safari/
            // some webviews), and a free-typed value like "AUG" used to sail through
            // as a bare 'required|string' — saving fine as a draft, then crashing the
            // whole admin dashboard the moment it tried to chart every claim_month.
            'claim_month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'company' => 'required|string',
            'sales_collection' => 'nullable|numeric',
            'month_start_odometer_proof' => $isDraft ? 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120' : 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'month_end_odometer_proof' => $isDraft ? 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120' : 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            // Consolidated monthly bill files (one per category).
            'food_bill_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'conveyance_petrol_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'toll_parking_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'customer_courier_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'items' => $isDraft ? 'nullable|array' : 'required|array|min:1',
            // A draft is work-in-progress: a half-filled row (no date yet) must
            // not block saving. Rows without a date are skipped when storing.
            'items.*.date' => $isDraft ? 'nullable|date' : 'required|date',
            'items.*.mode_of_travel' => 'nullable|string',
            'items.*.photo' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
            'items.*.petrol_slip' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
            'items.*.toll_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
            'items.*.additional_food_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
            'items.*.special_approval_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
            'items.*.transport_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
            'items.*.bills_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
            'items.*.freight_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
            'items.*.courier_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
            'items.*.auto_taxi_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
            'items.*.auto_taxi_amount' => 'nullable|numeric',
        ]);

        $deadline = null;
        try {
            // Anchor to day 1 — 'Y-m' alone overflows past short months on days 29-31.
            $claimMonthDate = \Carbon\Carbon::createFromFormat('Y-m-d', $request->claim_month . '-01');
            $deadline = $claimMonthDate->copy()->addMonth()->startOfMonth()->addDays(6)->endOfDay(); // 7th of next month
        } catch (\Exception $e) {
            // An unparseable month gives us no deadline, so no penalty either.
        }
        // Whether the 10% applies is decided further down, once we know when
        // this claim was FIRST submitted. It deliberately is not decided here:
        // deciding it from now() on every save is what made an on-time claim
        // turn late the moment it was sent back and corrected.
        $latePenalty = false;

        // Does the month contain at least one row on a metered vehicle? The
        // odometer rules below all hang off this: someone who only claimed
        // food, courier or a train fare never touched an odometer and must
        // not be asked for one.
        $hasVehicleRow = false;
        foreach (($request->items ?? []) as $it) {
            if (self::isMeteredMode($it['mode_of_travel'] ?? null) && self::claimsVehicleTravel($it)) {
                $hasVehicleRow = true;
                break;
            }
        }

        try {
            DB::beginTransaction();

            // Editing replaces the children. This MUST happen inside the
            // transaction — it used to run in update() before store() opened
            // one, so any later failure rolled back the rewrite but not the
            // delete, and the claim was left with no rows at all (every
            // amount reading zero) with no way back.
            // Per-day proofs were retired in favour of the monthly consolidated
            // files, so the grid no longer posts the `existing_*` fields this
            // method reads — meaning every edit silently nulled the paths on
            // historical rows and orphaned the files. Carry them across the
            // delete/recreate, keyed by the day they belong to.
            /* One draft per person per month per company. Every visit to
               /travel-claims/create used to start a fresh draft for the same
               month, and autosave turns that from an occasional annoyance into
               a new row on each visit — so adopt the open draft instead.
               MUST happen before the carry-proofs/delete block below, or the
               adopted draft keeps its old rows and they get duplicated.
               Only drafts are adopted: a submitted or verified claim is
               finished and must never be silently overwritten. */
            if (!$existingClaim && $isDraft) {
                $existingClaim = TravelClaim::where('user_id', auth()->id())
                    ->where('claim_month', $request->claim_month)
                    ->where('company', $request->company)
                    ->where('status', 'draft')
                    ->orderBy('id')
                    ->first();
            }

            $carriedProofs = [];
            if ($existingClaim) {
                foreach ($existingClaim->items()->get() as $old) {
                    $carriedProofs[Carbon::parse($old->date)->format('Y-m-d')] = [
                        'photo'                 => $old->photo_path,
                        'petrol_slip'           => $old->petrol_slip_proof,
                        'toll_proof'            => $old->toll_proof,
                        'additional_food_proof' => $old->additional_food_proof,
                        'special_approval_proof'=> $old->special_approval_proof,
                        'courier_proof'         => $old->courier_proof,
                    ];
                }
                $existingClaim->advances()->delete();
                $existingClaim->items()->delete();
            }

            /* Background autosave records what is being typed; it must not move
               the claim. Forcing 'draft' here took a claim HR had sent back out
               of the objection list the moment the employee opened it to
               correct, so it silently disappeared from the objection view. */
            $targetStatus = $isDraft
                ? (($request->boolean('autosave') && $existingClaim && $existingClaim->status === 'objection')
                    ? 'objection' : 'draft')
                : 'submitted';

            /* The 10% is fixed by the FIRST time this claim was submitted, and
               never recomputed afterwards. A claim filed inside the deadline
               that HR sends back for a correction is still a claim filed on
               time — measuring against now() on every save meant the
               resubmission (and even a background autosave) after the 7th
               reclassified it as late and quietly took 10% off the payout.

               A draft has not been submitted yet, so it previews against today
               and stamps nothing; the stamp is written the moment it really is
               submitted. */
            $firstSubmittedAt = $existingClaim ? $existingClaim->first_submitted_at : null;
            if ($targetStatus === 'submitted' && !$firstSubmittedAt) {
                $firstSubmittedAt = now();
            }
            $latePenalty = $deadline
                ? \Carbon\Carbon::parse($firstSubmittedAt ?: now())->gt($deadline)
                : false;

            // Replacing a proof used to leave the previous file on disk for
            // ever; nothing referenced it again, so storage only ever grew.
            $replaceProof = function (string $field, string $dir) use ($request, $existingClaim) {
                $current = $existingClaim->{$field} ?? null;
                if (!$request->hasFile($field)) {
                    return $current;
                }
                $new = $request->file($field)->store($dir, 'public');
                if ($current && $current !== $new && Storage::disk('public')->exists($current)) {
                    Storage::disk('public')->delete($current);
                }
                return $new;
            };

            $monthStartProofPath = $replaceProof('month_start_odometer_proof', 'travel_claims');
            $monthEndProofPath   = $replaceProof('month_end_odometer_proof', 'travel_claims');

            // Odometer rules, reported together so the whole claim can be fixed
            // in one pass rather than one message at a time. Both only apply to
            // bike/car rows; no per-day photo is ever asked for.
            if (!$isDraft) {
                $odoErrors = [];

                // At least ONE odometer photo, not one per box. People commonly
                // photograph the opening and closing readings together, or send
                // a single strip covering the month; demanding two separate
                // uploads rejected claims whose proof was already there.
                if ($hasVehicleRow && !$monthStartProofPath && !$monthEndProofPath) {
                    $odoErrors['month_start_odometer_proof'] =
                        'An odometer photo is required because this claim includes travel on a bike or car. '
                        . 'One image showing both the month start and month end readings is fine.';
                }

                foreach (($request->items ?? []) as $idx => $it) {
                    if (!self::isMeteredMode($it['mode_of_travel'] ?? null)) {
                        continue;
                    }
                    // A metered row only needs readings when it actually claims
                    // travel on the vehicle. Someone who stayed at the office,
                    // left their usual mode selected out of habit and claimed
                    // only the food allowance has nothing to read off an
                    // odometer — and being asked for one left the whole month
                    // unsubmittable with no legitimate way to satisfy it.
                    if (!self::claimsVehicleTravel($it)) {
                        continue;
                    }
                    $start = self::decimalOrNull($it['start_meter'] ?? null);
                    $end   = self::decimalOrNull($it['end_meter'] ?? null);
                    if ($start === null || $end === null) {
                        $odoErrors["items.{$idx}.start_meter"] =
                            "Day {$idx}: start and end meter readings are required for {$it['mode_of_travel']}.";
                    } elseif ($end < $start) {
                        $odoErrors["items.{$idx}.end_meter"] =
                            "Day {$idx}: the end meter reading cannot be lower than the start reading.";
                    }
                }

                if (!empty($odoErrors)) {
                    throw \Illuminate\Validation\ValidationException::withMessages($odoErrors);
                }
            }

            // ── Consolidated monthly bill files (replace the old per-day proofs) ──
            // Each keeps its previously-stored file on edit unless a new one is uploaded.
            $consolidatedProofs = [
                'food_bill_proof'         => 'travel_claims_food',
                'conveyance_petrol_proof' => 'travel_claims_conveyance',
                'toll_parking_proof'      => 'travel_claims_tolls',
                'customer_courier_proof'  => 'travel_claims_customer',
            ];
            $proofPaths = [];
            foreach ($consolidatedProofs as $field => $dir) {
                // Same replacement rule as the odometer photos: the file being
                // superseded is removed rather than abandoned on disk.
                $proofPaths[$field] = $replaceProof($field, $dir);
            }

            // Files uploaded while the form was open are held against the
            // browser's upload token until there is a claim to attach them to.
            $uploadToken = trim((string) $request->input('upload_token', ''));
            $attachmentCounts = [];
            if ($uploadToken !== '' || $existingClaim) {
                $attachmentCounts = \App\Models\TravelClaimAttachment::query()
                    ->where('user_id', auth()->id())
                    ->where(function ($q) use ($uploadToken, $existingClaim) {
                        if ($existingClaim) {
                            $q->where('travel_claim_id', $existingClaim->id);
                        }
                        if ($uploadToken !== '') {
                            $q->orWhere(function ($q2) use ($uploadToken) {
                                $q2->where('upload_token', $uploadToken)->whereNull('travel_claim_id');
                            });
                        }
                    })
                    ->selectRaw('category, COUNT(*) c')
                    ->groupBy('category')
                    ->pluck('c', 'category')
                    ->all();
            }

            // Block submission when a claimed category has no consolidated bill.
            // A category is "claimed" when its month total is above zero.
            if (!$isDraft) {
                $sum = ['food' => 0, 'conveyance' => 0, 'toll' => 0, 'custcourier' => 0, 'lodging' => 0];
                foreach (($request->items ?? []) as $it) {
                    $foodVal = (isset($it['is_outstation']) && $it['is_outstation']) ? 300 : floatval($it['food_allowance'] ?? 0);
                    $sum['food']        += $foodVal;
                    $sum['conveyance']  += floatval($it['conveyance_amount'] ?? 0);
                    $sum['toll']        += floatval($it['toll_amount'] ?? 0);
                    $sum['custcourier'] += floatval($it['special_approval_amount'] ?? 0) + floatval($it['courier_amount'] ?? 0);
                    $sum['lodging']     += floatval($it['lodging_amount'] ?? 0);
                }
                $required = [
                    'food'        => ['food_bill_proof',         'Food'],
                    'conveyance'  => ['conveyance_petrol_proof', 'Conveyance / Petrol'],
                    'toll'        => ['toll_parking_proof',      'Toll / Parking'],
                    'custcourier' => ['customer_courier_proof',  'Customer Expense / Courier'],
                    'lodging'     => ['hotel_bill_proof',        'Hotel'],
                ];
                $missing = [];
                foreach ($required as $key => [$field, $label]) {
                    // Satisfied by either the new multi-file uploads or the
                    // single legacy file an older claim was saved with.
                    $hasBill = ($attachmentCounts[$field] ?? 0) > 0 || !empty($proofPaths[$field]);
                    if ($sum[$key] > 0 && !$hasBill) {
                        $missing[$field] = "At least one {$label} bill file is required because you have claimed an amount in that category.";
                    }
                }
                if (!empty($missing)) {
                    throw \Illuminate\Validation\ValidationException::withMessages($missing);
                }
            }

            if ($existingClaim) {
                $claim = $existingClaim;
                $claim->forceFill(array_merge([
                    'claim_month' => $request->claim_month,
                    'company' => $request->company,
                    'sales_collection' => $request->sales_collection ?? 0,
                    'month_start_odometer_proof' => $monthStartProofPath,
                    'month_end_odometer_proof' => $monthEndProofPath,
                    'status' => $targetStatus,
                    'first_submitted_at' => $firstSubmittedAt,
                    'late_penalty_applied' => $latePenalty,
                ], $proofPaths))->save();
            } else {
                // Deliberately NOT unique per month: a salesperson may file two
                // or three claims for the same month, so multiple claims are
                // legitimate and must not be blocked.
                $claim = TravelClaim::forceCreate(array_merge([
                    'user_id' => auth()->id(),
                    'claim_month' => $request->claim_month,
                    'company' => $request->company,
                    'sales_collection' => $request->sales_collection ?? 0,
                    'month_start_odometer_proof' => $monthStartProofPath,
                    'month_end_odometer_proof' => $monthEndProofPath,
                    'bank_account_name' => 'On File',
                    'bank_account_no' => 'On File',
                    'bank_ifsc' => 'On File',
                    'status' => $targetStatus,
                    'first_submitted_at' => $firstSubmittedAt,
                    'late_penalty_applied' => $latePenalty,
                ], $proofPaths));
            }

            // Hand the token's files to the claim now that it has an id.
            if ($uploadToken !== '') {
                \App\Models\TravelClaimAttachment::whereNull('travel_claim_id')
                    ->where('upload_token', $uploadToken)
                    ->where('user_id', auth()->id())
                    ->update(['travel_claim_id' => $claim->id]);
            }

            $total_amount = 0;

            $skippedRows = 0;
            if (!empty($request->items) && is_array($request->items)) {
                foreach ($request->items as $index => $item) {
                    // travel_claim_items.date is NOT NULL — a draft row with no
                    // date yet simply isn't stored, rather than failing the save.
                    if (empty($item['date'])) {
                        $skippedRows++;
                        continue;
                    }

                    $carried = $carriedProofs[$item['date']] ?? [];

                    $photoPath = $request->hasFile("items.{$index}.photo")
                        ? $request->file("items.{$index}.photo")->store('travel_claims', 'public')
                        : ($item['existing_photo'] ?? $carried['photo'] ?? null);

                    $petrolSlipPath = $request->hasFile("items.{$index}.petrol_slip")
                        ? $request->file("items.{$index}.petrol_slip")->store('travel_claims_petrol', 'public')
                        : ($item['existing_petrol_slip'] ?? $carried['petrol_slip'] ?? null);

                    $tollProofPath = $request->hasFile("items.{$index}.toll_proof")
                        ? $request->file("items.{$index}.toll_proof")->store('travel_claims_tolls', 'public')
                        : ($item['existing_toll_proof'] ?? $carried['toll_proof'] ?? null);

                    $addFoodProofPath = $request->hasFile("items.{$index}.additional_food_proof")
                        ? $request->file("items.{$index}.additional_food_proof")->store('travel_claims_extra', 'public')
                        : ($item['existing_additional_food_proof'] ?? $carried['additional_food_proof'] ?? null);

                    $specialApprovalProofPath = $request->hasFile("items.{$index}.special_approval_proof")
                        ? $request->file("items.{$index}.special_approval_proof")->store('travel_claims_special', 'public')
                        : ($item['existing_special_approval_proof'] ?? $carried['special_approval_proof'] ?? null);

                    // Deleted proof paths handled dynamically below to null

                    $distance = floatval($item['distance_km'] ?? 0);
                    $mode = $item['mode_of_travel'] ?? null;
                    $rate = self::PER_KM_RATES[$mode] ?? null;

                    // Personal vehicles are paid per kilometre; everything else
                    // — official vehicles, cab, bus, train, flight — is paid at
                    // the fare the user actually typed. That fallback used to be
                    // `distance * rate`, which for a bus or train is 0 * 0, so
                    // those fares were silently wiped on save.
                    $conveyance = $rate !== null
                        ? $distance * $rate
                        : floatval($item['conveyance_amount'] ?? 0);

                    $penalty_applied = false;

                    // Penalty logic completely removed as per user request

                    
                    $food_allowance = isset($item['is_outstation']) && $item['is_outstation'] ? 300 : floatval($item['food_allowance'] ?? 0);

                    $lodging = floatval($item['lodging_amount'] ?? 0);
                    $courier = floatval($item['courier_amount'] ?? 0);
                    $other = floatval($item['other_amount'] ?? 0);
                    $toll_amount = floatval($item['toll_amount'] ?? 0);
                    $special_approval_amount = floatval($item['special_approval_amount'] ?? 0);
                    // A row sent without a currency (an older cached form, the app) is INR.
                    // This used to read $item['currency'] after the ?? check, so a missing
                    // key threw "Undefined array key" and the whole claim failed to save.
                    $currency = $item['currency'] ?? 'INR';
                    if (!in_array($currency, self::CURRENCIES, true)) {
                        $currency = 'INR';
                    }

                    // Columns removed from UI, hardcode to 0 for DB backward compatibility
                    $add_food_amount = 0;
                    $transport_amount = 0;
                    $bills_amount = 0;
                    $freight_amount = 0;
                    $auto_taxi_amount = 0;
                    $transportProofPath = null;
                    $billsProofPath = null;
                    $freightProofPath = null;
                    $autoTaxiProofPath = null;
                    // Was referenced when creating the item but never defined —
                    // every save containing items died with "Undefined variable
                    // $courierProofPath" and rolled back.
                    $courierProofPath = $request->hasFile("items.{$index}.courier_proof")
                        ? $request->file("items.{$index}.courier_proof")->store('travel_claims_courier', 'public')
                        : ($item['existing_courier_proof'] ?? $carried['courier_proof'] ?? null);

                    $row_total = $conveyance + $food_allowance + $lodging + $courier + $other + $toll_amount + $add_food_amount + $special_approval_amount;
                    $total_amount += $row_total;

                    TravelClaimItem::forceCreate([
                        'travel_claim_id' => $claim->id,
                        'date' => $item['date'],
                        'from_location' => $item['from_location'] ?? null,
                        'to_location' => $item['to_location'] ?? null,
                        'party_visited' => $item['party_visited'] ?? null,
                        'mode_of_travel' => $mode,
                        'currency' => $currency,
                        'start_meter' => self::decimalOrNull($item['start_meter'] ?? null),
                        'end_meter' => self::decimalOrNull($item['end_meter'] ?? null),
                        'distance_km' => $distance,
                        'photo_path' => $photoPath,
                        'petrol_slip_proof' => $petrolSlipPath,
                        'penalty_applied' => $penalty_applied,
                        'conveyance_amount' => $conveyance,
                        'auto_taxi_amount' => $auto_taxi_amount,
                        'auto_taxi_proof' => $autoTaxiProofPath,
                        'is_outstation' => isset($item['is_outstation']) ? true : false,
                        'food_allowance' => $food_allowance,
                        'lodging_amount' => $lodging,
                        'courier_amount' => $courier,
                        'other_amount' => $other,
                        'toll_amount' => $toll_amount,
                        'toll_proof' => $tollProofPath,
                        'additional_food_amount' => $add_food_amount,
                        'additional_food_proof' => $addFoodProofPath,
                        'special_approval_amount' => $special_approval_amount,
                        'special_approval_proof' => $specialApprovalProofPath,
                        'transport_amount' => $transport_amount,
                        'transport_proof' => $transportProofPath,
                        'bills_amount' => $bills_amount,
                        'bills_proof' => $billsProofPath,
                        'freight_amount' => $freight_amount,
                        'freight_proof' => $freightProofPath,
                        'courier_proof' => $courierProofPath,
                        'remarks' => $item['remarks'] ?? null,
                    ]);
                }
            }

        $total_advances = 0;
        if ($request->has('advances') && is_array($request->advances)) {
            foreach ($request->advances as $adv) {
                $amt = floatval($adv['amount'] ?? 0);
                if ($amt > 0) {
                    // The table's columns are advance_date / advance_type /
                    // remarks. Writing date / mode / cheque_number threw
                    // "Unknown column" inside the transaction, which rolled
                    // back the whole claim — so entering any advance destroyed
                    // the entire submission rather than just the advance.
                    $chequeNo = trim((string) ($adv['cheque_number'] ?? ''));
                    TravelClaimAdvance::forceCreate([
                        'travel_claim_id' => $claim->id,
                        'advance_date'    => $adv['date'] ?? null,
                        'advance_type'    => $adv['mode'] ?? null,
                        'remarks'         => $chequeNo !== '' ? 'Cheque no: ' . $chequeNo : null,
                        'amount'          => $amt,
                    ]);
                    $total_advances += $amt;
                }
            }
        }

        $net_payable = $total_amount - $total_advances;
        // 10% late penalty — only on money going OUT. When advances exceed the
        // claim the balance is negative (the employee owes it back), and
        // multiplying that by 0.90 shrank their debt, so filing late paid them.
        if ($latePenalty && $net_payable > 0) {
            $net_payable = $net_payable * 0.90;
        }

        $claim->forceFill([
            'total_amount' => $total_amount,
            'total_advances' => $total_advances,
            'net_payable' => $net_payable,
        ])->save();

        DB::commit();

        /* Autosave: the browser posts the form as a draft in the background, so
           it needs the claim id back (to keep updating the SAME draft instead of
           creating one per keystroke burst) rather than a redirect. */
        if ($request->boolean('autosave')) {
            return response()->json([
                'ok'       => true,
                'id'       => $claim->id,
                'saved_at' => now()->format('h:i A'),
                'skipped'  => $skippedRows ?? 0,
            ]);
        }

        if ($isDraft) {
            $msg = 'Draft saved. You can find it under your claims list and continue editing.';
            if (!empty($skippedRows)) {
                $msg .= " ({$skippedRows} row(s) without a date were not saved — add the date to keep them.)";
            }
        } else {
            $msg = request()->routeIs('travel-claims.update') ? 'Travel claim updated successfully.' : 'Travel claim submitted successfully.';
        }
        return redirect()->route('travel-claims.index')->with('success', $msg);
    } catch (\Illuminate\Validation\ValidationException $e) {
        // A missing odometer photo or meter reading is a form error, not a
        // crash. Letting it through means Laravel renders it against the right
        // field; the blanket catch below used to swallow it into an unhelpful
        // "Error submitting claim" banner.
        DB::rollBack();
        throw $e;
    } catch (\Exception $e) {
        DB::rollBack();
        // Autosave must not throw the user out of the form they are typing in.
        if ($request->boolean('autosave')) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
        return back()->with('error', 'Error submitting claim: ' . $e->getMessage())->withInput();
    }
}

public function edit($id)
{
    $claim = TravelClaim::with(['items', 'advances'])->where('user_id', auth()->user()->id)->findOrFail($id);
    if ($claim->status !== 'draft' && $claim->status !== 'objection') {
        return redirect()->route('travel-claims.index')->with('error', 'You can only edit claims that are in draft or have objections.');
    }
    $attachmentsByCategory = \App\Models\TravelClaimAttachment::where('travel_claim_id', $claim->id)
        ->orderBy('id')->get()->groupBy('category');

    return view('tenant.travel-claims.form', compact('claim', 'attachmentsByCategory'));
}

public function update(Request $request, $id)
{
    $claim = TravelClaim::where('user_id', auth()->user()->id)->findOrFail($id);
    if ($claim->status !== 'draft' && $claim->status !== 'objection') {
        return redirect()->route('travel-claims.index')->with('error', 'You can only edit claims that are in draft or have objections.');
    }

    // store() clears and repopulates the children inside its own transaction.
    // Deleting them here, outside it, destroyed the rows for good whenever the
    // save that followed failed.
    return $this->store($request, $claim);
}

    public function show($id)
    {
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'super_admin', 'accounts', 'Accounts', 'manager', 'Manager', 'hr', 'HR']) ||
            $user->can('travel_claim.verify') ||
            $user->can('travel_claim.approve');
        $claim = TravelClaim::with(['items', 'advances', 'user'])->findOrFail($id);
        if (!$isPrivileged && $claim->user_id !== $user->id) {
            abort(403);
        }
        return response()->json($claim);
    }

    public function verify(Request $request, $id)
    {
        $claim = TravelClaim::findOrFail($id);
        if ($claim->user_id === auth()->id()) {
            return back()->with('error', 'You cannot verify your own claim.');
        }
        // Only something awaiting review can be verified — not a draft, and not
        // a claim that has already been approved or paid.
        if (!in_array($claim->status, ['submitted', 'objection'], true)) {
            return back()->with('error',
                'Only a submitted claim can be verified. This claim is currently ' . $claim->status . '.');
        }
        $claim->forceFill([
            'status' => 'verified',
            'verifier_id' => auth()->id(),
            'verified_at' => now(),
            'remarks' => $request->remarks,
        ])->save();
        // Objection resolved — clear any row flags raised earlier.
        $claim->items()->update(['is_flagged' => false, 'flag_reason' => null]);
        return back()->with('success', 'Claim verified successfully.');
    }

    public function approve(Request $request, $id)
    {
        $claim = TravelClaim::findOrFail($id);
        if ($claim->user_id === auth()->id()) {
            return back()->with('error', 'You cannot approve your own claim.');
        }
        // Approval follows verification. Without this a submitted claim could
        // jump straight to approved, skipping the verifier entirely — the
        // two-step control existed only by convention.
        if ($claim->status !== 'verified') {
            return back()->with('error',
                'Only a verified claim can be approved. This claim is currently ' . $claim->status . '.');
        }

        // Split payments: 85% on 11th, 15% on 25th
        $split85 = round($claim->net_payable * 0.85, 2);
        $split15 = $claim->net_payable - $split85;
        
        // Anchor to day 1 — 'Y-m' alone overflows past short months on days 29-31.
        try {
            $claimMonthDate = Carbon::createFromFormat('Y-m-d', $claim->claim_month . '-01');
        } catch (\Exception $e) {
            // Store-time validation now blocks this, but an older row saved before
            // that guard existed could still carry a bad claim_month - fail the
            // approval with something the approver can act on, not a 500 page.
            return back()->with('error',
                "This claim's month ({$claim->claim_month}) isn't a valid YYYY-MM value, so a payment schedule can't be calculated. Please fix it before approving.");
        }
        // Payments happen next month
        $payDate85 = $claimMonthDate->copy()->addMonth()->setDay(11);
        $payDate15 = $claimMonthDate->copy()->addMonth()->setDay(25);

        $claim->forceFill([
            'status' => 'approved',
            'split_85_amount' => $split85,
            'split_15_amount' => $split15,
            'split_85_paid_on' => clone $payDate85,
            'split_15_paid_on' => clone $payDate15,
        ])->save();
        return back()->with('success', 'Claim approved successfully with split payment schedule.');
    }

    public function pay(Request $request, $id)
    {
        $claim = TravelClaim::findOrFail($id);
        // Money may only be recorded against a claim that has been approved.
        // Without this, a transaction reference could be written onto a draft.
        if (!in_array($claim->status, ['approved', 'paid'], true)) {
            return back()->with('error',
                'Only an approved claim can be paid. This claim is currently ' . $claim->status . '.');
        }

        if ($request->payment_type == '85') {
            $claim->forceFill(['split_85_transaction' => $request->transaction])->save();
        } elseif ($request->payment_type == '15') {
            $claim->forceFill(['split_15_transaction' => $request->transaction])->save();
        }
        
        if ($claim->split_85_transaction && $claim->split_15_transaction) {
            $claim->forceFill([
                'status' => 'paid',
                'payer_id' => auth()->id(),
                'paid_at' => now()
            ])->save();
        }
        return back()->with('success', 'Payment details recorded.');
    }

    public function adminDashboard(Request $request)
    {
        $query = TravelClaim::with('user')->orderBy('id', 'asc');

        // Filters
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }
        if ($request->has('company') && $request->company != '') {
            $query->where('company', $request->company);
        }
        if ($request->has('claim_month') && $request->claim_month != '') {
            $query->where('claim_month', $request->claim_month);
        }

        $claims = $query->get();

        // Stat cards must reflect the SAME filters as the table below them.
        // They used to be six unfiltered queries, so choosing a status or
        // month changed the table while the cards kept showing whole-company
        // totals — the two halves of the page disagreed.
        $statBase = function () use ($request) {
            $q = TravelClaim::query();
            if ($request->filled('status'))      $q->where('status', $request->status);
            if ($request->filled('company'))     $q->where('company', $request->company);
            if ($request->filled('claim_month')) $q->where('claim_month', $request->claim_month);
            return $q;
        };

        $byStatus = $statBase()
            ->selectRaw("status, COUNT(*) c, SUM(COALESCE(net_payable, total_amount, 0)) amt")
            ->groupBy('status')->get()->keyBy('status');
        $countOf = fn(array $statuses) => collect($statuses)->sum(fn($s) => (int) ($byStatus[$s]->c ?? 0));

        $stats = [
            'total'        => (int) $byStatus->sum('c'),
            'pending'      => $countOf(['submitted']),
            'approved'     => $countOf(['approved', 'paid']),
            'rejected'     => $countOf(['rejected']),
            'objection'    => $countOf(['objection']),
            'total_payout' => (float) collect(['approved', 'paid'])
                ->sum(fn($s) => (float) ($byStatus[$s]->amt ?? 0)),
        ];

        // Claims per month — this query already existed but the blade never
        // referenced it and the page had no chart at all, so it was a wasted
        // query expressing an intent nobody could see. Now shaped for and
        // rendered by a real chart on the page.
        $monthlyStatsRaw = TravelClaim::selectRaw('claim_month, count(*) as count')
            ->groupBy('claim_month')
            ->orderBy('claim_month', 'asc')
            ->get();

        $monthlyStats = [];
        foreach ($monthlyStatsRaw as $stat) {
            // A pre-validation draft can still carry a malformed claim_month (e.g.
            // "AUG", typed into the <input type="month"> fallback some browsers use)
            // - skip it here rather than let one bad row 500 the dashboard for every
            // admin. New saves can no longer produce this; old rows still can exist.
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $stat->claim_month)) {
                continue;
            }
            $monthlyStats[$stat->claim_month] = $stat->count;
        }

        $monthlyChart = [
            'labels' => array_map(
                // Anchor to day 1 — 'Y-m' alone overflows past short months on days 29-31.
                fn($m) => \Carbon\Carbon::createFromFormat('Y-m-d', $m . '-01')->format('M Y'),
                array_keys($monthlyStats)
            ),
            'data' => array_values($monthlyStats),
        ];

        return view('tenant.travel-claims.admin-dashboard', compact('claims', 'stats', 'monthlyStats', 'monthlyChart'));
    }

    public function reject(Request $request, $id)
    {
        $claim = TravelClaim::findOrFail($id);
        if ($claim->user_id === auth()->id()) {
            return back()->with('error', 'You cannot reject your own claim.');
        }
        // Paid is terminal: the money has already gone out, so rejecting it
        // afterwards would leave the books saying it never should have.
        if ($claim->status === 'paid') {
            return back()->with('error', 'A paid claim cannot be rejected. Raise a recovery separately.');
        }
        $claim->forceFill([
            'status' => 'rejected',
            'remarks' => 'Rejected: ' . $request->remarks,
        ])->save();
        return back()->with('success', 'Claim rejected.');
    }

    public function objection(Request $request, $id)
    {
        $claim = TravelClaim::findOrFail($id);
        if ($claim->user_id === auth()->id()) {
            return back()->with('error', 'You cannot raise an objection on your own claim.');
        }
        if ($claim->status === 'paid') {
            return back()->with('error', 'A paid claim cannot be sent back. Raise a recovery separately.');
        }
        $claim->forceFill([
            'status' => 'objection',
            'remarks' => 'Objection Raised: ' . $request->remarks,
            'objection_notes' => $request->remarks,
        ])->save();

        // Flag the specific rows the verifier ticked, so the user sees exactly
        // which entries to correct (highlighted red on their edit form).
        $flagged = array_filter(array_map('intval', explode(',', (string) $request->input('flagged_items'))));
        $claim->items()->update(['is_flagged' => false, 'flag_reason' => null]);
        if ($flagged) {
            $claim->items()->whereIn('id', $flagged)
                ->update(['is_flagged' => true, 'flag_reason' => $request->remarks]);
        }

        return back()->with('success', 'Objection raised and sent back to user.'
            . ($flagged ? ' ' . count($flagged) . ' row(s) flagged for correction.' : ''));
    }

    /**
     * Store one file for a monthly bill category and return its details as
     * JSON. Called as soon as the user picks a file, so nothing is lost if the
     * form is abandoned mid-way and large bills are not re-posted on every save.
     *
     * Before the claim exists the file is held against `upload_token`; store()
     * adopts those rows once the claim has an id.
     */
    public function uploadAttachment(Request $request)
    {
        $categories = array_keys(\App\Models\TravelClaimAttachment::CATEGORIES);

        $request->validate([
            'category'     => 'required|string|in:' . implode(',', $categories),
            'file'         => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'upload_token' => 'required|string|max:64',
            'claim_id'     => 'nullable|integer',
            // The browser uploads under a sanitised filename because the host's
            // firewall 403s an apostrophe in a multipart filename. This carries
            // the name the user actually picked, for display and download.
            'original_name' => 'nullable|string|max:255',
        ]);

        $category = $request->input('category');
        $token    = $request->input('upload_token');
        $claimId  = $request->input('claim_id') ?: null;

        // A claim can only gain files while its owner may still edit it.
        if ($claimId) {
            $claim = TravelClaim::where('user_id', auth()->id())->find($claimId);
            if (!$claim || !in_array($claim->status, ['draft', 'objection'], true)) {
                return response()->json(['message' => 'This claim can no longer be edited.'], 403);
            }
        }

        $existing = \App\Models\TravelClaimAttachment::where('user_id', auth()->id())
            ->where('category', $category)
            ->where(function ($q) use ($claimId, $token) {
                $q->where('upload_token', $token)->whereNull('travel_claim_id');
                if ($claimId) {
                    $q->orWhere('travel_claim_id', $claimId);
                }
            })
            ->count();

        if ($existing >= \App\Models\TravelClaimAttachment::MAX_PER_CATEGORY) {
            return response()->json([
                'message' => 'You already have ' . \App\Models\TravelClaimAttachment::MAX_PER_CATEGORY
                    . ' files in this category — remove one before adding another.',
            ], 422);
        }

        $file = $request->file('file');
        $path = $file->store(\App\Models\TravelClaimAttachment::CATEGORIES[$category], 'public');

        $attachment = \App\Models\TravelClaimAttachment::forceCreate([
            'travel_claim_id' => $claimId,
            'upload_token'    => $token,
            'user_id'         => auth()->id(),
            'category'        => $category,
            'path'            => $path,
            'original_name'   => trim((string) $request->input('original_name')) ?: $file->getClientOriginalName(),
            'mime'            => $file->getClientMimeType(),
            'size'            => $file->getSize(),
        ]);

        return response()->json([
            'id'       => $attachment->id,
            'name'     => $attachment->original_name,
            'url'      => $attachment->url,
            'size'     => $attachment->size,
            'is_image' => $attachment->is_image,
            'count'    => $existing + 1,
        ]);
    }

    /**
     * Remove one uploaded bill file, and its file on disk with it.
     */
    public function destroyAttachment($id)
    {
        $attachment = \App\Models\TravelClaimAttachment::where('user_id', auth()->id())->findOrFail($id);

        if ($attachment->travel_claim_id) {
            $claim = TravelClaim::find($attachment->travel_claim_id);
            if (!$claim || !in_array($claim->status, ['draft', 'objection'], true)) {
                return response()->json(['message' => 'This claim can no longer be edited.'], 403);
            }
        }

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * One claim as an .xlsx, so a verifier can check it away from the screen.
     *
     * Same authorisation as downloadAttachments(): admin / hr / accounts, the
     * verify or approve permission, or the person who filed the claim.
     */
    public function exportExcel($id)
    {
        $claim = TravelClaim::with(['items', 'user', 'advances'])->findOrFail($id);
        $user  = auth()->user();

        if (!$user->hasRole(['admin', 'hr', 'accounts']) &&
            !$user->can('travel_claim.verify') &&
            !$user->can('travel_claim.approve') &&
            $claim->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $name = preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($claim->user->name ?? 'employee'));
        $file = sprintf('Claim_%d_%s_%s.xlsx', $claim->id, trim($name, '_'), $claim->claim_month);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TravelClaimExport($claim), $file
        );
    }

    public function downloadAttachments($id)
    {
        $claim = TravelClaim::with(['items', 'user'])->findOrFail($id);
        $user = auth()->user();

        // Authorization check: admin, hr, accounts, or the owner of the claim
        if (!$user->hasRole(['admin', 'hr', 'accounts']) &&
            !$user->can('travel_claim.verify') &&
            !$user->can('travel_claim.approve') &&
            $claim->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $proofFields = [
            'petrol_slip_proof' => 'Conveyance_Bill_Proof',
            'toll_proof' => 'Toll_Parking_Proof',
            'additional_food_proof' => 'Food_Bill_Proof',
            'special_approval_proof' => 'Customer_Expense_Proof',
            'courier_proof' => 'Courier_Transport_Proof',
            'auto_taxi_proof' => 'Auto_Taxi_Proof'
        ];

        // Find all attachments
        $filesToAdd = [];

        // Add Header level proofs
        if ($claim->month_start_odometer_proof && Storage::disk('public')->exists($claim->month_start_odometer_proof)) {
            $path = Storage::disk('public')->path($claim->month_start_odometer_proof);
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $filesToAdd[] = [
                'path' => $path,
                'zipPath' => "Header/month_start_odometer_proof.{$ext}"
            ];
        }

        if ($claim->month_end_odometer_proof && Storage::disk('public')->exists($claim->month_end_odometer_proof)) {
            $path = Storage::disk('public')->path($claim->month_end_odometer_proof);
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $filesToAdd[] = [
                'path' => $path,
                'zipPath' => "Header/month_end_odometer_proof.{$ext}"
            ];
        }

        // Consolidated monthly bills — the legacy single file per category, plus
        // every file from the multi-upload boxes that replaced it.
        $categoryLabels = [
            'food_bill_proof'         => 'Food_Bills',
            'conveyance_petrol_proof' => 'Conveyance_Petrol_Bills',
            'toll_parking_proof'      => 'Toll_Parking_Bills',
            'customer_courier_proof'  => 'Customer_Expense_Courier_Bills',
            'hotel_bill_proof'        => 'Hotel_Bills',
        ];
        foreach ($categoryLabels as $field => $folderName) {
            if ($claim->$field && Storage::disk('public')->exists($claim->$field)) {
                $path = Storage::disk('public')->path($claim->$field);
                $ext  = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
                $filesToAdd[] = ['path' => $path, 'zipPath' => "{$folderName}/combined.{$ext}"];
            }
        }

        $seenNames = [];
        foreach (\App\Models\TravelClaimAttachment::where('travel_claim_id', $claim->id)->orderBy('id')->get() as $att) {
            if (!Storage::disk('public')->exists($att->path)) {
                continue;
            }
            $folderName = $categoryLabels[$att->category] ?? 'Other_Bills';
            // Two bills can genuinely share a filename; a collision inside the
            // zip would silently drop one of them.
            $name = $att->original_name;
            $key  = $folderName . '/' . strtolower($name);
            if (isset($seenNames[$key])) {
                $name = pathinfo($name, PATHINFO_FILENAME) . '_' . $att->id
                      . (($e = pathinfo($name, PATHINFO_EXTENSION)) ? '.' . $e : '');
            }
            $seenNames[$key] = true;
            $filesToAdd[] = [
                'path'    => Storage::disk('public')->path($att->path),
                'zipPath' => "{$folderName}/{$name}",
            ];
        }

        foreach ($claim->items as $item) {
            foreach ($proofFields as $field => $folderName) {
                if ($item->$field) {
                    $relativePath = $item->$field;
                    $absolutePath = \Illuminate\Support\Facades\Storage::disk('public')->path($relativePath);
                    if (file_exists($absolutePath)) {
                        $dateStr = \Carbon\Carbon::parse($item->date)->format('d_M');
                        $ext = pathinfo($absolutePath, PATHINFO_EXTENSION);
                        $ext = $ext ? $ext : 'jpg';
                        $filename = "{$dateStr}_item_{$item->id}_{$folderName}.{$ext}";
                        $filesToAdd[] = [
                            'path' => $absolutePath,
                            'zipPath' => "{$folderName}/{$filename}"
                        ];
                    }
                }
            }
        }

        if (empty($filesToAdd)) {
            return redirect()->back()->with('error', 'No attachments found for this claim.');
        }

        // Create temporary zip file
        $zipName = 'claim_' . $claim->id . '_' . \Illuminate\Support\Str::slug($claim->user->name) . '_attachments.zip';
        $zipPath = tempnam(sys_get_temp_dir(), 'zip');

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return redirect()->back()->with('error', 'Could not create ZIP file.');
        }

        foreach ($filesToAdd as $f) {
            $zip->addFile($f['path'], $f['zipPath']);
        }
        $zip->close();

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    public function destroy($id)
    {
        $claim = TravelClaim::where('user_id', auth()->id())->findOrFail($id);
        
        if ($claim->status !== 'draft') {
            return redirect()->route('travel-claims.index')->with('error', 'You can only delete claims that are in draft status.');
        }

        try {
            DB::beginTransaction();

            // Delete header level proofs
            if ($claim->month_start_odometer_proof) {
                Storage::disk('public')->delete($claim->month_start_odometer_proof);
            }
            if ($claim->month_end_odometer_proof) {
                Storage::disk('public')->delete($claim->month_end_odometer_proof);
            }

            // Delete proof files associated with the items
            foreach ($claim->items as $item) {
                $proofFields = [
                    'petrol_slip_proof' => 'Conveyance_Bill_Proof',
                    'toll_proof' => 'Toll_Parking_Proof',
                    'additional_food_proof' => 'Food_Bill_Proof',
                    'special_approval_proof' => 'Customer_Expense_Proof',
                    'courier_proof' => 'Courier_Transport_Proof',
                ];
                foreach ($proofFields as $field => $folderName) {
                    if ($item->$field) {
                        Storage::disk('public')->delete($item->$field);
                    }
                }
            }

            // Monthly bill uploads. The rows cascade with the claim, but the
            // files behind them do not — deleting a draft used to leave every
            // attached bill sitting in storage with nothing referencing it.
            foreach (\App\Models\TravelClaimAttachment::where('travel_claim_id', $claim->id)->get() as $bill) {
                if ($bill->path && Storage::disk('public')->exists($bill->path)) {
                    Storage::disk('public')->delete($bill->path);
                }
                $bill->delete();
            }

            // The four consolidated header proofs, likewise.
            foreach (['food_bill_proof', 'conveyance_petrol_proof', 'toll_parking_proof', 'customer_courier_proof'] as $field) {
                if ($claim->{$field} && Storage::disk('public')->exists($claim->{$field})) {
                    Storage::disk('public')->delete($claim->{$field});
                }
            }

            // Delete related items and advances
            $claim->items()->delete();
            $claim->advances()->delete();

            // Delete the claim itself
            $claim->delete();

            DB::commit();

            return redirect()->route('travel-claims.index')->with('success', 'Draft claim deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('travel-claims.index')->with('error', 'Failed to delete draft claim: ' . $e->getMessage());
        }
    }
}

