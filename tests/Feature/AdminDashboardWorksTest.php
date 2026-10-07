<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use App\Support\DashboardAnnouncements;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The admin dashboard and its announcements, as reported on 1 Oct 2026:
 *
 *  - an announcement ending today vanished at midnight (end_date compared with
 *    now()), so the feed was empty on its last day;
 *  - with nothing current, the dashboard fell back to the seeded legacy
 *    "Notice 1/2/3" rows;
 *  - "Publish" opened the old employee-lifecycle page and "Publish News" a
 *    404 (/noticeboard);
 *  - AI insights, today's events, the calendar and probation rows were not
 *    clickable, and the probation figures were capped at 25.
 */
class AdminDashboardWorksTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Cache::flush();
        $this->admin = User::role('admin')->where('status', 'active')->firstOrFail();
    }

    private function announce(string $title, $start, $end): Announcement
    {
        return Announcement::create(['created_by_id' => $this->admin->id, 'title' => $title, 'content' => 'x', 'type' => 'general',
            'priority' => 'medium', 'start_date' => $start, 'end_date' => $end, 'is_active' => true, 'audience_type' => 'global', 'published_at' => now()]);
    }

    public function test_an_announcement_runs_to_the_end_of_its_last_day(): void
    {
        $today = $this->announce('ZZ ends today', now()->subDays(7)->toDateString(), now()->toDateString());
        $gone  = $this->announce('ZZ ended yesterday', now()->subDays(7)->toDateString(), now()->subDay()->toDateString());

        $this->assertTrue(Announcement::current()->whereKey($today->id)->exists());
        $this->assertFalse(Announcement::current()->whereKey($gone->id)->exists());
        $this->actingAs($this->admin)->get('/announcements/feed')->assertOk()->assertSee('ZZ ends today')->assertDontSee('ZZ ended yesterday');
    }

    public function test_the_dashboard_never_shows_the_seeded_notices(): void
    {
        Announcement::query()->update(['is_active' => false]);
        $this->assertTrue(DashboardAnnouncements::forUser($this->admin)->isEmpty(), 'nothing current: an empty panel, not Notice 1/2/3');
    }

    public function test_dashboard_rows_are_links_and_point_at_live_pages(): void
    {
        $html = $this->actingAs($this->admin)->get('/')->assertOk()->getContent();
        $page = substr($html, strpos($html, '<div class="adc">'));

        $this->assertStringNotContainsString('/noticeboard', $page);
        $this->assertStringNotContainsString('employee-lifecycle/announcements', $page);
        preg_match_all('/<a href="([^"]*)" class="adc-rowlink"/', $page, $m);
        $this->assertNotEmpty($m[1], 'insights, events and the calendar are links');
        $this->assertNotContains('#', $m[1], 'every row has a destination');
        $this->assertStringContainsString(url('/probation/dashboard'), $page);
        $this->assertStringContainsString(route('employee.celebrations'), $page);
    }

    public function test_probation_figures_are_not_capped_at_25(): void
    {
        $q = User::where('status', \App\Enums\UserAccountStatus::ACTIVE)->whereNotNull('probation_end_date')->whereNull('probation_confirmed_at');
        $expected = (clone $q)->where('probation_end_date', '<=', now()->addDays(30)->toDateString())->count();

        $html = $this->actingAs($this->admin)->get('/')->getContent();
        $this->assertMatchesRegularExpression('/Probation Ending<\/span>\s*<span class="adc-ev-count">' . $expected . '</', $html);
    }

    public function test_an_everyone_post_shows_its_reach(): void
    {
        $this->announce('ZZ reach check', now()->toDateString(), now()->addDay()->toDateString());
        $res = $this->actingAs($this->admin)->getJson('/announcements/dashboardAjax?draw=1&start=0&length=10&searchTerm=ZZ%20reach')->assertOk();
        $row = collect($res->json('data'))->firstWhere('title', 'ZZ reach check');
        $this->assertGreaterThan(0, (int) $row['reach'], 'a post the system made still reaches everyone');
    }
}
