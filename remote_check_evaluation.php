<?php

use App\Models\User;
use App\Models\ProbationEvaluation;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== PROBATION EVALUATIONS AUDIT FOR MUKUL SAREEN ===\n";

// Find Mukul Sareen
$mukul = User::where('first_name', 'like', '%Mukul%')
    ->orWhere('last_name', 'like', '%Sareen%')
    ->first();

if (!$mukul) {
    echo "Employee Mukul Sareen not found on the live database.\n";
    
    // Fallback: list all evaluations in database to see what we have
    $allEvals = ProbationEvaluation::with(['user', 'manager'])->get();
    echo "Total evaluations in database: " . $allEvals->count() . "\n";
    foreach ($allEvals as $ev) {
        echo "- ID: {$ev->id} | Employee: {$ev->user?->getFullName()} | Manager: {$ev->manager?->getFullName()} | Submitted: " . ($ev->submitted_at ? $ev->submitted_at->format('Y-m-d H:i:s') : 'No') . "\n";
    }
    exit;
}

echo "Mukul Sareen found: ID {$mukul->id} | Email: {$mukul->email} | Designation: " . ($mukul->designation?->name ?? 'None') . "\n\n";

// Case 1: Evaluations submitted BY Mukul Sareen as manager
echo "--- Evaluations submitted BY Mukul Sareen (as Manager) ---\n";
$byMukul = ProbationEvaluation::where('manager_id', $mukul->id)->with(['user', 'manager', 'reviewer'])->get();
echo "Total evaluations submitted by Mukul: " . $byMukul->count() . "\n";
foreach ($byMukul as $ev) {
    echo "Evaluation ID: {$ev->id}\n";
    echo "  - Employee Evaluated: {$ev->user?->getFullName()} (ID: {$ev->user_id}, Email: {$ev->user?->email})\n";
    echo "  - Submitted At: " . ($ev->submitted_at ? $ev->submitted_at->format('Y-m-d H:i:s') : 'N/A') . "\n";
    echo "  - Recommendation: " . strtoupper($ev->recommendation) . "\n";
    echo "  - Extension Months: " . ($ev->extension_months ?: '0') . "\n";
    echo "  - Scores:\n";
    echo "    * Job Knowledge: {$ev->job_knowledge}/5\n";
    echo "    * Quality of Work: {$ev->quality_of_work}/5\n";
    echo "    * Attendance & Punctuality: {$ev->attendance_punctuality}/5\n";
    echo "    * Initiative & Reliability: {$ev->initiative_reliability}/5\n";
    echo "    * Overall Performance: {$ev->overall_performance}/5\n";
    echo "  - Manager Remarks: \"{$ev->manager_remarks}\"\n";
    echo "  - Areas for Improvement: \"{$ev->areas_for_improvement}\"\n";
    echo "  - HR Status: {$ev->hr_status} | HR Decision: {$ev->hr_decision}\n";
    echo "  - HR Remarks: \"{$ev->hr_remarks}\"\n";
    if ($ev->reviewer) {
        echo "  - Reviewed By: {$ev->reviewer->getFullName()} on " . ($ev->reviewed_at ? $ev->reviewed_at->format('Y-m-d H:i:s') : 'N/A') . "\n";
    }
    echo "---------------------------------------------------------\n";
}

// Case 2: Evaluations of Mukul Sareen
echo "\n--- Evaluations OF Mukul Sareen (as Employee) ---\n";
$ofMukul = ProbationEvaluation::where('user_id', $mukul->id)->with(['user', 'manager', 'reviewer'])->get();
echo "Total evaluations of Mukul: " . $ofMukul->count() . "\n";
foreach ($ofMukul as $ev) {
    echo "Evaluation ID: {$ev->id}\n";
    echo "  - Evaluated By (Manager): {$ev->manager?->getFullName()} (ID: {$ev->manager_id})\n";
    echo "  - Submitted At: " . ($ev->submitted_at ? $ev->submitted_at->format('Y-m-d H:i:s') : 'N/A') . "\n";
    echo "  - Recommendation: " . strtoupper($ev->recommendation) . "\n";
    echo "  - Overall Performance: {$ev->overall_performance}/5\n";
    echo "  - Manager Remarks: \"{$ev->manager_remarks}\"\n";
    echo "  - HR Status: {$ev->hr_status} | HR Decision: {$ev->hr_decision}\n";
    echo "---------------------------------------------------------\n";
}

// Case 3: List all recent probation evaluations on the platform to be thorough
echo "\n--- All Recent Probation Evaluations on Live Platform ---\n";
$recent = ProbationEvaluation::with(['user', 'manager'])->orderBy('created_at', 'desc')->take(10)->get();
foreach ($recent as $ev) {
    echo "- ID: {$ev->id} | Employee: {$ev->user?->getFullName()} | Manager: {$ev->manager?->getFullName()} | Recommendation: " . strtoupper($ev->recommendation) . " | Submitted: " . ($ev->submitted_at ? $ev->submitted_at->format('Y-m-d H:i:s') : 'No') . "\n";
}
