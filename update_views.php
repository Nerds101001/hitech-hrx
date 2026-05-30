<?php

$dir = 'd:/live_server/resources/views/tenant';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$updatedFiles = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') { // blade.php
        $content = file_get_contents($file->getPathname());
        $originalContent = $content;
        
        // Match cases like hasRole(['admin', 'hr']) and add 'accounts'
        $replacements = [
            "['admin', 'hr', 'Admin', 'HR']" => "['admin', 'hr', 'Admin', 'HR', 'accounts']",
            "['hr', 'admin', 'Admin', 'HR', 'Manager']" => "['hr', 'admin', 'Admin', 'HR', 'Manager', 'accounts']",
            "['admin', 'Admin', 'hr', 'manager', 'super_admin']" => "['admin', 'Admin', 'hr', 'manager', 'accounts', 'super_admin']",
            "['admin', 'hr', 'manager', 'super_admin']" => "['admin', 'hr', 'manager', 'accounts', 'super_admin']",
            "hasRole('hr')" => "hasRole(['hr', 'accounts'])",
            "hasRole(['admin', 'hr'])" => "hasRole(['admin', 'hr', 'accounts'])",
            "hasRole(['admin', 'hr', 'manager'])" => "hasRole(['admin', 'hr', 'manager', 'accounts'])"
        ];
        
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        
        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
            echo "Updated: " . $file->getFilename() . "\n";
            $updatedFiles++;
        }
    }
}
echo "Total view files updated: $updatedFiles\n";
