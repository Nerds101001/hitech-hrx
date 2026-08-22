<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\SalesDeleteRequestController;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineDeleteRequest as DeleteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Customer deletion requests — the review screen, its filters, and the bulk
 * approve/reject added for it.
 *
 * Approving is the point at which a customer and every monthly figure attached
 * to it are actually removed. Bulk approve does that to a whole selection at
 * once, so the tests below care most about two things: that it only ever
 * touches PENDING rows the reviewer selected, and that one failure cannot take
 * the others down with it.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class SalesDeleteRequestTest extends TestCase
{
    use DatabaseTransactions;

    private SalesDeleteRequestController $controller;
    private User $admin;
    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $this->controller = app(SalesDeleteRequestController::class);
        $this->admin      = $this->makeUser('admin');
        $this->requester  = $this->makeUser();
        Auth::login($this->admin);
    }

    private function makeUser(?string $role = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Reviewer',
            'email'      => 'dr' . uniqid() . '@example.test',
            'code'       => 'DR' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    private function pipeline(array $overrides = []): SalesPipeline
    {
        return SalesPipeline::forceCreate(array_merge([
            'party_name' => 'QA CUSTOMER ' . uniqid(),
            'is_locked'  => 1,
        ], $overrides));
    }

    private function pendingRequest(array $overrides = []): DeleteRequest
    {
        $p = $overrides['pipeline'] ?? $this->pipeline();
        unset($overrides['pipeline']);

        return DeleteRequest::create(array_merge([
            'sales_pipeline_id' => $p->id,
            'party_name'        => $p->party_name,
            'book'              => DeleteRequest::BOOK_REPORT,
            'requested_by'      => $this->requester->id,
            'reason'            => 'duplicate',
            'status'            => DeleteRequest::STATUS_PENDING,
        ], $overrides));
    }

    private function bulk(array $ids, string $action, ?string $note = null)
    {
        $r = Request::create('/sales-visits/delete-requests/bulk', 'POST', array_filter([
            'bulk_action' => $action, 'ids' => $ids, 'decision_note' => $note,
        ]));
        $r->setLaravelSession(app('session.store'));
        app()->instance('request', $r);
        return $this->controller->bulk($r);
    }

    private function index(array $query = [])
    {
        $r = Request::create('/sales-visits/delete-requests', 'GET', $query);
        $r->setLaravelSession(app('session.store'));
        app()->instance('request', $r);
        return $this->controller->index($r);
    }

    // ══ bulk approve — this deletes ══════════════════════════════════════

    public function test_bulk_approve_deletes_every_selected_customer(): void
    {
        $a = $this->pendingRequest();
        $b = $this->pendingRequest();

        $this->bulk([$a->id, $b->id], 'approve');

        foreach ([$a, $b] as $dr) {
            $this->assertSame(DeleteRequest::STATUS_APPROVED, $dr->fresh()->status);
            $this->assertNull(SalesPipeline::find($dr->sales_pipeline_id), 'the customer should be gone');
            $this->assertNotNull(SalesPipeline::withTrashed()->find($dr->sales_pipeline_id),
                'soft-deleted, so the audit trail survives');
            $this->assertSame($this->admin->id, $dr->fresh()->actioned_by);
            $this->assertNotNull($dr->fresh()->actioned_at);
        }
    }

    /** Nothing outside the selection may be touched. */
    public function test_bulk_approve_leaves_unselected_requests_alone(): void
    {
        $selected   = $this->pendingRequest();
        $unselected = $this->pendingRequest();

        $this->bulk([$selected->id], 'approve');

        $this->assertSame(DeleteRequest::STATUS_PENDING, $unselected->fresh()->status);
        $this->assertNotNull(SalesPipeline::find($unselected->sales_pipeline_id),
            'an unselected customer must still exist');
    }

    /** An already-decided request must not be re-run and re-deleted. */
    public function test_bulk_approve_skips_requests_that_were_already_decided(): void
    {
        $done = $this->pendingRequest(['status' => DeleteRequest::STATUS_REJECTED]);
        $open = $this->pendingRequest();

        $this->bulk([$done->id, $open->id], 'approve');

        $this->assertSame(DeleteRequest::STATUS_REJECTED, $done->fresh()->status,
            'a rejected request must not be flipped to approved');
        $this->assertNotNull(SalesPipeline::find($done->sales_pipeline_id),
            'its customer must still exist');
        $this->assertSame(DeleteRequest::STATUS_APPROVED, $open->fresh()->status);
    }

    /**
     * A request whose customer row has already vanished must still close, and
     * must not abort the rest of the batch.
     */
    public function test_a_missing_customer_does_not_stop_the_rest_of_the_batch(): void
    {
        $orphan = $this->pendingRequest();
        SalesPipeline::withTrashed()->where('id', $orphan->sales_pipeline_id)->forceDelete();
        $healthy = $this->pendingRequest();

        $this->bulk([$orphan->id, $healthy->id], 'approve');

        $this->assertSame(DeleteRequest::STATUS_APPROVED, $orphan->fresh()->status);
        $this->assertSame(DeleteRequest::STATUS_APPROVED, $healthy->fresh()->status);
        $this->assertNull(SalesPipeline::find($healthy->sales_pipeline_id));
    }

    // ══ bulk reject ══════════════════════════════════════════════════════

    public function test_bulk_reject_keeps_every_customer_and_records_the_note(): void
    {
        $a = $this->pendingRequest();
        $b = $this->pendingRequest();

        $this->bulk([$a->id, $b->id], 'reject', 'different unit, not a duplicate');

        foreach ([$a, $b] as $dr) {
            $this->assertSame(DeleteRequest::STATUS_REJECTED, $dr->fresh()->status);
            $this->assertSame('different unit, not a duplicate', $dr->fresh()->decision_note);
            $this->assertNotNull(SalesPipeline::find($dr->sales_pipeline_id),
                'rejecting must never delete the customer');
        }
    }

    public function test_an_empty_selection_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->bulk([], 'approve');
    }

    public function test_an_unknown_bulk_action_is_refused(): void
    {
        $dr = $this->pendingRequest();

        try {
            $this->bulk([$dr->id], 'obliterate');
            $this->fail('an unknown action was accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('bulk_action', $e->errors());
        }

        $this->assertSame(DeleteRequest::STATUS_PENDING, $dr->fresh()->status);
    }

    // ══ authorisation ════════════════════════════════════════════════════

    public function test_a_non_approver_cannot_bulk_delete(): void
    {
        $dr = $this->pendingRequest();
        Auth::login($this->requester);           // raised it, but cannot decide it

        try {
            $this->bulk([$dr->id], 'approve');
            $this->fail('a non-approver was allowed to bulk delete');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(DeleteRequest::STATUS_PENDING, $dr->fresh()->status);
        $this->assertNotNull(SalesPipeline::find($dr->sales_pipeline_id));
    }

    public function test_a_non_approver_cannot_open_the_review_screen(): void
    {
        Auth::login($this->requester);

        $this->expectException(HttpException::class);
        $this->index();
    }

    // ══ filters and sorting ══════════════════════════════════════════════

    public function test_the_reason_filter_narrows_the_list(): void
    {
        $this->pendingRequest(['reason' => 'duplicate']);
        $this->pendingRequest(['reason' => 'closed']);

        $dup = $this->index(['tab' => 'pending', 'reason' => 'duplicate'])->getData()['requests'];

        $this->assertGreaterThan(0, $dup->total());
        foreach ($dup as $row) {
            $this->assertSame('duplicate', $row->reason);
        }
    }

    public function test_the_requested_by_filter_narrows_the_list(): void
    {
        $mine   = $this->pendingRequest();
        $theirs = $this->pendingRequest(['requested_by' => $this->makeUser()->id]);

        $ids = $this->index(['tab' => 'pending', 'requested_by' => $this->requester->id])
            ->getData()['requests']->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    /**
     * The customer is soft-deleted on approval, so the representative columns
     * must still resolve on an approved request — otherwise the filter silently
     * returns nothing for every row that was actually deleted.
     */
    public function test_the_representative_filter_still_works_after_the_customer_is_deleted(): void
    {
        $rep = $this->makeUser();
        $dr  = $this->pendingRequest(['pipeline' => $this->pipeline(['salesperson_id' => $rep->id])]);

        $this->bulk([$dr->id], 'approve');

        $ids = $this->index(['tab' => 'approved', 'rep' => $rep->id])->getData()['requests']->pluck('id');

        $this->assertTrue($ids->contains($dr->id),
            'an approved request must still be findable by its salesperson');
    }

    public function test_the_customer_search_matches_on_part_of_the_name(): void
    {
        $dr = $this->pendingRequest(['party_name' => 'ZZQA UNIQUE PARTY NAME']);

        $ids = $this->index(['tab' => 'pending', 'q' => 'UNIQUE PARTY'])->getData()['requests']->pluck('id');

        $this->assertTrue($ids->contains($dr->id));
    }

    public function test_sorting_by_customer_name_reverses(): void
    {
        $this->pendingRequest(['party_name' => 'AAA QA FIRST']);
        $this->pendingRequest(['party_name' => 'ZZZ QA LAST']);

        $asc  = $this->index(['tab' => 'pending', 'sort' => 'customer', 'dir' => 'asc'])->getData()['requests'];
        $desc = $this->index(['tab' => 'pending', 'sort' => 'customer', 'dir' => 'desc'])->getData()['requests'];

        $this->assertNotSame($asc->first()->party_name, $desc->first()->party_name);
        $this->assertLessThan(0, strcmp($asc->first()->party_name, $desc->first()->party_name));
    }

    /** The cards must describe the tab being looked at. */
    public function test_the_category_cards_count_within_the_current_tab(): void
    {
        $this->pendingRequest(['reason' => 'duplicate']);
        $this->pendingRequest(['reason' => 'duplicate']);
        $this->pendingRequest(['reason' => 'closed']);

        $data = $this->index(['tab' => 'pending'])->getData();

        $this->assertSame(
            DeleteRequest::pending()->where('reason', 'duplicate')->count(),
            (int) ($data['reasonCounts']['duplicate'] ?? 0)
        );
        $this->assertSame(
            DeleteRequest::pending()->count(),
            (int) ($data['counts']['pending'] ?? 0)
        );
    }

    public function test_the_review_screen_renders_for_every_tab(): void
    {
        $this->pendingRequest();

        foreach (['pending', 'approved', 'rejected'] as $tab) {
            $html = $this->index(['tab' => $tab])->render();
            $this->assertStringContainsString('Customer Deletion Requests', $html);
            $this->assertGreaterThan(2000, strlen($html), "the $tab tab rendered almost nothing");
        }
    }
}
