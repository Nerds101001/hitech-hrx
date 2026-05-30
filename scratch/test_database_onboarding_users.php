<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Http\Middleware\OnboardingMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

// Setup mock routes so route names can resolve
Route::get('/onboarding', function() { return 'onboarding_form'; })->name('onboarding.form');
Route::get('/user/dashboard', function() { return 'user_dashboard'; })->name('user.dashboard.index');
Route::get('/training', function() { return 'training_portal'; })->name('training.portal');

function testRouteWithDbUser($userId, $url, $routeName) {
    $user = User::find($userId);
    if (!$user) {
        echo "[ERROR] User {$userId} not found in database.\n";
        return;
    }

    auth()->login($user);

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
        
        $statusStr = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;
        $trainingStr = $user->is_training_required ? ' (Training Req)' : '';
        
        if ($response->getStatusCode() === 302) {
            echo "[REDIRECT] User ID: {$userId} ({$statusStr}){$trainingStr} | Path: {$url} -> Redirected to: " . $response->getTargetUrl() . "\n";
        } else {
            echo "[PASSED]   User ID: {$userId} ({$statusStr}){$trainingStr} | Path: {$url} -> Access Allowed\n";
        }
    } catch (\Exception $e) {
        echo "[ERROR]    User ID: {$userId} | Path: {$url} | Exception: " . $e->getMessage() . "\n";
    }
}

echo "--- RUNNING GATE TESTS WITH DATABASE-HYDRATED ELOQUENT USERS ---\n\n";

// Test User 492 (onboarding status)
echo "--- Testing User 492 (onboarding) ---\n";
testRouteWithDbUser(492, '/onboarding', 'onboarding.form');
testRouteWithDbUser(492, '/user/dashboard', 'user.dashboard.index');
testRouteWithDbUser(492, '/user/leaves', 'user.leaves.index');

// Test User 476 (onboarding_submitted status)
echo "\n--- Testing User 476 (onboarding_submitted) ---\n";
testRouteWithDbUser(476, '/user/dashboard', 'user.dashboard.index');
testRouteWithDbUser(476, '/onboarding/status', 'onboarding.status');
testRouteWithDbUser(476, '/user/leaves', 'user.leaves.index');

// Test User 178 (let's check their status first)
$user178 = User::find(178);
if ($user178) {
    $status = $user178->status instanceof \UnitEnum ? $user178->status->value : $user178->status;
    echo "\n--- Testing User 178 ({$status}) ---\n";
    testRouteWithDbUser(178, '/user/dashboard', 'user.dashboard.index');
    testRouteWithDbUser(178, '/user/leaves', 'user.leaves.index');
}

echo "\n--- GATE TESTS COMPLETED ---\n";
