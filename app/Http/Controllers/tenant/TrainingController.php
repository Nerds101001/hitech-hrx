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

        // If it's an HR viewing, we might want a different view or the same journey view
        return view('tenant.training.portal', compact('phases', 'user', 'progressPercentage'));
    }

    /**
     * Generate a Report Card / Transcript for HR/Manager review
     */
    public function getReportCard($userId)
    {
        $user = User::with(['trainingProgress.module.phase'])->findOrFail($userId);

        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts'])) {
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

        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts'])) {
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

        if ($progress->status === 'available' || $progress->status === 'failed') {
            $progress->update([
                'status' => 'in_progress',
                'started_at' => now()
            ]);
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
        
        $questions = $module->questions()
            ->inRandomOrder()
            ->limit($module->questions_per_test ?? 5)
            ->get()
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
     * Submit assessment results.
     */
    public function submitAssessment(Request $request, $id)
    {
        $user = Auth::user();
        $module = TrainingModule::findOrFail($id);
        $answers = $request->input('answers'); // Format: [question_id => selected_index]
        
        $questions = $module->questions()->whereIn('id', array_keys($answers))->get();
        $earnedMarks = 0;
        $totalPossibleMarks = 0;

        foreach ($questions as $q) {
            $totalPossibleMarks += $q->marks;
            if (isset($answers[$q->id]) && $answers[$q->id] == $q->correct_option_index) {
                $earnedMarks += $q->marks;
            }
        }

        $score = ($totalPossibleMarks > 0) ? ($earnedMarks / $totalPossibleMarks) * 100 : 100;
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
     * Logic to unlock the next module in sequence.
     */
    private function unlockNextModule(TrainingModule $currentModule)
    {
        $nextModule = TrainingModule::where('phase_id', $currentModule->phase_id)
            ->where('order', '>', $currentModule->order)
            ->orderBy('order')
            ->first();

        if (!$nextModule) {
            // Check next phase
            $nextPhase = TrainingPhase::where('order', '>', $currentModule->phase->order)
                ->orderBy('order')
                ->first();
            
            if ($nextPhase) {
                $nextModule = $nextPhase->modules()->first();
            }
        }

        if ($nextModule) {
            UserTrainingProgress::updateOrCreate(
                ['user_id' => Auth::id(), 'module_id' => $nextModule->id],
                ['status' => 'available', 'tenant_id' => Auth::user()->tenant_id]
            );
        } else {
            // All training complete!
            Auth::user()->update(['training_status' => 'completed']);
        }
    }
    /**
     * Display the Management Interface for HR/Admin
     */
    public function managementIndex()
    {
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts'])) {
            abort(403);
        }

        $phases = TrainingPhase::with('modules.questions')->orderBy('order')->get();
        return view('tenant.training.manage.index', compact('phases'));
    }

    /**
     * Create or Update a Training Phase
     */
    public function storePhase(Request $request)
    {
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
        $question = TrainingQuestion::findOrFail($id);
        $question->delete();
        return redirect()->back()->with('success', 'Question removed from assessment.');
    }

    /**
     * AI Auto-Generation of Assessment Questions
     */
    public function generateAIQuestions($id)
    {
        $module = TrainingModule::findOrFail($id);
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

        try {
            $prompt = "Act as an HR Training Specialist. Based on the following training content, generate 3 professional multiple-choice questions for an onboarding assessment. 
            Each question should have exactly 4 options and one correct option index (0 for first, 3 for last).
            Return the result ONLY as a raw JSON array of objects. 
            Example format: [{\"question\": \"What is X?\", \"options\": [\"A\", \"B\", \"C\", \"D\"], \"correct_option_index\": 0}]
            
            CONTENT TO ANALYZE:
            " . \Illuminate\Support\Str::limit($content, 4000); // Limit context window

            $result = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
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

            foreach ($questions as $q) {
                TrainingQuestion::create([
                    'module_id' => $module->id,
                    'question' => $q['question'],
                    'options' => $q['options'],
                    'correct_option_index' => $q['correct_option_index']
                ]);
            }

            return response()->json(['success' => true, 'count' => count($questions)]);

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
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts'])) {
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
        if (!auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts'])) {
            abort(403);
        }

        $module = TrainingModule::findOrFail($id);
        $module->delete();

        return redirect()->back()->with('success', 'Module deleted successfully.');
    }
}
