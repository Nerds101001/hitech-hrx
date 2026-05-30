<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Department;
use App\Models\Designation;
use App\Models\SalesClient;
use App\Models\SalesVisit;
use App\Models\CcSalespersonMap;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

// Ensure roles exist
$employeeRole = Role::firstOrCreate(['name' => 'employee']);

// 1. Setup Departments
$ccDept = Department::firstOrCreate(['name' => 'CCARE'], ['code' => 'CC', 'tenant_id' => 1]);
$salesDept = Department::firstOrCreate(['name' => 'Sales'], ['code' => 'SALES', 'tenant_id' => 1]);

// Setup Designation
$designation = Designation::firstOrCreate(['name' => 'Agent'], ['code' => 'AGT', 'tenant_id' => 1]);

// 2. Create CC Agent
$ccAgent = clone User::updateOrCreate(
    ['email' => 'ccagent@test.com'],
    [
        'code' => 'EMP-CC-001',
        'first_name' => 'Test',
        'last_name' => 'CCAgent',
        'password' => Hash::make('password123'),
        'department_id' => $ccDept->id,
        'designation_id' => $designation->id,
        'tenant_id' => 1, // Assuming tenant 1 is active
    ]
);
if (!$ccAgent->hasRole('employee')) {
    $ccAgent->assignRole('employee');
}

// 3. Create Salesperson
$salesPerson = clone User::updateOrCreate(
    ['email' => 'salesperson@test.com'],
    [
        'code' => 'EMP-SALES-001',
        'first_name' => 'Test',
        'last_name' => 'Salesperson',
        'password' => Hash::make('password123'),
        'department_id' => $salesDept->id,
        'designation_id' => $designation->id,
        'tenant_id' => 1,
    ]
);
if (!$salesPerson->hasRole('employee')) {
    $salesPerson->assignRole('employee');
}

// 4. Create Mapping
CcSalespersonMap::firstOrCreate([
    'cc_user_id' => $ccAgent->id,
    'sales_user_id' => $salesPerson->id,
], ['tenant_id' => 1]);

// 5. Create Dummy Clients
$clients = [];
for ($i = 1; $i <= 5; $i++) {
    $clients[] = SalesClient::firstOrCreate(
        ['email' => "client{$i}@example.com"],
        [
            'name' => "Demo Client $i",
            'phone' => "987654321$i",
            'address' => "123 Test Street, Suite $i",
            'city' => 'Test City',
            'contact_person' => "Mr. Contact $i",
            'created_by' => $ccAgent->id,
            'tenant_id' => 1,
        ]
    );
}

// 6. Create Dummy Visits
// Clear old visits for this salesperson to avoid clutter during testing
SalesVisit::where('salesperson_id', $salesPerson->id)->forceDelete();

$visitsData = [
    ['type' => 'client_visit', 'status' => 'pending', 'days_offset' => 0], // Today Pending
    ['type' => 'product_trial', 'status' => 'pending', 'days_offset' => 1], // Tomorrow Pending
    ['type' => 'service_call', 'status' => 'completed', 'days_offset' => -1, 'verified' => 'approved'], // Yesterday Approved
    ['type' => 'client_visit', 'status' => 'completed', 'days_offset' => 0, 'verified' => 'pending'], // Today Completed (Needs Verification)
    ['type' => 'order_collection', 'status' => 'cancelled', 'days_offset' => 2], // Future Cancelled
    ['type' => 'product_trial', 'status' => 'completed', 'days_offset' => -2, 'verified' => 'rejected'], // Rejected
];

foreach ($visitsData as $index => $data) {
    $isCompleted = $data['status'] === 'completed';
    $scheduledAt = Carbon::now()->addDays($data['days_offset'])->setHour(10 + $index)->setMinute(0);
    
    SalesVisit::create([
        'visit_type' => $data['type'],
        'status' => $data['status'],
        'verification_status' => $data['verified'] ?? 'pending',
        'verification_notes' => ($data['verified'] ?? '') === 'rejected' ? 'Location does not match client address. Please redo.' : null,
        'client_id' => $clients[$index % 5]->id,
        'salesperson_id' => $salesPerson->id,
        'cc_user_id' => $ccAgent->id,
        'scheduled_at' => $scheduledAt,
        'notes' => 'Test instructions for the salesperson.',
        'completed_at' => $isCompleted ? $scheduledAt->copy()->addHours(1) : null,
        'completion_notes' => $isCompleted ? 'Met with the client, everything looks good.' : null,
        'tenant_id' => 1,
    ]);
}

echo "Seed Successful!\n\n";
echo "--- CC Agent Login ---\n";
echo "Email: ccagent@test.com\n";
echo "Password: password123\n\n";

echo "--- Salesperson Login ---\n";
echo "Email: salesperson@test.com\n";
echo "Password: password123\n";
