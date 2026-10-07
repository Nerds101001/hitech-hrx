<?php

namespace Tests\Feature;

use App\Models\NinetyDayGoal;
use App\Models\NinetyDayProgram;
use App\Models\NinetyDayRound;
use App\Models\User;
use App\Notifications\NinetyDay\AddCustomersReminder;
use App\Services\NinetyDayGoalService as Goals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The 2026-10-06 additions: admin reopen also covers self-started rounds, the
 * customer-count RAG light, and the 12-hour "finish adding customers" reminder.
 */
class NinetyDayReminderRagTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['ninety_day.min_customers' => 10, 'ninety_day.customers_amber_from' => 7, 'ninety_day.reminder_lead_hours' => 12]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'NDG ' . uniqid(), 'email' => 'ndg' . uniqid() . '@example.test',
            'code' => 'NDG' . random_int(100000, 999999), 'password' => Hash::make('x'), 'status' => 'active',
        ]);
    }

    private function round(?int $programId, string $windowUntil = null, int $customers = 0): NinetyDayRound
    {
        $r = NinetyDayRound::create([
            'user_id' => $this->person()->id, 'round_no' => 1, 'status' => 'active', 'program_id' => $programId,
            'starts_on' => Carbon::today()->toDateString(), 'ends_on' => Carbon::today()->addDays(90)->toDateString(),
            'customer_window_until' => $windowUntil,
        ]);
        for ($i = 0; $i < $customers; $i++) {
            NinetyDayGoal::create(['round_id' => $r->id, 'user_id' => $r->user_id, 'client_name' => 'C' . $i, 'product' => 'X', 'monthly_potential' => 1]);
        }

        return $r->fresh();
    }

    public function test_rag_thresholds(): void
    {
        $this->assertSame('red', Goals::customerRag(6)['level']);
        $this->assertSame('amber', Goals::customerRag(7)['level']);
        $this->assertSame('amber', Goals::customerRag(9)['level']);
        $this->assertSame('green', Goals::customerRag(10)['level']);
    }

    public function test_admin_reopen_also_covers_self_started_rounds(): void
    {
        $program = NinetyDayProgram::create(['name' => 'QA Prog', 'starts_on' => Carbon::today()->toDateString(), 'ends_on' => Carbon::today()->addDays(90)->toDateString(), 'created_by_id' => $this->person()->id]);
        $inProg = $this->round($program->id, Carbon::now()->subDay()->toDateTimeString());
        $self = $this->round(null, null);                 // self-started, default (closed) window
        $selfClosed = $this->round(null, null);
        $selfClosed->forceFill(['status' => 'closed'])->save();

        $until = Carbon::now()->addDays(3);
        $n = Goals::openCustomerWindow($program, $until);

        $this->assertTrue(Goals::customerWindowOpen($inProg->fresh()), 'program round reopened');
        $this->assertTrue(Goals::customerWindowOpen($self->fresh()), 'active self-started round reopened too');
        $this->assertNull($selfClosed->fresh()->customer_window_until, 'a closed self-started round is left alone');
        $this->assertGreaterThanOrEqual(2, $n);
    }

    public function test_reopen_rearms_the_reminder(): void
    {
        $program = NinetyDayProgram::create(['name' => 'QA Prog', 'starts_on' => Carbon::today()->toDateString(), 'ends_on' => Carbon::today()->addDays(90)->toDateString(), 'created_by_id' => $this->person()->id]);
        $r = $this->round($program->id, Carbon::now()->addHours(2)->toDateTimeString());
        $r->forceFill(['completion_reminder_sent_at' => Carbon::now()])->save();
        Goals::openCustomerWindow($program, Carbon::now()->addDays(2));
        $this->assertNull($r->fresh()->completion_reminder_sent_at, 'a new deadline re-arms the reminder');
    }

    public function test_reminder_fires_once_for_those_under_the_minimum_near_the_deadline(): void
    {
        Carbon::setTestNow('2031-05-10 10:00:00');
        $soon = $this->round(null, '2031-05-10 18:00:00', 5);   // 8h left, under min -> remind
        $soonMet = $this->round(null, '2031-05-10 18:00:00', 10); // met min -> no
        $farOff = $this->round(null, '2031-05-20 18:00:00', 2);   // window far away -> no

        $this->artisan('ninety-day:remind-add-customers')->assertExitCode(0);
        Notification::assertSentTo($soon->user, AddCustomersReminder::class);
        Notification::assertNotSentTo($soonMet->user, AddCustomersReminder::class);
        Notification::assertNotSentTo($farOff->user, AddCustomersReminder::class);
        $this->assertNotNull($soon->fresh()->completion_reminder_sent_at);

        // Does not fire again once stamped.
        Notification::fake();
        $this->artisan('ninety-day:remind-add-customers')->assertExitCode(0);
        Notification::assertNothingSent();
    }
}
