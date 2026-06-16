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
            $claims = TravelClaim::with('user')->orderBy('created_at', 'desc')->get();
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
            $filteredItems = array_filter($request->items, function($item) {
                return !empty($item['mode_of_travel']) || 
                       (isset($item['food_allowance']) && floatval($item['food_allowance']) > 0) ||
                       (isset($item['lodging_amount']) && floatval($item['lodging_amount']) > 0) ||
                       (isset($item['courier_amount']) && floatval($item['courier_amount']) > 0) ||
                       (isset($item['other_amount']) && floatval($item['other_amount']) > 0) ||
                       !empty($item['remarks']) || !empty($item['from_location']);
            });
            $request->merge(['items' => $filteredItems]);
        }

        $request->validate([
            'claim_month' => 'required|string',
            'company' => 'required|string',
            'sales_collection' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.date' => 'required|date',
            'items.*.mode_of_travel' => 'nullable|string',
            'items.*.photo' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:1024',
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

            if ($existingClaim) {
                $claim = $existingClaim;
                $claim->forceFill([
                    'claim_month' => $request->claim_month,
                    'company' => $request->company,
                    'sales_collection' => $request->sales_collection ?? 0,
                    'status' => 'submitted',
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
                    'status' => 'submitted',
                    'late_penalty_applied' => $latePenalty,
                ]);
            }

            $total_amount = 0;

            foreach ($request->items as $index => $item) {
                $photoPath = null;
                if ($request->hasFile("items.{$index}.photo")) {
                    $photoPath = $request->file("items.{$index}.photo")->store('travel_claims', 'public');
                }

                $distance = floatval($item['distance_km'] ?? 0);
                $mode = $item['mode_of_travel'] ?? null;
                $rate = 0;
                if ($mode == 'Bike') $rate = 4.00;
                elseif ($mode == 'Car') $rate = 9.50;

                $conveyance = $distance * $rate;
                $penalty_applied = false;

                if (in_array($mode, ['Bike', 'Car']) && !$photoPath) {
                    $conveyance = $conveyance * 0.70; // 30% reduction
                    $penalty_applied = true;
                }
                
                // If the user checked outstation, apply 300
                $food_allowance = isset($item['is_outstation']) && $item['is_outstation'] ? 300 : floatval($item['food_allowance'] ?? 0);

                $lodging = floatval($item['lodging_amount'] ?? 0);
                $courier = floatval($item['courier_amount'] ?? 0);
                $other = floatval($item['other_amount'] ?? 0);

                $row_total = $conveyance + $food_allowance + $lodging + $courier + $other;
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
                    'penalty_applied' => $penalty_applied,
                    'conveyance_amount' => $conveyance,
                    'is_outstation' => isset($item['is_outstation']) ? true : false,
                    'food_allowance' => $food_allowance,
                    'lodging_amount' => $lodging,
                    'courier_amount' => $courier,
                    'other_amount' => $other,
                    'remarks' => $item['remarks'] ?? null,
                ]);
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
        ])->save();
        return back()->with('success', 'Objection raised and sent back to user.');
    }
}
