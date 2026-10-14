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
use Tests\TestCase;

/**
 * /training showed "Training Complete" for people who had not finished:
 *  - a Production manager with 0/11 compulsory modules done;
 *  - Quality and Sales managers with 4/11 (36%) done, 8 left.
 *
 * Root cause: `users.training_status` is a separate, independently-written
 * cache column that several places trusted at face value instead of deriving
 * completion from the real UserTrainingProgress rows. It was set to
 * 'completed' purely as a side effect of is_training_required being false at
 * account creation (the 0/11 case: nobody had attempted a single module) and
 * again by HR's manual approveTraining() override (the 4/11 case: a genuine
 * partial completion, finalised early). This fixes it by making
 * User::isCompulsoryTrainingComplete() -- driven only by real per-module
 * completion -- the one source of truth everywhere that reads "is this
 * person done": the portal banner, the activity dashboard's state/held
 * columns, and TrainingLock. training_status is still written (other code
 * may read it as a label), but nothing authoritative trusts it alone anymore.
 */
class TrainingFalseCompleteTest extends TestCase
{
    use DatabaseTransactions;

    private int $phaseId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Reuses whichever real phase has the most modules, same convention as
        // TrainingLockBannerTest -- this environment's actual training data.
        $this->phaseId = (int) TrainingModule::selectRaw('phase_id, COUNT(*) c')->groupBy('phase_id')->orderByDesc('c')->value('phase_id');
        $this->assertNotSame(0, $this->phaseId, 'no training modules exist in this environment');
        config(['training.onboarding_phase_ids' => [$this->phaseId]]);
    }

    private function modules()
    {
        return TrainingModule::where('phase_id', $this->phaseId)->orderBy('id')->get();
    }

    private function person(bool $trainingRequired, string $trainingStatus): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Trainee ' . uniqid(), 'email' => 'qatr' . uniqid() . '@example.test',
            'code' => 'QTR' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        $u->forceFill(['is_training_required' => $trainingRequired, 'training_status' => $trainingStatus])->save();
        $u->assignRole('employee');

        return $u->fresh();
    }

    private function pass(User $u, int $count): void
    {
        foreach ($this->modules()->take($count) as $m) {
            UserTrainingProgress::create(['user_id' => $u->id, 'module_id' => $m->id, 'status' => 'completed', 'assessment_score' => 90, 'completed_at' => now()]);
        }
    }

    public function test_zero_of_eleven_the_production_manager_case(): void
    {
        // Created with is_training_required effectively false (e.g. the request simply
        // omitted the field) -- the old code wrote training_status='completed' for this,
        // with not a single module ever attempted.
        $u = $this->person(false, 'completed');

        $this->assertEquals(0, $u->getTrainingProgressPercentage());
        $this->assertFalse($u->isCompulsoryTrainingComplete(), 'zero modules attempted must never read as complete');

        $html = $this->actingAs($u)->get('/training')->getContent();
        $this->assertStringNotContainsString('class="tp-done-banner"', $html, 'the hero done-banner markup must not render');
    }

    public function test_four_of_eleven_the_quality_and_sales_manager_case(): void
    {
        $total = $this->modules()->count();
        $this->assertGreaterThanOrEqual(5, $total, 'needs enough real modules in this environment to represent a partial completion');

        // Genuinely did 4; then HR finalised them early (approveTraining()'s real effect
        // in this app) -- training_status='completed' despite 8 remaining.
        $u = $this->person(true, 'completed');
        $this->pass($u, 4);

        $pct = $u->getTrainingProgressPercentage();
        $this->assertLessThan(100, $pct);
        $this->assertFalse($u->isCompulsoryTrainingComplete(), '4 of ' . $total . ' done must never read as complete, however the training_status flag reads');

        $html = $this->actingAs($u)->get('/training')->getContent();
        $this->assertStringNotContainsString('class="tp-done-banner"', $html, 'the hero done-banner markup must not render');
    }

    public function test_genuinely_finishing_every_module_does_read_as_complete(): void
    {
        // The fix must not create a false NEGATIVE either.
        $u = $this->person(true, 'not_started');
        $this->pass($u, $this->modules()->count());

        $this->assertEquals(100, $u->fresh()->getTrainingProgressPercentage());
        $this->assertTrue($u->fresh()->isCompulsoryTrainingComplete());

        $html = $this->actingAs($u)->get('/training')->getContent();
        $this->assertStringContainsString('class="tp-done-banner"', $html);
        $this->assertStringContainsString("Training Complete!</b>", $html);
    }

    public function test_the_lock_banner_is_not_fooled_by_the_stale_flag_either(): void
    {
        $u = $this->person(true, 'completed');
        $u->forceFill(['status' => UserAccountStatus::ONBOARDING_SUBMITTED])->save();

        $this->assertNotNull(TrainingLock::progressFor($u->fresh()), 'the flag alone must not clear a held account that has not actually passed its modules');
    }
}
