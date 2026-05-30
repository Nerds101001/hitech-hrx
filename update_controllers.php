<?php

$dir = 'd:/live_server/app/Http/Controllers/tenant';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$updatedFiles = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        
        $originalContent = $content;
        
        // Add accounts to ['admin', 'hr', 'manager'] checks
        $content = str_replace("['admin', 'hr', 'manager']", "['admin', 'hr', 'manager', 'accounts']", $content);
        
        // Add accounts to ['admin', 'hr', 'Admin', 'HR', 'manager']
        $content = str_replace("['admin', 'hr', 'Admin', 'HR', 'manager']", "['admin', 'hr', 'Admin', 'HR', 'manager', 'accounts']", $content);
        
        // Add accounts to ['admin', 'hr', 'manager', 'Admin', 'HR', 'Manager', 'super_admin', 'Super Admin']
        $content = str_replace("['admin', 'hr', 'manager', 'Admin', 'HR', 'Manager', 'super_admin', 'Super Admin']", "['admin', 'hr', 'manager', 'accounts', 'Admin', 'HR', 'Manager', 'super_admin', 'Super Admin']", $content);

        // Add accounts to ['admin', 'hr'] if it relates to employees, but let's just do it broadly for employee view stuff since accounts need "complete access as it is given to HR and admin". Actually, let's keep it safe and only replace ['admin', 'hr', 'manager'] for now, since that explicitly unblocks them from Employee view edit actions.
        
        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
            echo "Updated: " . $file->getFilename() . "\n";
            $updatedFiles++;
        }
    }
}
echo "Total files updated: $updatedFiles\n";
