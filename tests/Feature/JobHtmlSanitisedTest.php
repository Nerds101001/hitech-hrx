<?php

namespace Tests\Feature;

use App\Support\SafeHtml;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Job description / requirement / benefits / terms come from a rich-text
 * editor and were printed raw - on the public careers page too, with nobody
 * logged in. A script or event handler in them ran for every visitor.
 */
class JobHtmlSanitisedTest extends TestCase
{
    use DatabaseTransactions;

    private const EVIL = '<p class="ql-align-center"><strong>Sales Manager</strong></p><ul><li>Travel</li></ul>'
        . '<img src=x onerror="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)">x</a>'
        . '<p class="evil-class" onclick="alert(4)">text</p><a href="https://example.com" target="_blank">site</a>';

    public function test_formatting_stays_and_anything_that_can_run_goes(): void
    {
        $out = SafeHtml::clean(self::EVIL);

        foreach (['<strong>Sales Manager</strong>', '<li>Travel</li>', 'class="ql-align-center"', 'href="https://example.com"'] as $kept) {
            $this->assertStringContainsString($kept, $out);
        }
        foreach (['<script', 'onerror', 'onclick', 'javascript:', '<img', 'evil-class'] as $gone) {
            $this->assertStringNotContainsString($gone, $out);
        }
        $this->assertSame('', SafeHtml::clean(null));
    }

    public function test_the_public_careers_page_does_not_print_a_planted_script(): void
    {
        DB::table('jobs')->insert([
            'title' => 'QA Careers ' . uniqid(), 'code' => 'QA' . uniqid(), 'created_by' => 1, 'status' => 'active',
            'description' => self::EVIL, 'requirement' => self::EVIL, 'benefits' => self::EVIL,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->get(route('career'))->assertOk()->getContent();

        $this->assertStringContainsString('<strong>Sales Manager</strong>', $html);
        $this->assertStringNotContainsString('onerror="alert(1)"', $html);
        $this->assertStringNotContainsString('<script>alert(2)</script>', $html);
    }
}
