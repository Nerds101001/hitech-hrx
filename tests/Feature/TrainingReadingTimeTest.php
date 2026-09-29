<?php

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\TrainingModule;
use App\Models\TrainingQuestion;
use App\Models\User;
use App\Models\UserTrainingProgress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * REGRESSION. The minimum reading time was a countdown in the browser only, and
 * reaching a PDF's last page switched it off, so the server handed out and
 * accepted a test the moment it was asked: 30-minute product catalogues were
 * passed two minutes apart on production. The server now refuses the questions
 * and the submission until the module's estimated time has passed since the
 * person first opened it.
 */
class TrainingReadingTimeTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private TrainingModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->user = User::create([
            'first_name' => 'QA', 'last_name' => 'Reader',
            'email' => 'qareader' . uniqid() . '@example.test', 'code' => 'QR' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'), 'status' => UserAccountStatus::ACTIVE->value,
        ]);
        $this->user->assignRole('employee');

        $this->module = TrainingModule::whereHas('questions')->firstOrFail();
        $this->module->forceFill(['estimated_time_minutes' => 30])->save();
    }

    private function opened(?\Carbon\Carbon $at, string $status = 'in_progress'): void
    {
        UserTrainingProgress::updateOrCreate(
            ['user_id' => $this->user->id, 'module_id' => $this->module->id],
            ['status' => $status, 'started_at' => $at]
        );
    }

    private function answers(): array
    {
        return TrainingQuestion::where('module_id', $this->module->id)->pluck('correct_option_index', 'id')->all();
    }

    public function test_the_test_is_refused_before_the_reading_time_is_up(): void
    {
        $this->opened(now()->subMinutes(2));

        $this->actingAs($this->user)->getJson(route('training.assessment.get', $this->module->id))
            ->assertStatus(403)->assertJson(['success' => false])->assertJsonPath('reading_time_left', fn ($s) => $s > 27 * 60);

        $this->actingAs($this->user)->postJson(route('training.assessment.submit', $this->module->id), ['answers' => $this->answers()])
            ->assertStatus(403);
        $this->assertNotSame('completed', UserTrainingProgress::where('user_id', $this->user->id)->value('status'), 'not passed');
    }

    public function test_a_module_never_opened_cannot_be_submitted(): void
    {
        $this->actingAs($this->user)->postJson(route('training.assessment.submit', $this->module->id), ['answers' => $this->answers()])
            ->assertStatus(403);
    }

    public function test_after_the_reading_time_the_test_opens_and_can_be_passed(): void
    {
        $this->opened(now()->subMinutes(31));

        $this->actingAs($this->user)->getJson(route('training.assessment.get', $this->module->id))
            ->assertOk()->assertJson(['success' => true]);
        $this->actingAs($this->user)->postJson(route('training.assessment.submit', $this->module->id), ['answers' => $this->answers()])
            ->assertOk()->assertJson(['success' => true, 'passed' => true]);
    }

    public function test_coming_back_after_a_failed_attempt_does_not_restart_the_reading_time(): void
    {
        $first = now()->subMinutes(45)->startOfSecond();
        $this->opened($first, 'failed');

        $this->actingAs($this->user)->get(route('training.module.show', $this->module->id));

        $p = UserTrainingProgress::where('user_id', $this->user->id)->where('module_id', $this->module->id)->first();
        $this->assertSame('failed', $p->status, 'stays failed (still retakeable) so the retry cooldown can see when it failed');
        $this->assertSame($first->toDateTimeString(), $p->started_at->toDateTimeString(), 'reading time still counts from the first opening');
    }

    // ── modules without a quiz ───────────────────────────────────────────

    /**
     * REGRESSION. CRM Dashboard required a quiz and had no questions, so it
     * could never be completed. A module with no questions is completed by
     * watching it - still only after its minimum time.
     */
    public function test_a_module_without_questions_is_completed_once_watched(): void
    {
        $video = TrainingModule::create(['phase_id' => $this->module->phase_id, 'title' => 'ZZ No-quiz video ' . uniqid(),
            'content_type' => 'video', 'content_url' => 'https://youtu.be/abcdefghijk', 'estimated_time_minutes' => 10, 'order' => 999]);
        UserTrainingProgress::create(['user_id' => $this->user->id, 'module_id' => $video->id, 'status' => 'in_progress', 'started_at' => now()->subMinutes(3)]);

        // Too early: refused like any other module.
        $this->actingAs($this->user)->postJson(route('training.assessment.submit', $video->id), ['answers' => []])->assertStatus(403);

        UserTrainingProgress::where('user_id', $this->user->id)->where('module_id', $video->id)->update(['started_at' => now()->subMinutes(11)]);

        $this->actingAs($this->user)->getJson(route('training.assessment.get', $video->id))
            ->assertOk()->assertJson(['success' => true, 'no_quiz' => true]);
        $this->actingAs($this->user)->postJson(route('training.assessment.submit', $video->id), ['answers' => []])
            ->assertOk()->assertJson(['success' => true, 'passed' => true, 'no_quiz' => true]);

        $this->assertSame('completed', UserTrainingProgress::where('user_id', $this->user->id)->where('module_id', $video->id)->value('status'));
    }

    public function test_a_module_with_questions_still_needs_its_quiz(): void
    {
        $this->opened(now()->subHour());

        $this->actingAs($this->user)->postJson(route('training.assessment.submit', $this->module->id), ['answers' => []])
            ->assertStatus(409);   // no served test: a blank submission is not a pass
        $this->assertNotSame('completed', UserTrainingProgress::where('user_id', $this->user->id)->value('status'));
    }

    // ── what is scored ───────────────────────────────────────────────────

    /** Load the test, then submit $answers built from the questions actually served. */
    private function serveAndSubmit(callable $build)
    {
        $served = $this->actingAs($this->user)->getJson(route('training.assessment.get', $this->module->id))->assertOk()->json('questions');
        $correct = TrainingQuestion::whereIn('id', array_column($served, 'id'))->pluck('correct_option_index', 'id')->all();

        return $this->actingAs($this->user)->postJson(route('training.assessment.submit', $this->module->id), ['answers' => $build($correct)]);
    }

    public function test_submitting_no_answers_is_not_a_pass(): void
    {
        $this->opened(now()->subHour());

        $this->serveAndSubmit(fn ($c) => [])->assertStatus(422);
        $this->assertNotSame('completed', UserTrainingProgress::where('user_id', $this->user->id)->value('status'));
    }

    public function test_answering_only_some_of_the_served_questions_is_refused(): void
    {
        $this->opened(now()->subHour());

        $this->serveAndSubmit(fn ($c) => array_slice($c, 0, 1, true))->assertStatus(422);
    }

    public function test_answers_for_questions_that_were_not_served_are_refused(): void
    {
        $this->opened(now()->subHour());

        $this->actingAs($this->user)->postJson(route('training.assessment.submit', $this->module->id), [
            'answers' => TrainingQuestion::where('module_id', $this->module->id)->limit(1)->pluck('correct_option_index', 'id')->all(),
        ])->assertStatus(409);
    }

    public function test_a_served_set_can_be_submitted_once_and_a_full_correct_set_passes(): void
    {
        $this->opened(now()->subHour());

        $answers = null;
        $this->serveAndSubmit(function ($c) use (&$answers) { return $answers = $c; })->assertOk()->assertJson(['passed' => true]);
        $this->actingAs($this->user)->postJson(route('training.assessment.submit', $this->module->id), ['answers' => $answers])
            ->assertStatus(409);
    }

    // ── retries ──────────────────────────────────────────────────────────

    public function test_three_failures_in_a_row_mean_a_30_minute_wait(): void
    {
        config(['training.max_failed_attempts' => 3, 'training.retry_cooldown_minutes' => 30]);
        $this->opened(now()->subHour());
        $wrong = fn ($c) => array_map(fn ($v) => (int) $v + 1, $c);

        $this->serveAndSubmit($wrong)->assertOk()->assertJson(['passed' => false]);
        $this->serveAndSubmit($wrong)->assertOk()->assertJson(['passed' => false]);
        $this->serveAndSubmit($wrong)->assertOk()->assertJson(['passed' => false]);

        $this->actingAs($this->user)->getJson(route('training.assessment.get', $this->module->id))
            ->assertStatus(429)->assertJsonPath('retry_in', fn ($s) => $s > 29 * 60);

        // 31 minutes later it opens again.
        UserTrainingProgress::where('user_id', $this->user->id)->where('module_id', $this->module->id)
            ->update(['updated_at' => now()->subMinutes(31)]);
        $this->actingAs($this->user)->getJson(route('training.assessment.get', $this->module->id))->assertOk();
    }
}
