<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\LibraryFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenAI;
use Illuminate\Pagination\LengthAwarePaginator;

class DigitalLibraryController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->get('category');
        $documentTypes = config('digital_library.document_types');
        $tier = $documentTypes[$category]['level'] ?? 'product';
        $perPage = 12;
        $page = max(1, (int) $request->get('page', 1));

        // Light rows across the whole category — just enough to compute the
        // brand-card and sidebar counts. The expensive full records (with
        // summary text etc.) are only fetched below for the ONE brand
        // (or, for Video, the one category) actually being viewed, not all
        // seven brands' worth every time someone switches tabs.
        $countRows = LibraryFile::query()
            ->when($category && $category !== 'all', fn($q) => $q->where('category', $category))
            ->get(['id', 'brand', 'sub_category', 'title']);

        $brands = \App\Models\LibraryTaxonomy::where('type', 'brand')->orderBy('order')->get()
            ->unique(fn($b) => strtolower($b->name))->values();
        $taxonomies = \App\Models\LibraryTaxonomy::where('type', 'category')->whereNotNull('parent_id')->get()->groupBy('parent_id');
        $videoTaxonomies = \App\Models\LibraryTaxonomy::where('type', 'category')->whereNull('parent_id')->orderBy('order')->get();

        $paginated = null;
        $activeBrand = null;
        $brandTaxonomies = collect();
        $brandCounts = collect();
        $subCatCounts = collect();
        $catNames = collect();
        $activeSubcat = $request->get('subcat', 'all');
        $totalScopedDocs = 0;

        if ($tier === 'video') {
            $subCatCounts = $countRows->groupBy(fn($f) => $f->sub_category ?: 'General')->map->count();
            $totalScopedDocs = $countRows->count();
            $catNames = $videoTaxonomies->pluck('name')->merge($subCatCounts->keys())->filter()->unique()->values();

            $activeCatName = $activeSubcat === 'all' ? null
                : $catNames->first(fn($n) => \Illuminate\Support\Str::slug($n) === $activeSubcat);

            $fullQuery = LibraryFile::query()->where('category', 'Video');
            if ($activeCatName) {
                $fullQuery->where('sub_category', $activeCatName);
            }
            $products = $fullQuery->orderBy('created_at', 'desc')->get()->groupBy('title');

            $paginated = new LengthAwarePaginator(
                $products->forPage($page, $perPage), $products->count(), $perPage, $page,
                ['path' => $request->url(), 'query' => $request->except('page')]
            );
        } else {
            $brandCounts = $countRows->groupBy(fn($f) => strtolower($f->brand ?? ''))->map->count();

            $activeBrandSlug = $request->get('brand');
            $activeBrand = $activeBrandSlug
                ? $brands->first(fn($b) => \Illuminate\Support\Str::slug($b->name) === $activeBrandSlug)
                : $brands->first(fn($b) => ($brandCounts[strtolower($b->name)] ?? 0) > 0);
            $activeBrand = $activeBrand ?? $brands->first();

            if ($activeBrand) {
                $brandTaxonomies = $taxonomies[$activeBrand->id] ?? collect();
                $brandRows = $countRows->where('brand', $activeBrand->name);
                $subCatCounts = $brandRows->groupBy('sub_category')->map->count();
                $totalScopedDocs = $brandRows->count();
                $catNames = $brandTaxonomies->pluck('name')->merge($subCatCounts->keys())->filter()->unique()->values();

                $activeCatName = $activeSubcat === 'all' ? null
                    : $catNames->first(fn($n) => \Illuminate\Support\Str::slug($n) === $activeSubcat);

                $fullQuery = LibraryFile::query()
                    ->when($category && $category !== 'all', fn($q) => $q->where('category', $category))
                    ->where('brand', $activeBrand->name);
                if ($activeCatName) {
                    $fullQuery->where('sub_category', $activeCatName);
                }
                $products = $fullQuery->orderBy('created_at', 'desc')->get()->groupBy('title');

                $paginated = new LengthAwarePaginator(
                    $products->forPage($page, $perPage), $products->count(), $perPage, $page,
                    ['path' => $request->url(), 'query' => $request->except('page')]
                );
            }
        }

        return view('tenant.digital-library.index', compact(
            'category', 'tier', 'documentTypes', 'brands', 'brandCounts', 'activeBrand', 'brandTaxonomies',
            'taxonomies', 'videoTaxonomies', 'subCatCounts', 'catNames', 'activeSubcat', 'totalScopedDocs', 'paginated'
        ));
    }

    public function addTaxonomy(Request $request)
    {
        $request->validate([
            'type' => 'required|in:brand,category',
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:library_taxonomies,id',
            'color' => 'nullable|string',
            'description' => 'nullable|string'
        ]);

        try {
            // SECURITY (M-15): mass-assign only the validated fields, not $request->all().
            $taxonomy = \App\Models\LibraryTaxonomy::create($request->only(['type', 'name', 'parent_id', 'color', 'description']));
            return response()->json(['message' => 'Archive structure updated.', 'data' => $taxonomy]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add: ' . $e->getMessage()], 500);
        }
    }

    public function updateTaxonomy(Request $request, $id)
    {
        $taxonomy = \App\Models\LibraryTaxonomy::findOrFail($id);
        $taxonomy->update($request->only(['name', 'color', 'description', 'order']));
        return response()->json(['message' => 'Archive updated.']);
    }

    public function deleteTaxonomy($id)
    {
        \App\Models\LibraryTaxonomy::findOrFail($id)->delete();
        return response()->json(['message' => 'Archive pruned.']);
    }

    /**
     * Phase 1: AI Content Guardian (Analysis Only)
     * Parses the file and returns a summary/category for user review.
     */
    public function analyze(Request $request)
    {
        $request->validate(['file' => 'required|max:20480|mimes:pdf']);
        $file = $request->file('file');
        
        $originalName = $file->getClientOriginalName();
        $duplicate = LibraryFile::where('title', $originalName)->first();

        try {
            $audit      = app(\App\Services\VaultAuditService::class);
            $textSample = $audit->extractText($file->getPathname());

            if (strlen(trim($textSample)) < 100) {
                Log::warning("DIGITAL_LIBRARY_OCR_NEEDED: Small text extracted from " . $originalName);
                // We'll try to let it pass if it's a valid file, but note it
            }

            /*
             * Try for an answer while the uploader waits, but do not hold the
             * request open indefinitely. The provider's queue for the larger
             * models swings between seconds and several minutes; past the
             * timeout the file is accepted anyway and left for the backlog
             * (vault:audit-pending) to classify. Failing an upload because the
             * AI was busy is the behaviour this replaces.
             */
            $result = $audit->audit($textSample, $originalName, (int) config('openai.audit_timeout', 20));

            if ($result['status'] === 'timeout') {
                return response()->json([
                    'success'      => true,
                    'pending'      => true,
                    'brand'        => 'HITECH',
                    'sub_category' => 'Industrial Assets',
                    'category'     => $request->input('category') ?: 'TDS',
                    'name'         => $originalName,
                    'date'         => date('Y-m-d'),
                    'summary'      => \App\Services\VaultAuditService::PENDING_SUMMARY,
                    'notice'       => 'The AI is busy right now. Save the file and it will be classified automatically in the background.',
                    'temp_name'    => $originalName,
                    'is_duplicate' => !!$duplicate,
                    'duplicate_id' => $duplicate?->id,
                ]);
            }

            if ($result['status'] === 'invalid') {
                return response()->json(['error' => $result['message']], 422);
            }

            if ($result['status'] !== 'ok') {
                return response()->json(['error' => 'Audit Error: ' . $result['message']], 500);
            }

            return response()->json([
                'success'      => true,
                'brand'        => $result['brand'],
                'sub_category' => $result['sub_category'],
                'category'     => $result['category'],
                'name'         => $result['name'],
                'date'         => $result['date'],
                'summary'      => $result['summary'],
                'temp_name'    => $originalName,
                'is_duplicate' => !!$duplicate,
                'duplicate_id' => $duplicate?->id,
            ]);
        } catch (\Exception $e) {
            Log::error("DIGITALLIBRARY_AUDIT_ERROR [" . $originalName . "]: " . $e->getMessage(), [
                'file' => $originalName,
                'exception' => $e
            ]);
            return response()->json(['error' => 'Audit Error: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            /*
             * Learn @ Hi Tech is now just a Video category (see
             * MigrateLearnToVideoCommand), not its own document type — Video
             * Title, Presenter and Session Date only need to be required when
             * that specific category is picked on the video-link tab.
             *
             * Both tabs of the Add Document dialog live in ONE form, so a plain
             * file upload still serialises the video tab's fields. Keying these
             * rules off sub_category alone (rather than youtube_url) would
             * therefore demand a Video Title from every PDF upload that happens
             * to reuse that same category name — gate on youtube_url too.
             */
            $isLearnVideo = $request->filled('youtube_url')
                && $request->input('sub_category') === \App\Console\Commands\MigrateLearnToVideoCommand::LEARN_CATEGORY;
            $learnRule = $isLearnVideo ? 'required' : 'nullable';

            $validator = Validator::make($request->all(), [
                'file' => 'required_without:youtube_url|nullable|mimes:pdf,mp4,mov,avi|max:51200',
                'youtube_url' => 'required_without:file|nullable|url',
                'category' => 'nullable',
                'overwrite' => 'nullable|boolean',
                'title_yt' => $learnRule,
                'presenter_id' => $learnRule,
                'session_date' => $learnRule
            ], [
                'title_yt.required' => 'Video Title is required for Learn @ HiTech sessions.',
                'presenter_id.required' => 'Presenter is compulsory for Learn @ HiTech sessions.',
                'session_date.required' => 'Session Date is compulsory for Learn @ HiTech sessions.'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'Input Required: ' . $validator->errors()->first()], 422);
            }

            $file = $request->file('file');
            $productName = $request->name ?? ($file ? $file->getClientOriginalName() : 'Hitech Asset');
            
            // --- Duplicate Check ---
            $existing = LibraryFile::where('title', $productName)->first();
            if ($existing && !$request->overwrite) {
                return response()->json([
                    'error' => 'DUPLICATE_FOUND',
                    'message' => "A file named '{$productName}' already exists in the vault. Do you want to replace it?",
                    'id' => $existing->id
                ], 409);
            }

            $youtubeUrl = $request->youtube_url;
            $summary = $request->summary ?? "Processing strategic crux...";
            $category = $request->category ?? 'SDS';
            $path = null;
            $mimeType = $file ? $file->getClientMimeType() : 'video/youtube';
            $size = $file ? $file->getSize() : 0;

            if ($youtubeUrl) {
                // Video-link tab only ever creates Video-type entries now —
                // Learn @ Hi Tech is a category within Video, not a separate type.
                $category = 'Video';
                $productName = $request->title_yt ?? $productName;
                if (!$request->title_yt && $productName === 'Hitech Asset'
                    && $request->sub_category === \App\Console\Commands\MigrateLearnToVideoCommand::LEARN_CATEGORY) {
                    $productName = 'Learn @ HiTech Session';
                }
                $summary = "Digital Video Asset: " . $youtubeUrl;
            } elseif ($file) {
                // R2 Storage
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('digital-library', $filename, 'r2');
                
                // If overwriting, delete old one
                if ($existing && $request->overwrite) {
                    if ($existing->file_path) {
                        try {
                            Storage::disk('r2')->delete($existing->file_path);
                        } catch (\Exception $e) { Log::warning("Failed to delete old R2 file: " . $e->getMessage()); }
                    }
                    $existing->delete();
                }
            }

            // --- Final Persistence ---
            $level = config("digital_library.document_types.{$category}.level", 'product');

            if ($level === 'video') {
                // Video is tied to its own category taxonomy, never a brand.
                $brandName = null;
                $defaultSubCategory = \App\Console\Commands\MigrateLearnToVideoCommand::GENERAL_CATEGORY;
            } else {
                $brandName = $request->brand ?? 'HITECH';
                $taxBrand = \App\Models\LibraryTaxonomy::where('type', 'brand')->where('name', 'like', $brandName)->first();
                if ($taxBrand) $brandName = $taxBrand->name;
                $defaultSubCategory = $level === 'brand' ? null : 'Industrial Assets';
            }

            LibraryFile::create([
                'title' => $productName,
                'brand' => $brandName,
                'sub_category' => $request->sub_category ?? $defaultSubCategory,
                'file_path' => $path,
                'youtube_url' => $youtubeUrl,
                'category' => $category,
                'mime_type' => $mimeType,
                'size' => $size,
                'summary' => $summary,
                'is_public' => true,
                'tenant_id' => auth()->user()->tenant_id ?? 1,
                'created_by_id' => auth()->id() ?? 1,
                'presenter_id' => $request->presenter_id,
                'session_date' => $request->session_date,
            ]);

            return response()->json(['success' => 'Asset secured to Hitech Vault. Type: ' . $category]);

        } catch (\Throwable $e) {
            Log::error("DIGITALLIBRARY_STORE_ERROR: " . $e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            return response()->json(['error' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required',
        ]);

        if ($validator->fails()) {
            return ($request->ajax() || $request->wantsJson())
                ? response()->json(['error' => 'No files detected for strategic ingestion.'], 422)
                : back()->withErrors($validator);
        }

        $uploadedCount = 0;
        $rejectedCount = 0;

        $factory = OpenAI::factory();
        $client = $factory->withApiKey(config('openai.api_key'))
                          ->withBaseUri(config('openai.base_url'))
                          ->make();

        foreach ($request->file('files') as $file) {
            if ($file->getClientMimeType() === 'application/pdf') {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($file->getPathname());
                    $text = mb_substr($pdf->getText(), 0, 3000);

                    $verify = $client->chat()->create([
                        'model' => config('openai.ai_model'),
                        'messages' => [
                            ['role' => 'system', 'content' => "Identify document type (SDS/TDS/Presentation/LEARN/Test Report/Comparison Report/INVALID). Reply ONLY with the key."],
                            ['role' => 'user', 'content' => "Text: " . $text],
                        ],
                    ]);

                    $aiResult = trim(strtoupper($verify->choices[0]->message->content));
                    if ($aiResult !== 'INVALID') {
                        $category = $aiResult;

                        // Original Filename Storage
                        $filename = time() . '_' . $file->getClientOriginalName();
                        $path = $file->storeAs('digital-library', $filename, 'r2');

                        $extract = $client->chat()->create([
                            'model' => config('openai.ai_model'),
                            'messages' => [
                                ['role' => 'system', 'content' => "Extract 5-6 lines summary. PLAIN TEXT ONLY. NO BOLDING, NO ASTERISKS, NO LISTS.
                                - Lines 1-4: Product Name & description (Detailed).
                                - Line 5: Features/Applications.
                                - Line 6: Technical/Chemical crux.
                                Strictly plain text sentences."],
                                ['role' => 'user', 'content' => "Text: " . $text],
                            ],
                        ]);
                        $summary = $extract->choices[0]->message->content ?? "Technical summary generated.";

                        LibraryFile::create([
                            'title' => $file->getClientOriginalName(),
                            'file_path' => $path,
                            'category' => $category,
                            'mime_type' => $file->getClientMimeType(),
                            'size' => $file->getSize(),
                            'summary' => $summary,
                            'is_public' => true,
                            'tenant_id' => auth()->user()->tenant_id ?? 1,
                            'created_by_id' => auth()->id() ?? 1,
                        ]);
                        $uploadedCount++;
                    } else {
                        $rejectedCount++;
                    }
                } catch (\Exception $e) { $rejectedCount++; }
            } elseif (strpos($file->getClientMimeType(), 'video/') !== false) {
                // Original Filename for Videos
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('digital-library', $filename, 'r2');

                LibraryFile::create([
                    'title' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'category' => 'Video',
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'summary' => "Technical video asset archived.",
                    'is_public' => true,
                    'tenant_id' => auth()->user()->tenant_id ?? 1,
                    'created_by_id' => auth()->id() ?? 1,
                ]);
                $uploadedCount++;
            }
        }

        return back()->with('success', "Bulk processing completed: $uploadedCount assets secured, $rejectedCount items rejected or failed storage.");
    }

    public function chat(Request $request)
    {
        $userInput = $request->input('message');
        $user = auth()->user();
        
        // 1. Fetch real-time employee data
        $userName = $user->first_name ?: 'User';
        $leaveData = $user->leaveBalances()->with('leaveType')->get()->map(function($lb) {
            return "{$lb->leaveType->name}: {$lb->balance} days";
        })->implode(", ");

        // 2. Fetch "Trained" Knowledge snippets
        $training = \App\Models\KnowledgeBase::all()->map(function($k) {
            return "[{$k->category}] {$k->title}: {$k->content}";
        })->implode("\n---\n");

        // 3. Technical Library Summary with Direct Links
        // SECURITY: We only pass metadata to the AI. It uses this to find matches.
        // We use relative links to avoid localhost/production URL mismatches.
        $contextFiles = LibraryFile::select('id', 'title', 'category', 'summary')->get();
        $filteredData = $contextFiles->unique('title')->map(function($f) {
            $accessLink = "/digital-library/access/" . $f->id;
            return "Product: {$f->title} | Category: {$f->category} | Summary: {$f->summary} | DownloadLink: {$accessLink}";
        })->implode("\n");

        try {
            // Force NVIDIA NIM Factory pattern to bypass any local OpenAI config conflicts
            $factory = OpenAI::factory();
            $client = $factory->withApiKey(config('openai.api_key'))
                              ->withBaseUri(config('openai.base_url'))
                              ->make();

            // Fetch any custom system instructions from the AI Training center
            $customInstructions = \App\Models\KnowledgeBase::where('category', 'SYSTEM_PROMPT')->first();
            $dynamicInstructions = $customInstructions ? $customInstructions->content : "";

            $systemPrompt = "You are the 'Senior Hitech Consultant', a Technical HR & Engineering Assistant for Hitech Group.
            
PERSONA & TONE:
- Tone: Professional, highly consultative, precise, and helpful.
- Language: English (Global).

CONSULTATIVE BEHAVIOR (CRITICAL):
- If a user asks a broad question like 'looking for tds', 'show me sds', or 'tell me about papers', DO NOT provide a full list immediately.
- Instead, ASK 1-2 clarifying questions to narrow down their specific need (e.g., 'Are you looking for VCI paper, ESD protection, or a specific brand like RUST-X?').
- Only provide specific download links once the user has narrowed down their request or if they explicitly ask for 'all' or 'complete list'.
- Your goal is to be a consultant, not just a search engine.

FORMATTING RULES:
- Use Markdown for structure (headings, bolding).
- When providing a download link, ALWAYS use this exact HTML structure for a premium button:
  <a href='LINK' class='sentinel-download-btn'><i class='bx bx-download me-1'></i> Download</a>
- Use Markdown tables for technical data comparison.

CONTEXT FROM DIGITAL LIBRARY:
{$filteredData}

USER REQUEST:
{$userInput}";

            $response = $client->chat()->create([
                'model' => config('openai.ai_model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userInput],
                ],
                'max_tokens' => 850,
            ]);

            return response()->json([
                'message' => $response->choices[0]->message->content
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => "Technician Unavailable: " . $e->getMessage()], 500);
        }
    }

    public function access($id)
    {
        $file = LibraryFile::findOrFail($id);

        if ($file->youtube_url) {
            // SECURITY (M-16): youtube_url is uploader-controlled, so redirecting to
            // it blindly is an open redirect (and a javascript:/data: URL slips
            // through). Only follow genuine YouTube hosts (exact match, so
            // youtube.com.evil.com is rejected).
            $host = strtolower((string) parse_url($file->youtube_url, PHP_URL_HOST));
            $allowedHosts = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];
            if (in_array($host, $allowedHosts, true)) {
                return redirect()->away($file->youtube_url);
            }
            abort(404, 'Invalid video URL.');
        }

        // SECURITY FIX: Use signed temporary URLs for private R2 storage
        return redirect()->away(Storage::disk('r2')->temporaryUrl($file->file_path, now()->addMinutes(30)));
    }

    public function previewFrame($id)
    {
        $file = LibraryFile::findOrFail($id);
        
        $embedUrl = '';
        $isDrive = false;
        $driveId = null;

        if ($file->youtube_url) {
            $embedUrl = $file->youtube_url;
            if (str_contains($embedUrl, 'youtube.com/watch?v=')) {
                $embedUrl = str_replace('watch?v=', 'embed/', $embedUrl);
                $embedUrl = explode('&', $embedUrl)[0];
            } elseif (str_contains($embedUrl, 'youtu.be/')) {
                $embedUrl = str_replace('youtu.be/', 'youtube.com/embed/', $embedUrl);
                $embedUrl = explode('?', $embedUrl)[0];
            } elseif (str_contains($embedUrl, 'drive.google.com/file/d/')) {
                preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $embedUrl, $matches);
                if (!empty($matches[1])) {
                    $isDrive = true;
                    $driveId = $matches[1];
                }
                $embedUrl = preg_replace('/\/view.*/', '/preview', $embedUrl);
            }
        } else {
            // For hosted files, it's safer to just return a signed temporary url to preview
            $embedUrl = Storage::disk('r2')->temporaryUrl($file->file_path, now()->addMinutes(30));
        }

        return view('tenant.digital-library.preview-frame', compact('embedUrl', 'isDrive', 'driveId'));
    }

    /**
     * Administrative Reassignment
     * Moves all files associated with a product title to a new Brand/Category.
     */
    public function reassign(Request $request)
    {
        $request->validate([
            'current_title' => 'required',
            'new_brand' => 'required',
            'new_sub_category' => 'required'
        ]);

        try {
            LibraryFile::where('title', $request->current_title)
                ->update([
                    'brand' => $request->new_brand,
                    'sub_category' => $request->new_sub_category
                ]);

            return response()->json(['message' => 'Product successfully relocated within the vault.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Reassignment failed: ' . $e->getMessage()], 500);
        }
    }

    public function deleteProduct(Request $request)
    {
        $request->validate(['title' => 'required']);
        
        // Ensure only admin can delete
        if (!auth()->user()->hasRole(['admin', 'Admin', 'super_admin'])) {
            return response()->json(['error' => 'Unauthorized. Only Administrators can delete vault documents.'], 403);
        }

        try {
            $files = LibraryFile::where('title', $request->title)->get();
            if ($files->isEmpty()) {
                return response()->json(['error' => 'Asset not found.'], 404);
            }

            foreach($files as $file) {
                if ($file->file_path) {
                    try {
                        Storage::disk('r2')->delete($file->file_path);
                    } catch (\Exception $e) {
                        Log::warning("Failed to delete R2 file: " . $e->getMessage());
                    }
                }
                $file->delete();
            }

            return response()->json(['message' => 'Document and associated assets successfully deleted from the vault.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }

    public function markLearnCompleted(Request $request)
    {
        $request->validate([
            'library_file_id' => 'required|exists:library_files,id',
            'user_id' => 'required|exists:users,id',
            'points' => 'nullable|numeric|min:1'
        ]);

        try {
            $file = LibraryFile::findOrFail($request->library_file_id);
            if ($file->sub_category !== \App\Console\Commands\MigrateLearnToVideoCommand::LEARN_CATEGORY) {
                return response()->json(['error' => 'Only Learn @ HiTech sessions can be marked as completed.'], 400);
            }

            $userId = $request->user_id;
            $points = $request->points ?? 5;

            // Check if already completed
            $existing = \App\Models\LearnHitechLog::where('library_file_id', $file->id)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                return response()->json(['error' => 'This user has already been awarded points for this session.'], 400);
            }

            \App\Models\LearnHitechLog::create([
                'library_file_id' => $file->id,
                'user_id' => $userId,
                'awarded_by_id' => auth()->id(),
                'awarded_points' => $points,
                'tenant_id' => auth()->user()->tenant_id ?? 1
            ]);

            // Update user's current appraisal scorecard
            $scorecard = \App\Models\AppraisalScorecard::where('user_id', $userId)
                ->latest('id')
                ->first();

            if ($scorecard) {
                $scorecard->learn_hitech_programs += 1;
                $scorecard->learn_hitech_score += $points;
                
                // Recalculate total score
                $scorecard->total_score = $scorecard->revenue_score + $scorecard->pipeline_score + 
                    $scorecard->yoy_growth_score + $scorecard->demos_score + $scorecard->new_customer_score + 
                    $scorecard->ninety_day_goal_score + $scorecard->sales_pitch_score + $scorecard->learn_hitech_score + 
                    $scorecard->seminars_score + $scorecard->competitor_insights_score + $scorecard->product_ideas_score + 
                    $scorecard->cost_of_selling_score + $scorecard->cross_sell_score + $scorecard->market_share_score + 
                    $scorecard->attrition_penalty;
                    
                $scorecard->save();
            }

            return response()->json(['message' => 'Points successfully awarded to the salesperson.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to assign completion: ' . $e->getMessage()], 500);
        }
    }

    public function scorePresenter(Request $request)
    {
        $request->validate([
            'library_file_id' => 'required|exists:library_files,id',
            'presenter_id' => 'required|exists:users,id',
            'points' => 'required|numeric|min:1'
        ]);

        try {
            $file = LibraryFile::findOrFail($request->library_file_id);
            if ($file->sub_category !== \App\Console\Commands\MigrateLearnToVideoCommand::LEARN_CATEGORY) {
                return response()->json(['error' => 'Only Learn @ HiTech sessions can be scored for presenters.'], 400);
            }

            if ($file->presenter_id != $request->presenter_id) {
                return response()->json(['error' => 'Presenter ID mismatch.'], 400);
            }

            $userId = $request->presenter_id;
            $points = $request->points;

            // Optional: Prevent scoring multiple times by checking logs if needed
            // But let's assume a presenter could receive additional bonus points or it's a one-time score.
            $existing = \App\Models\LearnHitechLog::where('library_file_id', $file->id)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                return response()->json(['error' => 'This presenter has already been scored for this session.'], 400);
            }

            // Create log entry for presenter score (assuming we can use LearnHitechLog with a flag, or just log it normally)
            // If the schema doesn't have is_presenter_score, we will just use the log normally
            // To differentiate, maybe we can add a note if there's a field for it, or just use normal logging.
            // Since we didn't add a new column, we'll just log it.
            \App\Models\LearnHitechLog::create([
                'library_file_id' => $file->id,
                'user_id' => $userId,
                'awarded_by_id' => auth()->id(),
                'awarded_points' => $points,
                'tenant_id' => auth()->user()->tenant_id ?? 1
            ]);

            // Update user's current appraisal scorecard
            $scorecard = \App\Models\AppraisalScorecard::where('user_id', $userId)
                ->latest('id')
                ->first();

            if ($scorecard) {
                // We'll add the points to seminars_score or learn_hitech_score
                $scorecard->seminars_score += $points;
                $scorecard->learn_hitech_programs += 1;
                
                // Recalculate total score
                $scorecard->total_score = $scorecard->revenue_score + $scorecard->pipeline_score + 
                    $scorecard->yoy_growth_score + $scorecard->demos_score + $scorecard->new_customer_score + 
                    $scorecard->ninety_day_goal_score + $scorecard->sales_pitch_score + $scorecard->learn_hitech_score + 
                    $scorecard->seminars_score + $scorecard->competitor_insights_score + $scorecard->product_ideas_score + 
                    $scorecard->cost_of_selling_score + $scorecard->cross_sell_score + $scorecard->market_share_score + 
                    $scorecard->attrition_penalty;
                    
                $scorecard->save();
            }

            return response()->json(['message' => 'Score successfully awarded to the presenter.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to score presenter: ' . $e->getMessage()], 500);
        }
    }
}
