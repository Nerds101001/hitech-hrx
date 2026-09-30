<?php

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Mail\TrainingCompleted;
use App\Models\TrainingModule;
use App\Models\TrainingQuestion;
use App\Models\User;
use App\Models\UserTrainingProgress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * New joiners are parked in onboarding_submitted, which allows the training
 * portal and nothing else. Passing the last gating module is what ends that -
 * there is no HR approval step in between - so the release has to happen on
 * the pass itself, exactly once, and only for accounts training was actually
 * holding.
 */
class TrainingAccountReleaseTest extends TestCase
{
    use DatabaseTransactions;

    private function heldUser(): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Joiner',
            'email'      => 'qajoiner' . uniqid() . '@example.test',
            'code'       => 'QAJ' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => UserAccountStatus::ONBOARDING_SUBMITTED->value,
        ]);
        $u->forceFill(['is_training_required' => true, 'training_status' => 'not_started'])->save();
        $u->assignRole('employee');

        return $u;
    }

    /**
     * Which phase gates the account is configuration, and it differs between
     * environments - production runs phase 1 (4 modules), this machine's copy
     * has only one module there. These tests are about the release mechanism,
     * not about which phase is configured, so they pick a phase that actually
     * has enough modules to exercise "all but one done" and point the config
     * at it for the duration of the test.
     *
     * @return array{0:\Illuminate\Support\Collection,1:TrainingModule} gating modules, and the last one left undone
     */
    private function gatingModules(User $user): array
    {
        $phaseId = TrainingModule::selectRaw('phase_id, COUNT(*) c')
            ->groupBy('phase_id')->orderByDesc('c')->value('phase_id');

        $this->assertNotNull($phaseId, 'no training modules exist in this environment');
        config(['training.onboarding_phase_ids' => [$phaseId]]);

        $ids = TrainingModule::where('phase_id', $phaseId)->orderBy('id')->pluck('id');

        $this->assertGreaterThan(1, $ids->count(), 'the chosen phase needs at least two modules for these tests to mean anything');

        $last = TrainingModule::find($ids->last());

        // Everything except the last one is already passed.
        foreach ($ids->slice(0, -1) as $mid) {
            UserTrainingProgress::create([
                'user_id' => $user->id, 'module_id' => $mid,
                'status' => 'completed', 'assessment_score' => 90, 'completed_at' => now(),
            ]);
        }
        // Opened two hours ago, so the minimum reading time has passed.
        UserTrainingProgress::create([
            'user_id' => $user->id, 'module_id' => $last->id, 'status' => 'in_progress',
            'started_at' => now()->subHours(2),
        ]);

        return [$ids, $last];
    }

    /**
     * Answers that score 100% on the given module: the test is loaded first, as
     * the reader does, because only the questions actually served are scored.
     */
    private function perfectAnswers(TrainingModule $m, ?User $as = null): array
    {
        if ($as) {
            $this->actingAs($as);
        }
        $served = $this->getJson(route('training.assessment.get', $m->id))->assertOk()->json('questions');
        $answers = TrainingQuestion::whereIn('id', array_column($served, 'id'))->pluck('correct_option_index', 'id')->all();
        $this->assertNotEmpty($answers, 'the final gating module needs questions for this test');

        return $answers;
    }

    public function test_passing_the_last_gating_module_activates_the_account_and_notifies(): void
    {
        Mail::fake();
        $user = $this->heldUser();
        [$ids, $last] = $this->gatingModules($user);

        $this->actingAs($user)
            ->postJson(route('training.assessment.submit', $last->id), ['answers' => $this->perfectAnswers($last, $user)])
            ->assertOk()
            ->assertJson(['success' => true, 'passed' => true]);

        $user->refresh();
        $this->assertSame(UserAccountStatus::ACTIVE->value, $user->status instanceof \UnitEnum ? $user->status->value : $user->status);
        $this->assertFalse((bool) $user->is_training_required, 'the training gate must be lifted');
        $this->assertSame('completed', $user->training_status);

        Mail::assertSent(TrainingCompleted::class, function ($mail) {
            foreach (config('training.completion_notify') as $to) {
                if (!$mail->hasTo($to)) return false;
            }
            return true;
        });
    }

    /**
     * Only the compulsory phases count: finishing them reads 100% and activates
     * the account, whatever is left in the optional ones (CRM, Learn @ Hi Tech).
     */
    public function test_progress_counts_only_the_compulsory_phases(): void
    {
        Mail::fake();
        $user = $this->heldUser();
        [$ids, $last] = $this->gatingModules($user);

        // An optional phase with a module nobody has done.
        $optional = \App\Models\TrainingPhase::create(['title' => 'ZZ Optional ' . uniqid(), 'order' => 50]);
        TrainingModule::create(['phase_id' => $optional->id, 'title' => 'ZZ optional mod', 'content_type' => 'video',
            'estimated_time_minutes' => 1, 'order' => 1]);

        $this->actingAs($user)
            ->postJson(route('training.assessment.submit', $last->id), ['answers' => $this->perfectAnswers($last, $user)])
            ->assertOk()->assertJson(['passed' => true]);

        $user->refresh();
        $this->assertSame(100.0, (float) $user->getTrainingProgressPercentage(), 'compulsory set done = 100%, the optional module does not count');
        $this->assertSame(UserAccountStatus::ACTIVE->value, $user->status instanceof \UnitEnum ? $user->status->value : $user->status);
    }

    /** The gate must not lift while any gating module is still outstanding. */
    public function test_an_unfinished_set_leaves_the_account_held_and_sends_nothing(): void
    {
        Mail::fake();
        $user = $this->heldUser();
        [$ids, $last] = $this->gatingModules($user);

        // Undo one of the already-passed modules, so finishing $last still leaves a gap.
        UserTrainingProgress::where('user_id', $user->id)
            ->where('module_id', $ids->first())
            ->update(['status' => 'available', 'completed_at' => null]);

        $this->actingAs($user)
            ->postJson(route('training.assessment.submit', $last->id), ['answers' => $this->perfectAnswers($last, $user)])
            ->assertOk()
            ->assertJson(['passed' => true]);

        $user->refresh();
        $this->assertSame(UserAccountStatus::ONBOARDING_SUBMITTED->value, $user->status instanceof \UnitEnum ? $user->status->value : $user->status);
        $this->assertTrue((bool) $user->is_training_required, 'one module short must not open the account');
        Mail::assertNotSent(TrainingCompleted::class);
    }

    /**
     * Someone suspended who happens to finish a module must not be activated
     * by it - training was not what was holding that account shut.
     */
    public function test_a_suspended_account_is_not_activated_by_finishing_training(): void
    {
        Mail::fake();
        $user = $this->heldUser();
        $user->forceFill(['status' => UserAccountStatus::SUSPENDED->value])->save();
        [$ids, $last] = $this->gatingModules($user);

        $this->actingAs($user)
            ->postJson(route('training.assessment.submit', $last->id), ['answers' => $this->perfectAnswers($last, $user)])
            ->assertOk();

        $user->refresh();
        $this->assertSame(UserAccountStatus::SUSPENDED->value, $user->status instanceof \UnitEnum ? $user->status->value : $user->status);
        $this->assertSame('completed', $user->training_status, 'training itself is still recorded as done');
    }

    /** Re-passing a module later must not fire a second release or a second email. */
    public function test_release_and_notification_happen_only_once(): void
    {
        Mail::fake();
        $user = $this->heldUser();
        [$ids, $last] = $this->gatingModules($user);
        $this->actingAs($user)->postJson(route('training.assessment.submit', $last->id), ['answers' => $this->perfectAnswers($last, $user)])->assertOk();
        $this->actingAs($user)->postJson(route('training.assessment.submit', $last->id), ['answers' => $this->perfectAnswers($last, $user)])->assertOk();

        Mail::assertSent(TrainingCompleted::class, 1);
    }
}
