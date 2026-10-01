<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * HTML typed into a rich-text editor (Quill), made safe to print with {!! !!}.
 *
 * Job descriptions, requirements, benefits and terms were printed raw - on the
 * public careers page too, where nobody is logged in. Anything a paste or an
 * old Quill 1.x bug let through (a script, an onerror handler, a javascript:
 * link) ran for every visitor. This keeps the formatting the editor produces
 * - paragraphs, bold/italic/underline, lists, headings, links, quotes - and
 * drops everything else.
 */
class SafeHtml
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null); // no writable cache folder needed
            $config->set('HTML.Allowed', 'p[class],br,b,strong,i,em,u,s,strike,sub,sup,ul[class],ol[class],li[class],h1[class],h2[class],h3[class],h4[class],h5[class],h6[class],blockquote[class],pre,code,span[class],a[href|target|rel],img[src|alt|width|height]');
            // Only Quill's own layout classes (alignment, indent) - a class cannot run code.
            $config->set('Attr.AllowedClasses', ['ql-align-center', 'ql-align-right', 'ql-align-justify', 'ql-indent-1', 'ql-indent-2', 'ql-indent-3', 'ql-indent-4', 'ql-indent-5', 'ql-indent-6', 'ql-indent-7', 'ql-indent-8']);
            // data: is for pictures the editor's Image button embeds inline; in an
            // <img> it cannot run code, and HTMLPurifier only accepts image types.
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true, 'data' => true]);
            // A picture without alt text gets an empty one - HTMLPurifier's default
            // copies the src in, which for an embedded picture is the whole data URI.
            $config->set('Attr.DefaultImageAlt', '');
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.TargetNoopener', true);
            $config->set('HTML.TargetNoreferrer', true);
            self::$purifier = new HTMLPurifier($config);
        }

        return self::$purifier->purify($html);
    }
}
