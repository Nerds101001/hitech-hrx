<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\TrainingModule;
use App\Models\UserTrainingProgress;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\tenant\TrainingController;
use Illuminate\Http\Request;

echo "--- STARTING TRAINING FLOW TEST CASES ---\n\n";

DB::beginTransaction();

try {
    // 1. Setup/find mock data
    $user = User::first(); // get any user
    $module = TrainingModule::first(); // get any module
    
    if (!$user || !$module) {
        throw new \Exception("Need at least one User and one TrainingModule in the DB to run tests.");
    }
    
    echo "Using User: ID {$user->id}, Name: {$user->name}\n";
    echo "Using Module: ID {$module->id}, Title: {$module->title}, Passing score: {$module->passing_percentage}%\n\n";

    // Clean previous progress for this test
    UserTrainingProgress::where('user_id', $user->id)->where('module_id', $module->id)->delete();

    // 2. Initialize progress
    $progress = UserTrainingProgress::create([
        'user_id' => $user->id,
        'module_id' => $module->id,
        'status' => 'available',
        'attempts' => 0,
        'tenant_id' => $user->tenant_id,
    ]);
    echo "[STEP 1] Created progress with status: {$progress->status}, attempts: {$progress->attempts}\n";

    // 3. Emulate showModule (which transitions available -> in_progress)
    if ($progress->status === 'available' || $progress->status === 'failed') {
        $progress->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);
    }
    echo "[STEP 2] showModule transitions status to: {$progress->status}\n";
    if ($progress->status !== 'in_progress') {
        throw new \Exception("Step 2 failed: status should be in_progress");
    }

    // 4. Submit failed assessment (failed)
    $score = 50.0;
    $passed = $score >= ($module->passing_percentage ?? 80);
    $newAttempts = $progress->attempts + 1;
    
    if ($passed) {
        $progress->update([
            'status' => 'completed',
            'assessment_score' => $score,
            'attempts' => $newAttempts,
            'completed_at' => now()
        ]);
    } else {
        $progress->update([
            'status' => 'failed',
            'assessment_score' => $score,
            'attempts' => $newAttempts
        ]);
    }
    
    $progress->refresh();
    echo "[STEP 3] Submitted failed assessment (Score: 50%): status is: {$progress->status}, attempts: {$progress->attempts}, score: {$progress->assessment_score}%\n";
    if ($progress->status !== 'failed' || $progress->attempts !== 1) {
        throw new \Exception("Step 3 failed: status should be failed and attempts should be 1");
    }

    // 5. Emulate showModule again (re-entering reader from failed status)
    if ($progress->status === 'available' || $progress->status === 'failed') {
        $progress->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);
    }
    $progress->refresh();
    echo "[STEP 4] Re-entering failed module transitions status to: {$progress->status}\n";
    if ($progress->status !== 'in_progress') {
        throw new \Exception("Step 4 failed: status should be in_progress");
    }

    // 6. Submit passing assessment
    $score = 95.0;
    $passed = $score >= ($module->passing_percentage ?? 80);
    $newAttempts = $progress->attempts + 1;
    
    if ($passed) {
        $progress->update([
            'status' => 'completed',
            'assessment_score' => $score,
            'attempts' => $newAttempts,
            'completed_at' => now()
        ]);
    } else {
        $progress->update([
            'status' => 'failed',
            'assessment_score' => $score,
            'attempts' => $newAttempts
        ]);
    }
    
    $progress->refresh();
    echo "[STEP 5] Submitted passing assessment (Score: 95%): status is: {$progress->status}, attempts: {$progress->attempts}, score: {$progress->assessment_score}%\n";
    if ($progress->status !== 'completed' || $progress->attempts !== 2) {
        throw new \Exception("Step 5 failed: status should be completed and attempts should be 2");
    }

    echo "\n--- ALL TESTS PASSED SUCCESSFULLY! ---\n";
    
} catch (\Exception $e) {
    echo "\n[FAIL] Test encountered an error: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
    echo "Database transaction rolled back to keep it clean.\n";
}
