<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores an image attached to a task (at creation, completion, or objection)
 * on the 'public' disk without ever exposing it through a direct/guessable
 * public URL - every read still goes through TaskController::attachment()/
 * updateAttachment(), which check the same task-ownership rules as the rest
 * of this module before streaming the file back. Unlike onboarding documents
 * (FileSecurityHelper::encryptAndStore), these aren't identity documents, so
 * plain storage is enough; the access control is what actually matters here.
 */
class TaskAttachment
{
    public const RULES = 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120';

    public static function store(UploadedFile $file, string $folder): string
    {
        $filename = Str::random(24) . '.' . $file->getClientOriginalExtension();
        $path = $folder . '/' . $filename;

        Storage::disk('public')->putFileAs($folder, $file, $filename);

        return $path;
    }
}
