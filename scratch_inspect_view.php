<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';

use App\Models\SalesVisit;
use App\Models\User;
use Illuminate\Support\ViewErrorBag;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find a salesperson user and a visit
$user = User::first();
if (!$user) {
    echo "No user found!\n";
    exit(1);
}
$visit = SalesVisit::first();
if (!$visit) {
    echo "No sales visit found!\n";
    exit(1);
}

// Log in the user
auth()->login($user);

// Share errors
view()->share('errors', new ViewErrorBag());

// Simulate pageConfigs
$view = view('tenant.sales-visits.show', compact('visit'))
    ->with('pageConfigs', ['contentLayout' => 'wide']);

// Render the HTML
$html = $view->render();

// Extract the first 30 lines of HTML to see the class names on html, body, layout-wrapper, layout-page
echo "=== HTML TAG ===\n";
if (preg_match('/<html[^>]*>/i', $html, $matches)) {
    echo $matches[0] . "\n";
}
echo "\n=== BODY TAG ===\n";
if (preg_match('/<body[^>]*>/i', $html, $matches)) {
    echo $matches[0] . "\n";
}
echo "\n=== LAYOUT WRAPPER ===\n";
if (preg_match('/<div class="layout-wrapper[^"]*"[^>]*>/i', $html, $matches)) {
    echo $matches[0] . "\n";
}
echo "\n=== LAYOUT PAGE ===\n";
if (preg_match('/<div class="layout-page[^"]*"[^>]*>/i', $html, $matches)) {
    echo $matches[0] . "\n";
}

echo "\n=== CONTROLLER VIEW OUTPUT LENGTH ===\n";
echo strlen($html) . " bytes\n";
