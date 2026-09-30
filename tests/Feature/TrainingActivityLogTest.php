<?php

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserTrainingProgress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The training activity log (/training/activity?view=log): every attempt and
 * how many it took to pass - admin/HR only, like the dashboard it hangs off.
 */
class TrainingActivityLogTest extends TestCase
{
    use DatabaseTransactions;

    private User $hr;
    private User $trainee;
    private TrainingModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->hr = $this->person('hr', 'Loghr');
        $this->trainee = $this->person('employee', 'Logtrainee');
        $this->module = TrainingModule::orderBy('id')->firstOrFail();

        // Two failed attempts, then a pass - through the model, so the audit
        // trail records each one as it does in production.
        $this->actingAs($this->trainee);
        $p = UserTrainingProgress::create(['user_id' => $this->trainee->id, 'module_id' => $this->module->id, 'status' => 'in_progress', 'attempts' => 0, 'started_at' => now()->subHour()]);
        $p->update(['status' => 'failed', 'assessment_score' => 40, 'attempts' => 1]);
        $p->update(['assessment_score' => 60, 'attempts' => 2]);
        $p->update(['status' => 'completed', 'assessment_score' => 95, 'attempts' => 3, 'completed_at' => now()]);
    }

    private function person(string $role, string $first): User
    {
        $u = User::create([
            'first_name' => $first . uniqid(), 'last_name' => 'QA', 'email' => 'qalog' . uniqid() . '@example.test',
            'code' => 'QL' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'),
            'status' => UserAccountStatus::ACTIVE->value,
        ]);
        $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $u->fresh();
    }

    public function test_it_is_admin_hr_only(): void
    {
        $this->actingAs($this->trainee)->get('/training/activity?view=log')->assertForbidden();
        $this->actingAs($this->trainee)->get('/training/activity?view=log&export=csv')->assertForbidden();
        $this->actingAs($this->hr)->get('/training/activity?view=log')->assertOk()->assertSee('Training Activity Log');
    }

    public function test_attempts_to_pass_are_counted(): void
    {
        $html = $this->actingAs($this->hr)->get('/training/activity?view=log&tab=attempts&q=' . urlencode($this->trainee->first_name))->assertOk()->getContent();
        $this->assertStringContainsString($this->trainee->first_name, $html);
        $this->assertStringContainsString('Passed on attempt 3', $html);
    }

    public function test_the_log_shows_each_attempt_with_its_result(): void
    {
        $res = $this->actingAs($this->hr)->get('/training/activity?view=log&tab=log&kind=attempts&export=csv&q=' . urlencode($this->trainee->first_name))->assertOk();
        $csv = $res->streamedContent();
        $this->assertMatchesRegularExpression('/,Failed,1,40\r?\n/', $csv);
        $this->assertMatchesRegularExpression('/,Failed,2,60\r?\n/', $csv, 'a retry that stays failed is still a fail');
        $this->assertMatchesRegularExpression('/,Passed,3,95\r?\n/', $csv);
    }

    public function test_every_tab_and_export_opens(): void
    {
        foreach (['attempts', 'modules', 'log'] as $tab) {
            $this->actingAs($this->hr)->get("/training/activity?view=log&tab=$tab")->assertOk();
            $this->actingAs($this->hr)->get("/training/activity?view=log&tab=$tab&export=csv")->assertOk()->assertDownload();
        }
    }
}
