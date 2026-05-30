<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Enums\UserAccountStatus;
use App\Http\Middleware\OnboardingMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

// Setup mock routes so route names can resolve
Route::get('/onboarding', function() { return 'onboarding_form'; })->name('onboarding.form');
Route::get('/user/dashboard', function() { return 'user_dashboard'; })->name('user.dashboard.index');
Route::get('/training', function() { return 'training_portal'; })->name('training.portal');

function testRoute($user, $url, $routeName) {
    // Authenticate the user
    if ($user) {
        auth()->login($user);
    } else {
        auth()->logout();
    }

    // Create a mock Request
    $request = Request::create($url, 'GET');
    
    // Set route on request so getName() works
    $mockRoute = new \Illuminate\Routing\Route('GET', $url, ['as' => $routeName]);
    $mockRoute->bind($request);
    $request->setRouteResolver(function() use ($mockRoute) {
        return $mockRoute;
    });

    $middleware = new OnboardingMiddleware();

    // Define next closure
    $next = function($req) {
        return new Response('passed', 200);
    };

    try {
        $response = $middleware->handle($request, $next);
        
        $statusStr = $user ? $user->status->value : 'Guest';
        $trainingStr = ($user && $user->is_training_required) ? ' (Training Req)' : '';
        
        if ($response->getStatusCode() === 302) {
            echo "[REDIRECT] User status: {$statusStr}{$trainingStr} | Path: {$url} -> Redirected to: " . $response->getTargetUrl() . "\n";
        } else {
            echo "[PASSED]   User status: {$statusStr}{$trainingStr} | Path: {$url} -> Access Allowed\n";
        }
    } catch (\Exception $e) {
        echo "[ERROR]    Path: {$url} | Exception: " . $e->getMessage() . "\n";
    }
}

// Create a temp user using transaction so it's not permanently persisted, or just use memory/mock User model.
// Since Laravel Models can be instantiated without saving to DB, we can just instantiate them:
$guestUser = null;

$onboardingUser = new User([
    'name' => 'Onboarding User',
    'status' => UserAccountStatus::ONBOARDING,
    'is_training_required' => false
]);

$onboardingSubmittedUser = new User([
    'name' => 'Submitted User',
    'status' => UserAccountStatus::ONBOARDING_SUBMITTED,
    'is_training_required' => false
]);

$onboardingSubmittedTrainingUser = new User([
    'name' => 'Submitted User With Training',
    'status' => UserAccountStatus::ONBOARDING_SUBMITTED,
    'is_training_required' => true
]);

$activeUser = new User([
    'name' => 'Active User',
    'status' => UserAccountStatus::ACTIVE,
    'is_training_required' => false
]);

echo "--- STARTING ONBOARDING GATE TEST CASES ---\n\n";

// 1. Guest User Tests
echo "--- 1. Guest User (Unauthenticated) ---\n";
testRoute($guestUser, '/auth/login', 'auth.login');
testRoute($guestUser, '/user/dashboard', 'user.dashboard.index');

// 2. Active User Tests
echo "\n--- 2. Active User ---\n";
testRoute($activeUser, '/user/dashboard', 'user.dashboard.index');
testRoute($activeUser, '/user/leaves', 'user.leaves.index');

// 3. Onboarding User Tests
echo "\n--- 3. User in ONBOARDING Status ---\n";
testRoute($onboardingUser, '/onboarding', 'onboarding.form');
testRoute($onboardingUser, '/onboarding/status', 'onboarding.status');
testRoute($onboardingUser, '/user/dashboard', 'user.dashboard.index');
testRoute($onboardingUser, '/user/leaves', 'user.leaves.index');

// 4. Onboarding Submitted User Tests
echo "\n--- 4. User in ONBOARDING_SUBMITTED Status (No Training Required) ---\n";
testRoute($onboardingSubmittedUser, '/user/dashboard', 'user.dashboard.index');
testRoute($onboardingSubmittedUser, '/onboarding/status', 'onboarding.status');
testRoute($onboardingSubmittedUser, '/training', 'training.portal');
testRoute($onboardingSubmittedUser, '/user/leaves', 'user.leaves.index');

// 5. Onboarding Submitted User with Training Required Tests
echo "\n--- 5. User in ONBOARDING_SUBMITTED Status (Training Required) ---\n";
testRoute($onboardingSubmittedTrainingUser, '/user/dashboard', 'user.dashboard.index');
testRoute($onboardingSubmittedTrainingUser, '/onboarding/status', 'onboarding.status');
testRoute($onboardingSubmittedTrainingUser, '/training', 'training.portal');
testRoute($onboardingSubmittedTrainingUser, '/user/leaves', 'user.leaves.index');

echo "\n--- TESTS COMPLETED ---\n";
