<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\EmployeePromotion;
use App\Models\EmployeeTransfer;
use App\Models\EmployeeWarning;
use App\Models\EmployeeResignation;
use App\Models\EmployeeTermination;
use App\Models\EmployeeComplaint;
use App\Models\EmployeeTrip;
use App\Models\Announcement;
use App\Models\User;
use App\Models\Designation;
use App\Models\Department;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class EmployeeLifecycleController extends Controller
{
    /**
     * Display employee lifecycle dashboard
     */
    public function index(): View
    {
        $user = Auth::user();
        
        // Get statistics
        $stats = [
            'pending_promotions' => EmployeePromotion::pending()->count(),
            'pending_transfers' => EmployeeTransfer::pending()->count(),
            'active_warnings' => EmployeeWarning::active()->count(),
            'pending_resignations' => EmployeeResignation::pending()->count(),
            'open_complaints' => EmployeeComplaint::open()->count(),
            'pending_trips' => EmployeeTrip::pending()->count(),
            'active_announcements' => Announcement::current()->count(),
        ];

        // Get recent activities
        $recentPromotions = EmployeePromotion::latest()->limit(5)->get();
        $recentTransfers = EmployeeTransfer::latest()->limit(5)->get();
        $recentWarnings = EmployeeWarning::latest()->limit(5)->get();

        return view('tenant.employee-lifecycle.index', compact(
            'stats', 
            'recentPromotions', 
            'recentTransfers', 
            'recentWarnings'
        ));
    }

    /**
     * Display promotions page
     */
    public function promotions(): View
    {
        $promotions = EmployeePromotion::with(['user', 'previousDesignation', 'newDesignation', 'approvedBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.promotions', compact('promotions'));
    }

    /**
     * Store new promotion
     */
    public function storePromotion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'new_designation_id' => 'required|exists:designations,id',
            'promotion_type' => 'required|in:merit,seniority,performance',
            'promotion_date' => 'required|date',
            'salary_increase' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $validated['previous_designation_id'] = $user->designation_id;

        EmployeePromotion::create($validated);

        return redirect()->back()->with('success', 'Promotion request submitted successfully.');
    }

    /**
     * Approve promotion
     */
    public function approvePromotion(EmployeePromotion $promotion): RedirectResponse
    {
        $promotion->update([
            'status' => 'approved',
            'approved_by_id' => Auth::id(),
        ]);

        // Update user's designation
        if ($promotion->newDesignation) {
            $promotion->user->update(['designation_id' => $promotion->new_designation_id]);
        }

        return redirect()->back()->with('success', 'Promotion approved successfully.');
    }

    /**
     * Display transfers page
     */
    public function transfers(): View
    {
        $transfers = EmployeeTransfer::with(['user', 'fromDepartment', 'toDepartment', 'fromTeam', 'toTeam', 'approvedBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.transfers', compact('transfers'));
    }

    /**
     * Store new transfer
     */
    public function storeTransfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'to_department_id' => 'required|exists:departments,id',
            'to_team_id' => 'nullable|exists:teams,id',
            'transfer_date' => 'required|date',
            'effective_date' => 'required|date|after_or_equal:transfer_date',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $validated['from_department_id'] = $user->department_id;
        $validated['from_team_id'] = $user->team_id;

        EmployeeTransfer::create($validated);

        return redirect()->back()->with('success', 'Transfer request submitted successfully.');
    }

    /**
     * Approve transfer
     */
    public function approveTransfer(EmployeeTransfer $transfer): RedirectResponse
    {
        $transfer->update([
            'status' => 'approved',
            'approved_by_id' => Auth::id(),
        ]);

        // Update user's department and team
        $transfer->user->update([
            'department_id' => $transfer->to_department_id,
            'team_id' => $transfer->to_team_id,
        ]);

        return redirect()->back()->with('success', 'Transfer approved successfully.');
    }

    /**
     * Display warnings page
     */
    public function warnings(): View
    {
        $warnings = EmployeeWarning::with(['user', 'givenBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.warnings', compact('warnings'));
    }

    /**
     * Store new warning
     */
    public function storeWarning(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'warning_type' => 'required|in:verbal,written,performance,attendance',
            'severity' => 'required|in:low,medium,high,critical',
            'warning_date' => 'required|date',
            'description' => 'required|string|max:1000',
            'action_taken' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['given_by_id'] = Auth::id();

        EmployeeWarning::create($validated);

        return redirect()->back()->with('success', 'Warning recorded successfully.');
    }

    /**
     * Display resignations page
     */
    public function resignations(): View
    {
        $resignations = EmployeeResignation::with(['user', 'approvedBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.resignations', compact('resignations'));
    }

    /**
     * Store new resignation
     */
    public function storeResignation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'resignation_date' => 'required|date',
            'last_working_day' => 'required|date|after:resignation_date',
            'reason_type' => 'required|in:voluntary,involuntary,retirement',
            'reason' => 'nullable|string|max:1000',
            'exit_interview_notes' => 'nullable|string|max:1000',
            'is_rehireable' => 'boolean',
        ]);

        EmployeeResignation::create($validated);

        return redirect()->back()->with('success', 'Resignation submitted successfully.');
    }

    /**
     * Display terminations page
     */
    public function terminations(): View
    {
        $terminations = EmployeeTermination::with(['user', 'approvedBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.terminations', compact('terminations'));
    }

    /**
     * Store new termination
     */
    public function storeTermination(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'termination_date' => 'required|date',
            'last_working_day' => 'required|date|after_or_equal:termination_date',
            'termination_type' => 'required|in:misconduct,performance,redundancy,contract_end',
            'reason' => 'nullable|string|max:1000',
            'termination_notes' => 'nullable|string|max:1000',
            'is_eligible_for_rehire' => 'boolean',
        ]);

        EmployeeTermination::create($validated);

        return redirect()->back()->with('success', 'Termination recorded successfully.');
    }

    /**
     * Display complaints page
     */
    public function complaints(): View
    {
        $complaints = EmployeeComplaint::with(['complainant', 'respondent', 'approvedBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.complaints', compact('complaints'));
    }

    /**
     * Store new complaint
     */
    public function storeComplaint(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'complainant_id' => 'required|exists:users,id',
            'respondent_id' => 'nullable|exists:users,id',
            'complaint_type' => 'required|in:harassment,discrimination,safety,policy',
            'severity' => 'required|in:low,medium,high,critical',
            'complaint_date' => 'required|date',
            'description' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        EmployeeComplaint::create($validated);

        return redirect()->back()->with('success', 'Complaint recorded successfully.');
    }

    /**
     * Display trips page
     */
    public function trips(): View
    {
        $trips = EmployeeTrip::with(['user', 'approvedBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.trips', compact('trips'));
    }

    /**
     * Store new trip
     */
    public function storeTrip(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'trip_title' => 'required|string|max:200',
            'trip_type' => 'required|in:business,training,conference',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'destination' => 'required|string|max:200',
            'purpose' => 'required|string|max:1000',
            'estimated_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        EmployeeTrip::create($validated);

        return redirect()->back()->with('success', 'Trip request submitted successfully.');
    }

    /**
     * Display announcements page
     */
    public function announcements(): View
    {
        $announcements = Announcement::with('createdBy')
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.announcements', compact('announcements'));
    }

    /**
     * Store new announcement
     */
    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:2000',
            'type' => 'required|in:general,urgent,policy,holiday,event',
            'priority' => 'required|in:low,medium,high,critical',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
            'requires_acknowledgment' => 'boolean',
        ]);

        $validated['created_by_id'] = Auth::id();

        Announcement::create($validated);

        return redirect()->back()->with('success', 'Announcement created successfully.');
    }
}
