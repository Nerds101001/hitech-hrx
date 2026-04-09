<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\DocumentRequest;
use App\Models\ProfileUpdateApproval;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApprovalController extends Controller
{
    public function index()
    {
        $profileApprovals = ProfileUpdateApproval::where('status', 'pending')
            ->with('user')
            ->latest()
            ->get();

        $documentApprovals = DocumentRequest::where('status', 'pending')
            ->with(['user', 'documentType'])
            ->latest()
            ->get();

        return view('tenant.approvals.index', compact('profileApprovals', 'documentApprovals'));
    }

    public function approveProfile(Request $request, $id)
    {
        $approval = ProfileUpdateApproval::findOrFail($id);
        
        DB::beginTransaction();
        try {
            $user = $approval->user;
            $data = $approval->requested_data;

            if ($approval->type === 'bank_details') {
                $bank = BankAccount::where('user_id', $user->id)->first();
                if ($bank) {
                    $bank->update($data);
                } else {
                    $user->bankAccount()->create($data);
                }
            } else {
                // Handle generic profile updates if we add more types later
                $user->update($data);
            }

            $approval->update([
                'status' => 'approved',
                'actioned_by_id' => auth()->id(),
                'actioned_at' => now(),
                'remarks' => $request->remarks
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Profile update approved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approve Profile Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to approve profile update.');
        }
    }

    public function rejectProfile(Request $request, $id)
    {
        $approval = ProfileUpdateApproval::findOrFail($id);
        
        $approval->update([
            'status' => 'rejected',
            'actioned_by_id' => auth()->id(),
            'actioned_at' => now(),
            'remarks' => $request->remarks
        ]);

        return redirect()->back()->with('success', 'Profile update rejected.');
    }

    public function approveDocument(Request $request, $id)
    {
        $docRequest = DocumentRequest::findOrFail($id);
        
        $docRequest->update([
            'status' => 'approved',
            'remarks' => $request->remarks . ($docRequest->remarks ? " | Original Ref: " . $docRequest->remarks : "")
        ]);

        return redirect()->back()->with('success', 'Document approved successfully.');
    }

    public function rejectDocument(Request $request, $id)
    {
        $docRequest = DocumentRequest::findOrFail($id);
        
        $docRequest->update([
            'status' => 'rejected',
            'remarks' => $request->remarks
        ]);

        return redirect()->back()->with('success', 'Document rejected.');
    }
}
