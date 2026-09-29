<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\TrainingPhase;
use App\Models\TrainingModule;
use App\Models\TrainingQuestion;
use App\Models\UserTrainingProgress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

use OpenAI\Laravel\Facades\OpenAI;

class TrainingController extends Controller
{
    /**
     * Display the training portal (Learning Path).
     * Display the training portal for the current user or a specific user (for HR/Manager)
     */
    public function index(Request $request)
    {
        // Admin/HR with no specific user_id → send to activity dashboard
        if (!$request->has('user_id') && auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts'])) {
            return redirect()->route('training.activity');
        }

        $userId = $request->query('user_id', auth()->id());
        $user = User::findOrFail($userId);

        // Security: Employees can only view their own portal
        if (auth()->user()->hasRole('employee') && !auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && $user->id !== auth()->id()) {
            abort(403, 'Unauthorized access.');
        }

        $phases = TrainingPhase::with(['modules' => function ($query) use ($user) {
            $query->with(['userProgress' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])->orderBy('order');
        }])->orderBy('order')->get();

        $progressPercentage = $user->getTrainingProgressPercentage();

        // Learn @ Hi Tech: these phases list Digital Library videos live.
        $libraryVideos = self::libraryVideos();
        $videoViews = \App\Models\TrainingVideoView::where('user_id', $user->id)->get()->keyBy('library_file_id');
        $libraryPhaseIds = config('training.library_video_phase_ids', []);
        $videosRequired = max(1, (int) config('training.library_videos_required', 3));

        // If it's an HR viewing, we might want a different view or the same journey view
        return view('tenant.training.portal', compact('phases', 'user', 'progressPercentage',
            'libraryVideos', 'videoViews', 'libraryPhaseIds', 'videosRequired'));
    }

    // ── Learn @ Hi Tech: Digital Library videos inside training ──────────

    /**
     * The Digital Library videos the Learn @ Hi Tech phase lists: in the order an
     * admin arranged them on Training Content Management, then any not yet
     * placed (a session added to the library since), newest first.
     */
    public static function libraryVideos()
    {
        $positions = \Illuminate\Support\Facades\Schema::hasTable('training_library_video_positions')
            ? DB::table('training_library_video_positions')->pluck('position', 'library_file_id')
            : collect();

        return \App\Models\LibraryFile::where('category', 'Video')
            ->where('sub_category', config('training.library_video_subcategory', 'Learn @ Hi Tech'))
            ->orderByDesc('session_date')->orderBy('title')
            ->get()
            ->sortBy(fn ($f) => $positions->has($f->id) ? $positions[$f->id] : PHP_INT_MAX, SORT_NUMERIC)
            ->values();
    }

    /** Save the order of the Learn @ Hi Tech videos (drag and drop on the manage page). */
    public function saveLibraryVideoOrder(Request $request)
    {
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }
        $ids = (array) $request->validate(['ids' => 'required|array', 'ids.*' => 'string|max:36'])['ids'];

        // Only videos that are really in the phase; anything else is ignored.
        $known = self::libraryVideos()->pluck('id')->all();
        $ids = array_values(array_intersect($ids, $known));

        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $id) {
                DB::table('training_library_video_positions')->updateOrInsert(
                    ['library_file_id' => $id],
                    ['position' => $i + 1, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        return response()->json(['success' => true, 'saved' => count($ids), 'message' => 'Order saved. Trainees now see the videos in this order.']);
    }

    /** How many of those videos this person has watched to the end of the minimum time. */
    public static function libraryVideosWatched(int $userId): int
    {
        $ids = self::libraryVideos()->pluck('id');

        return \App\Models\TrainingVideoView::where('user_id', $userId)
            ->whereIn('library_file_id', $ids)->whereNotNull('completed_at')->count();
    }

    /** The library-video phase, and a refusal if its earlier phases are not finished yet. */
    private function libraryPhaseGate(User $user): ?string
    {
        if ($user->hasRole(['admin', 'hr'])) {
            return null;
        }
        $phase = TrainingPhase::whereIn('id', config('training.library_video_phase_ids', []))->orderBy('order')->first();
        if (!$phase) {
            return 'The Learn @ Hi Tech phase is not set up.';
        }
        // Same rule as a module: everything in the earlier phases passed first.
        $undone = TrainingModule::whereHas('phase', fn ($q) => $q->where('order', '<', $phase->order))
            ->whereDoesntHave('userProgress', fn ($q) => $q->where('user_id', $user->id)->where('status', 'completed'))
            ->with('phase')->first();

        return $undone ? 'Please complete ' . $undone->phase->title . ': ' . $undone->title . ' first.' : null;
    }

    /** Where a library video can be played from: an embeddable URL, and whether it is a <video> file. */
    public static function libraryVideoSource(\App\Models\LibraryFile $file): array
    {
        $url = (string) $file->youtube_url;
        if ($url !== '') {
            if (preg_match('~drive\.google\.com/(?:file/d/|open\?id=)([a-zA-Z0-9_-]+)~', $url, $m)) {
                return ['https://drive.google.com/file/d/' . $m[1] . '/preview', false];
            }
            if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{6,})~', $url, $m)) {
                return ['https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0', false];
            }
            return [null, false];
        }
        if ($file->file_path) {
            try {
                return [\Illuminate\Support\Facades\Storage::disk('r2')->temporaryUrl($file->file_path, now()->addHours(3)), true];
            } catch (\Throwable $e) {
                return [null, true];
            }
        }

        return [null, false];
    }

    /** Play one Learn @ Hi Tech video; the first opening starts its watch timer. */
    public function showLibraryVideo($id)
    {
        $user = Auth::user();
        $file = self::libraryVideos()->firstWhere('id', $id);
        abort_unless($file, 404);

        if ($why = $this->libraryPhaseGate($user)) {
            return redirect()->route('training.portal')->with('error', $why);
        }

        $view = \App\Models\TrainingVideoView::firstOrCreate(
            ['user_id' => $user->id, 'library_file_id' => $file->id],
            ['started_at' => now(), 'tenant_id' => $user->tenant_id]
        );
        [$src, $isFile] = self::libraryVideoSource($file);
        $minSecs = max(0, (int) config('training.library_video_min_minutes', 20)) * 60;

        return view('tenant.training.library-video', [
            'file'      => $file,
            'view'      => $view,
            'src'       => $src,
            'isFile'    => $isFile,
            'secsLeft'  => $view->completed_at ? 0 : max(0, $minSecs - (int) $view->started_at->diffInSeconds(now())),
            'minMins'   => (int) config('training.library_video_min_minutes', 20),
            'watched'   => self::libraryVideosWatched($user->id),
            'required'  => max(1, (int) config('training.library_videos_required', 3)),
        ]);
    }

    /**
     * "Mark as watched" - accepted only once the minimum watch time has passed
     * since the video was first opened, enforced here, not just by the page's
     * countdown (the reading-time lesson).
     */
    public function markLibraryVideoWatched($id)
    {
        $user = Auth::user();
        $file = self::libraryVideos()->firstWhere('id', $id);
        abort_unless($file, 404);
        if ($why = $this->libraryPhaseGate($user)) {
            return response()->json(['success' => false, 'message' => $why], 403);
        }

        $view = \App\Models\TrainingVideoView::where('user_id', $user->id)->where('library_file_id', $file->id)->first();
        $minSecs = max(0, (int) config('training.library_video_min_minutes', 20)) * 60;
        $left = $view && $view->started_at ? $minSecs - (int) $view->started_at->diffInSeconds(now()) : $minSecs;

        if (!$view || (!$view->completed_at && $left > 0 && !$user->hasRole(['admin', 'hr']))) {
            return response()->json([
                'success' => false,
                'seconds_left' => max(1, $left),
                'message' => 'Please keep watching - this counts as watched after ' . ($minSecs / 60) . ' minutes ('
                    . max(1, (int) ceil($left / 60)) . ' more minute(s)).',
            ], 403);
        }

        if (!$view->completed_at) {
            $view->update(['completed_at' => now()]);
        }
        $watched = self::libraryVideosWatched($user->id);
        $required = max(1, (int) config('training.library_videos_required', 3));

        return response()->json([
            'success'  => true,
            'watched'  => $watched,
            'required' => $required,
            'message'  => $watched >= $required
                ? "Watched. That completes the Learn @ Hi Tech phase ({$watched} videos watched)."
                : "Watched. {$watched} of {$required} done - " . ($required - $watched) . ' more to complete this phase.',
        ]);
    }

    /**
     * Generate a Report Card / Transcript for HR/Manager review
     */
    public function getReportCard($userId)
    {
        $user = User::with(['trainingProgress.module.phase'])->findOrFail($userId);

        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }

        $progress = $user->trainingProgress;
        $phases = TrainingPhase::with(['modules.userProgress' => function($q) use ($userId) {
            $q->where('user_id', $userId);
        }])->get();

        $summary = [
            'total_modules' => TrainingModule::count(),
            'completed_modules' => $progress->where('status', 'completed')->count(),
            'average_score' => $progress->where('status', 'completed')->avg('assessment_score') ?? 0,
            'is_finished' => $user->training_status === 'completed'
        ];

        return view('tenant.training.report_card', compact('user', 'phases', 'summary'));
    }

    /**
     * Final Approval of Training by HR/Manager
     */
    public function approveTraining($userId)
    {
        $user = User::findOrFail($userId);

        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $user->update([
            'training_status' => 'completed',
            'is_training_required' => false // Unlocks the main dashboard
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Training for ' . $user->first_name . ' has been approved and finalized.'
        ]);
    }

    /**
     * Show a specific training module.
     */
    public function showModule($id)
    {
        $user = Auth::user();
        $module = TrainingModule::with('phase')->findOrFail($id);
        
        // --- STRICT PROGRESSION CHECK ---
        // Check if there is any incomplete module before this one
        $previousIncomplete = TrainingModule::where(function($query) use ($module) {
                $query->whereHas('phase', function($q) use ($module) {
                    $q->where('order', '<', $module->phase->order);
                })->orWhere(function($q) use ($module) {
                    $q->where('phase_id', $module->phase_id)
                      ->where('order', '<', $module->order);
                });
            })
            ->whereDoesntHave('userProgress', function($q) use ($user) {
                $q->where('user_id', $user->id)->where('status', 'completed');
            })
            ->first();

        if ($previousIncomplete && !auth()->user()->hasRole(['admin', 'hr'])) {
            return redirect()->route('training.portal')->with('error', 'Please complete Phase ' . $previousIncomplete->phase->order . ': ' . $previousIncomplete->title . ' before proceeding.');
        }

        // Find or create progress
        $progress = UserTrainingProgress::firstOrCreate(
            ['user_id' => $user->id, 'module_id' => $module->id],
            ['status' => 'available', 'tenant_id' => $user->tenant_id]
        );

        if ($progress->status === 'locked') {
            return redirect()->route('training.portal')->with('error', 'This module is currently locked.');
        }

        // Reading time counts from the FIRST time the module was opened. A failed
        // module stays 'failed' when reopened (it can still be read and retaken):
        // its last-updated time is when the last attempt failed, which is what
        // the retry cooldown measures from.
        if ($progress->status === 'available') {
            $progress->update([
                'status' => 'in_progress',
                'started_at' => $progress->started_at ?? now(),
            ]);
        } elseif ($progress->status === 'failed' && !$progress->started_at) {
            $progress->update(['started_at' => now()]);
        }

        // PDF text-caching fallback
        if ($module->content_type === 'catalog' && !empty($module->content_url)) {
            $cachePath = storage_path('app/training_pdf_cache/' . $module->id . '.json');
            if (!file_exists($cachePath)) {
                try {
                    $relativePath = str_replace(asset('storage/'), '', $module->content_url);
                    $fullPath = storage_path('app/public/' . $relativePath);
                    
                    if (!file_exists($fullPath) && str_contains($module->content_url, '/storage/')) {
                        $parts = explode('/storage/', $module->content_url);
                        $fullPath = storage_path('app/public/' . end($parts));
                    }
                    
                    if (file_exists($fullPath)) {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($fullPath);
                        $pages = $pdf->getPages();
                        $pageTexts = [];
                        foreach ($pages as $idx => $page) {
                            $pageTexts[$idx + 1] = mb_convert_encoding($page->getText(), 'UTF-8', 'UTF-8');
                        }
                        if (!file_exists(dirname($cachePath))) {
                            mkdir(dirname($cachePath), 0755, true);
                        }
                        file_put_contents($cachePath, json_encode($pageTexts, JSON_UNESCAPED_UNICODE));
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to generate PDF text cache in showModule: " . $e->getMessage());
                }
            }
        }

        return view('tenant.training.reader', [
            'module' => $module,
            'progress' => $progress
        ]);
    }

    /**
     * Get assessment questions for a module (Randomized subset).
     */
    public function getAssessment($id)
    {
        $module = TrainingModule::findOrFail($id);
        if ($refusal = $this->readingTimeRefusal($module) ?? $this->retryRefusal($module)) {
            return $refusal;
        }

        // When a module's question bank is segmented into categories (e.g. the
        // Rust-X catalogue: VCI/SMP, corrosion control, films & papers, oils &
        // coatings, commercial, compliance), draw a few random questions from
        // EVERY segment so each attempt covers the whole catalogue, capped at 25.
        $categories = $module->questions()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        if ($categories->count() > 1) {
            $perCategory = (int) min(5, max(3, floor(25 / $categories->count())));
            $questions = collect();
            foreach ($categories as $category) {
                $questions = $questions->merge(
                    $module->questions()
                        ->where('category', $category)
                        ->inRandomOrder()
                        ->limit($perCategory)
                        ->get()
                );
            }
            $questions = $questions->shuffle()->take(25)->values();
        } else {
            // Uncategorised modules: show up to 20 randomly picked questions.
            $questions = $module->questions()
                ->inRandomOrder()
                ->limit(20)
                ->get();
        }

        // Remember exactly what was served. submitAssessment() scores against
        // this set and nothing else, once.
        session([self::SERVED_KEY . $module->id => $questions->pluck('id')->map(fn ($i) => (int) $i)->all()]);

        $questions = $questions
            ->map(function($q) {
                return [
                    'id' => $q->id,
                    'question' => $q->question,
                    'options' => $q->options,
                    'marks' => $q->marks
                ];
            });

        return response()->json([
            'success' => true,
            'questions' => $questions,
            'show_all_at_once' => (bool)$module->show_all_at_once,
            'passing_percentage' => $module->passing_percentage ?? 80
        ]);
    }

    /**
     * The minimum reading time, enforced here rather than only by the reader's
     * countdown. The countdown ran in the browser alone -- and jumping to the
     * last page of a PDF switched it off -- so the server served and accepted a
     * test the moment it was asked: 30-minute product catalogues were passed two
     * minutes apart. No questions and no submission until the module's
     * estimated time has passed since the person first opened it.
     *
     * Admin and HR are exempt (they check modules, as with the progression
     * rule in showModule), and so is a module already passed.
     */
    private function readingTimeRefusal(TrainingModule $module): ?\Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if ($user->hasRole(['admin', 'hr'])) {
            return null;
        }

        $progress = UserTrainingProgress::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->first();
        if ($progress && $progress->status === 'completed') {
            return null;
        }

        $required = max(0, (int) ($module->estimated_time_minutes ?? 5)) * 60;
        $elapsed = ($progress && $progress->started_at) ? $progress->started_at->diffInSeconds(now()) : 0;
        $left = (int) ceil($required - $elapsed);
        if (!$progress || !$progress->started_at || $left > 0) {
            $left = max($left, 1);
            return response()->json([
                'success' => false,
                'reading_time_left' => $left,
                'message' => 'Please finish reading first. The test opens after the minimum reading time of '
                    . ($module->estimated_time_minutes ?? 5) . ' minutes - '
                    . ($left >= 60 ? ceil($left / 60) . ' more minute(s)' : $left . ' more second(s)') . ' to go.',
            ], 403);
        }

        return null;
    }

    /** Session key prefix holding the question ids last served for a module. */
    private const SERVED_KEY = 'training_quiz_served.';

    /**
     * After max_failed_attempts failures in a row, the next attempt waits
     * retry_cooldown_minutes from the last failure. Retries were unlimited and
     * instant, so a small question bank could be learnt by trial and error
     * (21 attempts on one catalogue, 19 on a one-page policy). Every retry
     * already draws a fresh random set.
     *
     * Admin/HR and already-passed modules are exempt.
     */
    private function retryRefusal(TrainingModule $module): ?\Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if ($user->hasRole(['admin', 'hr'])) {
            return null;
        }

        $progress = UserTrainingProgress::where('user_id', $user->id)->where('module_id', $module->id)->first();
        $max = max(1, (int) config('training.max_failed_attempts', 3));
        $cooldown = max(0, (int) config('training.retry_cooldown_minutes', 30));

        if (!$progress || $progress->status !== 'failed' || $cooldown === 0
            || (int) $progress->attempts < $max || (int) $progress->attempts % $max !== 0) {
            return null;
        }

        $opensAt = $progress->updated_at->copy()->addMinutes($cooldown);
        if (now()->greaterThanOrEqualTo($opensAt)) {
            return null;
        }

        $left = (int) ceil(now()->diffInSeconds($opensAt));

        return response()->json([
            'success' => false,
            'retry_in' => $left,
            'message' => "That was {$max} failed attempts in a row. Take time to go through the material again - "
                . 'the test can be retaken in ' . max(1, (int) ceil($left / 60)) . ' minute(s).',
        ], 429);
    }

    /**
     * Submit assessment results.
     */
    public function submitAssessment(Request $request, $id)
    {
        $user = Auth::user();
        $module = TrainingModule::findOrFail($id);
        if ($refusal = $this->readingTimeRefusal($module)) {
            return $refusal;
        }
        $answers = (array) $request->input('answers', []); // Format: [question_id => selected_index]

        // Score only the questions this person was actually served, and only
        // when every one of them is answered. The score used to be taken over
        // whatever question ids came back: no answers at all meant 0 possible
        // marks, which scored 100% - a pass - and one known answer was 100% too.
        // Each served set can be submitted once.
        $served = session()->pull(self::SERVED_KEY . $module->id);
        if (!$served) {
            return response()->json([
                'success' => false,
                'message' => 'This test was not loaded (or was already submitted). Please open the test again.',
            ], 409);
        }
        $given = array_map('intval', array_keys($answers));
        sort($given);
        $expected = $served;
        sort($expected);
        if ($given !== $expected || in_array(null, $answers, true) || in_array('', $answers, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Every question in the test must be answered, and only those questions. Please open the test again.',
            ], 422);
        }

        $questions = $module->questions()->whereIn('id', $served)->get();
        $earnedMarks = 0;
        $totalPossibleMarks = 0;

        foreach ($questions as $q) {
            $totalPossibleMarks += $q->marks;
            if (isset($answers[$q->id]) && (string) $answers[$q->id] === (string) $q->correct_option_index) {
                $earnedMarks += $q->marks;
            }
        }

        // Nothing to score (e.g. every served question since deleted) is not a pass.
        $score = ($totalPossibleMarks > 0) ? ($earnedMarks / $totalPossibleMarks) * 100 : 0;
        $passed = $score >= ($module->passing_percentage ?? 80);

        $progress = UserTrainingProgress::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->first();

        $newAttempts = ($progress->attempts ?? 0) + 1;

        if ($passed) {
            $progress->update([
                'status' => 'completed',
                'assessment_score' => $score,
                'attempts' => $newAttempts,
                'completed_at' => now()
            ]);

            // Unlock next module
            $this->unlockNextModule($module);

            // That pass may have been the last one standing between this
            // person and a working account.
            $this->releaseAccountIfTrainingDone($user);
        } else {
            $progress->update([
                'status' => 'failed',
                'assessment_score' => $score,
                'attempts' => $newAttempts
            ]);
        }

        return response()->json([
            'success' => true,
            'passed' => $passed,
            'score' => round($score, 2),
            'earned_marks' => $earnedMarks,
            'total_marks' => $totalPossibleMarks,
            'passing_percentage' => $module->passing_percentage ?? 80
        ]);
    }

    /**
     * Opens the account once every gating module has been passed.
     *
     * New joiners are parked in onboarding_submitted, which allows the
     * training portal and nothing else - no attendance, no leave, no tasks.
     * Passing the last gating module is what ends that, with no HR step in
     * between, so this runs on the pass itself rather than on an approval.
     *
     * Only the phases in config('training.onboarding_phase_ids') count. The
     * CRM and Learn @ Hi Tech phases stay available in the portal but never
     * hold an account shut.
     *
     * Safe to call after every pass: it returns early unless this pass was
     * the one that finished the set, and again if the account is already
     * released, so no one is unlocked - or emailed - twice.
     */
    private function releaseAccountIfTrainingDone(User $user): void
    {
        if ($user->training_status === 'completed') {
            return; // already released
        }

        $phaseIds = config('training.onboarding_phase_ids', [1, 2]);
        $requiredIds = TrainingModule::whereIn('phase_id', $phaseIds)->pluck('id');

        // A misconfigured phase list would otherwise read as "nothing left to
        // do" and release everyone on their first pass.
        if ($requiredIds->isEmpty()) {
            Log::warning('TRAINING_RELEASE: no modules found for gating phases, skipping', ['phases' => $phaseIds]);
            return;
        }

        $completed = UserTrainingProgress::where('user_id', $user->id)
            ->whereIn('module_id', $requiredIds)
            ->where('status', 'completed')
            ->distinct('module_id')
            ->count('module_id');

        if ($completed < $requiredIds->count()) {
            return; // still work to do
        }

        $wasHeld = in_array($user->status instanceof \UnitEnum ? $user->status->value : $user->status, [
            \App\Enums\UserAccountStatus::ONBOARDING->value,
            \App\Enums\UserAccountStatus::ONBOARDING_REQUESTED->value,
            \App\Enums\UserAccountStatus::ONBOARDING_SUBMITTED->value,
        ], true);

        $user->training_status      = 'completed';
        $user->is_training_required = false;

        // Only lift an account that training was actually holding. Someone
        // suspended or relieved who happens to finish a module must not be
        // activated by it.
        if ($wasHeld) {
            $user->status = \App\Enums\UserAccountStatus::ACTIVE->value;
        }

        $user->save();

        $avg = UserTrainingProgress::where('user_id', $user->id)
            ->whereIn('module_id', $requiredIds)
            ->avg('assessment_score');

        Log::info('TRAINING_RELEASE: ' . $user->id . ' completed gating training', [
            'activated' => $wasHeld,
            'modules'   => $requiredIds->count(),
            'avg_score' => $avg ? round($avg, 2) : null,
        ]);

        $recipients = array_filter((array) config('training.completion_notify', []));

        if (empty($recipients)) {
            return;
        }

        try {
            Mail::to($recipients)->send(new \App\Mail\TrainingCompleted(
                $user,
                $requiredIds->count(),
                $avg ? round($avg, 2) : null,
                $wasHeld
            ));
        } catch (\Throwable $e) {
            // The account is already open; a mail failure must not undo that
            // or bubble up into the employee's assessment result.
            Log::error('TRAINING_RELEASE_MAIL_FAILED for user ' . $user->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Logic to unlock the next module in sequence.
     */
    private function unlockNextModule(TrainingModule $currentModule)
    {
        $user = Auth::user();
        $nextModules = collect();

        // Find the lowest order value of modules in the current phase that is strictly greater than current order
        $minNextOrder = TrainingModule::where('phase_id', $currentModule->phase_id)
            ->where('order', '>', $currentModule->order)
            ->min('order');

        if ($minNextOrder !== null) {
            $nextModules = TrainingModule::where('phase_id', $currentModule->phase_id)
                ->where('order', $minNextOrder)
                ->get();
        } else {
            // Check next phase
            $nextPhase = TrainingPhase::where('order', '>', $currentModule->phase->order)
                ->orderBy('order')
                ->first();
            
            if ($nextPhase) {
                $minPhaseOrder = TrainingModule::where('phase_id', $nextPhase->id)->min('order');
                if ($minPhaseOrder !== null) {
                    $nextModules = TrainingModule::where('phase_id', $nextPhase->id)
                        ->where('order', $minPhaseOrder)
                        ->get();
                }
            }
        }

        if ($nextModules->isNotEmpty()) {
            foreach ($nextModules as $nm) {
                // Unlocking must never un-complete. updateOrCreate wrote
                // 'available' unconditionally, so finishing one module could
                // reset a module the person had ALREADY passed - real on
                // production, where phase 2 has two pairs of modules sharing
                // an order value (2 and 7, 4 and 9). Passing one of a pair
                // reset the other, and the completed count went backwards.
                $existing = UserTrainingProgress::where('user_id', $user->id)
                    ->where('module_id', $nm->id)
                    ->first();

                if ($existing && $existing->status === 'completed') {
                    continue;
                }

                UserTrainingProgress::updateOrCreate(
                    ['user_id' => $user->id, 'module_id' => $nm->id],
                    ['status' => 'available', 'tenant_id' => $user->tenant_id]
                );
            }
        } else {
            // Check if ALL modules across all phases are completed for this user
            $totalModules = TrainingModule::count();
            $completedModulesCount = UserTrainingProgress::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count();
            
            if ($completedModulesCount >= $totalModules) {
                $user->update(['training_status' => 'completed']);
            }
        }
    }
    /**
     * Admin/HR Training Activity Dashboard
     */
    public function activityDashboard()
    {
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }

        $phases = TrainingPhase::with(['modules'])->orderBy('order')->get();
        $totalModules = TrainingModule::count();

        // All users with any training activity or who require training
        $users = User::where(function($q) {
                $q->whereHas('trainingProgress')
                  ->orWhere('is_training_required', true);
            })
            ->with(['trainingProgress.module.phase'])
            ->get();

        $totalEnrolled  = $users->count();
        $totalCompleted = $users->where('training_status', 'completed')->count();
        $inProgress     = $users->filter(fn($u) => $u->trainingProgress->count() > 0 && $u->training_status !== 'completed')->count();
        $avgScore       = round(UserTrainingProgress::where('status', 'completed')->whereNotNull('assessment_score')->avg('assessment_score') ?? 0, 1);

        // Phase-wise completion (how many users finished ALL modules in each phase)
        $phaseStats = $phases->map(function ($phase) use ($users) {
            if ($phase->modules->isEmpty()) {
                return ['phase' => $phase, 'completed_count' => 0, 'total' => $users->count(), 'pct' => 0];
            }
            $completedCount = $users->filter(function ($user) use ($phase) {
                foreach ($phase->modules as $module) {
                    $done = $user->trainingProgress
                        ->where('module_id', $module->id)
                        ->where('status', 'completed')
                        ->isNotEmpty();
                    if (!$done) return false;
                }
                return true;
            })->count();

            return [
                'phase'           => $phase,
                'completed_count' => $completedCount,
                'total'           => $users->count(),
                'pct'             => $users->count() > 0 ? round($completedCount / $users->count() * 100) : 0,
            ];
        });

        // Per-user summary row
        $userStats = $users->map(function ($user) use ($phases, $totalModules) {
            $progress       = $user->trainingProgress;
            $completedCount = $progress->where('status', 'completed')->count();
            $avgScore       = round($progress->where('status', 'completed')->whereNotNull('assessment_score')->avg('assessment_score') ?? 0, 1);
            $lastActivity   = $progress->sortByDesc('updated_at')->first()?->updated_at;

            // Highest phase with any activity
            $currentPhase = null;
            foreach ($phases->sortByDesc('order') as $phase) {
                $ids = $phase->modules->pluck('id')->toArray();
                if ($progress->whereIn('module_id', $ids)->isNotEmpty()) {
                    $currentPhase = $phase;
                    break;
                }
            }

            return [
                'user'          => $user,
                'completed'     => $completedCount,
                'total'         => $totalModules,
                'pct'           => $totalModules > 0 ? round($completedCount / $totalModules * 100) : 0,
                'avg_score'     => $avgScore,
                'last_activity' => $lastActivity,
                'current_phase' => $currentPhase,
                'status'        => $user->training_status ?? 'in_progress',
                'failed_count'  => $progress->where('status', 'failed')->count(),
            ];
        })->sortByDesc('pct')->values();

        // Top 5 hardest modules (most failures)
        $moduleStats = TrainingModule::with('phase')
            ->withCount([
                'userProgress as total_attempts' => fn($q) => $q->whereIn('status', ['completed','failed']),
                'userProgress as fail_count'      => fn($q) => $q->where('status', 'failed'),
                'userProgress as pass_count'      => fn($q) => $q->where('status', 'completed'),
            ])
            ->having('total_attempts', '>', 0)
            ->orderByDesc('fail_count')
            ->limit(6)
            ->get();

        return view('tenant.training.activity', compact(
            'phases', 'totalEnrolled', 'totalCompleted', 'inProgress',
            'avgScore', 'phaseStats', 'userStats', 'moduleStats', 'totalModules'
        ));
    }

    /**
     * Display the Management Interface for HR/Admin
     */
    public function managementIndex()
    {
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }

        $phases = TrainingPhase::with('modules.questions')->orderBy('order')->get();
        // The Learn @ Hi Tech phase has no modules of its own: it shows the
        // Digital Library's session videos, so list them here too - otherwise
        // the phase looks empty to admins while trainees see every session.
        $libraryVideos = self::libraryVideos();
        $libraryPhaseIds = config('training.library_video_phase_ids', []);

        return view('tenant.training.manage.index', compact('phases', 'libraryVideos', 'libraryPhaseIds'));
    }

    /**
     * Create or Update a Training Phase
     */
    public function storePhase(Request $request)
    {
        // SECURITY (M-4): guard curriculum writes like the sibling manage methods.
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }

        $validated = $request->validate([
            'id' => 'nullable|exists:training_phases,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'required|integer'
        ]);

        TrainingPhase::updateOrCreate(
            ['id' => $validated['id'] ?? null],
            [
                'title' => $validated['title'],
                'description' => $validated['description'],
                'order' => $validated['order']
            ]
        );

        return redirect()->back()->with('success', 'Phase saved successfully.');
    }

    /**
     * Create or Update a Training Module
     */
    public function storeModule(Request $request)
    {
        // SECURITY (M-4): guard curriculum writes like the sibling manage methods.
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }

        $validated = $request->validate([
            'id' => 'nullable|exists:training_modules,id',
            'phase_id' => 'required|exists:training_phases,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content_type' => 'required|in:video,policy,catalog',
            'content_body' => 'nullable|string',
            'content_url' => 'nullable|string',
            'estimated_time_minutes' => 'required|integer',
            'order' => 'required|integer',
            'pdf_file' => 'nullable|file|mimes:pdf|max:20480', // 20MB Max
            'passing_percentage' => 'required|integer|min:0|max:100',
            'questions_per_test' => 'required|integer|min:1',
            'show_all_at_once' => 'nullable|boolean',
            'video_chapters' => 'nullable|string',
            'video_milestones' => 'nullable|string'
        ]);

        $data = $validated;
        unset($data['pdf_file']);
        $data['show_all_at_once'] = $request->has('show_all_at_once');

        // SECURITY (S6/S7): content_body is rendered as raw HTML in the training
        // reader ({!! !!}) and injected via innerHTML in the course player. Sanitize
        // it on save (broad allow-list so legitimate rich content -- images, tables,
        // code -- survives, while scripts/handlers/javascript: URLs are stripped).
        if (! empty($data['content_body'])) {
            $data['content_body'] = \App\Helpers\HtmlSanitizer::clean(
                $data['content_body'],
                'p,br,b,strong,i,em,u,s,a[href|title|target],ul,ol,li,h1,h2,h3,h4,h5,h6,span,blockquote,pre,code,hr,img[src|alt|width|height],table,thead,tbody,tr,td,th,div,figure,figcaption'
            );
        }

        if ($request->filled('video_chapters')) {
            $decoded = json_decode($request->video_chapters, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()->withErrors(['video_chapters' => 'Chapters must be a valid JSON array.'])->withInput();
            }
            $data['video_chapters'] = $decoded;
        } else {
            $data['video_chapters'] = null;
        }

        if ($request->filled('video_milestones')) {
            $decoded = json_decode($request->video_milestones, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()->withErrors(['video_milestones' => 'Milestones must be a valid JSON array.'])->withInput();
            }
            $data['video_milestones'] = $decoded;
        } else {
            $data['video_milestones'] = null;
        }

        if ($request->hasFile('pdf_file')) {
            $path = $request->file('pdf_file')->store('training', 'public');
            $data['content_url'] = asset('storage/' . $path);
        }

        TrainingModule::updateOrCreate(
            ['id' => $validated['id'] ?? null],
            $data
        );

        return redirect()->back()->with('success', 'Module saved successfully.');
    }

    /**
     * Create or Update an Assessment Question
     */
    public function storeQuestion(Request $request)
    {
        // SECURITY (M-4): guard curriculum writes like the sibling manage methods.
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }

        $validated = $request->validate([
            'id' => 'nullable|exists:training_questions,id',
            'module_id' => 'required|exists:training_modules,id',
            'question' => 'required|string',
            'options' => 'required|array|min:2',
            'correct_option_index' => 'required|integer',
            'marks' => 'required|integer|min:1'
        ]);

        TrainingQuestion::updateOrCreate(
            ['id' => $validated['id'] ?? null],
            $validated
        );

        return redirect()->back()->with('success', 'Question saved successfully.');
    }

    /**
     * Delete an assessment question.
     */
    public function destroyQuestion($id)
    {
        // SECURITY (M-4): guard curriculum writes like the sibling manage methods.
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }

        $question = TrainingQuestion::findOrFail($id);
        $question->delete();
        return redirect()->back()->with('success', 'Question removed from assessment.');
    }

    /**
     * AI Auto-Generation of Assessment Questions
     */
    /**
     * Two steps behind one route, so no route file has to change:
     *
     *  - POST with no "save" flag  -> ask the AI and RETURN the questions for
     *                                 review. Nothing is written.
     *  - POST with save=1          -> persist the set the reviewer approved.
     *
     * It used to generate and write in a single pass, so whatever the model
     * produced went straight onto the assessment with nobody having read it.
     */
    public function generateAIQuestions(Request $request, $id)
    {
        // SECURITY (M-4): guard curriculum writes like the sibling manage methods.
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }

        $module = TrainingModule::findOrFail($id);

        // --- STEP 2: save the reviewed set ---
        if ($request->boolean('save')) {
            $validated = $request->validate([
                'questions'                         => 'required|array|min:1',
                'questions.*.question'              => 'required|string|max:1000',
                'questions.*.options'               => 'required|array|min:2|max:6',
                'questions.*.options.*'             => 'required|string|max:500',
                'questions.*.correct_option_index'  => 'required|integer|min:0',
            ]);

            $saved = 0;
            foreach ($validated['questions'] as $q) {
                // A correct index pointing past the end of its own options
                // would render as a question with no right answer.
                if ($q['correct_option_index'] >= count($q['options'])) {
                    continue;
                }
                TrainingQuestion::create([
                    'module_id'            => $module->id,
                    'question'             => $q['question'],
                    'options'              => array_values($q['options']),
                    'correct_option_index' => $q['correct_option_index'],
                ]);
                $saved++;
            }

            return response()->json(['success' => true, 'count' => $saved]);
        }

        // --- STEP 1: generate for review ---
        $content = $module->content_body;

        // --- PDF TEXT EXTRACTION FOR AI ---
        if ($module->content_type === 'catalog' && empty($content) && !empty($module->content_url)) {
            try {
                // Convert URL to absolute path for the parser
                $relativePath = str_replace(asset('storage/'), '', $module->content_url);
                $fullPath = storage_path('app/public/' . $relativePath);
                
                if (file_exists($fullPath)) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($fullPath);
                    $content = $pdf->getText();
                }
            } catch (\Exception $e) {
                // Silently continue or log if needed
            }
        }

        if (empty($content)) {
            return response()->json(['success' => false, 'message' => 'No module content or PDF text found to analyze.'], 422);
        }

        // Smalot's extractor hands back the bytes as they sit in the PDF, which
        // for plenty of real documents is not valid UTF-8. json_encode() inside
        // the OpenAI client then refuses the whole payload with "Malformed UTF-8
        // characters, possibly incorrectly encoded" and the request dies before
        // it is ever sent -- which is exactly what AI Auto-Generate reported.
        // Confirmed live on module 30; module 9 only escaped it because its bad
        // bytes happened to fall past the 4000-character cut below.
        //
        // Scrub before Str::limit(), not after: truncating a string that already
        // holds invalid sequences can slice a multi-byte character in half and
        // manufacture fresh damage.
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

        try {
            $prompt = "Act as an HR Training Specialist. Based on the following training content, generate 3 professional multiple-choice questions for an onboarding assessment. 
            Each question should have exactly 4 options and one correct option index (0 for first, 3 for last).
            Return the result ONLY as a raw JSON array of objects. 
            Example format: [{\"question\": \"What is X?\", \"options\": [\"A\", \"B\", \"C\", \"D\"], \"correct_option_index\": 0}]
            
            CONTENT TO ANALYZE:
            " . \Illuminate\Support\Str::limit($content, 4000); // Limit context window

            $factory = \OpenAI::factory();
            $client = $factory->withApiKey(config('openai.api_key'))
                              ->withBaseUri(config('openai.base_url'))
                              ->make();

            $result = $client->chat()->create([
                'model' => config('openai.ai_model'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $jsonContent = $result->choices[0]->message->content;
            // Strip markdown if AI included it
            $jsonContent = preg_replace('/```json\s*|\s*```/', '', $jsonContent);
            $questions = json_decode($jsonContent, true);

            if (!is_array($questions)) {
                throw new \Exception("The AI returned an invalid format. Please try again.");
            }

            // Drop anything malformed before it reaches the reviewer. The old
            // code indexed $q['question'] straight away, so one missing key in
            // the model's reply was a 500 rather than a skipped item.
            $clean = [];
            foreach ($questions as $q) {
                if (!is_array($q) || !isset($q['question'], $q['options'], $q['correct_option_index'])) {
                    continue;
                }
                if (!is_array($q['options']) || count($q['options']) < 2) {
                    continue;
                }
                $idx = (int) $q['correct_option_index'];
                if ($idx < 0 || $idx >= count($q['options'])) {
                    continue;
                }
                $clean[] = [
                    'question'             => (string) $q['question'],
                    'options'              => array_values(array_map('strval', $q['options'])),
                    'correct_option_index' => $idx,
                ];
            }

            if (empty($clean)) {
                throw new \Exception("The AI returned no usable questions. Please try again.");
            }

            // Nothing is written here - the reviewer confirms first.
            return response()->json(['success' => true, 'questions' => $clean]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'AI Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Chat / Ask the Document feature for PDF Catalogues.
     */
    public function chatWithDocument(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        $module = TrainingModule::findOrFail($id);

        if ($module->content_type !== 'catalog') {
            return response()->json(['success' => false, 'message' => 'AI study assistant is only available for PDF catalogs.'], 400);
        }

        $cachePath = storage_path('app/training_pdf_cache/' . $module->id . '.json');

        if (!file_exists($cachePath)) {
            try {
                $relativePath = str_replace(asset('storage/'), '', $module->content_url);
                $fullPath = storage_path('app/public/' . $relativePath);
                if (!file_exists($fullPath) && str_contains($module->content_url, '/storage/')) {
                    $parts = explode('/storage/', $module->content_url);
                    $fullPath = storage_path('app/public/' . end($parts));
                }
                
                if (file_exists($fullPath)) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($fullPath);
                    $pages = $pdf->getPages();
                    $pageTexts = [];
                    foreach ($pages as $idx => $page) {
                        $pageTexts[$idx + 1] = mb_convert_encoding($page->getText(), 'UTF-8', 'UTF-8');
                    }
                    if (!file_exists(dirname($cachePath))) {
                        mkdir(dirname($cachePath), 0755, true);
                    }
                    file_put_contents($cachePath, json_encode($pageTexts, JSON_UNESCAPED_UNICODE));
                } else {
                    return response()->json(['success' => false, 'message' => 'Catalogue file not found on disk.'], 404);
                }
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Failed to parse PDF catalog: ' . $e->getMessage()], 500);
            }
        }

        $pageTexts = json_decode(file_get_contents($cachePath), true);
        if (empty($pageTexts)) {
            return response()->json(['success' => false, 'message' => 'No text could be extracted from this PDF catalog.'], 400);
        }

        // --- RETRIEVAL SCORING (RAG) ---
        $query = strtolower($request->input('message'));
        $terms = preg_split('/[\s,\.\?\!\-\/]+/', $query);
        $stopwords = ['is', 'the', 'a', 'an', 'and', 'or', 'for', 'with', 'to', 'in', 'on', 'at', 'by', 'of', 'about', 'does', 'do', 'what', 'how', 'why', 'can', 'you', 'i', 'we', 'they', 'our'];
        $terms = array_filter($terms, function($t) use ($stopwords) {
            return strlen($t) > 2 && !in_array($t, $stopwords);
        });

        $scores = [];
        foreach ($pageTexts as $pageNum => $text) {
            $textLower = strtolower($text);
            $score = 0;
            foreach ($terms as $term) {
                $score += substr_count($textLower, $term) * 2;
                if (str_contains($textLower, $term)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scores[$pageNum] = $score;
            }
        }

        arsort($scores);
        $topPages = array_slice(array_keys($scores), 0, 3, true);

        if (empty($topPages)) {
            $topPages = array_slice(array_keys($pageTexts), 0, 3);
        }

        $context = "";
        foreach ($topPages as $pNum) {
            $context .= "--- PAGE {$pNum} ---\n" . $pageTexts[$pNum] . "\n\n";
        }

        try {
            $factory = \OpenAI::factory();
            $client = $factory->withApiKey(config('openai.api_key'))
                              ->withBaseUri(config('openai.base_url'))
                              ->make();

            $systemPrompt = "You are the Hitech AI Book Assistant for the catalog: '{$module->title}'.
            Your job is to answer user questions using the provided catalog content page snippets.
            
            RULES:
            1. Be concise, extremely professional, and helpful.
            2. ALWAYS cite which page the information is from (e.g., 'According to Page X...' or '(Page X)').
            3. Answer using ONLY the facts found in the context below. If the context does not contain enough information to answer the question, state: 'I could not find the exact information for this in the catalog.'
            4. Use markdown formatting (bolding, lists) to present data clearly.
            
            CONTEXT CHUNKS:
            {$context}";

            $resp = $client->chat()->create([
                'model' => config('openai.ai_model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $request->input('message')]
                ],
                'max_tokens' => 600
            ]);

            $answer = $resp->choices[0]->message->content;
            return response()->json([
                'success' => true,
                'message' => $answer,
                'cited_pages' => $topPages
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'AI study assistant currently unavailable: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a training phase and all its modules.
     */
    public function destroyPhase($id)
    {
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }

        $phase = TrainingPhase::findOrFail($id);
        
        // Soft delete all child modules
        $phase->modules()->delete();
        
        // Soft delete the phase itself
        $phase->delete();

        return redirect()->back()->with('success', 'Phase and all its modules deleted successfully.');
    }

    /**
     * Delete a training module.
     */
    public function destroyModule($id)
    {
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) && !auth()->user()->can('training.manage')) {
            abort(403);
        }

        $module = TrainingModule::findOrFail($id);
        $module->delete();

        return redirect()->back()->with('success', 'Module deleted successfully.');
    }
}
