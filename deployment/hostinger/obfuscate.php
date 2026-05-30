<?php

$dir = $argv[1] ?? '.';

if (!is_dir($dir)) {
    die("Invalid directory: $dir\n");
}

function obfuscateFile($file) {
    $content = php_strip_whitespace($file);
    
    // Check if it's already obfuscated or doesn't start with <?php
    if (strpos($content, 'eval(gzinflate') !== false || substr(trim($content), 0, 5) !== '<?php') {
        return;
    }

    // Remove the opening <?php tag for deflation
    $code = preg_replace('/^<\?php\s*/i', '', $content);
    
    // Compress and encode
    $compressed = gzdeflate($code, 9);
    $encoded = base64_encode($compressed);
    
    // Create the obfuscated payload
    $obfuscated = "<?php\n/* Protected by HRX Security */\neval(gzinflate(base64_decode('$encoded')));\n";
    
    file_put_contents($file, $obfuscated);
    echo "Obfuscated: $file\n";
}

function processDirectory($path) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            // Exclude config files, language files, and views (Blade templates break if obfuscated like this)
            $filePath = $file->getPathname();
            if (
                strpos($filePath, DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR) !== false ||
                strpos($filePath, DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR) !== false ||
                strpos($filePath, DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR) !== false ||
                strpos($filePath, DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR) !== false ||
                strpos($filePath, '.blade.php') !== false
            ) {
                continue;
            }
            
            // Only obfuscate app, routes, and Modules folders
            if (
                strpos($filePath, DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR) !== false ||
                strpos($filePath, DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR) !== false ||
                strpos($filePath, DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR) !== false
            ) {
                obfuscateFile($filePath);
            }
        }
    }
}

echo "Starting Obfuscation in $dir...\n";
processDirectory($dir);
echo "Obfuscation Complete.\n";
