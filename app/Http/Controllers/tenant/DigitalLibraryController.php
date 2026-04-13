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
        $query = LibraryFile::query();

        if ($category) {
            $query->where('category', $category);
        }

        $libraryFiles = $query->orderBy('created_at', 'desc')->paginate(12);

        return view('tenant.digital-library.index', compact('libraryFiles', 'category'));
    }

    /**
     * Phase 1: AI Content Guardian (Analysis Only)
     * Parses the file and returns a summary/category for user review.
     */
    public function analyze(Request $request)
    {
        $request->validate(['file' => 'required|max:20480']);
        $file = $request->file('file');
        
        $category = 'SDS';
        $summary = "Processing...";

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($file->getPathname());
            // Increase vision to 5000 chars for better context
            $text = mb_substr($pdf->getText(), 0, 5000);

            $factory = OpenAI::factory();
            $client = $factory->withApiKey(env('OPENAI_API_KEY'))
                              ->withBaseUri(env('OPENAI_BASE_URL', 'https://integrate.api.nvidia.com/v1'))
                              ->make();

            $resp = $client->chat()->create([
                'model' => env('AI_MODEL', 'openai/gpt-oss-120b'),
                'messages' => [
                    ['role' => 'system', 'content' => "You are an expert technical document auditor for Hitech HRX. 
                    Categories: TDS, SDS, MOM, LEARN.
                    
                    STRICT INSTRUCTIONS:
                    - Extract: CATEGORY|NAME|DATE|CRUX
                    - CRUX Rules (Must be 5-6 lines total, PLAIN TEXT ONLY):
                      - NO markdown, NO asterisks (**), NO bolding, NO numbered lists.
                      - Lines 1-3: Clear Product Description (What is it?).
                      - Lines 4-5: Features & Key Applications.
                      - Line 6: Technical/Chemical crux (Safety or Metric).
                    - If invalid, reply ONLY 'INVALID'."],
                    ['role' => 'user', 'content' => "Text Sample: " . $text],
                ],
            ]);

            $aiResult = $resp->choices[0]->message->content ?? 'INVALID';
            
            // 🛡️ Strategic Logging: See exactly what the AI is thinking
            Log::info("AI RAW AUDIT: " . $aiResult);

            if (str_contains(strtoupper($aiResult), 'INVALID') && strlen($aiResult) < 20) {
                return response()->json(['error' => 'AI Guardian: Could not verify document as technical asset.'], 422);
            }

            // Robust Splitting
            $parts = explode('|', $aiResult);
            
            return response()->json([
                'success' => true,
                'category' => trim($parts[0] ?? 'TDS'),
                'name' => trim($parts[1] ?? $file->getClientOriginalName()),
                'date' => trim($parts[2] ?? date('Y-m-d')),
                'summary' => trim($parts[3] ?? (count($parts) > 1 ? end($parts) : 'Technicial summary extracted.')),
                'temp_name' => $file->getClientOriginalName()
            ]);
        } catch (\Exception $e) {
            Log::error("DIGITALLIBRARY_AUDIT_ERROR: " . $e->getMessage());
            return response()->json(['error' => 'Audit Error: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        // 🔒 Explicitly handle validation for AJAX to avoid 302 redirects
        $validator = Validator::make($request->all(), [
            'file' => 'required_without:youtube_url|nullable|mimes:pdf,mp4,mov,avi|max:51200',
            'youtube_url' => 'required_without:file|nullable|url',
            'category' => 'nullable',
        ]);

        if ($validator->fails()) {
            return ($request->ajax() || $request->wantsJson())
                ? response()->json(['error' => 'Input Required: Either a file or a valid YouTube URL must be provided.'], 422)
                : back()->withErrors($validator)->withInput();
        }

        $file = $request->file('file');
        $youtubeUrl = $request->youtube_url;
        $productName = $request->title ?: ($request->title_yt ?: ($file ? $file->getClientOriginalName() : 'YouTube Digital Asset'));
        $summary = "Processing strategic crux...";
        $category = $request->category ?? 'SDS';
        $path = null;
        $mimeType = 'video/youtube';
        $size = 0;

        // --- Case 1: YouTube Video (No AI Audit) ---
        if ($youtubeUrl) {
            $category = 'Video';
            $summary = "YouTube Digital Asset: " . $youtubeUrl;
            $mimeType = 'video/youtube';
            $size = 0;
        } 
        // --- Case 2: File Upload ---
        elseif ($file) {
            $mimeType = $file->getClientMimeType();
            $size = $file->getSize();

            if ($mimeType === 'application/pdf') {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($file->getPathname());
                    $text = mb_substr($pdf->getText(), 0, 4000);

                    $factory = OpenAI::factory();
                    $client = $factory->withApiKey(env('OPENAI_API_KEY'))
                                      ->withBaseUri(env('OPENAI_BASE_URL', 'https://integrate.api.nvidia.com/v1'))
                                      ->make();

                    $verify = $client->chat()->create([
                        'model' => env('AI_MODEL', 'openai/gpt-oss-120b'),
                        'messages' => [
                            ['role' => 'system', 'content' => "Industrial Auditor. Classify as SDS, TDS, MOM (Meeting Minutes), or LEARN (Training).
                            Rules: If valid, reply only with the category key. If invalid, reply 'INVALID'."],
                            ['role' => 'user', 'content' => "Text Sample: " . $text],
                        ],
                    ]);

                    $aiResult = trim(strtoupper($verify->choices[0]->message->content));
                    
                    if ($aiResult === 'INVALID') {
                        return response()->json(['error' => 'Rejected: Content does not match supported technical categories.'], 422);
                    }

                    $category = $aiResult;

                    // Extract Strategic Summary
                    $extract = $client->chat()->create([
                        'model' => env('AI_MODEL', 'openai/gpt-oss-120b'),
                        'messages' => [
                            ['role' => 'system', 'content' => "Extract a 5-6 line summary CRUX. 
                            STRICT RULES:
                            - PLAIN TEXT ONLY. NO BOLDING, NO ASTERISKS (**), NO LISTS.
                            - NO headers like 'Application:' or 'Description:'. Just the raw facts.
                            - Start directly with the product description. 
                            - Use only sentences, no numbering."],
                            ['role' => 'user', 'content' => "Text Sample: " . $text],
                        ],
                    ]);
                    $summary = $extract->choices[0]->message->content ?? "Technical crux extracted.";
                } catch (\Exception $e) { $summary = "Crux extraction pending."; }
            } else {
                $category = 'Video';
                $summary = "Direct video asset storage.";
            }

            // R2 Storage with ORIGINAL NAME
            try {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('digital-library', $filename, 'r2');
            } catch (\Exception $e) {
                return response()->json(['error' => 'Vault Connection Failed: ' . $e->getMessage()], 500);
            }
        }

        // --- Final Persistence ---
        LibraryFile::create([
            'title' => $productName,
            'file_path' => $path,
            'youtube_url' => $youtubeUrl,
            'category' => $category,
            'mime_type' => $mimeType,
            'size' => $size,
            'summary' => $summary,
            'is_public' => $request->has('is_public'),
            'tenant_id' => $request->header('X-Tenant-Id') ?? (auth()->check() ? auth()->user()->tenant_id : 1),
            'created_by_id' => auth()->id() ?? 1,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => 'Document auto-categorized as ' . $category . ' and summarized.']);
        }

        return back()->with('success', 'Document auto-categorized as ' . $category . ' and summarized.');
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
        $client = $factory->withApiKey(env('OPENAI_API_KEY'))
                          ->withBaseUri(env('OPENAI_BASE_URL', 'https://integrate.api.nvidia.com/v1'))
                          ->make();

        foreach ($request->file('files') as $file) {
            if ($file->getClientMimeType() === 'application/pdf') {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($file->getPathname());
                    $text = mb_substr($pdf->getText(), 0, 3000);

                    $verify = $client->chat()->create([
                        'model' => env('AI_MODEL', 'openai/gpt-oss-120b'),
                        'messages' => [
                            ['role' => 'system', 'content' => "Identify document type (SDS/TDS/MOM/LEARN/INVALID). Reply ONLY with the key."],
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
                            'model' => env('AI_MODEL', 'openai/gpt-oss-120b'),
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
        $contextFiles = LibraryFile::select('id', 'title', 'category', 'summary')->get();
        $filteredData = $contextFiles->map(function($f) {
            $accessLink = route('library.access', $f->id);
            return "Product: {$f->title} | Category: {$f->category} | Summary: {$f->summary} | DownloadLink: {$accessLink}";
        })->implode("\n");

        try {
            // Force NVIDIA NIM Factory pattern to bypass any local OpenAI config conflicts
            $factory = OpenAI::factory();
            $client = $factory->withApiKey(env('OPENAI_API_KEY'))
                              ->withBaseUri(env('OPENAI_BASE_URL', 'https://integrate.api.nvidia.com/v1'))
                              ->make();

            // Fetch any custom system instructions from the AI Training center
            $customInstructions = \App\Models\KnowledgeBase::where('category', 'SYSTEM_PROMPT')->first();
            $dynamicInstructions = $customInstructions ? $customInstructions->content : "";

            $systemPrompt = "You are the 'Senior Hitech Consultant', a Technical HR Assistant for Hitech Group.
Your primary directive is to provide accurate information from the Hitech Digital Library.

CORE BRANDING & PERSONA:
- Name: Senior Hitech Consultant
- Company: Hitech Group
- Tone: Professional, helpful, consultative, and precise.

DYNAMIC INSTRUCTIONS & USER RULES (FOLLOW THESE STRICTLY):
{$dynamicInstructions}

CONTEXT FROM DIGITAL LIBRARY:
{$filteredData}

USER REQUEST:
{$userInput}";

            $response = $client->chat()->create([
                'model' => env('AI_MODEL', 'openai/gpt-oss-120b'),
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
        
        // 🔒 SECURITY FIX: Use signed temporary URLs for private R2 storage
        return redirect()->away(Storage::disk('r2')->temporaryUrl($file->file_path, now()->addMinutes(30)));
    }
}
