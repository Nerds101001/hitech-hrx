<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class FileSecurityHelper
{
    /**
     * Encrypt and store a file.
     */
    public static function encryptAndStore(UploadedFile $file, string $folder, string $prefix, string $disk = 'public'): ?string
    {
        try {
            $fileName = $prefix . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $folder . '/' . $fileName;

            // Read and encrypt content
            $encryptedContent = Crypt::encrypt(file_get_contents($file->getRealPath()));

            // Store to disk
            Storage::disk($disk)->put($path, $encryptedContent);

            return $path;
        } catch (\Exception $e) {
            Log::error('File Encryption Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Decrypt and retrieve file content.
     */
    public static function decryptAndGet(string $path, string $disk = 'public'): ?string
    {
        try {
            if (!Storage::disk($disk)->exists($path)) {
                return null;
            }

            $content = Storage::disk($disk)->get($path);

            try {
                // Try decryption first
                return Crypt::decrypt($content);
            } catch (\Exception $e) {
                // If decryption fails, it might be an unencrypted (old) file.
                // Log it as a warning but return the raw content for backward compatibility.
                return $content;
            }
        } catch (\Exception $e) {
            Log::error('File Retrieval Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate a secure URL for the document.
     */
    public static function generateSecureUrl(string $path): string
    {
        return route('auth.document.serve', ['path' => base64_encode($path)]);
    }
}
