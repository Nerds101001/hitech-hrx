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


    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->hasRole(['admin', 'Admin', 'super_admin'])) {
            $claims = TravelClaim::with('user')
                ->where(function($q) use ($user) {
                    $q->where('status', '!=', 'draft')
                      ->orWhere('user_id', $user->id);
                })
                ->orderBy('created_at', 'desc')->get();
            return view('tenant.travel-claims.verify', compact('claims'));
        } elseif ($user->hasRole(['accounts', 'Accounts'])) {
            $claims = TravelClaim::with('user')->whereIn('status', ['verified', 'approved', 'paid'])->orderBy('created_at', 'desc')->get();
            return view('tenant.travel-claims.accounts', compact('claims'));
        } else {
            $claims = TravelClaim::with('items')->where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
            return view('tenant.travel-claims.index', compact('claims'));
        }
    }

    public function create()
    {
        return view('tenant.travel-claims.form');
    }

    public function store(Request $request, TravelClaim $existingClaim = null)
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

                $hasValue = !empty($item['mode_of_travel']) || 
                            !empty($item['to_location']) ||
                            !empty($item['from_location']) ||
                            !empty($item['remarks']) ||
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

        $isDraft = $request->input('action') === 'draft';

        $request->validate([
            'claim_month' => 'required|string',
            'company' => 'required|string',
            'sales_collection' => 'nullable|numeric',
            'items' => $isDraft ? 'nullable|array' : 'required|array|min:1',
            'items.*.date' => 'required|date',
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

        $latePenalty = false;
        try {
            $claimMonthDate = \Carbon\Carbon::createFromFormat('Y-m', $request->claim_month);
            $deadline = $claimMonthDate->copy()->addMonth()->startOfMonth()->addDays(6)->endOfDay(); // 7th of next month
            if (now()->gt($deadline)) {
                $latePenalty = true;
            }
        } catch (\Exception $e) {
            // fallback
        }

        try {
            DB::beginTransaction();

            $targetStatus = $isDraft ? 'draft' : 'submitted';

            if ($existingClaim) {
                $claim = $existingClaim;
                $claim->forceFill([
                    'claim_month' => $request->claim_month,
                    'company' => $request->company,
                    'sales_collection' => $request->sales_collection ?? 0,
                    'status' => $targetStatus,
                    'late_penalty_applied' => $latePenalty,
                ])->save();
            } else {
                $claim = TravelClaim::forceCreate([
                    'user_id' => auth()->id(),
                    'claim_month' => $request->claim_month,
                    'company' => $request->company,
                    'sales_collection' => $request->sales_collection ?? 0,
                    'bank_account_name' => 'On File',
                    'bank_account_no' => 'On File',
                    'bank_ifsc' => 'On File',
                    'status' => $targetStatus,
                    'late_penalty_applied' => $latePenalty,
                ]);
            }

            $total_amount = 0;

            if (!empty($request->items) && is_array($request->items)) {
                foreach ($request->items as $index => $item) {
                    $photoPath = $request->hasFile("items.{$index}.photo")
                        ? $request->file("items.{$index}.photo")->store('travel_claims', 'public')
                        : ($item['existing_photo'] ?? null);
                    
                    $petrolSlipPath = $request->hasFile("items.{$index}.petrol_slip")
                        ? $request->file("items.{$index}.petrol_slip")->store('travel_claims_petrol', 'public')
                        : ($item['existing_petrol_slip'] ?? null);

                    $tollProofPath = $request->hasFile("items.{$index}.toll_proof")
                        ? $request->file("items.{$index}.toll_proof")->store('travel_claims_tolls', 'public')
                        : ($item['existing_toll_proof'] ?? null);

                    $addFoodProofPath = $request->hasFile("items.{$index}.additional_food_proof")
                        ? $request->file("items.{$index}.additional_food_proof")->store('travel_claims_extra', 'public')
                        : ($item['existing_additional_food_proof'] ?? null);

                    $specialApprovalProofPath = $request->hasFile("items.{$index}.special_approval_proof")
                        ? $request->file("items.{$index}.special_approval_proof")->store('travel_claims_special', 'public')
                        : ($item['existing_special_approval_proof'] ?? null);

                    $transportProofPath = $request->hasFile("items.{$index}.transport_proof")
                        ? $request->file("items.{$index}.transport_proof")->store('travel_claims_transport', 'public')
                        : ($item['existing_transport_proof'] ?? null);

                    $billsProofPath = $request->hasFile("items.{$index}.bills_proof")
                        ? $request->file("items.{$index}.bills_proof")->store('travel_claims_bills', 'public')
                        : ($item['existing_bills_proof'] ?? null);

                    $freightProofPath = $request->hasFile("items.{$index}.freight_proof")
                        ? $request->file("items.{$index}.freight_proof")->store('travel_claims_freight', 'public')
                        : ($item['existing_freight_proof'] ?? null);

                    $courierProofPath = $request->hasFile("items.{$index}.courier_proof")
                        ? $request->file("items.{$index}.courier_proof")->store('travel_claims_courier', 'public')
                        : ($item['existing_courier_proof'] ?? null);

                    $autoTaxiProofPath = $request->hasFile("items.{$index}.auto_taxi_proof")
                        ? $request->file("items.{$index}.auto_taxi_proof")->store('travel_claims_taxi', 'public')
                        : ($item['existing_auto_taxi_proof'] ?? null);

                    $distance = floatval($item['distance_km'] ?? 0);
                    $mode = $item['mode_of_travel'] ?? null;
                    $rate = 0;
                    $isOfficial = false;
                    
                    if ($mode == 'Personal Bike' || $mode == 'Bike') {
                        $rate = 4.00;
                    } elseif ($mode == 'Personal Car' || $mode == 'Car') {
                        $rate = 9.50;
                    } elseif ($mode == 'Official Bike' || $mode == 'Official Car') {
                        $isOfficial = true;
                    }

                    if ($isOfficial) {
                        $conveyance = floatval($item['conveyance_amount'] ?? 0);
                    } else {
                        $conveyance = $distance * $rate;
                    }

                    $penalty_applied = false;

                    if (in_array($mode, ['Personal Bike', 'Personal Car', 'Bike', 'Car']) && !$photoPath) {
                        $conveyance = $conveyance * 0.70; // 30% reduction
                        $penalty_applied = true;
                    }
                    
                    // If the user checked outstation, apply 300
                    $food_allowance = isset($item['is_outstation']) && $item['is_outstation'] ? 300 : floatval($item['food_allowance'] ?? 0);

                    $lodging = floatval($item['lodging_amount'] ?? 0);
                    $courier = floatval($item['courier_amount'] ?? 0);
                    $other = floatval($item['other_amount'] ?? 0);
                    $toll_amount = floatval($item['toll_amount'] ?? 0);
                    $add_food_amount = floatval($item['additional_food_amount'] ?? 0);
                    $special_approval_amount = floatval($item['special_approval_amount'] ?? 0);
                    $transport_amount = floatval($item['transport_amount'] ?? 0);
                    $bills_amount = floatval($item['bills_amount'] ?? 0);
                    $freight_amount = floatval($item['freight_amount'] ?? 0);
                    $auto_taxi_amount = floatval($item['auto_taxi_amount'] ?? 0);

                    $row_total = $conveyance + $food_allowance + $lodging + $courier + $other + $toll_amount + $add_food_amount + $special_approval_amount + $transport_amount + $bills_amount + $freight_amount + $auto_taxi_amount;
                    $total_amount += $row_total;

                    TravelClaimItem::forceCreate([
                        'travel_claim_id' => $claim->id,
                        'date' => $item['date'],
                        'from_location' => $item['from_location'] ?? null,
                        'to_location' => $item['to_location'] ?? null,
                        'party_visited' => $item['party_visited'] ?? null,
                        'mode_of_travel' => $mode,
                        'start_meter' => $item['start_meter'] ?? null,
                        'end_meter' => $item['end_meter'] ?? null,
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
                    TravelClaimAdvance::forceCreate([
                        'travel_claim_id' => $claim->id,
                        'date' => $adv['date'] ?? null,
                        'mode' => $adv['mode'] ?? null,
                        'cheque_number' => $adv['cheque_number'] ?? null,
                        'amount' => $amt,
                    ]);
                    $total_advances += $amt;
                }
            }
        }

        $net_payable = $total_amount - $total_advances;
        if ($latePenalty) {
            $net_payable = $net_payable * 0.90; // 10% late penalty
        }

        $claim->forceFill([
            'total_amount' => $total_amount,
            'total_advances' => $total_advances,
            'net_payable' => $net_payable,
        ])->save();

        DB::commit();
        $msg = request()->routeIs('travel-claims.update') ? 'Travel claim updated successfully.' : 'Travel claim submitted successfully.';
        return redirect()->route('travel-claims.index')->with('success', $msg);
    } catch (\Exception $e) {
        DB::rollBack();
        return back()->with('error', 'Error submitting claim: ' . $e->getMessage())->withInput();
    }
}

public function edit($id)
{
    $claim = TravelClaim::with(['items', 'advances'])->where('user_id', auth()->user()->id)->findOrFail($id);
    if ($claim->status !== 'draft' && $claim->status !== 'objection') {
        return redirect()->route('travel-claims.index')->with('error', 'You can only edit claims that are in draft or have objections.');
    }
    return view('tenant.travel-claims.form', compact('claim'));
}

public function update(Request $request, $id)
{
    $claim = TravelClaim::where('user_id', auth()->user()->id)->findOrFail($id);
    if ($claim->status !== 'draft' && $claim->status !== 'objection') {
        return redirect()->route('travel-claims.index')->with('error', 'You can only edit claims that are in draft or have objections.');
    }

    // Clear children so store() repopulates them on the same parent record
    $claim->items()->delete();
    $claim->advances()->delete();

    return $this->store($request, $claim);
}

    public function show($id)
    {
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'super_admin', 'accounts', 'Accounts', 'manager', 'Manager', 'hr', 'HR']);
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
        $claim->forceFill([
            'status' => 'verified',
            'verifier_id' => auth()->id(),
            'verified_at' => now(),
            'remarks' => $request->remarks,
        ])->save();
        return back()->with('success', 'Claim verified successfully.');
    }

    public function approve(Request $request, $id)
    {
        $claim = TravelClaim::findOrFail($id);
        if ($claim->user_id === auth()->id()) {
            return back()->with('error', 'You cannot approve your own claim.');
        }
        
        // Split payments: 85% on 11th, 15% on 25th
        $split85 = round($claim->net_payable * 0.85, 2);
        $split15 = $claim->net_payable - $split85;
        
        $claimMonthDate = Carbon::createFromFormat('Y-m', $claim->claim_month);
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

        $stats = [
            'total' => TravelClaim::count(),
            'pending' => TravelClaim::where('status', 'submitted')->count(),
            'approved' => TravelClaim::whereIn('status', ['approved', 'paid'])->count(),
            'rejected' => TravelClaim::where('status', 'rejected')->count(),
            'objection' => TravelClaim::where('status', 'objection')->count(),
            'total_payout' => TravelClaim::whereIn('status', ['approved', 'paid'])->sum('net_payable'),
        ];

        // Format for chart: Count of claims per month
        $monthlyStatsRaw = TravelClaim::selectRaw('claim_month, count(*) as count')
            ->groupBy('claim_month')
            ->orderBy('claim_month', 'asc')
            ->get();
        
        $monthlyStats = [];
        foreach ($monthlyStatsRaw as $stat) {
            $monthlyStats[$stat->claim_month] = $stat->count;
        }

        return view('tenant.travel-claims.admin-dashboard', compact('claims', 'stats', 'monthlyStats'));
    }

    public function reject(Request $request, $id)
    {
        $claim = TravelClaim::findOrFail($id);
        if ($claim->user_id === auth()->id()) {
            return back()->with('error', 'You cannot reject your own claim.');
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
        $claim->forceFill([
            'status' => 'objection',
            'remarks' => 'Objection Raised: ' . $request->remarks,
            'objection_notes' => $request->remarks,
        ])->save();
        return back()->with('success', 'Objection raised and sent back to user.');
    }

    public function downloadAttachments($id)
    {
        $claim = TravelClaim::with(['items', 'user'])->findOrFail($id);
        $user = auth()->user();

        // Authorization check: admin, hr, accounts, or the owner of the claim
        if (!$user->hasRole(['admin', 'hr', 'accounts']) && $claim->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $proofFields = [
            'photo_path' => 'odometer_proof',
            'toll_proof' => 'toll_proof',
            'additional_food_proof' => 'additional_food_proof',
            'special_approval_proof' => 'special_approval_proof',
            'transport_proof' => 'transport_proof',
            'bills_proof' => 'bills_proof',
            'freight_proof' => 'freight_proof',
            'courier_proof' => 'courier_proof',
            'petrol_slip_proof' => 'petrol_slip_proof',
            'auto_taxi_proof' => 'auto_taxi_proof'
        ];

        // Find all attachments
        $filesToAdd = [];
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

            // Delete proof files associated with the items
            foreach ($claim->items as $item) {
                $proofFields = [
                    'photo_path',
                    'toll_proof',
                    'additional_food_proof',
                    'special_approval_proof',
                    'transport_proof',
                    'bills_proof',
                    'freight_proof',
                    'courier_proof',
                    'petrol_slip_proof',
                    'auto_taxi_proof'
                ];
                foreach ($proofFields as $field) {
                    if ($item->$field) {
                        Storage::disk('public')->delete($item->$field);
                    }
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

