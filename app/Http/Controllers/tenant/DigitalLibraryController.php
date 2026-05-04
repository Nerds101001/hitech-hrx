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

        if ($category && $category != 'all') {
            $query->where('category', $category);
        }

        $allFiles = $query->orderBy('created_at', 'desc')->get();
        
        // Grouping logic for the Matrix View - Normalized to match taxonomy names
        $groupedFiles = $allFiles->groupBy(fn($f) => strtolower($f->brand))->map(function ($brandFiles) {
            return $brandFiles->groupBy('sub_category')->map(function ($subCatFiles) {
                return $subCatFiles->groupBy('title');
            });
        });

        // Fetch dynamic taxonomy from DB
        $brands = \App\Models\LibraryTaxonomy::where('type', 'brand')->orderBy('order')->get();
        $taxonomies = \App\Models\LibraryTaxonomy::where('type', 'category')->get()->groupBy('parent_id');

        return view('tenant.digital-library.index', compact('groupedFiles', 'category', 'brands', 'taxonomies'));
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
            $taxonomy = \App\Models\LibraryTaxonomy::create($request->all());
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
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($file->getPathname());
            
            // Extract text with clean encoding
            $text = mb_convert_encoding($pdf->getText(), 'UTF-8', 'UTF-8');
            $textSample = mb_substr($text, 0, 8000); // Increased for better accuracy

            if (strlen(trim($textSample)) < 100) {
                Log::warning("DIGITAL_LIBRARY_OCR_NEEDED: Small text extracted from " . $originalName);
                // We'll try to let it pass if it's a valid file, but note it
            }

            $factory = OpenAI::factory();
            $client = $factory->withApiKey(config('openai.api_key'))
                              ->withBaseUri(config('openai.base_url'))
                              ->make();

            // Request AI Audit
            $resp = $client->chat()->create([
                'model' => config('openai.ai_model'),
                'messages' => [
                    ['role' => 'system', 'content' => "You are an expert technical document auditor for Hitech HRX. 
                    Categories: TDS (Technical Data Sheet), SDS (Safety Data Sheet), MOM (Minutes of Meeting), LEARN (Training).
                    Brands: RUST-X, Dr.Bio, KIF, Fillezy, Tuffpaulin, ZOrbit, HITECH.
                    Sub-Categories: Cleaners, Cutting Oil, Coatings, VCI Packaging, Rust Preventive Oils, VCI Emitters, Steel Coil Packaging, VCI Sprays, Zorbit Desiccant, Rust Removers & Converters, Industrial Lubricants, VCI Masterbatch, Data Logger.
                    
                    STRICT INSTRUCTIONS:
                    - You MUST identify the document type.
                    - Identify the BRAND from the list above. If not found, use 'HITECH'.
                    - Identify the SUB-CATEGORY from the list above. If not explicitly found, categorize it into the most logical one.
                    - If it looks like a technical asset (Chemical TDS, Industrial SDS, Corporate MOM), do NOT reject it.
                    - Extract: BRAND|SUB_CATEGORY|CATEGORY|NAME|DATE|CRUX
                    - CATEGORY MUST be one of (TDS, SDS, MOM, LEARN).
                    - CRUX Rules (Must be 5-6 lines total, PLAIN TEXT ONLY):
                      - NO markdown, NO asterisks, NO bolding, NO numbered lists.
                      - Lines 1-3: Clear Product Description.
                      - Lines 4-5: Features & Key Applications.
                      - Line 6: Technical crux (Safety/Metric).
                    - If completely garbage or unrelated to business/industry, reply ONLY 'INVALID'."],
                    ['role' => 'user', 'content' => "Text Sample: " . $textSample],
                ],
            ]);

            // Robust response handling
            $respArray = json_decode(json_encode($resp), true);
            
            if (!isset($respArray['choices'][0]['message']['content'])) {
                Log::error("DIGITALLIBRARY_AI_MALFORMED_RESPONSE: " . json_encode($respArray));
                throw new \Exception("AI Guardian returned an unexpected response structure. Raw: " . mb_substr(json_encode($respArray), 0, 200));
            }

            $aiResult = $respArray['choices'][0]['message']['content'];
            Log::info("AI RAW AUDIT [" . $originalName . "]: " . $aiResult);

            if (str_contains(strtoupper($aiResult), 'INVALID') && strlen($aiResult) < 20) {
                return response()->json(['error' => 'AI Guardian: Could not verify document as a valid technical asset. Please ensure the PDF is not scouted or image-only.'], 422);
            }

            // Robust Splitting
            $parts = explode('|', $aiResult);
            
            return response()->json([
                'success' => true,
                'brand' => trim($parts[0] ?? 'HITECH'),
                'sub_category' => trim($parts[1] ?? 'Industrial Assets'),
                'category' => trim($parts[2] ?? 'TDS'),
                'name' => trim($parts[3] ?? $originalName),
                'date' => trim($parts[4] ?? date('Y-m-d')),
                'summary' => trim($parts[5] ?? (count($parts) > 1 ? end($parts) : 'Technical summary extracted.')),
                'temp_name' => $originalName,
                'is_duplicate' => !!$duplicate,
                'duplicate_id' => $duplicate?->id
            ]);
        } catch (\Exception $e) {
            Log::error("DIGITALLIBRARY_AUDIT_ERROR [" . $originalName . "]: " . $e->getMessage());
            return response()->json(['error' => 'Audit Error: The PDF might be encrypted or malformed. ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required_without:youtube_url|nullable|mimes:pdf,mp4,mov,avi|max:51200',
            'youtube_url' => 'required_without:file|nullable|url',
            'category' => 'nullable',
            'overwrite' => 'nullable|boolean'
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
            $category = 'Video';
            $summary = "YouTube Digital Asset: " . $youtubeUrl;
        } elseif ($file) {
            // R2 Storage
            try {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('digital-library', $filename, 'r2');
                
                // If overwriting, delete old one
                if ($existing && $request->overwrite) {
                    if ($existing->file_path) {
                        Storage::disk('r2')->delete($existing->file_path);
                    }
                    $existing->delete();
                }
            } catch (\Exception $e) {
                Log::error("DIGITALLIBRARY_STORAGE_ERROR: " . $e->getMessage());
                return response()->json(['error' => 'Vault Connection Failed: ' . $e->getMessage()], 500);
            }
        }

        // --- Final Persistence ---
        $brandName = $request->brand ?? 'HITECH';
        // Match existing taxonomy name case if possible
        $taxBrand = \App\Models\LibraryTaxonomy::where('type', 'brand')->where('name', 'like', $brandName)->first();
        if ($taxBrand) $brandName = $taxBrand->name;

        LibraryFile::create([
            'title' => $productName,
            'brand' => $brandName,
            'sub_category' => $request->sub_category ?? 'Industrial Assets',
            'file_path' => $path,
            'youtube_url' => $youtubeUrl,
            'category' => $category,
            'mime_type' => $mimeType,
            'size' => $size,
            'summary' => $summary,
            'is_public' => $request->has('is_public') || true,
            'tenant_id' => auth()->user()->tenant_id ?? 1,
            'created_by_id' => auth()->id() ?? 1,
        ]);

        return response()->json(['success' => 'Asset secured to Hitech Vault. Type: ' . $category]);
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
        $contextFiles = LibraryFile::select('id', 'title', 'category', 'summary')->get();
        $filteredData = $contextFiles->map(function($f) {
            $accessLink = route('library.access', $f->id);
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
        
        // 🔒 SECURITY FIX: Use signed temporary URLs for private R2 storage
        return redirect()->away(Storage::disk('r2')->temporaryUrl($file->file_path, now()->addMinutes(30)));
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
}
