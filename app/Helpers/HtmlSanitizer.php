<?php

namespace App\Helpers;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * SECURITY (H-2): server-side sanitizer for user-authored rich text (job posts,
 * lesson/policy content, etc.).
 *
 * strip_tags() was previously used, but it only removes disallowed *tags* — it
 * keeps every attribute on the allowed tags, so `<a href="javascript:...">` and
 * `<p onmouseover="...">` survived and then executed when the field was rendered
 * with {!! !!}. HTMLPurifier (ezyang/htmlpurifier, already a dependency) parses
 * the HTML and strips event handlers, javascript:/data: URLs and any attribute or
 * tag not on the allow-list.
 */
class HtmlSanitizer
{
    /** Formatting tags a rich-text editor legitimately produces. */
    public const DEFAULT_ALLOWED = 'b,strong,i,em,u,a[href|title|target],p,br,ul,ol,li,h1,h2,h3,h4,h5,h6,span,blockquote';

    public static function clean(?string $html, ?string $allowed = null): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', $allowed ?? self::DEFAULT_ALLOWED);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        // HTMLPurifier keeps a schema cache on disk; point it at a writable dir,
        // and fall back to no on-disk cache on a read-only filesystem rather than
        // throwing.
        $cacheDir = storage_path('app/htmlpurifier');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $config->set('Cache.SerializerPath', $cacheDir);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }

        return (new HTMLPurifier($config))->purify($html);
    }
}
