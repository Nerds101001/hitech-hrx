<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\TravelClaimController;
use App\Models\TravelClaim;
use App\Models\TravelClaimAttachment;
use App\Models\TravelClaimItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Travel & Expense — employee claim flow, attachments and the admin dashboard.
 *
 * This module handles money that gets paid to real people, and it has already
 * produced three live incidents: bus/train fares silently stored as zero, a
 * blank meter reading killing the whole save, and a failed edit destroying
 * every row of a draft. The tests below are written to FIND that class of
 * defect, not to confirm the happy path.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class TravelClaimModuleTest extends TestCase
{
    use DatabaseTransactions;

    private User $employee;
    private TravelClaimController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();               // claim lifecycle mails real people
        Storage::fake('public');    // keep uploads out of real storage
        $this->controller = app(TravelClaimController::class);
        $this->employee   = $this->makeUser();
        Auth::login($this->employee);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'QA', 'last_name' => 'Claimant',
            'email'      => 'qa' . uniqid() . '@example.test',
            'code'       => 'QA' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ], $overrides));
    }

    /** A POST as the claim form submits it. */
    private function req(array $payload, array $files = []): Request
    {
        $r = Request::create('/travel-claims/store', 'POST', $payload, [], $files);
        $r->setLaravelSession(app('session.store'));
        return $r;
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-07-05', 'mode_of_travel' => 'Personal Bike',
            'start_meter' => '1000', 'end_meter' => '1050', 'distance_km' => '50',
        ], $overrides);
    }

    private function payload(array $items, string $action = 'draft', array $extra = []): array
    {
        return array_merge([
            'claim_month' => '2026-07', 'company' => 'Hi Tech International',
            'action' => $action, 'items' => $items,
        ], $extra);
    }

    private function latestClaim(): ?TravelClaim
    {
        return TravelClaim::where('user_id', $this->employee->id)->latest('id')->first();
    }

    // ══ conveyance maths ═════════════════════════════════════════════════

    public function test_personal_bike_is_paid_at_four_rupees_per_kilometre(): void
    {
        $this->controller->store($this->req($this->payload([5 => $this->row()])));

        $this->assertEquals(200.0, $this->latestClaim()->items->first()->conveyance_amount);
    }

    public function test_personal_car_is_paid_at_nine_fifty_per_kilometre(): void
    {
        $this->controller->store($this->req($this->payload([
            5 => $this->row(['mode_of_travel' => 'Personal Car']),
        ])));

        $this->assertEquals(475.0, $this->latestClaim()->items->first()->conveyance_amount);
    }

    /**
     * REGRESSION — live incident. Anything that is not a personal vehicle fell
     * through to distance * rate, and for a bus that is 0 * 0. Employees typed
     * a real fare, the screen showed it, the database stored zero.
     */
    public function test_bus_train_and_flight_fares_are_not_silently_zeroed(): void
    {
        foreach (['Bus' => 1200, 'Train' => 800, 'Flight' => 4500, 'Cab / Taxi' => 380] as $mode => $fare) {
            $this->controller->store($this->req($this->payload([
                5 => ['date' => '2026-07-05', 'mode_of_travel' => $mode, 'conveyance_amount' => (string) $fare],
            ])));
            $this->assertEquals(
                (float) $fare,
                $this->latestClaim()->items->first()->conveyance_amount,
                "{$mode} fare was not preserved"
            );
        }
    }

    public function test_an_official_vehicle_uses_the_typed_amount_not_a_rate(): void
    {
        $this->controller->store($this->req($this->payload([
            5 => $this->row(['mode_of_travel' => 'Official Car', 'conveyance_amount' => '640']),
        ])));

        $this->assertEquals(640.0, $this->latestClaim()->items->first()->conveyance_amount);
    }

    public function test_outstation_forces_the_food_allowance_to_three_hundred(): void
    {
        $this->controller->store($this->req($this->payload([
            5 => $this->row(['is_outstation' => '1', 'food_allowance' => '75']),
        ])));

        $this->assertEquals(300.0, $this->latestClaim()->items->first()->food_allowance);
    }

    // ══ draft integrity ══════════════════════════════════════════════════

    /**
     * REGRESSION — live incident. A blank meter box posts '' into a decimal
     * column; MySQL rejected the INSERT and the entire save died.
     */
    public function test_a_vehicle_row_with_blank_meters_still_saves_as_draft(): void
    {
        $this->controller->store($this->req($this->payload([
            5 => $this->row(['start_meter' => '', 'end_meter' => '', 'distance_km' => '']),
        ])));

        $claim = $this->latestClaim();
        $this->assertNotNull($claim, 'the draft was not saved at all');
        $this->assertSame(1, $claim->items->count());
        $this->assertNull($claim->items->first()->start_meter);
    }

    /**
     * REGRESSION — live incident. update() deleted the children before store()
     * opened its transaction, so a rejected submit wiped the draft for good.
     */
    public function test_a_rejected_submit_does_not_destroy_the_saved_draft(): void
    {
        $this->controller->store($this->req($this->payload([5 => $this->row(), 6 => $this->row(['date' => '2026-07-06'])])));
        $claim = $this->latestClaim();
        $before = $claim->items()->count();

        try {
            // submit with no odometer proof -> must be refused
            $this->controller->update($this->req($this->payload([5 => $this->row()], 'submit')), $claim->id);
        } catch (ValidationException $e) {
            // expected
        }

        $this->assertSame($before, $claim->fresh()->items()->count(), 'the draft lost rows on a failed submit');
    }

    public function test_saving_a_draft_twice_does_not_duplicate_its_rows(): void
    {
        $this->controller->store($this->req($this->payload([5 => $this->row()])));
        $claim = $this->latestClaim();

        $this->controller->update($this->req($this->payload([5 => $this->row()])), $claim->id);

        $this->assertSame(1, $claim->fresh()->items()->count());
    }

    /** A row holding only a customer name is data the user typed. */
    public function test_a_row_with_only_a_customer_name_is_not_silently_dropped(): void
    {
        $this->controller->store($this->req($this->payload([
            5 => ['date' => '2026-07-05', 'party_visited' => 'Acme Ltd'],
        ])));

        $this->assertSame(1, $this->latestClaim()->items->count(),
            'a row with a customer name was discarded without warning');
    }

    public function test_the_claim_total_is_the_sum_of_its_rows(): void
    {
        $this->controller->store($this->req($this->payload([
            5 => $this->row(),                                        // 200 conveyance
            6 => ['date' => '2026-07-06', 'food_allowance' => '150', 'toll_amount' => '60'],
        ])));

        $this->assertEquals(410.0, $this->latestClaim()->total_amount);
    }

    // ══ odometer rules ═══════════════════════════════════════════════════

    public function test_one_odometer_photo_is_enough_to_submit(): void
    {
        $claim = TravelClaim::forceCreate([
            'user_id' => $this->employee->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'draft',
            'month_start_odometer_proof' => 'p/combined.jpg',
            'conveyance_petrol_proof' => 'p/conv.jpg',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $this->controller->update($this->req($this->payload([5 => $this->row()], 'submit')), $claim->id);

        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_a_bike_claim_with_no_odometer_photo_is_refused(): void
    {
        $this->controller->store($this->req($this->payload([5 => $this->row()])));
        $claim = $this->latestClaim();

        $this->expectException(ValidationException::class);
        $this->controller->update($this->req($this->payload([5 => $this->row()], 'submit')), $claim->id);
    }

    public function test_a_claim_without_vehicle_travel_needs_no_odometer_photo(): void
    {
        $claim = TravelClaim::forceCreate([
            'user_id' => $this->employee->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'draft',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $this->controller->update($this->req($this->payload([
            5 => ['date' => '2026-07-05', 'mode_of_travel' => 'Train', 'remarks' => 'ticket by office'],
        ], 'submit')), $claim->id);

        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_a_bike_row_missing_meter_readings_is_refused_on_submit(): void
    {
        $claim = TravelClaim::forceCreate([
            'user_id' => $this->employee->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'draft',
            'month_start_odometer_proof' => 'p/a.jpg', 'month_end_odometer_proof' => 'p/b.jpg',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $this->expectException(ValidationException::class);
        $this->controller->update($this->req($this->payload([
            5 => $this->row(['start_meter' => '', 'end_meter' => '']),
        ], 'submit')), $claim->id);
    }

    public function test_an_end_meter_lower_than_the_start_is_refused(): void
    {
        $claim = TravelClaim::forceCreate([
            'user_id' => $this->employee->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'draft',
            'month_start_odometer_proof' => 'p/a.jpg',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $this->expectException(ValidationException::class);
        $this->controller->update($this->req($this->payload([
            5 => $this->row(['start_meter' => '900', 'end_meter' => '500']),
        ], 'submit')), $claim->id);
    }

    // ══ bill attachments ═════════════════════════════════════════════════

    private function upload(string $category, string $token, ?int $claimId = null)
    {
        $r = Request::create('/travel-claims/attachments', 'POST',
            array_filter(['category' => $category, 'upload_token' => $token, 'claim_id' => $claimId]),
            [], ['file' => UploadedFile::fake()->image('bill.jpg')]);
        $r->setLaravelSession(app('session.store'));
        return $this->controller->uploadAttachment($r);
    }

    public function test_a_bill_uploaded_before_the_claim_exists_is_attached_when_it_is_saved(): void
    {
        $token = 'tok-' . uniqid();
        $this->upload('food_bill_proof', $token);

        $this->controller->store($this->req($this->payload(
            [5 => ['date' => '2026-07-05', 'food_allowance' => '120']],
            'draft', ['upload_token' => $token]
        )));

        $this->assertSame(1, TravelClaimAttachment::where('travel_claim_id', $this->latestClaim()->id)->count());
    }

    public function test_a_category_with_a_claimed_amount_but_no_bill_blocks_submission(): void
    {
        $claim = TravelClaim::forceCreate([
            'user_id' => $this->employee->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'draft',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $this->expectException(ValidationException::class);
        $this->controller->update($this->req($this->payload([
            5 => ['date' => '2026-07-05', 'mode_of_travel' => 'Train', 'toll_amount' => '250'],
        ], 'submit')), $claim->id);
    }

    public function test_uploads_are_capped_per_category(): void
    {
        $token = 'tok-' . uniqid();
        for ($i = 0; $i < TravelClaimAttachment::MAX_PER_CATEGORY; $i++) {
            $this->upload('toll_parking_proof', $token);
        }

        $response = $this->upload('toll_parking_proof', $token);

        $this->assertSame(422, $response->getStatusCode(), 'the per-category cap was not enforced');
    }

    public function test_a_bill_cannot_be_added_to_someone_elses_claim(): void
    {
        $victim = $this->makeUser();
        $theirClaim = TravelClaim::forceCreate([
            'user_id' => $victim->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'draft',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $response = $this->upload('food_bill_proof', 'tok-' . uniqid(), $theirClaim->id);

        $this->assertSame(403, $response->getStatusCode(), 'a bill was attached to another employee\'s claim');
    }

    public function test_a_bill_cannot_be_added_to_an_already_submitted_claim(): void
    {
        $claim = TravelClaim::forceCreate([
            'user_id' => $this->employee->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'submitted',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $response = $this->upload('food_bill_proof', 'tok-' . uniqid(), $claim->id);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_deleting_a_bill_removes_the_file_from_disk(): void
    {
        $token = 'tok-' . uniqid();
        $body = json_decode($this->upload('food_bill_proof', $token)->getContent(), true);
        $path = TravelClaimAttachment::find($body['id'])->path;
        $this->assertTrue(Storage::disk('public')->exists($path));

        $this->controller->destroyAttachment($body['id']);

        $this->assertFalse(Storage::disk('public')->exists($path), 'the file was orphaned on disk');
        $this->assertNull(TravelClaimAttachment::find($body['id']));
    }

    public function test_one_employee_cannot_delete_another_employees_bill(): void
    {
        $token = 'tok-' . uniqid();
        $body = json_decode($this->upload('food_bill_proof', $token)->getContent(), true);

        Auth::login($this->makeUser());

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->controller->destroyAttachment($body['id']);
    }

    // ══ editing permissions ══════════════════════════════════════════════

    public function test_a_submitted_claim_can_no_longer_be_edited(): void
    {
        $claim = TravelClaim::forceCreate([
            'user_id' => $this->employee->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'submitted',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $response = $this->controller->update($this->req($this->payload([5 => $this->row()])), $claim->id);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_one_employee_cannot_edit_another_employees_claim(): void
    {
        $victim = $this->makeUser();
        $theirClaim = TravelClaim::forceCreate([
            'user_id' => $victim->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'draft',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->controller->edit($theirClaim->id);
    }

    public function test_only_a_draft_can_be_deleted(): void
    {
        $claim = TravelClaim::forceCreate([
            'user_id' => $this->employee->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'submitted',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $this->controller->destroy($claim->id);

        $this->assertNotNull(TravelClaim::find($claim->id), 'a submitted claim was deleted');
    }

    // ══ approval workflow ════════════════════════════════════════════════

    private function claimAwaitingAction(string $status = 'submitted', float $net = 1000): TravelClaim
    {
        return TravelClaim::forceCreate([
            'user_id' => $this->employee->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => $status,
            'total_amount' => $net, 'net_payable' => $net,
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);
    }

    public function test_an_employee_cannot_verify_their_own_claim(): void
    {
        $claim = $this->claimAwaitingAction();

        $this->controller->verify($this->req([]), $claim->id);

        $this->assertSame('submitted', $claim->fresh()->status, 'a claim was self-verified');
    }

    public function test_an_employee_cannot_approve_their_own_claim(): void
    {
        $claim = $this->claimAwaitingAction('verified');

        $this->controller->approve($this->req([]), $claim->id);

        $this->assertSame('verified', $claim->fresh()->status, 'a claim was self-approved');
    }

    public function test_an_employee_cannot_reject_their_own_claim(): void
    {
        $claim = $this->claimAwaitingAction();

        $this->controller->reject($this->req(['remarks' => 'no']), $claim->id);

        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_approval_splits_the_payment_eighty_five_fifteen_without_losing_a_paisa(): void
    {
        $claim = $this->claimAwaitingAction('verified', 1000.05);
        Auth::login($this->makeUser());   // a different person approves

        $this->controller->approve($this->req([]), $claim->id);

        $fresh = $claim->fresh();
        $this->assertEqualsWithDelta(
            (float) $fresh->net_payable,
            (float) $fresh->split_85_amount + (float) $fresh->split_15_amount,
            0.001,
            'the two split amounts do not add back up to the net payable'
        );
    }

    public function test_verifying_clears_row_flags_raised_by_an_earlier_objection(): void
    {
        $claim = $this->claimAwaitingAction();
        $item = TravelClaimItem::forceCreate([
            'travel_claim_id' => $claim->id, 'date' => '2026-07-05',
            'is_flagged' => true, 'flag_reason' => 'check this',
        ]);
        Auth::login($this->makeUser());

        $this->controller->verify($this->req(['remarks' => 'ok']), $claim->id);

        $this->assertFalse((bool) $item->fresh()->is_flagged);
    }

    public function test_an_objection_flags_only_the_rows_the_verifier_ticked(): void
    {
        $claim = $this->claimAwaitingAction();
        $flagged = TravelClaimItem::forceCreate(['travel_claim_id' => $claim->id, 'date' => '2026-07-05']);
        $clean   = TravelClaimItem::forceCreate(['travel_claim_id' => $claim->id, 'date' => '2026-07-06']);
        Auth::login($this->makeUser());

        $this->controller->objection(
            $this->req(['remarks' => 'fix row 1', 'flagged_items' => (string) $flagged->id]),
            $claim->id
        );

        $this->assertTrue((bool) $flagged->fresh()->is_flagged);
        $this->assertFalse((bool) $clean->fresh()->is_flagged);
        $this->assertSame('objection', $claim->fresh()->status);
    }

    // ══ admin dashboard ══════════════════════════════════════════════════

    private function adminView(array $query = [])
    {
        $r = Request::create('/travel-claims/admin/dashboard', 'GET', $query);
        $r->setLaravelSession(app('session.store'));
        return $this->controller->adminDashboard($r)->getData();
    }

    public function test_the_dashboard_stat_cards_agree_with_the_table_beneath_them(): void
    {
        $this->claimAwaitingAction('submitted', 500);
        $this->claimAwaitingAction('approved', 700);

        $data = $this->adminView(['status' => 'submitted']);

        $this->assertSame(
            $data['claims']->count(),
            $data['stats']['total'],
            'the stat cards and the table are counting different sets'
        );
    }

    public function test_the_dashboard_payout_counts_only_approved_and_paid(): void
    {
        $data = $this->adminView(['status' => 'rejected']);

        $this->assertEquals(0.0, $data['stats']['total_payout'],
            'rejected claims contributed to the payout figure');
    }

    public function test_the_dashboard_month_filter_narrows_the_table(): void
    {
        $this->claimAwaitingAction('submitted', 100);

        $all      = $this->adminView();
        $filtered = $this->adminView(['claim_month' => '1999-01']);

        $this->assertGreaterThan($filtered['claims']->count(), $all['claims']->count());
        $this->assertSame(0, $filtered['claims']->count());
    }

    public function test_the_monthly_chart_labels_and_values_line_up(): void
    {
        $data = $this->adminView();

        $this->assertSame(
            count($data['monthlyChart']['labels']),
            count($data['monthlyChart']['data']),
            'the chart has a different number of labels and values'
        );
    }

    /** Month keys are 'Y-m'; day-31 overflow used to break Carbon parsing. */
    public function test_the_dashboard_survives_a_month_with_fewer_than_thirty_one_days(): void
    {
        TravelClaim::forceCreate([
            'user_id' => $this->employee->id, 'claim_month' => '2026-02',
            'company' => 'Hi Tech International', 'status' => 'submitted',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $data = $this->adminView();

        $this->assertIsArray($data['monthlyChart']['labels']);
    }

    // ══ late penalty ═════════════════════════════════════════════════════

    /**
     * The penalty is recomputed from now() on EVERY save. Re-saving an old
     * draft after the 7th therefore applies a 10% cut to a claim that was
     * submitted on time — and re-saving repeatedly must not compound it.
     */
    public function test_the_late_penalty_is_never_applied_twice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20'));   // long past the 7 Aug deadline
        try {
            $this->controller->store($this->req($this->payload([
                5 => ['date' => '2026-07-05', 'food_allowance' => '1000'],
            ])));
            $claim = $this->latestClaim();
            $firstNet = (float) $claim->net_payable;

            $this->controller->update($this->req($this->payload([
                5 => ['date' => '2026-07-05', 'food_allowance' => '1000'],
            ])), $claim->id);

            $this->assertEqualsWithDelta($firstNet, (float) $claim->fresh()->net_payable, 0.01,
                're-saving the same draft changed the net payable — the penalty compounded');
        } finally {
            Carbon::setTestNow();
        }
    }
}
