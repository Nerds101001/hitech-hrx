<?php
// Safety key check to prevent unauthorized access
if (($_GET['key'] ?? '') !== 'hitech_secure_debug_2026') {
    die('Unauthorized');
}

header('Content-Type: text/plain');

$action = $_GET['action'] ?? 'status';

if ($action === 'migrate') {
    echo "Running migrations...\n";
    try {
        require __DIR__.'/../vendor/autoload.php';
        $app = require_once __DIR__.'/../bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        
        $output = new Symfony\Component\Console\Output\BufferedOutput();
        $status = $kernel->handle(
            new Symfony\Component\Console\Input\StringInput('migrate --force'),
            $output
        );
        echo "Exit Code: " . $status . "\n";
        echo "Output:\n" . $output->fetch() . "\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} elseif ($action === 'clear') {
    echo "Clearing cache...\n";
    try {
        require __DIR__.'/../vendor/autoload.php';
        $app = require_once __DIR__.'/../bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        
        $output = new Symfony\Component\Console\Output\BufferedOutput();
        $status = $kernel->handle(
            new Symfony\Component\Console\Input\StringInput('optimize:clear'),
            $output
        );
        echo "Exit Code: " . $status . "\n";
        echo "Output:\n" . $output->fetch() . "\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} elseif ($action === 'logs') {
    $logPath = __DIR__.'/../storage/logs/laravel.log';
    if (file_exists($logPath)) {
        echo "Laravel Log (Last 100 lines):\n\n";
        $lines = file($logPath);
        $lastLines = array_slice($lines, -100);
        echo implode("", $lastLines);
    } else {
        echo "Log file not found at $logPath\n";
    }
} else {
    echo "Status check:\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    echo "Database: " . (class_exists('PDO') ? 'PDO available' : 'PDO not available') . "\n";
}
