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
        $this->assertSame('in_progress', $p->status);
        $this->assertSame($first->toDateTimeString(), $p->started_at->toDateTimeString(), 'reading time still counts from the first opening');
    }
}
