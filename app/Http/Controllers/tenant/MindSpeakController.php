<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\MindSpeak;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MindSpeakController extends Controller
{
    /**
     * Mind Speak is a plain-text channel. Blade escapes everything on the way
     * out, so pasted markup was never executable — it rendered as visible
     * characters. But a submission reading `<script>alert(1)</script>` has no
     * place on an HR record that goes to reviewers and into a CSV, and neither
     * does a row of emoji. Both are refused at the door.
     *
     * Emoji only — accented letters, ₹, dashes and other ordinary punctuation
     * stay allowed.
     */
    private function plainTextRule(string $field): \Closure
    {
        return function (string $attribute, $value, $fail) use ($field) {
            $value = (string) $value;

            // `<` followed by a letter, slash, ! or ? — a tag. "under < 5 lakhs"
            // is left alone.
            if (preg_match('/<[a-z\/!?]/i', $value)) {
                $fail("The $field cannot contain HTML or script tags. Please write in plain text.");
                return;
            }
            if (preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}]/u', $value)) {
                $fail("The $field cannot contain emoji. Please write in plain text.");
            }
        };
    }

    /**
     * Show Mind Speak dashboard (Admin/HR) or submission page (Employees).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdminOrHR = $user->hasRole(['admin', 'hr']) || $user->can('mind_speak.manage');
        $isKaizenReviewer = in_array($user->id, MindSpeak::KAIZEN_REVIEWER_IDS, true);

        // Admin and HR were sent straight to the dashboard, which has no submit
        // form — so they had no way to post an entry of their own at all.
        // ?compose=1 gives them the same portal everyone else sees; store()
        // never restricted who may submit.
        if (($isAdminOrHR || $isKaizenReviewer) && !$request->boolean('compose')) {
            // --- ADMIN/HR DASHBOARD (or, for Vijender/Manoj, a Kaizen-only one) ---

            // Vijender and Manoj aren't general Mind Speak reviewers - they're
            // the two people Kaizens route to. If that's the only reason this
            // user can see this page at all, scope everything to Kaizen so
            // they never see (or act on) anything else.
            $kaizenOnly = $isKaizenReviewer && !$isAdminOrHR;

            // 1. KPI cards
            if ($kaizenOnly) {
                $stats = [
                    'kaizen' => MindSpeak::where('category', 'Kaizen')->count(),
                    'kaizen_pending' => MindSpeak::where('category', 'Kaizen')->where('status', MindSpeak::STATUS_PENDING)->count(),
                    'kaizen_approved' => MindSpeak::where('category', 'Kaizen')->where('status', MindSpeak::STATUS_APPROVED)->count(),
                    'kaizen_rejected' => MindSpeak::where('category', 'Kaizen')->where('status', MindSpeak::STATUS_REJECTED)->count(),
                ];
            } else {
                $stats = [
                    'total' => MindSpeak::count(),
                    'idea' => MindSpeak::where('category', 'Idea')->count(),
                    'complaint' => MindSpeak::where('category', 'Complaint')->count(),
                    'sales_pitch' => MindSpeak::where('category', 'Sales Pitch')->count(),
                    'seminar' => MindSpeak::where('category', 'Seminar')->count(),
                    'competitor_insights' => MindSpeak::where('category', 'Competitor Insights')->count(),
                    'kaizen' => MindSpeak::where('category', 'Kaizen')->count(),
                    'feedback_360' => MindSpeak::where('category', '360° Feedback')->count(),
                    'anonymous' => MindSpeak::where('is_anonymous', true)->count(),
                ];
            }

            // The monthly-trend chart and the department breakdown were removed
            // from the dashboard — the reviewer's job here is to act on
            // submissions, and both pushed the table below the fold. Their two
            // aggregate queries went with them.
            //
            // The department breakdown had also been leaking: six live
            // departments have exactly one active member, so counting anonymous
            // rows there named the submitter. The department FILTER below still
            // excludes anonymous rows for that reason.

            // 2. Detailed table with filters
            $query = MindSpeak::with(['user.department']);

            if ($kaizenOnly) {
                // Not a filter the user can lift - this view only ever shows Kaizen.
                $query->where('category', 'Kaizen');
            } elseif ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            if ($request->filled('is_anonymous')) {
                $query->where('is_anonymous', $request->is_anonymous == '1');
            }

            if ($request->filled('department_id')) {
                $deptId = $request->department_id;
                // Anonymous rows are excluded: narrowing to a one-person
                // department and Anonymous=Yes named the submitter outright.
                $query->where('is_anonymous', false)
                    ->whereHas('user', function ($q) use ($deptId) {
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
            $departments = Department::cachedActive();

            return view('tenant.mind-speak.admin-dashboard', compact(
                'stats',
                'submissions',
                'departments',
                'kaizenOnly'
            ))->with('pageConfigs', ['contentLayout' => 'wide']);
        } else {
            // --- EMPLOYEE SUBMISSION VIEW ---
            $submissions = MindSpeak::where('user_id', $user->id)
                ->latest()
                ->get();

            return view('tenant.mind-speak.employee-index', compact('submissions'))
                // Lets the portal offer a way back for an admin (or Vijender/
                // Manoj) who came here only to submit something.
                ->with('canSeeDashboard', $isAdminOrHR || $isKaizenReviewer)
                ->with('pageConfigs', ['contentLayout' => 'wide']);
        }
    }

    /**
     * Admin / HR act on a submission. Vijender Singh and Manoj Patle
     * (MindSpeak::KAIZEN_REVIEWER_IDS) may act on Kaizen entries specifically,
     * on top of — not instead of — admin/HR, who keep full access as a
     * fallback.
     *
     *   approve      Sales Pitch, Seminar, Competitor Insights, Kaizen — accepted
     *   acknowledge  Idea, Complaint, 360° Feedback — seen, with a reply back
     *   reject       any category; a reason is compulsory so the employee knows
     *                what to fix before resubmitting
     */
    public function review(Request $request, $id)
    {
        $user = Auth::user();
        $isAdminOrHR = $user->hasRole(['admin', 'hr']) || $user->can('mind_speak.manage');
        $isKaizenReviewer = in_array($user->id, MindSpeak::KAIZEN_REVIEWER_IDS, true);

        if (!$isAdminOrHR && !$isKaizenReviewer) {
            return back()->with('error', 'You are not allowed to review Mind Speak submissions.');
        }

        $data = $request->validate([
            'action'  => 'required|in:approve,acknowledge,reject',
            'remarks' => ['nullable', 'string', 'max:2000', $this->plainTextRule('remarks')],
        ]);

        $entry = MindSpeak::findOrFail($id);

        // Vijender/Manoj's access is Kaizen-only - a crafted request against
        // any other category must still be refused, same as if they had no
        // Mind Speak access at all.
        if (!$isAdminOrHR && $entry->category !== 'Kaizen') {
            return back()->with('error', 'You are not allowed to review this submission.');
        }

        // A rejected entry is the employee's to act on; reviewing it again
        // would overwrite the reason they are looking at.
        if ($entry->status === MindSpeak::STATUS_REJECTED) {
            return back()->with('error', 'This submission was already rejected. The employee needs to resubmit it.');
        }
        if (!in_array($data['action'], $entry->availableActions(), true)) {
            return back()->with('error', ucfirst($data['action']) . ' does not apply to a ' . $entry->category . '.');
        }
        // Turning something down without saying why leaves the employee stuck.
        if ($data['action'] === 'reject' && trim((string) ($data['remarks'] ?? '')) === '') {
            return back()->with('error', 'Please give a reason so the employee knows what to change.');
        }

        $entry->update([
            'status' => match ($data['action']) {
                'approve'     => MindSpeak::STATUS_APPROVED,
                'acknowledge' => MindSpeak::STATUS_ACKNOWLEDGED,
                'reject'      => MindSpeak::STATUS_REJECTED,
            },
            'admin_remarks'  => $data['remarks'] ?: null,
            'reviewed_by_id' => $user->id,
            'reviewed_at'    => now(),
        ]);

        return back()->with('success', 'Submission marked as ' . $entry->status . '.');
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
            // Free text let a hand-rolled POST invent a category. "Sales Pitch "
            // with a trailing space slipped past the identify-the-submitter rule
            // and the approve/acknowledge split below, and showed up on the
            // dashboard in none of the counts.
            'category' => ['required', \Illuminate\Validation\Rule::in(MindSpeak::CATEGORIES)],
            'content'  => ['required', 'string', 'min:5', 'max:2000', $this->plainTextRule('submission')],
            'is_anonymous' => 'nullable|boolean',
            'attachment' => $attachmentRule,
        ], [
            'attachment.required' => 'A presentation or document attachment is compulsory for Seminars.'
        ]);

        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                // extension() reads the real MIME type; getClientOriginalExtension()
                // trusts the browser-supplied name. Private disk — see attachment().
                $filename = time() . '_' . uniqid() . '.' . $file->extension();
                $attachmentPath = $file->storeAs('mind_speaks', $filename, 'local');
            }

            $isAnonymous = $request->has('is_anonymous') ? (bool) $request->is_anonymous : false;
            if (in_array($request->category, MindSpeak::APPROVABLE, true)) {
                $isAnonymous = false;
            }
            // 360° Feedback is anonymous unconditionally — there is no toggle
            // for it in the form, and this is the actual enforcement: even a
            // hand-rolled POST claiming is_anonymous=0 can't turn it off.
            if (in_array($request->category, MindSpeak::FORCED_ANONYMOUS, true)) {
                $isAnonymous = true;
            }

            // A resubmission points back at the rejected original, so the
            // reviewer's reason and the first attempt stay on record. Only the
            // employee's OWN rejected entry may be resubmitted.
            $resubmitOf = null;
            if ($request->filled('resubmitted_from_id')) {
                $original = MindSpeak::where('id', $request->resubmitted_from_id)
                    ->where('user_id', Auth::id())
                    ->where('status', MindSpeak::STATUS_REJECTED)
                    ->first();
                $resubmitOf = $original?->id;
            }

            MindSpeak::create([
                'user_id' => Auth::id(),
                'category' => $request->category,
                'content' => $request->content,
                'attachment' => $attachmentPath,
                'is_anonymous' => $isAnonymous,
                'tenant_id' => Auth::user()->tenant_id,
                'status' => MindSpeak::STATUS_PENDING,
                'resubmitted_from_id' => $resubmitOf,
            ]);

            return redirect()->back()->with('success', $resubmitOf
                ? 'Resubmitted. Your updated entry is with the reviewer again.'
                : 'Thank you for sharing your thoughts! Mind Speak submitted successfully.');
        } catch (\Exception $e) {
            // A bare message left nothing to debug with. The submitter is
            // recorded by id only — never the content, which may be a complaint
            // someone filed anonymously and must not end up in a log file.
            Log::error('Mind Speak store failed', [
                'user_id'   => Auth::id(),
                'category'  => $request->category,
                'exception' => $e,
            ]);
            return redirect()->back()->with('error', 'Failed to submit Mind Speak. Please try again.');
        }
    }

    /**
     * Serve a submission's attachment.
     *
     * Attachments used to be written to the PUBLIC disk, so a complaint
     * recording or a competitor document sat at a guessable URL under
     * /storage/mind_speaks/ that anyone on the internet could fetch without
     * logging in. They are now written to the private disk and handed out
     * here, to the submitter or to a reviewer and nobody else. Files that
     * predate the change are still read from the public disk so old links
     * keep working.
     */
    public function attachment($id)
    {
        $user  = Auth::user();
        $entry = MindSpeak::findOrFail($id);

        $isReviewer = $user->hasRole(['admin', 'hr']) || $user->can('mind_speak.manage');
        $isKaizenReviewer = $entry->category === 'Kaizen' && in_array($user->id, MindSpeak::KAIZEN_REVIEWER_IDS, true);
        if (!$isReviewer && !$isKaizenReviewer && $entry->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }
        if (!$entry->attachment) {
            abort(404);
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($entry->attachment)) {
                return Storage::disk($disk)->response(
                    $entry->attachment,
                    basename($entry->attachment),
                    ['Cache-Control' => 'private, no-store']
                );
            }
        }

        abort(404);
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

        // Pulling every complaint in the company out of the system in one file
        // is worth a trail of its own.
        Log::info('Mind Speak export', [
            'by'      => $user->id,
            'filters' => $request->only(['category', 'is_anonymous', 'department_id', 'date_from', 'date_to']),
        ]);

        $query = MindSpeak::with(['user.department']);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('is_anonymous')) {
            $query->where('is_anonymous', $request->is_anonymous == '1');
        }

        if ($request->filled('department_id')) {
            $deptId = $request->department_id;
            // Same rule as the dashboard — see index().
            $query->where('is_anonymous', false)
                ->whereHas('user', function ($q) use ($deptId) {
                    $q->where('department_id', $deptId);
                });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // A cell beginning with = + - @ (or a lone tab/CR) is executed as a
        // formula by Excel and LibreOffice, so submitted text could run on the
        // reviewer's machine. Prefixing with an apostrophe forces it to text.
        $csvCell = function ($v): string {
            $s = (string) ($v ?? '');
            if ($s !== '' && str_contains("=+-@\t\r", $s[0])) {
                $s = "'" . $s;
            }
            return '"' . str_replace('"', '""', $s) . '"';
        };

        $fileName = 'mind_speak_submissions_' . date('Y_m_d_His') . '.csv';

        // get() loaded every submission and every submitter into memory and then
        // built one big string — two full copies of the table. Streamed in
        // chunks the export costs the same whether there are 50 rows or 50,000.
        return response()->streamDownload(function () use ($query, $csvCell) {
            $out = fopen('php://output', 'w');
            fwrite($out, 'Submission ID,Category,Content,Submitted At,Is Anonymous,Employee ID,Submitter Name,Submitter Email,Department' . "\n");

            $query->orderByDesc('id')->chunk(500, function ($records) use ($out, $csvCell) {
                foreach ($records as $record) {
                    if ($record->is_anonymous) {
                        // The employee code identifies the submitter as surely
                        // as their name does. It used to be written out even for
                        // anonymous rows, which de-anonymised every
                        // whistleblower in the export.
                        $empId = $name = $email = $deptName = '[Anonymous]';
                    } else {
                        $empId    = $record->user->code;
                        $name     = $record->user->full_name ?? $record->user->name;
                        $email    = $record->user->email;
                        $deptName = $record->user->department?->name ?? 'N/A';
                    }

                    fwrite($out, implode(',', array_map($csvCell, [
                        $record->id,
                        $record->category,
                        $record->content,
                        $record->created_at->format('Y-m-d H:i:s'),
                        $record->is_anonymous ? 'Yes' : 'No',
                        $empId,
                        $name,
                        $email,
                        $deptName
                    ])) . "\n");
                }
                flush();
            });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
