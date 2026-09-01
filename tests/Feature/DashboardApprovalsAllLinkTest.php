<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The admin/HR dashboard's "Pending Approvals" card mixes four request
 * types (Leave/Expense/Document/Loan) in one list, but its header "All"
 * link was hardcoded to the Leave Approval page regardless of what the
 * card actually showed - reported live: a card full of Document requests
 * still sent the click to Leave Approval. The link now points to whichever
 * type has the most pending items.
 */
class DashboardApprovalsAllLinkTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(?string $role = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Approvals',
            'email'      => 'qaapprovals' . uniqid() . '@example.test',
            'code'       => 'QAAP' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    private function pendingApprovalsUrl(): string
    {
        $admin = $this->makeUser('admin');
        Auth::login($admin);
        $view = app(\App\Http\Controllers\tenant\DashboardController::class)->index();
        return $view->getData()['pendingApprovalsUrl'];
    }

    public function test_all_link_points_to_document_approval_when_documents_dominate(): void
    {
        $docType = DocumentType::first() ?? DocumentType::create(['name' => 'QA Doc Type', 'code' => 'QADT' . random_int(100000, 999999), 'status' => 'active']);

        for ($i = 0; $i < 3; $i++) {
            $employee = $this->makeUser();
            DocumentRequest::create([
                'user_id' => $employee->id,
                'document_type_id' => $docType->id,
                'status' => 'pending',
            ]);
        }

        $url = $this->pendingApprovalsUrl();

        $this->assertEquals(url('/documentmanagement'), $url, 'a card dominated by Document requests must send "All" to Document Approval, not Leave');
    }

    public function test_all_link_still_points_to_leave_approval_when_leave_dominates(): void
    {
        $alp = LeaveType::where('code', 'ALP')->firstOrFail();

        // This local database carries real synced pending requests of its
        // own (dozens of real pending Document requests at the time this
        // was written) - a fixed handful of new Leave requests isn't a
        // reliable margin, so out-number whatever the largest real bucket
        // already is.
        $baseline = max(
            \App\Models\DocumentRequest::where('status', 'pending')->count(),
            \App\Models\ExpenseRequest::where('status', 'pending')->count(),
            \App\Models\LoanRequest::where('status', 'pending')->count()
        );
        for ($i = 0; $i < $baseline + 5; $i++) {
            $employee = $this->makeUser();
            LeaveRequest::create([
                'user_id' => $employee->id, 'leave_type_id' => $alp->id,
                'from_date' => today()->addDays(5 + $i), 'to_date' => today()->addDays(5 + $i),
                'status' => 'pending', 'user_notes' => 'dashboard approvals link test',
            ]);
        }
        $docType = DocumentType::first() ?? DocumentType::create(['name' => 'QA Doc Type 2', 'code' => 'QADT2' . random_int(100000, 999999), 'status' => 'active']);
        DocumentRequest::create(['user_id' => $this->makeUser()->id, 'document_type_id' => $docType->id, 'status' => 'pending']);

        $url = $this->pendingApprovalsUrl();

        $this->assertEquals(url('/leaveRequests'), $url, 'the common leave-dominated case must keep working as before');
    }
}
