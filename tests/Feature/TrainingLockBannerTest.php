<?php

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserTrainingProgress;
use App\Support\TrainingLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * New joiners held until they pass their gating training see a top banner
 * telling them to finish training and assessments to unlock their account,
 * with how many modules are done - on their dashboard and in the Training
 * Portal. It replaces "Profile Under Review", which told them HR was checking
 * documents when what was actually holding them was training.
 */
class TrainingLockBannerTest extends TestCase
{
    use DatabaseTransactions;

    private const TEXT = 'Complete your training and assessments to unlock your account.';

    private int $phaseId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->phaseId = (int) TrainingModule::selectRaw('phase_id, COUNT(*) c')->groupBy('phase_id')->orderByDesc('c')->value('phase_id');
        $this->assertNotSame(0, $this->phaseId, 'no training modules exist in this environment');
        config(['training.onboarding_phase_ids' => [$this->phaseId]]);
    }

    private function person(string $status, bool $trainingRequired = true, ?string $trainingStatus = 'not_started'): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Joiner ' . uniqid(), 'email' => 'qabanner' . uniqid() . '@example.test',
            'code' => 'QTB' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => $status,
        ]);
        $u->forceFill(['is_training_required' => $trainingRequired, 'training_status' => $trainingStatus])->save();
        $u->assignRole('employee');

        return $u->fresh();
    }

    private function total(): int
    {
        return TrainingModule::where('phase_id', $this->phaseId)->count();
    }

    public function test_a_held_joiner_sees_the_banner_with_progress_on_their_report_card(): void
    {
        $u = $this->person(UserAccountStatus::ONBOARDING_SUBMITTED->value);
        $first = TrainingModule::where('phase_id', $this->phaseId)->orderBy('id')->first();
        UserTrainingProgress::create(['user_id' => $u->id, 'module_id' => $first->id, 'status' => 'completed', 'assessment_score' => 90, 'completed_at' => now()]);

        // The dashboard itself sends a held joiner to the Training Portal.
        $this->actingAs($u)->get('/user/dashboard')->assertRedirect();
        $html = $this->actingAs($u)->get('/training/report-card/' . $u->id)->getContent();
        $this->assertStringContainsString(self::TEXT, $html);
        $this->assertStringContainsString('1 of ' . $this->total() . ' done', $html);
        $this->assertStringContainsString('Continue training', $html);
        $this->assertStringNotContainsString('Profile Under Review', $html);
    }

    public function test_the_training_portal_shows_it_without_the_continue_button(): void
    {
        $html = $this->actingAs($this->person(UserAccountStatus::ONBOARDING_SUBMITTED->value))->get('/training')->getContent();
        $this->assertStringContainsString(self::TEXT, $html);
        $this->assertStringContainsString('0 of ' . $this->total() . ' done', $html);
        $this->assertStringNotContainsString('Continue training', $html);
    }

    public function test_awaiting_hr_review_without_training_keeps_the_old_banner(): void
    {
        $u = $this->person(UserAccountStatus::ONBOARDING_SUBMITTED->value, false, null);
        $this->assertNull(TrainingLock::progressFor($u));
        $html = $this->actingAs($u)->get('/user/dashboard')->getContent();
        $this->assertStringNotContainsString(self::TEXT, $html);
        $this->assertStringContainsString('Profile Under Review', $html);
    }

    public function test_no_banner_once_training_is_done_or_for_active_people(): void
    {
        // Real completion, not the training_status flag: that flag used to short-circuit
        // this check on its own, so someone marked 'completed' with ZERO modules actually
        // passed still read as "no banner" -- the exact false "Training Complete" bug this
        // was built to prevent.
        $done = $this->person(UserAccountStatus::ONBOARDING_SUBMITTED->value, true, 'completed');
        $this->assertNotNull(TrainingLock::progressFor($done), 'the flag alone must not suppress the banner for someone who has not actually passed anything');
        foreach (TrainingModule::where('phase_id', $this->phaseId)->get() as $m) {
            UserTrainingProgress::create(['user_id' => $done->id, 'module_id' => $m->id, 'status' => 'completed', 'assessment_score' => 90, 'completed_at' => now()]);
        }
        $this->assertNull(TrainingLock::progressFor($done->fresh()), 'genuinely finishing every required module does clear it');

        $active = $this->person(UserAccountStatus::ACTIVE->value, false, null);
        $this->assertNull(TrainingLock::progressFor($active));
        $this->assertStringNotContainsString(self::TEXT, $this->actingAs($active)->get('/user/dashboard')->getContent());
    }
}
