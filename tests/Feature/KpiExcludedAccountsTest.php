<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\User;
use App\Services\KpiTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Office/branch accounts (GM-North, Hyderabad) sit in Sales - people report to
 * them, customers are booked to them - but are not people to score. A template
 * edit re-applies to its whole audience, so switching their KPIs off by hand
 * would not last; config/kpi.php keeps them out.
 */
class KpiExcludedAccountsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_templates_skip_an_excluded_account_but_still_reach_everyone_else(): void
    {
        Mail::fake();
        $dept = Department::create(['name' => 'ZZ Sales ' . uniqid(), 'code' => 'ZZX' . random_int(1000, 9999)]);
        $make = fn ($name) => User::create(['first_name' => $name, 'last_name' => 'QA', 'email' => 'qax' . uniqid() . '@example.test',
            'code' => 'QAX' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active', 'department_id' => $dept->id]);
        $office = $make('Office');
        $person = $make('Person');
        config(['kpi.excluded_user_ids' => [$office->id]]);

        $t = KpiTemplate::create(['title' => 'ZZ Calls', 'category' => 'KPI', 'department_id' => $dept->id, 'frequency' => 'monthly',
            'scoring_type' => 'target', 'max_marks' => 10, 'target_value' => 100, 'data_source' => 'manual', 'is_active' => true]);
        KpiTemplateService::apply($t);
        KpiTemplateService::apply($t);   // a later edit re-applies

        $this->assertTrue(KpiAssignment::where('user_id', $person->id)->where('kpi_template_id', $t->id)->exists());
        $this->assertFalse(KpiAssignment::where('user_id', $office->id)->exists());
        $this->assertSame(0, KpiTemplateService::applyAllForUser($office));
        $this->assertFalse(KpiAssignment::where('user_id', $office->id)->exists());
    }

    public function test_the_real_office_accounts_are_on_the_list(): void
    {
        $this->assertSame([568, 585, 597], config('kpi.excluded_user_ids'));
    }
}
