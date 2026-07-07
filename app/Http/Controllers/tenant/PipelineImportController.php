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

        $ccareUsers = User::where('status', 'active')
            ->whereHas('department', function($q) {
                $q->where('name', 'like', '%Customer Care%');
            })->orderBy('first_name')->get();

        $newBizUsers = User::where('status', 'active')
            ->whereHas('department', function($q) {
                $q->where('name', 'like', '%New Biz%');
            })->orderBy('first_name')->get();

        return view('tenant.sales-pipeline.import', compact('salespersons', 'ccareUsers', 'newBizUsers'));
    }

    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
            'salesperson_id' => 'required|exists:users,id',
            'ccare_id' => 'nullable|exists:users,id',
            'new_biz_id' => 'nullable|exists:users,id'
        ]);

        $path = null;
        try {
            // Keep the file temporarily in storage
            $path = $request->file('file')->storeAs('temp', 'pipeline_matrix_'.time().'.'.$request->file('file')->getClientOriginalExtension());
            $fullPath = storage_path('app/' . $path);
            $data = Excel::toArray(new \stdClass(), $fullPath)[0];
        } catch (\Exception $e) {
            if ($path) @unlink(storage_path('app/' . $path));
            return back()->with('error', 'Error reading file: ' . $e->getMessage());
        }

        if (count($data) < 2) {
            return back()->with('error', 'File does not contain enough rows.');
        }

        // Auto-detect single-row vs 2-row layout
        $isSingleRowHeader = false;
        $firstRow = $data[0] ?? [];
        $inferredYear = null;

        // Scan first row to find year references as a fallback
        foreach ($firstRow as $cell) {
            $cellStr = strtoupper(trim(preg_replace('/\s+/', ' ', (string)$cell)));
            if (preg_match('/(?:\b|-)(\d{2}|\d{4})\b/', $cellStr, $matches)) {
                $yr = (int)$matches[1];
                if ($yr > 20 && $yr < 99) {
                    $inferredYear = 2000 + $yr;
                } elseif ($yr >= 2020 && $yr <= 2040) {
                    $inferredYear = $yr;
                }
            }
        }
        if (!$inferredYear) {
            $inferredYear = (int)date('Y');
        }

        // Count how many combined month-metric columns exist in the first row
        $monthMetricColsCount = 0;
        foreach ($firstRow as $cell) {
            if ($cell) {
                $parsed = $this->parseMonthAndMetricFromHeader((string)$cell, $inferredYear);
                if ($parsed) {
                    $monthMetricColsCount++;
                }
            }
        }

        $partyColIdx = null;
        $potentialColIdx = null;
        $typeColIdx = null;
        $productColIdx = null;
        $monthMappings = [];

        if ($monthMetricColsCount >= 2) {
            $isSingleRowHeader = true;
            $dataStartRowIdx = 1; // start from row 2 (which contains "TOTAL" and will be skipped)
            
            // Scan first row for party, potential, and type columns
            foreach ($firstRow as $idx => $cell) {
                $cellStr = strtolower(trim((string)$cell));
                if (str_contains($cellStr, 'party') && $partyColIdx === null) {
                    $partyColIdx = $idx;
                } elseif ((str_contains($cellStr, 'potential') || str_contains($cellStr, 'business potential')) && $potentialColIdx === null) {
                    $potentialColIdx = $idx;
                } elseif ((str_contains($cellStr, 'type') || str_contains($cellStr, 'customer type') || str_contains($cellStr, 'customer/dealer type')) && $typeColIdx === null) {
                    $typeColIdx = $idx;
                } elseif ((str_contains($cellStr, 'brand') || str_contains($cellStr, 'product')) && $productColIdx === null) {
                    $productColIdx = $idx;
                }
            }
            if ($partyColIdx === null) $partyColIdx = 1;
            if ($potentialColIdx === null) $potentialColIdx = 2;
            if ($typeColIdx === null) $typeColIdx = 3;

            // Map the months
            foreach ($firstRow as $idx => $cell) {
                $parsed = $this->parseMonthAndMetricFromHeader((string)$cell, $inferredYear);
                if ($parsed) {
                    $mYear = $parsed['month_year'];
                    $metric = $parsed['metric'];
                    if (!isset($monthMappings[$mYear])) {
                        $monthMappings[$mYear] = [];
                    }
                    $monthMappings[$mYear][$metric] = $idx;
                }
            }
            
            $headers = [];
            foreach ($firstRow as $idx => $val) {
                $headers[] = [
                    'index' => $idx,
                    'name' => trim($val) ?: "Column " . chr(65 + ($idx % 26))
                ];
            }
            $totalMonthsDetected = count($monthMappings);
        } else {
            // Standard 2-row layout fallback
            $dataStartRowIdx = $request->input('data_start_row', 4);
            $monthRow = $data[1] ?? [];
            $metricRow = $data[2] ?? [];

            $headers = [];
            foreach ($metricRow as $idx => $val) {
                $headers[] = [
                    'index' => $idx,
                    'name' => trim($val) ?: "Column " . chr(65 + ($idx % 26))
                ];
            }

            if ($partyColIdx === null) {
                foreach ($headers as $h) {
                    if (str_contains(strtolower($h['name']), 'party')) {
                        $partyColIdx = $h['index']; break;
                    }
                }
                if ($partyColIdx === null) $partyColIdx = 1;
            }

            if ($potentialColIdx === null) {
                foreach ($headers as $h) {
                    if (str_contains(strtolower($h['name']), 'potential')) {
                        $potentialColIdx = $h['index']; break;
                    }
                }
                if ($potentialColIdx === null) $potentialColIdx = 2;
            }

            foreach ($headers as $h) {
                $name = strtolower($h['name']);
                if (str_contains($name, 'ccare') || str_contains($name, 'newbiz') || str_contains($name, 'new biz')) {
                    $typeColIdx = $h['index']; break;
                }
            }
            if ($typeColIdx === null) $typeColIdx = 3;

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
        }

        // All detected months for preview
        $previewMonthKeys = array_keys($monthMappings);

        // Preview First 10 Parties
        $previewData = [];
        $consecutiveEmptyRows = 0;
        for ($r = $dataStartRowIdx; $r < count($data); $r++) {
            $row = $data[$r];
            $partyName = trim($row[$partyColIdx] ?? '');
            if (empty($partyName) || strtolower($partyName) == 'party name' || strtolower($partyName) == 'sr no') {
                $consecutiveEmptyRows++;
                if ($consecutiveEmptyRows > 15) {
                    break;
                }
                continue;
            }
            $consecutiveEmptyRows = 0;
            if (strtolower($partyName) == 'total') continue;

            $potential = (float) str_replace(',', '', $row[$potentialColIdx] ?? 0);
            $type = strtolower(trim($row[$typeColIdx] ?? ''));

            $monthSales = [];
            foreach ($previewMonthKeys as $mk) {
                $saleIdx = $monthMappings[$mk]['sale'] ?? null;
                $monthSales[$mk] = $saleIdx !== null ? (float) str_replace(',', '', $row[$saleIdx] ?? 0) : null;
            }

            $product = $productColIdx !== null ? trim($row[$productColIdx] ?? '') : '';

            $previewData[] = [
                'row_num'    => $r + 1,
                'party_name' => $partyName,
                'potential'  => $potential,
                'type'       => $type ?: '—',
                'product'    => $product ?: '—',
                'months'     => $monthSales,
            ];
            if (count($previewData) >= 50) break;
        }

        $salesperson = User::find($request->salesperson_id);
        $ccareId = $request->input('ccare_id');
        $newBizId = $request->input('new_biz_id');

        return view('tenant.sales-pipeline.import-preview', compact(
            'previewData', 'totalMonthsDetected', 'monthMappings', 'path', 'salesperson',
            'partyColIdx', 'potentialColIdx', 'typeColIdx', 'productColIdx', 'dataStartRowIdx', 'headers', 'previewMonthKeys',
            'ccareId', 'newBizId'
        ));
    }

    public function processImport(Request $request)
    {
        $request->validate([
            'temp_path' => 'required',
            'salesperson_id' => 'required|exists:users,id',
            'ccare_id' => 'nullable|exists:users,id',
            'new_biz_id' => 'nullable|exists:users,id',
            'party_col' => 'required|numeric',
            'potential_col' => 'required|numeric',
            'type_col' => 'nullable|numeric',
            'product_col' => 'nullable|numeric',
            'data_start_row' => 'required|numeric',
            'month_mappings' => 'required|json'
        ]);

        $tempPath = $request->temp_path;
        if (!str_starts_with($tempPath, 'temp/')) {
            return redirect()->route('sales-visits.pipeline.import')->with('error', 'Invalid file reference.');
        }
        $fullPath = storage_path('app/' . $tempPath);
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
        $productColIdx = $request->product_col;
        $dataStartRowIdx = $request->data_start_row;

        // FY key map for YoY totals (fyStart year → DB column). FY 26-27 shown via monthly actuals, not stored here.
        $fyKeyMap = [2022 => 'yoy_22_23', 2023 => 'yoy_23_24', 2024 => 'yoy_24_25', 2025 => 'yoy_25_26'];

        // Apply potential overrides from preview screen if user edited them
        $potentialOverrides = $request->input('potentials', []);
        $currentActualMonth = Carbon::now()->format('Y-m');

        $user = auth()->user();
        $isCcare = $user->department && str_contains(strtolower($user->department->name), 'customer care');
        $isNewBiz = $user->department && str_contains(strtolower($user->department->name), 'new biz');

        // Automatically assign CCare/New Biz mapping if available
        $ccareId = $request->input('ccare_id');
        $newBizId = $request->input('new_biz_id');

        if (!$ccareId && $isCcare) {
            $ccareId = $user->id;
        }
        if (!$newBizId && $isNewBiz) {
            $newBizId = $user->id;
        }

        // Retrieve mapped CCare and New Biz users from map if not set
        $mappedCcMaps = \App\Models\CcSalespersonMap::where('sales_user_id', $salespersonId)
            ->with(['ccUser.department'])
            ->get();

        foreach ($mappedCcMaps as $map) {
            $ccUser = $map->ccUser;
            if ($ccUser && $ccUser->department) {
                $deptName = strtolower($ccUser->department->name);
                if (str_contains($deptName, 'customer care') || str_contains($deptName, 'c-care') || str_contains($deptName, 'ccare')) {
                    if (!$ccareId) {
                        $ccareId = $ccUser->id;
                    }
                } elseif (str_contains($deptName, 'new biz') || str_contains($deptName, 'newbiz')) {
                    if (!$newBizId) {
                        $newBizId = $ccUser->id;
                    }
                }
            }
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $consecutiveEmptyRows = 0;
        for ($r = $dataStartRowIdx; $r < count($data); $r++) {
            $rowNum = $r + 1;
            $row = $data[$r];

            $partyName = trim($row[$partyColIdx] ?? '');
            if (empty($partyName) || strtolower($partyName) == 'party name' || strtolower($partyName) == 'sr no') {
                $consecutiveEmptyRows++;
                if ($consecutiveEmptyRows > 15) {
                    break;
                }
                continue;
            }
            $consecutiveEmptyRows = 0;

            if (strtolower($partyName) == 'total') {
                continue;
            }

            // CSV stores potential in lakhs; DB also expects lakhs
            $potential = (float) str_replace(',', '', $row[$potentialColIdx] ?? 0);

            if (isset($potentialOverrides[$rowNum])) {
                $potential = (float) $potentialOverrides[$rowNum];
            }

            $typeRaw = strtolower(trim($row[$typeColIdx] ?? ''));
            $typeMap = [
                'ccare' => 'CCare', 'newbiz' => 'New Biz', 'new biz' => 'New Biz',
                'distributor' => 'Distributor', 'dealer' => 'Dealer', 'customer' => 'Customer',
                'closed' => 'Closed', 'dropped' => 'Dropped', 'inactive' => 'Inactive'
            ];
            $partyType = $typeMap[$typeRaw] ?? null;
            
            $product = null;
            if ($productColIdx !== null) {
                $product = trim($row[$productColIdx] ?? '');
            }

            try {
                $updateData = [
                    'total_business_potential' => $potential,
                    'type' => $partyType,
                    'is_locked' => true,
                ];
                
                if ($product) {
                    $updateData['product'] = $product;
                }

                if ($ccareId) {
                    $updateData['ccare_id'] = $ccareId;
                }
                if ($newBizId) {
                    $updateData['new_biz_id'] = $newBizId;
                }

                $pipeline = SalesPipeline::updateOrCreate(
                    [
                        'party_name' => $partyName,
                        'salesperson_id' => $salespersonId
                    ],
                    $updateData
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
                        if ($remarksVal && in_array($remarksVal[0], ['=', '+', '-', '@'])) {
                            $remarksVal = "'" . $remarksVal;
                        }
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

    private function parseMonthAndMetricFromHeader($headerStr, $fallbackYear = null)
    {
        $str = strtoupper(trim(preg_replace('/\s+/', ' ', $headerStr)));
        
        $monthsMap = [
            'JANUARY' => '01', 'FEBRUARY' => '02', 'MARCH' => '03', 'APRIL' => '04',
            'MAY' => '05', 'JUNE' => '06', 'JULY' => '07', 'AUGUST' => '08',
            'SEPTEMBER' => '09', 'OCTOBER' => '10', 'NOVEMBER' => '11', 'DECEMBER' => '12',
            'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
            'JUN' => '06', 'JUL' => '07', 'AUG' => '08', 'SEP' => '09',
            'OCT' => '10', 'NOV' => '11', 'DEC' => '12'
        ];
        
        $matchedMonth = null;
        $monthNum = null;
        foreach ($monthsMap as $name => $num) {
            if (str_contains($str, $name)) {
                $matchedMonth = $name;
                $monthNum = $num;
                break;
            }
        }
        
        if (!$matchedMonth) {
            return null; // Not a month column
        }
        
        // Determine metric type
        $metric = null;
        if (str_contains($str, 'PENDING')) {
            $metric = 'pending';
        } elseif (str_contains($str, 'FORECAST')) {
            $metric = 'forecast';
        } elseif (str_contains($str, 'REMARK')) {
            $metric = 'remarks';
        } elseif (str_contains($str, 'SALE') || str_contains($str, 'ACTUAL')) {
            $metric = 'sale';
        } else {
            // Default to sale if no metric is specified
            $metric = 'sale';
        }
        
        // Parse year
        $year = null;
        // Check for FY format first (e.g. 26-27 or 2026-2027)
        if (preg_match('/(\d{2,4})\s*-\s*(\d{2,4})/', $str, $matches)) {
            $yr = (int)$matches[1];
            if ($yr < 100) $yr = 2000 + $yr;
            
            if (in_array($monthNum, ['01', '02', '03'])) {
                $year = $yr + 1;
            } else {
                $year = $yr;
            }
        }
        // Check for single year format (e.g. -26 or 2026)
        elseif (preg_match('/(?:-|\b)(20\d{2}|\d{2})\b/', $str, $matches)) {
            $yr = (int)$matches[1];
            if ($yr < 100) $yr = 2000 + $yr;
            $year = $yr;
        } else {
            $year = $fallbackYear ?? date('Y');
        }
        
        return [
            'month_year' => "{$year}-{$monthNum}",
            'metric' => $metric
        ];
    }
}
