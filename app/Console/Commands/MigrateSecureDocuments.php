<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateSecureDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:migrate-documents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing onboarding documents to use cryptographically secure randomized filenames.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting secure document migration...');
        
        $documents = DocumentRequest::whereNotNull('generated_file')->get();
        $count = 0;
        
        foreach ($documents as $doc) {
            $oldPath = $doc->generated_file;
            
            if (!Storage::disk('public')->exists($oldPath)) {
                $this->warn("File not found for Document Request ID {$doc->id}: {$oldPath}");
                continue;
            }
            
            // Extract the filename and extension
            $pathInfo = pathinfo($oldPath);
            $directory = $pathInfo['dirname'];
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $oldFilename = $pathInfo['filename'];
            
            // Check if already randomized (we use 24 chars + time(), roughly > 30 chars usually)
            if (strlen($oldFilename) > 30) {
                continue; // Likely already secured
            }
            
            // Generate a secure random filename while keeping the original prefix
            // Assuming prefix is before the first underscore, e.g., 'aadhaar_card_1692... .jpg'
            $parts = explode('_', $oldFilename);
            $prefix = count($parts) > 1 ? $parts[0] . '_' . $parts[1] : 'doc';
            if (count($parts) > 2) {
                // Combine everything except the timestamp part as prefix
                array_pop($parts);
                $prefix = implode('_', $parts);
            }
            
            $newFilename = $prefix . '_' . Str::random(24) . $extension;
            $newPath = $directory . '/' . $newFilename;
            
            // Move the physical file
            Storage::disk('public')->move($oldPath, $newPath);
            
            // Update the database record
            $doc->update(['generated_file' => $newPath]);
            $count++;
            
            $this->info("Migrated {$oldPath} -> {$newPath}");
        }
        
        $this->info("Migration completed successfully! Migrated {$count} documents.");
        return 0;
    }
}
