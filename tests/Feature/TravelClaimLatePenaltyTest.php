<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\TravelClaimController;
use App\Models\TravelClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Two live defects on the July 2026 claims, both about money.
 *
 * 1. The 10% late-submission penalty was decided from now() on EVERY save, so
 *    a claim filed inside the deadline that HR sent back for a correction came
 *    back penalised — as did one merely reopened, because the background
 *    autosave runs the same code. 20 of the 24 penalised July claims were
 *    started before the deadline.
 *
 * 2. Any row on a bike or car demanded start and end meter readings, even when
 *    the day claimed no travel at all. A day spent at the office claiming only
 *    the food allowance could not be submitted and had no legitimate fix.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class TravelClaimLatePenaltyTest extends TestCase
{
    use DatabaseTransactions;

    private User $employee;
    private TravelClaimController $controller;

    /** July 2026 is due by the 7th of August. */
    private const ON_TIME = '2026-08-03 10:00:00';
    private const LATE    = '2026-08-20 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->controller = app(TravelClaimController::class);
        $this->employee = User::create([
            'first_name' => 'QA', 'last_name' => 'Penalty',
            'email' => 'qa' . uniqid() . '@example.test',
            'code' => 'QA' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'),
            'status' => 'active',
        ]);
        Auth::login($this->employee);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function req(array $payload, array $files = []): Request
    {
        $r = Request::create('/travel-claims/store', 'POST', $payload, [], $files);
        $r->setLaravelSession(app('session.store'));
        return $r;
    }

    /** A submittable month: one bike day, with the bills it needs. */
    private function submission(array $extraItems = [], string $action = 'submit'): array
    {
        return [
            'claim_month' => '2026-07', 'company' => 'Hi Tech International',
            'action' => $action,
            'items' => [
                5 => [
                    'date' => '2026-07-05', 'mode_of_travel' => 'Personal Bike',
                    'start_meter' => '1000', 'end_meter' => '1050', 'distance_km' => '50',
                ],
            ] + $extraItems,
        ];
    }

    private function files(array $extra = []): array
    {
        return array_merge([
            'month_start_odometer_proof' => UploadedFile::fake()->image('odo.jpg'),
            'conveyance_petrol_proof'    => UploadedFile::fake()->image('petrol.jpg'),
        ], $extra);
    }

    private function claim(): TravelClaim
    {
        return TravelClaim::where('user_id', $this->employee->id)->latest('id')->first();
    }

    // ══ the penalty is fixed at first submission ═════════════════════════

    /**
     * THE REPORTED BUG. Filed on 3 August, sent back, corrected on the 20th.
     * The correction must not turn an on-time claim into a late one.
     */
    public function test_a_claim_filed_on_time_is_not_penalised_when_it_is_resubmitted_late(): void
    {
        Carbon::setTestNow(self::ON_TIME);
        $this->controller->store($this->req($this->submission(), $this->files()));
        $claim = $this->claim();
        $this->assertFalse((bool) $claim->late_penalty_applied, 'penalised on the original on-time filing');
        $onTimeNet = (float) $claim->net_payable;

        // HR sends it back, the employee corrects it after the deadline.
        $claim->forceFill(['status' => 'objection'])->save();
        Carbon::setTestNow(self::LATE);
        $this->controller->store($this->req($this->submission(), $this->files()), $claim->fresh());

        $after = $claim->fresh();
        $this->assertFalse((bool) $after->late_penalty_applied, 'the resubmission was treated as a late filing');
        $this->assertEquals($onTimeNet, (float) $after->net_payable, 'the resubmission lost 10% of the payout');
    }

    /**
     * The discriminator. Without it the test above would also pass on code
     * that never penalises anything at all.
     */
    public function test_a_claim_first_filed_after_the_deadline_is_still_penalised(): void
    {
        Carbon::setTestNow(self::LATE);
        $this->controller->store($this->req($this->submission(), $this->files()));

        $claim = $this->claim();
        $this->assertTrue((bool) $claim->late_penalty_applied, 'a genuinely late claim escaped the penalty');
        $this->assertEquals(
            round((float) $claim->total_amount * 0.90, 2),
            round((float) $claim->net_payable, 2),
            'the 10% was not taken off a genuinely late claim'
        );
    }

    /** Reopening the form fires the background autosave; it must decide nothing. */
    public function test_a_background_autosave_after_the_deadline_does_not_penalise_an_on_time_claim(): void
    {
        Carbon::setTestNow(self::ON_TIME);
        $this->controller->store($this->req($this->submission(), $this->files()));
        $claim = $this->claim();

        Carbon::setTestNow(self::LATE);
        $claim->forceFill(['status' => 'objection'])->save();
        $payload = $this->submission([], 'draft');
        $payload['autosave'] = 1;
        $this->controller->store($this->req($payload), $claim->fresh());

        $this->assertFalse((bool) $claim->fresh()->late_penalty_applied, 'an autosave applied the penalty');
    }

    /** The first submission is stamped once and never moves afterwards. */
    public function test_the_first_submission_is_stamped_and_never_overwritten(): void
    {
        Carbon::setTestNow(self::ON_TIME);
        $this->controller->store($this->req($this->submission(), $this->files()));
        $claim = $this->claim();
        $this->assertNotNull($claim->first_submitted_at);
        $this->assertEquals(self::ON_TIME, $claim->first_submitted_at->format('Y-m-d H:i:s'));

        $claim->forceFill(['status' => 'objection'])->save();
        Carbon::setTestNow(self::LATE);
        $this->controller->store($this->req($this->submission(), $this->files()), $claim->fresh());

        $this->assertEquals(self::ON_TIME, $claim->fresh()->first_submitted_at->format('Y-m-d H:i:s'));
    }

    /** A draft is not a submission, so nothing is stamped until it really is one. */
    public function test_a_draft_saved_before_the_deadline_is_late_if_it_is_only_submitted_afterwards(): void
    {
        Carbon::setTestNow(self::ON_TIME);
        $this->controller->store($this->req($this->submission([], 'draft')));
        $claim = $this->claim();
        $this->assertNull($claim->first_submitted_at, 'a draft was stamped as submitted');

        Carbon::setTestNow(self::LATE);
        $this->controller->store($this->req($this->submission(), $this->files()), $claim->fresh());

        $this->assertTrue((bool) $claim->fresh()->late_penalty_applied, 'submitting late from an old draft escaped the penalty');
    }

    /** Autosave must not quietly take a returned claim out of HR's objection list. */
    public function test_an_autosave_leaves_a_returned_claim_in_the_objection_queue(): void
    {
        Carbon::setTestNow(self::ON_TIME);
        $this->controller->store($this->req($this->submission(), $this->files()));
        $claim = $this->claim();
        $claim->forceFill(['status' => 'objection'])->save();

        $payload = $this->submission([], 'draft');
        $payload['autosave'] = 1;
        $this->controller->store($this->req($payload), $claim->fresh());

        $this->assertEquals('objection', $claim->fresh()->status);
    }

    // ══ meter readings are only asked for when travel is claimed ═════════

    /**
     * THE REPORTED BUG. A day at the office: the usual mode is still selected,
     * nothing was driven, only the food allowance is claimed. It has no
     * odometer reading to give and must not be blocked for lacking one.
     */
    public function test_an_office_day_on_a_bike_row_with_no_travel_can_be_submitted(): void
    {
        Carbon::setTestNow(self::ON_TIME);
        $payload = $this->submission([
            23 => [
                'date' => '2026-07-23', 'mode_of_travel' => 'Personal Bike',
                'start_meter' => '0', 'end_meter' => '', 'distance_km' => '',
                'conveyance_amount' => '0', 'food_allowance' => '300',
            ],
        ]);

        $this->controller->store($this->req($payload, $this->files([
            'food_bill_proof' => UploadedFile::fake()->image('food.jpg'),
        ])));

        $this->assertEquals('submitted', $this->claim()->status);
    }

    /** The discriminator: a day that DOES claim travel still owes its readings. */
    public function test_a_bike_day_that_claims_travel_still_requires_its_meter_readings(): void
    {
        Carbon::setTestNow(self::ON_TIME);
        $payload = $this->submission([
            23 => [
                'date' => '2026-07-23', 'mode_of_travel' => 'Personal Bike',
                'start_meter' => '', 'end_meter' => '', 'distance_km' => '40',
                'conveyance_amount' => '160',
            ],
        ]);

        try {
            $this->controller->store($this->req($payload, $this->files()));
            $this->fail('a claimed 40 km with no meter readings was accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.23.start_meter', $e->errors());
        }
    }

    /** A month of bike rows that claim nothing needs no odometer photo either. */
    public function test_no_odometer_photo_is_demanded_when_no_travel_is_claimed(): void
    {
        Carbon::setTestNow(self::ON_TIME);
        $payload = [
            'claim_month' => '2026-07', 'company' => 'Hi Tech International',
            'action' => 'submit',
            'items' => [
                23 => [
                    'date' => '2026-07-23', 'mode_of_travel' => 'Personal Bike',
                    'start_meter' => '0', 'end_meter' => '', 'distance_km' => '',
                    'conveyance_amount' => '0', 'food_allowance' => '300',
                ],
            ],
        ];

        $this->controller->store($this->req($payload, [
            'food_bill_proof' => UploadedFile::fake()->image('food.jpg'),
        ]));

        $this->assertEquals('submitted', $this->claim()->status);
    }
}
