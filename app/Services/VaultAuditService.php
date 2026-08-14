<?php

namespace App\Services;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use OpenAI;

/**
 * The Digital Library's AI audit — one place for the prompt, the model call and
 * the parsing, shared by the upload dialog and the background backlog.
 *
 * The dialog wants an answer while someone is watching, so it passes a short
 * timeout and accepts "not this time". The backlog command has nobody waiting
 * and passes a generous one. Both must classify identically, which is why the
 * prompt lives here rather than being copied into each caller.
 */
class VaultAuditService
{
    /** Marks a file whose crux has not been extracted yet. */
    public const PENDING_SUMMARY = 'Processing strategic crux...';

    /**
     * Ask the model to classify a document.
     *
     * @return array{status:string, brand?:string, sub_category?:string, category?:string, name?:string, date?:string, summary?:string, message?:string}
     *         status is 'ok', 'timeout' (hand it to the backlog), 'invalid'
     *         (the model rejected the document) or 'error'.
     */
    public function audit(string $textSample, string $originalName, int $timeout): array
    {
        try {
            $client = OpenAI::factory()
                ->withApiKey(config('openai.api_key'))
                ->withBaseUri(config('openai.base_url'))
                // The packaged client has no timeout of its own; without this a
                // slow provider queue holds the request open indefinitely.
                ->withHttpClient(new GuzzleClient(['timeout' => $timeout, 'connect_timeout' => 10]))
                ->make();

            $resp = $client->chat()->create([
                'model' => config('openai.ai_model'),
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => "Filename: {$originalName}\nText Sample: {$textSample}"],
                ],
            ]);
        } catch (ConnectException $e) {
            return ['status' => 'timeout', 'message' => 'Connection to the AI service timed out.'];
        } catch (\Throwable $e) {
            // Guzzle reports a read timeout as a RequestException wrapping a
            // cURL 28, which is a wait rather than a real failure — treat it as
            // "ask again later" instead of an error the user has to act on.
            if ($this->looksLikeTimeout($e)) {
                return ['status' => 'timeout', 'message' => 'The AI service did not answer in time.'];
            }

            Log::error("VAULT_AUDIT_ERROR [{$originalName}]: " . $e->getMessage());

            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        $respArray = json_decode(json_encode($resp), true);

        if (!isset($respArray['choices'][0]['message']['content'])) {
            Log::error('VAULT_AUDIT_MALFORMED: ' . json_encode($respArray));

            return ['status' => 'error', 'message' => 'The AI returned an unexpected response structure.'];
        }

        $result = $respArray['choices'][0]['message']['content'];
        Log::info("AI RAW AUDIT [{$originalName}]: {$result}");

        if (str_contains(strtoupper($result), 'INVALID') && strlen($result) < 20) {
            return [
                'status'  => 'invalid',
                'message' => 'AI Guardian: Could not verify document as a valid technical asset. Please ensure the PDF is not scouted or image-only.',
            ];
        }

        return $this->parse($result, $originalName);
    }

    /** Split the pipe-delimited reply, falling back field by field. */
    private function parse(string $result, string $originalName): array
    {
        $parts = explode('|', $result);

        $summary = trim($parts[5] ?? '');
        if ($summary === '') {
            $summary = count($parts) > 1 ? trim((string) end($parts)) : 'Technical summary extracted.';
        }

        $date = trim($parts[4] ?? '');

        return [
            'status'       => 'ok',
            'brand'        => trim($parts[0] ?? '') ?: 'HITECH',
            'sub_category' => trim($parts[1] ?? '') ?: 'Industrial Assets',
            'category'     => trim($parts[2] ?? '') ?: 'TDS',
            'name'         => trim($parts[3] ?? '') ?: $originalName,
            // The model often answers "Unknown" or leaves this blank; the file
            // still needs a usable date.
            'date'         => ($date === '' || strcasecmp($date, 'Unknown') === 0) ? date('Y-m-d') : $date,
            'summary'      => $summary,
        ];
    }

    /** Pull readable text out of a PDF, capped at what the model needs. */
    public function extractText(string $path): string
    {
        $pdf  = (new \Smalot\PdfParser\Parser())->parseFile($path);
        $text = mb_convert_encoding($pdf->getText(), 'UTF-8', 'UTF-8');

        return mb_substr($text, 0, 8000);
    }

    private function looksLikeTimeout(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        foreach (['timed out', 'timeout', 'operation too slow'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function systemPrompt(): string
    {
        return "You are an expert technical document auditor for Hitech HRX.
                    Categories: TDS (Technical Data Sheet), SDS (Safety Data Sheet), Presentation (Corporate Presentation), Test Report, Comparison Report.
                    Brands: RUST-X, Dr.Bio, KIF, Fillezy, Tuffpaulin, ZOrbit, HITECH.
                    Sub-Categories: Cleaners, Cutting Oil, Coatings, VCI Packaging, Rust Preventive Oils, VCI Emitters, Steel Coil Packaging, VCI Sprays, Zorbit Desiccant, Rust Removers & Converters, Industrial Lubricants, VCI Masterbatch, Data Logger.

                    STRICT INSTRUCTIONS:
                    - You MUST identify the document type.
                    - Identify the BRAND from the list above. If not found, use 'HITECH'.
                    - Identify the SUB-CATEGORY from the list above. If not explicitly found, categorize it into the most logical one.
                    - If it looks like a technical asset (Chemical TDS, Industrial SDS, Corporate Presentation), do NOT reject it.
                    - Extract: BRAND|SUB_CATEGORY|CATEGORY|NAME|DATE|CRUX
                    - CATEGORY MUST be one of (TDS, SDS, Presentation, Test Report, Comparison Report).
                    - CRUX Rules (Must be 5-6 lines total, PLAIN TEXT ONLY):
                      - NO markdown, NO asterisks, NO bolding, NO numbered lists.
                      - Lines 1-3: Clear Product Description.
                      - Lines 4-5: Features & Key Applications.
                      - Line 6: Technical crux (Safety/Metric).
                    - If completely garbage or unrelated to business/industry, reply ONLY 'INVALID'.";
    }
}
