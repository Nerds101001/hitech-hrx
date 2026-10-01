<?php

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\TrainingModule;
use App\Models\TrainingPhase;
use App\Models\User;
use App\Models\UserTrainingProgress;
use App\Support\TrainingTranscript;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The training report card, on screen and as a PDF (?pdf=1):
 *
 *  - the same access rule for both: your own, or anyone's for admin/HR;
 *  - phases in their order, titles printed once ("Phase 1: Phase 1: ..."
 *    and an unordered list were what the old card showed);
 *  - "passed" means the compulsory modules are passed - an HR approval
 *    without them is shown as such, not as a pass.
 */
class TrainingReportCardTest extends TestCase
{
    use DatabaseTransactions;

    private User $me;
    private User $other;
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->me    = $this->person('employee', 'Carda');
        $this->other = $this->person('employee', 'Cardb');
        $this->hr    = $this->person('hr', 'Cardhr');
    }

    private function person(string $role, string $first): User
    {
        $u = User::create([
            'first_name' => $first . uniqid(), 'last_name' => 'QA', 'email' => 'qarc' . uniqid() . '@example.test',
            'code' => 'QR' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'),
            'status' => UserAccountStatus::ACTIVE->value,
        ]);
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $u->fresh();
    }

    public function test_the_page_and_its_pdf_follow_the_same_access_rule(): void
    {
        $html = $this->actingAs($this->me)->get('/training/report-card/' . $this->me->id)->assertOk()->getContent();
        $this->assertStringContainsString('TRAINING REPORT CARD', $html);
        $this->assertStringContainsString('?pdf=1', $html);

        $pdf = $this->actingAs($this->me)->get('/training/report-card/' . $this->me->id . '?pdf=1')->assertOk();
        $this->assertStringContainsString('attachment', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs($this->me)->get('/training/report-card/' . $this->other->id . '?pdf=1')->assertForbidden();
        $this->actingAs($this->hr)->get('/training/report-card/' . $this->other->id . '?pdf=1')->assertOk();
    }

    public function test_phases_are_in_order_and_titled_once(): void
    {
        $html = $this->actingAs($this->me)->get('/training/report-card/' . $this->me->id)->getContent();
        $titles = TrainingPhase::orderBy('order')->orderBy('id')->pluck('title');
        $last = -1;
        foreach ($titles as $title) {
            $at = strpos($html, e($title));
            $this->assertNotFalse($at, "phase {$title} is on the card");
            $this->assertGreaterThan($last, $at, 'phases appear in their order');
            $last = $at;
        }
        $this->assertDoesNotMatchRegularExpression('/Phase \d+: Phase \d+:/', $html);
    }

    public function test_passed_means_the_compulsory_modules(): void
    {
        $compulsory = TrainingModule::whereIn('phase_id', config('training.onboarding_phase_ids', [1, 2]))->get();
        $this->assertNotEmpty($compulsory, 'no compulsory modules in this environment');

        // Approved by HR with nothing passed: not a pass.
        $this->me->forceFill(['training_status' => 'completed'])->save();
        $t = TrainingTranscript::for($this->me->fresh());
        $this->assertSame('APPROVED BY HR', $t->result);
        $this->assertSame(0, $t->compDone);

        // Every compulsory module passed: a pass, with a completion date.
        foreach ($compulsory as $m) {
            UserTrainingProgress::create(['user_id' => $this->other->id, 'module_id' => $m->id, 'status' => 'completed',
                'assessment_score' => 90, 'attempts' => 1, 'started_at' => now()->subDay(), 'completed_at' => now()]);
        }
        $t = TrainingTranscript::for($this->other->fresh());
        $this->assertSame('PASSED', $t->result);
        $this->assertSame($compulsory->count(), $t->compDone);
        $this->assertEquals(90, $t->avgScore);
        $this->assertNotNull($t->completedAt);
    }
}
