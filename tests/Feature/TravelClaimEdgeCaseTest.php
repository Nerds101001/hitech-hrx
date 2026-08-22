<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\TravelClaimController;
use App\Models\TravelClaim;
use App\Models\TravelClaimAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Adversarial pass over Travel & Expense: money, files and state transitions.
 *
 * The happy-path suite passes, so this one probes the places a suite that
 * agrees with the implementation would never look — abandoned files, state
 * jumps, negative money, and reading other people's claims.
 */
class TravelClaimEdgeCaseTest extends TestCase
{
    use DatabaseTransactions;

    private User $employee;
    private TravelClaimController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->controller = app(TravelClaimController::class);
        $this->employee = $this->makeUser();
        Auth::login($this->employee);
    }

    private function makeUser(array $o = []): User
    {
        return User::create(array_merge([
            'first_name' => 'QA', 'last_name' => 'Edge',
            'email' => 'qa' . uniqid() . '@example.test',
            'code' => 'QA' . random_int(100000, 999999),
            'password' => Hash::make('x'), 'status' => 'active',
        ], $o));
    }

    private function req(array $p = [], array $f = []): Request
    {
        $r = Request::create('/x', 'POST', $p, [], $f);
        $r->setLaravelSession(app('session.store'));
        return $r;
    }

    private function claim(array $o = []): TravelClaim
    {
        return TravelClaim::forceCreate(array_merge([
            'user_id' => $this->employee->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'draft',
            'total_amount' => 1000, 'net_payable' => 1000,
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ], $o));
    }

    private function attach(TravelClaim $c, string $category = 'food_bill_proof'): TravelClaimAttachment
    {
        $r = Request::create('/x', 'POST',
            ['category' => $category, 'upload_token' => 'tok-' . uniqid(), 'claim_id' => $c->id],
            [], ['file' => UploadedFile::fake()->image('b.jpg')]);
        $r->setLaravelSession(app('session.store'));
        $body = json_decode($this->controller->uploadAttachment($r)->getContent(), true);
        return TravelClaimAttachment::find($body['id']);
    }

    // ══ file lifecycle ═══════════════════════════════════════════════════

    /**
     * Deleting a draft removes the claim and its per-day proofs. The newer
     * multi-file bills live in their own table — if destroy() does not clear
     * them, the rows and the files on disk are both abandoned.
     */
    public function test_deleting_a_draft_also_removes_its_uploaded_bills(): void
    {
        $claim = $this->claim();
        $attachment = $this->attach($claim);
        $path = $attachment->path;

        $this->controller->destroy($claim->id);

        $this->assertNull(TravelClaim::find($claim->id), 'the draft itself should be gone');
        $this->assertNull(
            TravelClaimAttachment::find($attachment->id),
            'the bill row outlived the claim it belonged to'
        );
        $this->assertFalse(
            Storage::disk('public')->exists($path),
            'the bill file was left on disk after its claim was deleted'
        );
    }

    /** Replacing a header proof should not leave the old file behind forever. */
    public function test_replacing_an_odometer_photo_does_not_abandon_the_old_file(): void
    {
        Storage::disk('public')->put('travel_claims/old-odo.jpg', 'original');
        $claim = $this->claim(['month_start_odometer_proof' => 'travel_claims/old-odo.jpg']);

        $this->controller->update($this->req([
            'claim_month' => '2026-07', 'company' => 'Hi Tech International', 'action' => 'draft',
            'items' => [5 => ['date' => '2026-07-05', 'food_allowance' => '10']],
        ], ['month_start_odometer_proof' => UploadedFile::fake()->image('new-odo.jpg')]), $claim->id);

        $this->assertNotSame('travel_claims/old-odo.jpg', $claim->fresh()->month_start_odometer_proof,
            'the new photo did not replace the old one');
        $this->assertFalse(
            Storage::disk('public')->exists('travel_claims/old-odo.jpg'),
            'the replaced odometer photo is still occupying disk space'
        );
    }

    // ══ state transitions ════════════════════════════════════════════════

    /** Payment should not be recordable against a claim nobody approved. */
    public function test_a_draft_claim_cannot_be_marked_paid(): void
    {
        $claim = $this->claim(['status' => 'draft']);
        Auth::login($this->makeUser());

        $this->controller->pay($this->req(['payment_type' => '85', 'transaction' => 'TXN-1']), $claim->id);

        $this->assertNotSame('paid', $claim->fresh()->status, 'a draft was marked paid');
        $this->assertNull($claim->fresh()->split_85_transaction,
            'a payment reference was recorded against an unapproved claim');
    }

    /** Approval is meant to follow verification, not replace it. */
    public function test_a_submitted_claim_cannot_skip_verification_and_be_approved(): void
    {
        $claim = $this->claim(['status' => 'submitted']);
        Auth::login($this->makeUser());

        $this->controller->approve($this->req([]), $claim->id);

        $this->assertNotSame('approved', $claim->fresh()->status,
            'a claim jumped from submitted straight to approved, skipping verification');
    }

    public function test_an_already_paid_claim_cannot_be_rejected_afterwards(): void
    {
        $claim = $this->claim(['status' => 'paid']);
        Auth::login($this->makeUser());

        $this->controller->reject($this->req(['remarks' => 'changed my mind']), $claim->id);

        $this->assertSame('paid', $claim->fresh()->status, 'a paid claim was rejected after the money left');
    }

    // ══ money ════════════════════════════════════════════════════════════

    /** Negative amounts would let a claim inflate another row or go negative. */
    public function test_a_negative_expense_amount_is_not_accepted(): void
    {
        $this->controller->store($this->req([
            'claim_month' => '2026-07', 'company' => 'Hi Tech International', 'action' => 'draft',
            'items' => [
                5 => ['date' => '2026-07-05', 'food_allowance' => '500'],
                6 => ['date' => '2026-07-06', 'food_allowance' => '-400'],
            ],
        ]));

        $claim = TravelClaim::where('user_id', $this->employee->id)->latest('id')->first();
        $this->assertGreaterThanOrEqual(0, (float) $claim->total_amount,
            'a negative expense pulled the claim total down');
        $this->assertSame(0, $claim->items()->where('food_allowance', '<', 0)->count(),
            'a negative expense amount was stored');
    }

    /**
     * An advance bigger than the claim leaves the employee owing money back,
     * so a negative net payable is correct — it is a recovery, not an error.
     */
    public function test_an_advance_larger_than_the_claim_leaves_a_recoverable_balance(): void
    {
        $this->controller->store($this->req([
            'claim_month' => '2026-07', 'company' => 'Hi Tech International', 'action' => 'draft',
            'items' => [5 => ['date' => '2026-07-05', 'food_allowance' => '100']],
            'advances' => [['date' => '2026-07-01', 'mode' => 'cash', 'amount' => '5000']],
        ]));

        $claim = TravelClaim::where('user_id', $this->employee->id)->latest('id')->first();
        $this->assertEquals(-4900.0, (float) $claim->net_payable,
            'the recoverable balance is not total minus advances');
        $this->assertEquals(5000.0, (float) $claim->total_advances);
    }

    /**
     * The 10% late penalty multiplies net_payable. When the employee OWES money
     * that turns -4900 into -4410 — being late would reduce their debt to the
     * company by 490. A penalty must never pay someone.
     */
    public function test_the_late_penalty_never_shrinks_money_the_employee_owes_back(): void
    {
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-20'));
        try {
            $this->controller->store($this->req([
                'claim_month' => '2026-07', 'company' => 'Hi Tech International', 'action' => 'draft',
                'items' => [5 => ['date' => '2026-07-05', 'food_allowance' => '100']],
                'advances' => [['date' => '2026-07-01', 'mode' => 'cash', 'amount' => '5000']],
            ]));

            $claim = TravelClaim::where('user_id', $this->employee->id)->latest('id')->first();
            $this->assertEquals(-4900.0, (float) $claim->net_payable,
                'a late penalty was applied to a negative balance, reducing what the employee owes');
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    // ══ reading other people's data ══════════════════════════════════════

    public function test_an_unrelated_employee_cannot_read_someone_elses_claim_json(): void
    {
        $victim = $this->makeUser();
        $theirs = TravelClaim::forceCreate([
            'user_id' => $victim->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'submitted',
            'bank_account_name' => 'SECRET BANK', 'bank_account_no' => '999888777', 'bank_ifsc' => 'XXXX',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->controller->show($theirs->id);
    }

    public function test_an_unrelated_employee_cannot_download_someone_elses_attachments(): void
    {
        $victim = $this->makeUser();
        $theirs = TravelClaim::forceCreate([
            'user_id' => $victim->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'submitted',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->controller->downloadAttachments($theirs->id);
    }

    /** A plain employee's claim list must contain only their own claims. */
    public function test_the_claim_list_shows_an_employee_only_their_own_claims(): void
    {
        $this->claim();
        $other = $this->makeUser();
        TravelClaim::forceCreate([
            'user_id' => $other->id, 'claim_month' => '2026-07',
            'company' => 'Hi Tech International', 'status' => 'draft',
            'bank_account_name' => 'On File', 'bank_account_no' => 'On File', 'bank_ifsc' => 'On File',
        ]);

        $claims = $this->controller->index($this->req())->getData()['claims'];

        $this->assertTrue(
            $claims->every(fn ($c) => $c->user_id === $this->employee->id),
            'the list leaked another employee\'s claims'
        );
    }

    // ══ input hardening ══════════════════════════════════════════════════

    public function test_an_upload_of_a_disallowed_file_type_is_rejected(): void
    {
        $r = Request::create('/x', 'POST',
            ['category' => 'food_bill_proof', 'upload_token' => 'tok-' . uniqid()],
            [], ['file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php')]);
        $r->setLaravelSession(app('session.store'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->uploadAttachment($r);
    }

    public function test_an_unknown_bill_category_is_rejected(): void
    {
        $r = Request::create('/x', 'POST',
            ['category' => 'not_a_real_category', 'upload_token' => 'tok-' . uniqid()],
            [], ['file' => UploadedFile::fake()->image('b.jpg')]);
        $r->setLaravelSession(app('session.store'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->uploadAttachment($r);
    }

    /**
     * Multiple claims per month are INTENDED — a salesperson may file two or
     * three for the same month. This pins that down so a future "duplicate
     * guard" cannot quietly break their workflow.
     */
    public function test_a_salesperson_may_file_more_than_one_claim_for_the_same_month(): void
    {
        $this->claim(['status' => 'submitted']);

        $this->controller->store($this->req([
            'claim_month' => '2026-07', 'company' => 'Hi Tech International', 'action' => 'draft',
            'items' => [5 => ['date' => '2026-07-05', 'food_allowance' => '100']],
        ]));

        $this->assertSame(2,
            TravelClaim::where('user_id', $this->employee->id)->where('claim_month', '2026-07')->count(),
            'a second claim for the same month was blocked — salespeople need this'
        );
    }
}
