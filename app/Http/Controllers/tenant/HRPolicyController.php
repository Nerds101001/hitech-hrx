<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\HRPolicy;
use App\Models\HRPolicyAcknowledgment;
use App\Notifications\PolicyAcknowledgedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class HRPolicyController extends Controller
{
    /**
     * Admin: List all policies and acknowledgment stats
     */
    public function index()
    {
        $policies = HRPolicy::withCount('acknowledgments')->get();
        $departments = \App\Models\Department::orderBy('name')->get();
        return view('tenant.hr-policies.admin-index', compact('policies', 'departments'));
    }

    /**
     * Employee: List policies assigned to them
     */
    public function employeeIndex()
    {
        $user = auth()->user();
        $policies = HRPolicy::where('is_active', true)->get()->filter(function ($policy) use ($user) {
            if (empty($policy->target_departments)) {
                return true;
            }
            return in_array((string)$user->department_id, $policy->target_departments);
        });
        $acknowledgedIds = HRPolicyAcknowledgment::where('user_id', $user->id)
            ->pluck('hr_policy_id')
            ->toArray();

        return view('tenant.hr-policies.employee-index', compact('policies', 'acknowledgedIds'));
    }

    /**
     * Admin: Store new policy
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|mimes:pdf|max:10240', // Max 10MB
            'category' => 'nullable|string',
            'is_mandatory' => 'boolean',
            'show_as_popup' => 'boolean',
            'target_departments' => 'nullable|array',
            'target_departments.*' => 'exists:departments,id'
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . Str::slug($request->title) . '.pdf';
            $path = $file->storeAs('hr-policies', $filename, 'r2');

            HRPolicy::create([
                'tenant_id' => auth()->user()->tenant_id ?? 1,
                'title' => $request->title,
                'description' => $request->description,
                'file_path' => $path,
                'category' => $request->category ?? 'General',
                'is_mandatory' => $request->boolean('is_mandatory'),
                'show_as_popup' => $request->boolean('show_as_popup'),
                'target_departments' => $request->has('target_departments') && in_array('all', $request->target_departments) ? null : $request->target_departments,
                'created_by_id' => auth()->id()
            ]);

            return back()->with('success', 'Policy uploaded and secured in vault.');
        }

        return back()->with('error', 'File upload failed.');
    }

    /**
     * Employee: Acknowledge a policy
     */
    public function acknowledge(Request $request, $id)
    {
        $policy = HRPolicy::findOrFail($id);
        $user = auth()->user();

        try {
            // Save acknowledgment record
            $acknowledgment = HRPolicyAcknowledgment::updateOrCreate(
                ['user_id' => $user->id, 'hr_policy_id' => $policy->id],
                [
                    'tenant_id' => $user->tenant_id ?? 1,
                    'acknowledged_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'signature_data' => [
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'employee_id' => $user->employee_code ?? $user->id,
                        'acknowledged_at' => now()->format('Y-m-d H:i:s'),
                        'ip' => $request->ip()
                    ]
                ]
            );

            // Send simple confirmation email (Instant background task)
            \App\Jobs\SendPolicyAcknowledgmentEmail::dispatch($user, $acknowledgment)->afterResponse();


            return response()->json(['success' => 'Policy acknowledged successfully.']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to save acknowledgment.'], 500);
        }
    }

    /**
     * View Policy PDF
     */
    public function view($id)
    {
        $policy = HRPolicy::findOrFail($id);
        return redirect()->away(Storage::disk('r2')->temporaryUrl($policy->file_path, now()->addMinutes(30)));
    }

    public function getEmbedUrl($id)
    {
        $policy = HRPolicy::findOrFail($id);
        return response()->json([
            'url' => Storage::disk('r2')->temporaryUrl($policy->file_path, now()->addMinutes(60))
        ]);
    }

    /**
     * Display acknowledgments for a specific policy.
     */
    public function acknowledgments($id)
    {
        $policy = HRPolicy::with(['acknowledgments.user'])->findOrFail($id);
        $acknowledgments = $policy->acknowledgments()->latest('acknowledged_at')->get();

        return view('tenant.hr-policies.acknowledgments', compact('policy', 'acknowledgments'));
    }

    /**
     * Admin: Delete a policy
     */
    public function destroy($id)
    {
        $policy = HRPolicy::findOrFail($id);

        try {
            // Delete file from R2
            if (Storage::disk('r2')->exists($policy->file_path)) {
                Storage::disk('r2')->delete($policy->file_path);
            }

            // Delete database record
            $policy->delete();

            return response()->json(['success' => 'Policy deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete policy.'], 500);
        }
    }
}
