<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\User;
use App\Models\SalespersonTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SalesTargetImportController extends Controller
{
    /**
     * Show the bulk import upload form
     */
    public function showImport()
    {
        $user = auth()->user();
        $isCcare = $user->department && str_contains(strtolower($user->department->name), 'customer care');
        $isNewBiz = $user->department && str_contains(strtolower($user->department->name), 'new biz');

        $query = User::where('status', 'active');
        if ($user->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
            // Admin and HR can see all active users
        } elseif (($isCcare || $isNewBiz)) {
            $taggedSalespersonIds = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)
                ->pluck('sales_user_id')->filter()->unique();
            $query->whereIn('id', $taggedSalespersonIds);
        } else {
            $query->whereHas('department', function($q) {
                $q->where('name', 'like', '%Sales%');
            });
        }
        $salespersons = $query->orderBy('first_name')->get();

        return view('tenant.sales-targets.import', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'salespersons' => $salespersons
        ]);
    }

    /**
     * Read the uploaded file, parse headers, and suggest column mappings
     */
    public function previewImport(Request $request)
    {
        $request->validate([
            'salesperson_id' => 'required|exists:users,id',
            'file' => 'required|mimes:xlsx,xls,csv|max:10240'
        ]);

        $file = $request->file('file');
        
        try {
            $data = Excel::toArray(new \stdClass(), $file)[0];
        } catch (\Exception $e) {
            return back()->with('error', 'Error reading file: ' . $e->getMessage());
        }

        if (count($data) < 2) {
            return back()->with('error', 'File seems to be empty or missing data rows.');
        }

        // The first row should be headers
        $headers = array_shift($data);
        
        // Remove completely empty columns from headers
        $validHeaders = [];
        $validHeaderIndexes = [];
        foreach ($headers as $index => $header) {
            if (trim($header) !== '') {
                $validHeaders[] = trim($header);
                $validHeaderIndexes[] = $index;
            }
        }

        // Filter the data to only include valid columns
        $previewData = [];
        foreach (array_slice($data, 0, 5) as $row) {
            $filteredRow = [];
            foreach ($validHeaderIndexes as $idx) {
                $filteredRow[] = $row[$idx] ?? '';
            }
            $previewData[] = $filteredRow;
        }

        // Smart Mapping Logic
        $suggestedMappings = [
            'month_year' => '',
            'kingo_target' => '',
            'bingo_target' => ''
        ];

        foreach ($validHeaders as $index => $header) {
            $lowerHeader = strtolower($header);
            
            // Month Year
            if (str_contains($lowerHeader, 'month') || str_contains($lowerHeader, 'year') || str_contains($lowerHeader, 'period') || str_contains($lowerHeader, 'date')) {
                if ($suggestedMappings['month_year'] === '') $suggestedMappings['month_year'] = $index;
            }

            // Kingo Target
            if (str_contains($lowerHeader, 'kingo') || str_contains($lowerHeader, 'king')) {
                if ($suggestedMappings['kingo_target'] === '') $suggestedMappings['kingo_target'] = $index;
            }

            // Bingo Target
            if (str_contains($lowerHeader, 'bingo') || str_contains($lowerHeader, 'bing')) {
                if ($suggestedMappings['bingo_target'] === '') $suggestedMappings['bingo_target'] = $index;
            }
        }

        // Store file path in session for the next step
        $path = $file->store('temp_imports');

        return view('tenant.sales-targets.import-preview', [
            'headers' => $validHeaders,
            'previewData' => $previewData,
            'salesperson_id' => $request->salesperson_id,
            'suggestedMappings' => $suggestedMappings,
            'filePath' => $path,
            'pageConfigs' => ['contentLayout' => 'wide']
        ]);
    }

    /**
     * Process the confirmed mapping and import the data
     */
    public function storeImport(Request $request)
    {
        $request->validate([
            'salesperson_id' => 'required|exists:users,id',
            'file_path' => 'required',
            'mapping' => 'required|array',
            'mapping.month_year' => 'required|numeric',
        ]);

        $rawFilePath = $request->file_path;
        if (!str_starts_with($rawFilePath, 'temp_imports/')) {
            return redirect()->route('sales-targets.import')->with('error', 'Invalid file reference.');
        }
        $filePath = storage_path('app/' . $rawFilePath);

        if (!file_exists($filePath)) {
            return redirect()->route('sales-targets.import')->with('error', 'Uploaded file was lost. Please try uploading again.');
        }

        try {
            $data = Excel::toArray(new \stdClass(), $filePath)[0];
        } catch (\Exception $e) {
            return redirect()->route('sales-targets.import')->with('error', 'Error reading file during import.');
        }

        $headers = array_shift($data); // Remove headers
        
        $mapping = $request->mapping;
        $salespersonId = $request->salesperson_id;
        $monthIdx = $mapping['month_year'];
        $kingoIdx = $mapping['kingo_target'] ?? null;
        $bingoIdx = $mapping['bingo_target'] ?? null;

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $consecutiveEmptyRows = 0;
        foreach ($data as $rowIndex => $row) {
            $rowNum = $rowIndex + 2; // +1 for 0-index, +1 for header row
            
            $monthRaw = trim($row[$monthIdx] ?? '');
            
            if (empty($monthRaw)) {
                $consecutiveEmptyRows++;
                if ($consecutiveEmptyRows > 15) {
                    break;
                }
                $errorCount++;
                $errors[] = "Row {$rowNum}: Missing Month.";
                continue;
            }
            $consecutiveEmptyRows = 0;

            $userId = $salespersonId;

            // Parse Date into YYYY-MM
            $parsedDate = $this->parseMonthYear($monthRaw);
            if (!$parsedDate) {
                $errorCount++;
                $errors[] = "Row {$rowNum}: Invalid date format '{$monthRaw}'. Please use YYYY-MM, MM/YYYY, or Jan 2026 format.";
                continue;
            }

            // Parse Targets
            $kingo = 0;
            if ($kingoIdx !== null && $kingoIdx !== '') {
                $kingo = (float) str_replace(',', '', $row[$kingoIdx] ?? 0);
            }

            $bingo = 0;
            if ($bingoIdx !== null && $bingoIdx !== '') {
                $bingo = (float) str_replace(',', '', $row[$bingoIdx] ?? 0);
            }

            // Upsert Target
            try {
                SalespersonTarget::updateOrCreate(
                    [
                        'salesperson_id' => $userId,
                        'month_year' => $parsedDate
                    ],
                    [
                        'kingo_target' => $kingo,
                        'bingo_target' => $bingo
                    ]
                );
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "Row {$rowNum}: Database error - " . $e->getMessage();
            }
        }

        // Cleanup temp file
        @unlink($filePath);

        $msg = "Successfully imported {$successCount} targets.";
        if ($errorCount > 0) {
            $msg .= " Failed to import {$errorCount} targets.";
            return redirect()->route('sales-targets.import')->with('warning', $msg)->with('import_errors', $errors);
        }

        return redirect()->route('sales-targets.import')->with('success', $msg);
    }

    /**
     * Smartly parse different month/year formats into YYYY-MM
     */
    private function parseMonthYear($raw)
    {
        // Financial Year format like "APRIL 21-22" or "MARCH 21-22"
        if (preg_match('/^([A-Za-z]+)\s+(\d{2})-(\d{2})$/', trim($raw), $matches)) {
            $monthStr = strtolower($matches[1]);
            $yy1 = (int)$matches[2];
            $yy2 = (int)$matches[3];
            
            $monthNum = (int)date('m', strtotime($monthStr . ' 1 2000'));
            
            if ($monthNum >= 4) { // April to December
                $year = 2000 + $yy1;
            } else { // January to March
                $year = 2000 + $yy2;
            }
            
            return sprintf('%04d-%02d', $year, $monthNum);
        }

        // If it's an Excel serial date number
        if (is_numeric($raw) && $raw > 10000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($raw)->format('Y-m');
            } catch (\Exception $e) {
                // fallback below
            }
        }

        // Already YYYY-MM
        if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
            return $raw;
        }

        // Common formats
        $formats = [
            'Y-m', 'Y-m-d', 'd/m/Y', 'm/d/Y', 'm/Y', 'Y/m', 
            'M Y', 'M y', 'F Y', 'd M Y', 'd-M-Y', 'Y-M-d'
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $raw);
                if ($date !== false) {
                    return $date->format('Y-m');
                }
            } catch (\Exception $e) {
                // Ignore and try next format
            }
        }
        
        // Final fallback using strtotime
        $timestamp = strtotime($raw);
        if ($timestamp !== false) {
            return date('Y-m', $timestamp);
        }

        return null;
    }
}
