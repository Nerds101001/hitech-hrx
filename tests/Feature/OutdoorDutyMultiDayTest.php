<?php

namespace Tests\Feature;

use App\Models\OutdoorDuty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Outdoor Duty only ever took a single date (there is no from_date/to_date
 * column on outdoor_duties, unlike leave_requests). "od_to_date" is now
 * accepted alongside the existing "od_date" ("from") - one OutdoorDuty row
 * per calendar day in the span, same as a single-day request, created
 * all-or-nothing if any day in the span already has a request.
 */
class OutdoorDutyMultiDayTest extends TestCase
{
    use DatabaseTransactions;

    private User $emp;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-24 11:00:00'));
        $this->emp = User::create([
            'first_name' => 'QA', 'last_name' => 'OD MultiDay ' . uniqid(), 'email' => 'qaodmd' . uniqid() . '@example.test',
            'code' => 'QOM' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        $this->emp->assignRole(Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function apply(string $from, ?string $to = null)
    {
        return $this->actingAs($this->emp)->postJson(route('user.outdoor_duty.storeAjax'), array_filter([
            'od_date' => $from, 'od_to_date' => $to, 'od_purpose' => 'Multi-city client visits',
        ], fn ($v) => $v !== null));
    }

    public function test_a_three_day_span_creates_one_row_per_day(): void
    {
        $this->apply('2026-10-24', '2026-10-26')->assertOk()->assertJson(['status' => 'success']);

        $rows = OutdoorDuty::where('user_id', $this->emp->id)->orderBy('date')->get();
        $this->assertCount(3, $rows);
        $this->assertSame(['2026-10-24', '2026-10-25', '2026-10-26'], $rows->map(fn ($r) => $r->date->toDateString())->all());
        $this->assertTrue($rows->every(fn ($r) => $r->status === 'pending' && $r->purpose === 'Multi-city client visits'));
    }

    public function test_omitting_to_date_still_creates_exactly_one_day_as_before(): void
    {
        $this->apply('2026-10-24')->assertOk()->assertJson(['status' => 'success']);
        $this->assertSame(1, OutdoorDuty::where('user_id', $this->emp->id)->count());
    }

    public function test_a_span_with_one_conflicting_day_creates_none_of_it(): void
    {
        OutdoorDuty::create(['user_id' => $this->emp->id, 'date' => '2026-10-25', 'purpose' => 'Already booked', 'status' => 'pending', 'created_by_id' => $this->emp->id]);

        $response = $this->apply('2026-10-24', '2026-10-26');
        $response->assertStatus(422)->assertJsonFragment(['status' => 'error']);
        $this->assertStringContainsString('25 Oct 2026', $response->json('message'));

        // Still only the one pre-existing row - none of the span was created.
        $this->assertSame(1, OutdoorDuty::where('user_id', $this->emp->id)->count());
    }

    public function test_to_date_before_from_date_is_rejected(): void
    {
        $this->apply('2026-10-24', '2026-10-20')->assertStatus(422);
        $this->assertSame(0, OutdoorDuty::where('user_id', $this->emp->id)->count());
    }

    public function test_a_span_over_31_days_is_rejected(): void
    {
        $this->apply('2026-10-24', '2026-12-10')->assertStatus(422)->assertJsonFragment(['status' => 'error']);
        $this->assertSame(0, OutdoorDuty::where('user_id', $this->emp->id)->count());
    }

    public function test_the_backdate_rule_applies_across_the_whole_span(): void
    {
        // Span starts 10 days back (past the 7-day limit) even though it ends within it.
        $this->apply('2026-10-14', '2026-10-20')->assertStatus(422);
        $this->assertSame(0, OutdoorDuty::where('user_id', $this->emp->id)->count());
    }
}
