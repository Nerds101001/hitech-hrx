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
    public static function encryptAndStore(UploadedFile $file, string $folder, string $prefix, string $disk = 'local'): ?string
    {
        try {
            $fileName = $prefix . '_' . \Illuminate\Support\Str::random(24) . '.' . $file->getClientOriginalExtension();
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
    public static function decryptAndGet(string $path, string $disk = 'local'): ?string
    {
        try {
            // SECURITY (QA fix): reject path traversal / absolute paths so a
            // caller-supplied path cannot escape the disk root (e.g. read ../../.env
            // or another user's files). Legitimate stored paths are always relative.
            if (!self::isPathSafe($path)) {
                Log::warning('File Retrieval blocked: unsafe path rejected.', ['path' => $path]);
                return null;
            }

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
     * SECURITY (QA fix): validate a storage-relative path.
     * Rejects traversal (`..`), absolute paths, Windows drive prefixes, backslashes,
     * URL-encoded traversal and null bytes. Returns true only for a safe relative path.
     */
    public static function isPathSafe(string $path): bool
    {
        if ($path === '' || strlen($path) > 1024) {
            return false;
        }
        // Null byte
        if (strpos($path, "\0") !== false) {
            return false;
        }
        // Decode once to catch %2e%2e / %2f style encodings, then check.
        $decoded = rawurldecode($path);
        foreach ([$path, $decoded] as $candidate) {
            $normalized = str_replace('\\', '/', $candidate);
            if (str_contains($normalized, '../') || str_contains($normalized, '/..') || $normalized === '..') {
                return false;
            }
            // Absolute path (unix) or Windows drive letter (e.g. C:\)
            if (str_starts_with($normalized, '/') || preg_match('/^[a-zA-Z]:/', $normalized)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Generate a secure URL for the document.
     *
     * NOTE (QA follow-up): the `path` param is still base64 (not signed). The
     * traversal guard above prevents arbitrary file reads, but object-level
     * ownership must be enforced in AuthController::serveDocument, and this URL
     * should be migrated to URL::signedRoute() or an opaque document id.
     */
    public static function generateSecureUrl(string $path): string
    {
        return route('auth.document.serve', ['path' => base64_encode($path)]);
    }
}
