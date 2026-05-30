<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\TravelClaim;
use App\Models\TravelClaimItem;
use App\Models\TravelClaimAdvance;
use Carbon\Carbon;

// Find a user or use admin
$user = User::first();
if (!$user) {
    echo "No user found to assign claims.\n";
    exit;
}

// 1. Submitted Claim (Pending)
$claim1 = TravelClaim::create([
    'user_id' => $user->id,
    'claim_month' => '2026-05',
    'company' => 'Hi Tech International',
    'sales_collection' => 150000,
    'bank_account_name' => $user->name,
    'bank_account_no' => '1234567890123',
    'bank_ifsc' => 'HDFC0001234',
    'status' => 'submitted',
    'total_amount' => 1250,
    'total_advances' => 0,
    'net_payable' => 1250,
    'late_penalty_applied' => false,
]);

TravelClaimItem::create([
    'travel_claim_id' => $claim1->id,
    'date' => '2026-05-10',
    'from_location' => 'Office',
    'to_location' => 'Client A',
    'party_visited' => 'ABC Corp',
    'mode_of_travel' => 'Car',
    'start_meter' => 10000,
    'end_meter' => 10050,
    'distance_km' => 50,
    'photo_path' => null, // No photo -> penalty
    'penalty_applied' => true,
    'conveyance_amount' => (50 * 9.5) * 0.70, // 332.5
    'food_allowance' => 300, // Outstation
    'lodging_amount' => 500,
    'courier_amount' => 0,
    'other_amount' => 117.5, // To make it 1250
    'remarks' => 'Visited ABC Corp for new contract',
]);

// 2. Verified Claim
$claim2 = TravelClaim::create([
    'user_id' => $user->id,
    'claim_month' => '2026-04',
    'company' => 'KEEP IT FRESH LLP',
    'sales_collection' => 0,
    'bank_account_name' => $user->name,
    'bank_account_no' => '1234567890123',
    'bank_ifsc' => 'HDFC0001234',
    'status' => 'verified',
    'verifier_id' => $user->id,
    'verified_at' => now()->subDays(2),
    'remarks' => 'Verified by HR. Looks good.',
    'total_amount' => 850,
    'total_advances' => 0,
    'net_payable' => 850,
    'late_penalty_applied' => false,
]);

TravelClaimItem::create([
    'travel_claim_id' => $claim2->id,
    'date' => '2026-04-15',
    'from_location' => 'Home',
    'to_location' => 'Vendor Site',
    'party_visited' => 'XYZ Suppliers',
    'mode_of_travel' => 'Bike',
    'start_meter' => 5000,
    'end_meter' => 5030,
    'distance_km' => 30,
    'photo_path' => 'demo_photo.jpg',
    'penalty_applied' => false,
    'conveyance_amount' => 120, // 30 * 4
    'food_allowance' => 200,
    'lodging_amount' => 0,
    'courier_amount' => 50,
    'other_amount' => 480,
    'remarks' => 'Routine vendor check',
]);

// 3. Approved Claim
$claim3 = TravelClaim::create([
    'user_id' => $user->id,
    'claim_month' => '2026-03',
    'company' => 'Hi Tech International',
    'sales_collection' => 500000,
    'bank_account_name' => $user->name,
    'bank_account_no' => '1234567890123',
    'bank_ifsc' => 'HDFC0001234',
    'status' => 'approved',
    'verifier_id' => $user->id,
    'verified_at' => now()->subDays(10),
    'total_amount' => 5000,
    'total_advances' => 1000,
    'net_payable' => 4000,
    'late_penalty_applied' => false,
    'split_85_amount' => 3400,
    'split_15_amount' => 600,
    'split_85_paid_on' => '2026-04-11',
    'split_15_paid_on' => '2026-04-25',
]);

TravelClaimItem::create([
    'travel_claim_id' => $claim3->id,
    'date' => '2026-03-20',
    'from_location' => 'Office',
    'to_location' => 'Delhi Branch',
    'party_visited' => 'Internal Audit',
    'mode_of_travel' => 'Train',
    'distance_km' => 0,
    'penalty_applied' => false,
    'conveyance_amount' => 3000,
    'food_allowance' => 1000,
    'lodging_amount' => 1000,
    'courier_amount' => 0,
    'other_amount' => 0,
    'remarks' => 'Train tickets and hotel stay',
]);

TravelClaimAdvance::create([
    'travel_claim_id' => $claim3->id,
    'date' => '2026-03-15',
    'mode' => 'Cash',
    'amount' => 1000,
]);

// 4. Rejected Claim
$claim4 = TravelClaim::create([
    'user_id' => $user->id,
    'claim_month' => '2026-05',
    'company' => 'KEEP IT FRESH LLP',
    'bank_account_name' => $user->name,
    'bank_account_no' => '1234567890123',
    'bank_ifsc' => 'HDFC0001234',
    'status' => 'rejected',
    'remarks' => 'Rejected: Fake bills provided.',
    'total_amount' => 10000,
    'total_advances' => 0,
    'net_payable' => 10000,
    'late_penalty_applied' => false,
]);

echo "Seeded 4 demo claims successfully!\n";
