<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\User;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use Carbon\Carbon;

class PipelineImportController extends Controller
{
    public function showImport()
    {
        $user = auth()->user();
        $isCcare = $user->department && str_contains(strtolower($user->department->name), 'customer care');
        $isNewBiz = $user->department && str_contains(strtolower($user->department->name), 'new biz');

        $query = User::where('status', 'active');

        if (($isCcare || $isNewBiz) && !$user->hasRole(['admin', 'Admin'])) {
            $taggedSalespersonIds = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)
                ->pluck('sales_user_id')->filter()->unique();

            $query->whereIn('id', $taggedSalespersonIds);
        } else {
            $query->whereHas('department', function($q) {
                $q->where('name', 'like', '%Sales%');
            });
        }

        $salespersons = $query->get();

        return view('tenant.sales-pipeline.import', compact('salespersons'));
    }

    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
            'salesperson_id' => 'required|exists:users,id'
        ]);

        try {
            // Keep the file temporarily in storage
            $path = $request->file('file')->storeAs('temp', 'pipeline_matrix_'.time().'.'.$request->file('file')->getClientOriginalExtension());
            $fullPath = storage_path('app/' . $path);
            $data = Excel::toArray(new \stdClass(), $fullPath)[0];
        } catch (\Exception $e) {
            return back()->with('error', 'Error reading file: ' . $e->getMessage());
        }

        if (count($data) < 5) {
            return back()->with('error', 'File does not contain enough rows to match the expected Matrix format.');
        }

        $dataStartRowIdx = $request->input('data_start_row', 4); // Row 5 (0-indexed), skips blank + 2 header rows + totals row

        $monthRow = $data[1] ?? [];  // Row 2: month labels (JUNE 26-27, MAY 26-27, ...)
        $metricRow = $data[2] ?? []; // Row 3: column names (S.No., Party Name, SALE, PENDING, ...)
        
        // Extract headers from Row 3 (index 2) for Zoho-style mapping
        $headers = [];
        foreach ($metricRow as $idx => $val) {
            $headers[] = [
                'index' => $idx,
                'name' => trim($val) ?: "Column " . chr(65 + ($idx % 26)) // Fallback if empty
            ];
        }

        // Auto-detect party and potential columns if not explicitly posted
        $partyColIdx = $request->input('party_col');
        $potentialColIdx = $request->input('potential_col');

        if ($partyColIdx === null) {
            foreach ($headers as $h) {
                if (str_contains(strtolower($h['name']), 'party')) {
                    $partyColIdx = $h['index']; break;
                }
            }
            if ($partyColIdx === null) $partyColIdx = 1; // Default Col B
        }

        if ($potentialColIdx === null) {
            foreach ($headers as $h) {
                if (str_contains(strtolower($h['name']), 'potential')) {
                    $potentialColIdx = $h['index']; break;
                }
            }
            if ($potentialColIdx === null) $potentialColIdx = 2; // Default Col C
        }

        $typeColIdx = null;
        foreach ($headers as $h) {
            $name = strtolower($h['name']);
            if (str_contains($name, 'ccare') || str_contains($name, 'newbiz') || str_contains($name, 'new biz')) {
                $typeColIdx = $h['index']; break;
            }
        }
        if ($typeColIdx === null) $typeColIdx = 3; // Default Col D

        // Parse month columns mapped to their indices
        $monthMappings = [];
        $currentParsedMonth = null;
        $totalMonthsDetected = 0;

        for ($i = 0; $i < count($monthRow); $i++) {
            $monthRaw = trim($monthRow[$i] ?? '');
            
            if ($monthRaw) {
                $parsed = $this->parseFinancialMonthYear($monthRaw);
                if ($parsed) {
                    $currentParsedMonth = $parsed;
                    $totalMonthsDetected++;
                }
            }

            if ($currentParsedMonth) {
                $metric = strtolower(trim($metricRow[$i] ?? ''));
                if (in_array($metric, ['sale', 'pending', 'forecast', 'remarks'])) {
                    if (!isset($monthMappings[$currentParsedMonth])) {
                        $monthMappings[$currentParsedMonth] = [];
                    }
                    $monthMappings[$currentParsedMonth][$metric] = $i;
                }
            }
        }

        // All detected months for preview
        $previewMonthKeys = array_keys($monthMappings);

        // Preview First 10 Parties
        $previewData = [];
        for ($r = $dataStartRowIdx; $r < min(count($data), $dataStartRowIdx + 15); $r++) {
            $row = $data[$r];
            $partyName = trim($row[$partyColIdx] ?? '');
            if (empty($partyName) || strtolower($partyName) == 'total') continue;

            $potential = (float) str_replace(',', '', $row[$potentialColIdx] ?? 0);
            $type = strtolower(trim($row[$typeColIdx] ?? ''));

            $monthSales = [];
            foreach ($previewMonthKeys as $mk) {
                $saleIdx = $monthMappings[$mk]['sale'] ?? null;
                $monthSales[$mk] = $saleIdx !== null ? (float) str_replace(',', '', $row[$saleIdx] ?? 0) : null;
            }

            $previewData[] = [
                'row_num'    => $r + 1,
                'party_name' => $partyName,
                'potential'  => $potential,
                'type'       => $type ?: '—',
                'months'     => $monthSales,
            ];
            if (count($previewData) >= 10) break;
        }

        $salesperson = User::find($request->salesperson_id);

        return view('tenant.sales-pipeline.import-preview', compact(
            'previewData', 'totalMonthsDetected', 'monthMappings', 'path', 'salesperson',
            'partyColIdx', 'potentialColIdx', 'typeColIdx', 'dataStartRowIdx', 'headers', 'previewMonthKeys'
        ));
    }

    public function processImport(Request $request)
    {
        $request->validate([
            'temp_path' => 'required',
            'salesperson_id' => 'required|exists:users,id',
            'party_col' => 'required|numeric',
            'potential_col' => 'required|numeric',
            'type_col' => 'nullable|numeric',
            'data_start_row' => 'required|numeric',
            'month_mappings' => 'required|json'
        ]);

        $fullPath = storage_path('app/' . $request->temp_path);
        if (!file_exists($fullPath)) {
            return redirect()->route('sales-visits.pipeline.import')->with('error', 'Temporary file expired. Please upload again.');
        }

        try {
            $data = Excel::toArray(new \stdClass(), $fullPath)[0];
        } catch (\Exception $e) {
            return redirect()->route('sales-visits.pipeline.import')->with('error', 'Error reading file: ' . $e->getMessage());
        }

        $monthMappings = json_decode($request->month_mappings, true);
        $salespersonId = $request->salesperson_id;
        $partyColIdx = $request->party_col;
        $potentialColIdx = $request->potential_col;
        $typeColIdx = $request->type_col ?? 3;
        $dataStartRowIdx = $request->data_start_row;

        // FY key map for YoY totals (fyStart year → DB column). FY 26-27 shown via monthly actuals, not stored here.
        $fyKeyMap = [2022 => 'yoy_22_23', 2023 => 'yoy_23_24', 2024 => 'yoy_24_25', 2025 => 'yoy_25_26'];

        // Apply potential overrides from preview screen if user edited them
        $potentialOverrides = $request->input('potentials', []);
        $currentActualMonth = Carbon::now()->format('Y-m');

        $user = auth()->user();
        $isCcare = $user->department && str_contains(strtolower($user->department->name), 'customer care');
        $isNewBiz = $user->department && str_contains(strtolower($user->department->name), 'new biz');

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        for ($r = $dataStartRowIdx; $r < count($data); $r++) {
            $rowNum = $r + 1;
            $row = $data[$r];

            $partyName = trim($row[$partyColIdx] ?? '');
            if (empty($partyName) || strtolower($partyName) == 'total') {
                continue;
            }

            // CSV stores potential in lakhs; DB expects rupees (display divides by 100000)
            $potential = (float) str_replace(',', '', $row[$potentialColIdx] ?? 0) * 100000;

            if (isset($potentialOverrides[$rowNum])) {
                $potential = (float) $potentialOverrides[$rowNum] * 100000;
            }

            $typeRaw = strtolower(trim($row[$typeColIdx] ?? ''));
            $typeMap = ['ccare' => 'CCare', 'newbiz' => 'New Biz', 'closed' => 'Closed', 'dropped' => 'Dropped', 'inactive' => 'Inactive'];
            $partyType = $typeMap[$typeRaw] ?? null;

            try {
                $pipeline = SalesPipeline::updateOrCreate(
                    [
                        'party_name' => $partyName,
                        'salesperson_id' => $salespersonId
                    ],
                    [
                        'total_business_potential' => $potential,
                        'type' => $partyType,
                        'is_locked' => true,
                    ]
                );

                // Insert months + accumulate YoY
                $yoyTotals = ['yoy_22_23' => 0.0, 'yoy_23_24' => 0.0, 'yoy_24_25' => 0.0, 'yoy_25_26' => 0.0];

                foreach ($monthMappings as $monthYear => $indices) {
                    $saleVal = 0;
                    $pendingVal = 0;
                    $forecastVal = 0;
                    $remarksVal = null;

                    if (isset($indices['sale'])) {
                        $saleVal = (float) str_replace(',', '', $row[$indices['sale']] ?? 0);
                    }
                    if (isset($indices['pending'])) {
                        $pendingVal = (float) str_replace(',', '', $row[$indices['pending']] ?? 0);
                    }
                    if (isset($indices['forecast'])) {
                        $forecastVal = (float) str_replace(',', '', $row[$indices['forecast']] ?? 0);
                    }
                    if (isset($indices['remarks'])) {
                        $remarksVal = trim($row[$indices['remarks']] ?? null);
                    }

                    // Accumulate YoY by financial year (April = FY start)
                    if ($saleVal > 0) {
                        [$y, $m] = explode('-', $monthYear);
                        $fyStart = ((int)$m >= 4) ? (int)$y : (int)$y - 1;
                        if (isset($fyKeyMap[$fyStart])) {
                            $yoyTotals[$fyKeyMap[$fyStart]] += $saleVal;
                        }
                    }

                    // Enforce rule: Only save pending and remarks for the current calendar month
                    if ($monthYear !== $currentActualMonth) {
                        $pendingVal = 0;
                        $remarksVal = null;
                    }

                    if ($saleVal > 0 || $pendingVal > 0 || $forecastVal > 0 || !empty($remarksVal)) {
                        SalesPipelineMonth::updateOrCreate(
                            [
                                'sales_pipeline_id' => $pipeline->id,
                                'month_year' => $monthYear
                            ],
                            [
                                'sale_amount' => $saleVal,
                                'pending_amount' => $pendingVal,
                                'forecast_amount' => $forecastVal,
                                'remarks' => $remarksVal
                            ]
                        );
                    }
                }

                // Update YoY totals on the pipeline record
                $pipeline->update($yoyTotals);

                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "Row {$rowNum} ({$partyName}): " . $e->getMessage();
            }
        }

        // Cleanup
        @unlink($fullPath);

        $msg = "Successfully processed {$successCount} parties/pipelines.";
        if ($errorCount > 0) {
            $msg .= " Failed on {$errorCount} rows.";
            return redirect()->route('sales-visits.pipeline.index')->with('warning', $msg)->with('import_errors', $errors);
        }

        return redirect()->route('sales-visits.pipeline.index')->with('success', $msg);
    }

    private function parseFinancialMonthYear($raw)
    {
        $raw = strtoupper(trim(preg_replace('/\s+/', ' ', $raw)));
        if (preg_match('/([A-Z]+)\s+(\d{2})\s*-\s*(\d{2})/', $raw, $matches)) {
            $monthStr = $matches[1];
            $startYearShort = $matches[2];
            
            $months = [
                'JAN' => '01', 'JANUARY' => '01',
                'FEB' => '02', 'FEBRUARY' => '02',
                'MAR' => '03', 'MARCH' => '03',
                'APR' => '04', 'APRIL' => '04',
                'MAY' => '05',
                'JUN' => '06', 'JUNE' => '06',
                'JUL' => '07', 'JULY' => '07',
                'AUG' => '08', 'AUGUST' => '08',
                'SEP' => '09', 'SEPTEMBER' => '09',
                'OCT' => '10', 'OCTOBER' => '10',
                'NOV' => '11', 'NOVEMBER' => '11',
                'DEC' => '12', 'DECEMBER' => '12',
            ];

            if (!isset($months[$monthStr])) {
                return null;
            }

            $monthNum = $months[$monthStr];
            $startYear = 2000 + (int)$startYearShort;

            if (in_array($monthNum, ['01', '02', '03'])) {
                $actualYear = $startYear + 1;
            } else {
                $actualYear = $startYear;
            }

            return "{$actualYear}-{$monthNum}";
        }
        return null;
    }
}
