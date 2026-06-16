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
        $user = auth()->user();
        $isManager = $user->hasRole('manager') && !$user->hasRole(['admin', 'hr']);
        
        $profileQuery = ProfileUpdateApproval::where('status', 'pending')->with('user');
        $documentQuery = DocumentRequest::where('status', 'pending')->with(['user', 'documentType']);

        if ($isManager) {
            $managedSubordinateIds = User::where('reporting_to_id', $user->id)->pluck('id')->toArray();
            $profileQuery->whereIn('user_id', $managedSubordinateIds);
            $documentQuery->whereIn('user_id', $managedSubordinateIds);
        }

        $profileApprovals = $profileQuery->latest()->get();
        $documentApprovals = $documentQuery->latest()->get();

        return view('tenant.approvals.index', compact('profileApprovals', 'documentApprovals'));
    }

    public function approveProfile(Request $request, $id)
    {
        $approval = ProfileUpdateApproval::findOrFail($id);
        $user = auth()->user();
        
        if ($approval->user_id === $user->id) {
            return redirect()->back()->with('error', 'You cannot approve your own profile update.');
        }
        
        if ($user->hasRole('manager') && !$user->hasRole(['admin', 'hr'])) {
            if ($approval->user->reporting_to_id !== $user->id) {
                return redirect()->back()->with('error', 'Unauthorized: You can only approve profile updates for your direct reports.');
            }
        }
        
        DB::beginTransaction();
        try {
            $targetUser = $approval->user;
            $data = $approval->requested_data;

            if ($approval->type === 'bank_details') {
                $bank = BankAccount::where('user_id', $targetUser->id)->first();
                if ($bank) {
                    $bank->update($data);
                } else {
                    $targetUser->bankAccount()->create($data);
                }
            } else {
                $targetUser->update($data);
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
        $user = auth()->user();

        if ($approval->user_id === $user->id) {
            return redirect()->back()->with('error', 'You cannot reject your own profile update.');
        }

        if ($user->hasRole('manager') && !$user->hasRole(['admin', 'hr'])) {
            if ($approval->user->reporting_to_id !== $user->id) {
                return redirect()->back()->with('error', 'Unauthorized: You can only reject profile updates for your direct reports.');
            }
        }
        
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
        $user = auth()->user();

        if ($docRequest->user_id === $user->id) {
            return redirect()->back()->with('error', 'You cannot approve your own document request.');
        }

        if ($user->hasRole('manager') && !$user->hasRole(['admin', 'hr'])) {
            if ($docRequest->user->reporting_to_id !== $user->id) {
                return redirect()->back()->with('error', 'Unauthorized: You can only approve documents for your direct reports.');
            }
        }
        
        $docRequest->update([
            'status' => 'approved',
            'actioned_by_id' => auth()->id(),
            'actioned_at' => now(),
            'remarks' => $request->remarks . ($docRequest->remarks ? " | Original Ref: " . $docRequest->remarks : "")
        ]);

        return redirect()->back()->with('success', 'Document approved successfully.');
    }

    public function rejectDocument(Request $request, $id)
    {
        $docRequest = DocumentRequest::findOrFail($id);
        $user = auth()->user();

        if ($docRequest->user_id === $user->id) {
            return redirect()->back()->with('error', 'You cannot reject your own document request.');
        }

        if ($user->hasRole('manager') && !$user->hasRole(['admin', 'hr'])) {
            if ($docRequest->user->reporting_to_id !== $user->id) {
                return redirect()->back()->with('error', 'Unauthorized: You can only reject documents for your direct reports.');
            }
        }
        
        $docRequest->update([
            'status' => 'rejected',
            'actioned_by_id' => auth()->id(),
            'actioned_at' => now(),
            'remarks' => $request->remarks
        ]);

        return redirect()->back()->with('success', 'Document rejected.');
    }
}
