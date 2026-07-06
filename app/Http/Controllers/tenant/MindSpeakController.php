<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\MindSpeak;
use App\Models\User;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MindSpeakController extends Controller
{
    /**
     * Show Mind Speak dashboard (Admin/HR) or submission page (Employees).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdminOrHR = $user->hasRole(['admin', 'hr']) || $user->can('mind_speak.manage');

        if ($isAdminOrHR) {
            // --- ADMIN/HR DASHBOARD ---
            
            // 1. KPI cards
            $stats = [
                'total' => MindSpeak::count(),
                'idea' => MindSpeak::where('category', 'Idea')->count(),
                'complaint' => MindSpeak::where('category', 'Complaint')->count(),
                'sales_pitch' => MindSpeak::where('category', 'Sales Pitch')->count(),
                'seminar' => MindSpeak::where('category', 'Seminar')->count(),
                'competitor_insights' => MindSpeak::where('category', 'Competitor Insights')->count(),
                'anonymous' => MindSpeak::where('is_anonymous', true)->count(),
            ];

            // 2. Department-wise counts
            $deptStats = MindSpeak::join('users', 'mind_speaks.user_id', '=', 'users.id')
                ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                ->select('departments.name as department_name', DB::raw('count(mind_speaks.id) as total'))
                ->groupBy('departments.id', 'departments.name')
                ->get();

            // 3. Month-wise counts (for current year)
            $currentYear = Carbon::now()->year;
            $monthlyStats = MindSpeak::select(
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('count(id) as total')
                )
                ->whereYear('created_at', $currentYear)
                ->groupBy(DB::raw('MONTH(created_at)'))
                ->orderBy(DB::raw('MONTH(created_at)'))
                ->get()
                ->pluck('total', 'month')
                ->toArray();

            // Format month-wise array to have all 12 months initialized
            $formattedMonths = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthName = Carbon::create()->month($m)->format('F');
                $formattedMonths[$monthName] = $monthlyStats[$m] ?? 0;
            }

            // 4. Detailed table with filters
            $query = MindSpeak::with(['user.department']);

            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            if ($request->filled('is_anonymous')) {
                $query->where('is_anonymous', $request->is_anonymous == '1');
            }

            if ($request->filled('department_id')) {
                $deptId = $request->department_id;
                $query->whereHas('user', function ($q) use ($deptId) {
                    $q->where('department_id', $deptId);
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $submissions = $query->latest()->paginate(15);
            $departments = Department::where('status', \App\Enums\Status::ACTIVE)->get();

            return view('tenant.mind-speak.admin-dashboard', compact(
                'stats',
                'deptStats',
                'formattedMonths',
                'submissions',
                'departments'
            ))->with('pageConfigs', ['contentLayout' => 'wide']);
        } else {
            // --- EMPLOYEE SUBMISSION VIEW ---
            $submissions = MindSpeak::where('user_id', $user->id)
                ->latest()
                ->get();

            return view('tenant.mind-speak.employee-index', compact('submissions'))
                ->with('pageConfigs', ['contentLayout' => 'wide']);
        }
    }

    /**
     * Store a new Mind Speak submission.
     */
    public function store(Request $request)
    {
        $attachmentRule = 'nullable|file|mimes:pdf,jpeg,png,jpg,mp3,wav,ogg,webm,m4a,mp4,ppt,pptx,doc,docx|max:20480';
        if ($request->category === 'Seminar') {
            $attachmentRule = 'required|file|mimes:pdf,jpeg,png,jpg,mp3,wav,ogg,webm,m4a,mp4,ppt,pptx,doc,docx|max:20480';
        }

        $request->validate([
            'category' => 'required|string|max:255',
            'content' => 'required|string|min:5|max:2000',
            'is_anonymous' => 'nullable|boolean',
            'attachment' => $attachmentRule,
        ], [
            'attachment.required' => 'A presentation or document attachment is compulsory for Seminars.'
        ]);

        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $attachmentPath = $file->storeAs('mind_speaks', $filename, 'public');
            }

            $isAnonymous = $request->has('is_anonymous') ? (bool) $request->is_anonymous : false;
            if (in_array($request->category, ['Sales Pitch', 'Seminar', 'Competitor Insights'])) {
                $isAnonymous = false;
            }

            MindSpeak::create([
                'user_id' => Auth::id(),
                'category' => $request->category,
                'content' => $request->content,
                'attachment' => $attachmentPath,
                'is_anonymous' => $isAnonymous,
                'tenant_id' => Auth::user()->tenant_id
            ]);

            return redirect()->back()->with('success', 'Thank you for sharing your thoughts! Mind Speak submitted successfully.');
        } catch (\Exception $e) {
            Log::error('Mind Speak Store Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to submit Mind Speak. Please try again.');
        }
    }

    /**
     * Export Mind Speaks to CSV.
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'hr']) && !$user->can('mind_speak.manage')) {
            abort(403, 'Unauthorized action.');
        }

        $query = MindSpeak::with(['user.department']);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('is_anonymous')) {
            $query->where('is_anonymous', $request->is_anonymous == '1');
        }

        if ($request->filled('department_id')) {
            $deptId = $request->department_id;
            $query->whereHas('user', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $records = $query->latest()->get();

        $csvLines = [];
        $csvLines[] = 'Submission ID,Category,Content,Submitted At,Is Anonymous,Employee ID,Submitter Name,Submitter Email,Department';

        foreach ($records as $record) {
            $empId = $record->user->code;
            $isAnon = $record->is_anonymous ? 'Yes' : 'No';

            if ($record->is_anonymous) {
                $name = '[Anonymous]';
                $email = '[Anonymous]';
                $deptName = '[Anonymous]';
            } else {
                $name = $record->user->full_name ?? $record->user->name;
                $email = $record->user->email;
                $deptName = $record->user->department?->name ?? 'N/A';
            }

            $csvLines[] = implode(',', array_map(
                fn($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"',
                [
                    $record->id,
                    $record->category,
                    $record->content,
                    $record->created_at->format('Y-m-d H:i:s'),
                    $isAnon,
                    $empId,
                    $name,
                    $email,
                    $deptName
                ]
            ));
        }

        $csvContent = implode("\n", $csvLines);
        $fileName = 'mind_speak_submissions_' . date('Y_m_d_His') . '.csv';

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
