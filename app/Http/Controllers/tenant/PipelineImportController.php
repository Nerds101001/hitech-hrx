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
    /**
     * Who may import at all. The same rule for the page, the preview and the
     * final write: preview and process used to skip it, so anyone who could
     * reach the sales section - a read-only analytics grant included - could
     * post an import straight to them.
     */
    private function canImport($user): bool
    {
        return $user && (\App\Http\Middleware\SalesAccessGuard::isBroadSalesUser($user) || $user->can('sales_report.import'));
    }

    /**
     * The salespeople this user may import for: exactly the page's dropdown.
     * Only people in a Sales department, never terminated; a CCare or New Biz
     * user only the salespeople tagged to them.
     */
    private function salespersonQuery($user)
    {
        $isCcare = $user->department && str_contains(strtolower($user->department->name), 'customer care');
        $isNewBiz = $user->department && str_contains(strtolower($user->department->name), 'new biz');

        $query = User::where('status', 'active')
            ->notTerminated()
            ->whereHas('department', function($q) {
                $q->where('name', 'like', '%Sales%');
            });

        if ($user->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
            // Admin and HR see all active Sales-department users
        } elseif (($isCcare || $isNewBiz)) {
            $taggedSalespersonIds = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)
                ->pluck('sales_user_id')->filter()->unique();

            $query->whereIn('id', $taggedSalespersonIds);
        }
        // Any other role: still limited to Sales-department users above

        return $query;
    }

    /** Session key holding the one upload this user's preview saved. */
    private const TEMP_KEY = 'pipeline_import_temp';

    public function showImport(Request $request)
    {
        $importTarget = $request->input('target', 'report');
        $user = auth()->user();
        if (!$this->canImport($user)) {
            abort(403);
        }

        $salespersons = $this->salespersonQuery($user)->orderBy('first_name')->get();

        $ccareUsers = User::where('status', 'active')
            ->whereHas('department', function($q) {
                $q->where('name', 'like', '%Customer Care%');
            })->orderBy('first_name')->get();

        $newBizUsers = User::where('status', 'active')
            ->whereHas('department', function($q) {
                $q->where('name', 'like', '%New Biz%');
            })->orderBy('first_name')->get();

        return view('tenant.sales-pipeline.import', compact('salespersons', 'ccareUsers', 'newBizUsers', 'importTarget'));
    }

    public function downloadTemplate(Request $request)
    {
        // Streamed directly (no file on disk) so the download never depends on
        // deployment — *.csv files are excluded from the release archive.
        $type = $request->input('type', 'report');

        if ($type === 'target') {
            $name = 'sales_target_template.csv';
            $header = ['Month/Year', 'Kingo Target', 'Bingo Target'];
            $rows = [
                ['2026-06', '250000.00', '150000.00'],
                ['2026-07', '260000.00', '160000.00'],
            ];
        } elseif ($type === 'pipeline') {
            $name = 'sales_pipeline_template.csv';
            $header = ['S.No.', 'Start Date', 'Customer Name', 'Product', 'Salesperson', 'New Biz', 'Potential/Month (Lakhs)', 'Stage', 'Status Remarks', 'Action'];
            $rows = [
                ['1', '01/07/2026', 'Alpha Industries Ltd', 'Premium Line', 'Rajesh Kumar', 'Priya Sharma', '15.00', 'Prospect', 'Initial contact made', ''],
                ['2', '10/07/2026', 'Beta Technologies', 'Standard Line', 'Rajesh Kumar', 'Priya Sharma', '20.00', 'Negotiation', 'Awaiting response', ''],
            ];
        } else {
            $name = 'sales_report_template.csv';
            $header = ['S.No.', 'Party Name', 'Type', 'Potential (Lakhs)', 'Brand', 'Salesperson', 'New Biz', 'JUNE 26-27', 'MAY 26-27', 'APRIL 26-27'];
            $rows = [
                ['1', 'Alpha Industries Ltd', 'Dealer', '15.00', 'Premium Line', 'Rajesh Kumar', 'Priya Sharma', '500000', '450000', '400000'],
            ];
        }

        return response()->streamDownload(function () use ($header, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $header);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, $name, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function previewImport(Request $request)
    {
        if (!$this->canImport(auth()->user())) {
            abort(403);
        }
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
            'salesperson_id' => 'required|exists:users,id',
            'ccare_id' => 'nullable|exists:users,id',
            'new_biz_id' => 'nullable|exists:users,id'
        ]);
        if (!$this->salespersonQuery(auth()->user())->whereKey($request->salesperson_id)->exists()) {
            return back()->with('error', 'You can only import for a salesperson in your own list.');
        }

        $path = null;
        try {
            // Keep the file temporarily in storage. The extension comes from
            // the validated file type, not the client's file name.
            $path = $request->file('file')->storeAs('temp', 'pipeline_matrix_'.time().'_'.\Illuminate\Support\Str::random(8).'.'.$request->file('file')->guessExtension());
            // The final step accepts only this file, for this user.
            session([self::TEMP_KEY => $path]);
            $fullPath = storage_path('app/' . $path);
            $data = Excel::toArray(new \stdClass(), $fullPath)[0];
        } catch (\Exception $e) {
            if ($path) @unlink(storage_path('app/' . $path));
            return back()->with('error', 'Error reading file: ' . $e->getMessage());
        }

        if (count($data) < 2) {
            return back()->with('error', 'File does not contain enough rows.');
        }

        // Scan the first 15 rows to find the actual header row
        $headerRowIdx = 0;
        $firstRow = [];
        for ($i = 0; $i < min(15, count($data)); $i++) {
            $rowStr = strtolower(implode(' ', $data[$i]));
            if (str_contains($rowStr, 'party') || str_contains($rowStr, 'potential')) {
                $headerRowIdx = $i;
                $firstRow = $data[$i];
                break;
            }
        }
        
        if (empty($firstRow)) {
            $firstRow = $data[0] ?? [];
        }

        // Auto-detect single-row vs 2-row layout
        $isSingleRowHeader = false;
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

        $stageColIdx = null;
        $remarksColIdx = null;

        $salespersonColIdx = null;
        $newBizColIdx = null;
        $ccareColIdx = null;

        if ($request->input('import_target') === 'pipeline') {
            $isSingleRowHeader = true;
            $dataStartRowIdx = $headerRowIdx + 1; // start from the row after headers
            
            // Scan first row for columns
            foreach ($firstRow as $idx => $cell) {
                $cellStr = strtolower(trim((string)$cell));
                if (str_contains($cellStr, 'party') || str_contains($cellStr, 'customer name')) $partyColIdx = $idx;
                elseif ((str_contains($cellStr, 'potential') || str_contains($cellStr, 'business potential'))) $potentialColIdx = $idx;
                elseif ((str_contains($cellStr, 'type') || str_contains($cellStr, 'customer type') || str_contains($cellStr, 'customer/dealer type'))) $typeColIdx = $idx;
                elseif ((str_contains($cellStr, 'brand') || str_contains($cellStr, 'product'))) $productColIdx = $idx;
                elseif (str_contains($cellStr, 'stage')) $stageColIdx = $idx;
                elseif (str_contains($cellStr, 'remark')) $remarksColIdx = $idx;
                elseif (str_contains($cellStr, 'salesperson') || str_contains($cellStr, 'sales person') || str_contains($cellStr, 'rep')) $salespersonColIdx = $idx;
                elseif (str_contains($cellStr, 'new biz') || str_contains($cellStr, 'newbiz')) $newBizColIdx = $idx;
                elseif (str_contains($cellStr, 'ccare') || str_contains($cellStr, 'cust care') || str_contains($cellStr, 'cust. care')) $ccareColIdx = $idx;
            }
            if ($partyColIdx === null) $partyColIdx = 1;
            if ($potentialColIdx === null) $potentialColIdx = 4;
            
            $headers = [];
            foreach ($firstRow as $idx => $val) {
                $headers[] = [
                    'index' => $idx,
                    'name' => trim($val) ?: "Column " . chr(65 + ($idx % 26))
                ];
            }
            $totalMonthsDetected = 0;
            $monthMappings = [];
        } elseif ($monthMetricColsCount >= 2) {
            $isSingleRowHeader = true;
            $dataStartRowIdx = $headerRowIdx + 1; // start from the row after headers

            
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
                } elseif (str_contains($cellStr, 'salesperson') || str_contains($cellStr, 'sales person') || str_contains($cellStr, 'rep')) {
                    $salespersonColIdx = $idx;
                } elseif (str_contains($cellStr, 'new biz') || str_contains($cellStr, 'newbiz')) {
                    $newBizColIdx = $idx;
                } elseif (str_contains($cellStr, 'ccare') || str_contains($cellStr, 'cust care') || str_contains($cellStr, 'cust. care')) {
                    $ccareColIdx = $idx;
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
            $dataStartRowIdx = $request->input('data_start_row', $headerRowIdx + 2);
            $monthRow = $data[$headerRowIdx] ?? [];
            $metricRow = $data[$headerRowIdx + 1] ?? [];

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
                if (str_contains($name, 'type') || str_contains($name, 'customer type')) {
                    $typeColIdx = $h['index']; break;
                }
            }
            if ($typeColIdx === null) $typeColIdx = 3;

            foreach ($headers as $h) {
                $name = strtolower($h['name']);
                if (str_contains($name, 'brand') || str_contains($name, 'product')) {
                    $productColIdx = $h['index']; break;
                }
            }

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
        $importTarget = $request->input('import_target', 'report');

        return view('tenant.sales-pipeline.import-preview', compact(
            'previewData', 'totalMonthsDetected', 'monthMappings', 'path', 'salesperson',
            'partyColIdx', 'potentialColIdx', 'typeColIdx', 'productColIdx', 'stageColIdx', 'remarksColIdx',
            'salespersonColIdx', 'newBizColIdx', 'ccareColIdx',
            'dataStartRowIdx', 'headers', 'previewMonthKeys',
            'ccareId', 'newBizId', 'importTarget'
        ));
    }

    public function processImport(Request $request)
    {
        $user = auth()->user();
        if (!$this->canImport($user)) {
            abort(403);
        }
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

        // Only the file this user's own preview saved. The path used to be
        // trusted from the form with just a 'temp/' prefix check, so
        // 'temp/../../<anything>' read any file on the server.
        $tempPath = (string) $request->temp_path;
        if ($tempPath === '' || $tempPath !== session(self::TEMP_KEY)
            || !preg_match('~^temp/pipeline_matrix_[0-9]+_[A-Za-z0-9]{8}\.(xlsx|xls|csv)$~', $tempPath)) {
            return redirect()->route('sales-visits.pipeline.import')->with('error', 'Invalid file reference. Please upload the file again.');
        }
        $allowedSalespersonIds = $this->salespersonQuery($user)->pluck('id')->map(fn ($i) => (int) $i)->all();
        if (!in_array((int) $request->salesperson_id, $allowedSalespersonIds, true)) {
            return redirect()->route('sales-visits.pipeline.import')->with('error', 'You can only import for a salesperson in your own list.');
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
        $typeColIdx = $request->input('type_col') !== '' ? (int)$request->input('type_col') : null;
        $productColIdx = $request->input('product_col') !== '' ? (int)$request->input('product_col') : null;
        $stageColIdx = $request->input('stage_col') !== '' ? (int)$request->input('stage_col') : null;
        $remarksColIdx = $request->input('remarks_col') !== '' ? (int)$request->input('remarks_col') : null;
        $salespersonColIdx = $request->input('salesperson_col') !== '' ? (int)$request->input('salesperson_col') : null;
        $newBizColIdx = $request->input('new_biz_col') !== '' ? (int)$request->input('new_biz_col') : null;
        $ccareColIdx = $request->input('ccare_col') !== '' ? (int)$request->input('ccare_col') : null;
        $dataStartRowIdx = (int)$request->input('data_start_row', 2);

        // Pre-load users map for quick lookup by name (case-insensitive). Keyed
        // by first+last name — the `name` column itself is blank for almost
        // every user (it's a legacy field; the app displays getFullName()
        // everywhere), so a raw `pluck('id', 'name')` silently matched almost
        // nobody and left Salesperson/New Biz/CCare unset on every imported
        // row whose sheet carried those columns.
        $usersMap = \App\Models\User::where('status', 'active')->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(function ($u) {
                return [strtolower(trim($u->first_name . ' ' . $u->last_name)) => $u->id];
            })->toArray();

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

        // 1. First assign CCare mapping
        foreach ($mappedCcMaps as $map) {
            $ccUser = $map->ccUser;
            if (!$ccUser) continue;
            // The mapping's role is authoritative; older rows without one fall
            // back to the agent's department.
            if ($map->role === 'ccare') {
                if (!$ccareId) $ccareId = $ccUser->id;
                continue;
            }
            if ($map->role) continue;
            if ($ccUser->department) {
                $deptName = strtolower($ccUser->department->name);
                if (str_contains($deptName, 'customer care') || str_contains($deptName, 'c-care') || str_contains($deptName, 'ccare')) {
                    if (!$ccareId) {
                        $ccareId = $ccUser->id;
                    }
                }
            }
        }

        // 2. Only if no CCare is selected or mapped, assign New Biz mapping
        if (!$ccareId) {
            foreach ($mappedCcMaps as $map) {
                $ccUser = $map->ccUser;
                if (!$ccUser) continue;
                if ($map->role === 'newbiz') {
                    if (!$newBizId) $newBizId = $ccUser->id;
                    continue;
                }
                if ($map->role) continue;
                if ($ccUser->department) {
                    $deptName = strtolower($ccUser->department->name);
                    if (str_contains($deptName, 'new biz') || str_contains($deptName, 'newbiz')) {
                        if (!$newBizId) {
                            $newBizId = $ccUser->id;
                        }
                    }
                }
            }
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $lockedMonthsSkipped = 0;

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
                    'is_locked' => $request->input('import_target') === 'pipeline' ? false : true,
                ];
                
                // Dynamic user assignments if columns exist
                $rowSalespersonId = $salespersonId;
                if ($salespersonColIdx !== null) {
                    $name = strtolower(trim($row[$salespersonColIdx] ?? ''));
                    // A name in the sheet may only point at someone this user
                    // could have picked; anyone else keeps the chosen salesperson.
                    if ($name && isset($usersMap[$name]) && in_array((int) $usersMap[$name], $allowedSalespersonIds, true)) {
                        $rowSalespersonId = $usersMap[$name];
                    }
                }

                $rowNewBizId = $newBizId;
                if ($newBizColIdx !== null) {
                    $name = strtolower(trim($row[$newBizColIdx] ?? ''));
                    if ($name && isset($usersMap[$name])) $rowNewBizId = $usersMap[$name];
                }

                $rowCcareId = $ccareId;
                if ($ccareColIdx !== null) {
                    $name = strtolower(trim($row[$ccareColIdx] ?? ''));
                    if ($name && isset($usersMap[$name])) $rowCcareId = $usersMap[$name];
                }

                if ($product) {
                    $updateData['product'] = $product;
                }

                if ($stageColIdx !== null) {
                    $stageRaw = trim($row[$stageColIdx] ?? '');
                    if ($stageRaw) {
                        $updateData['status_stage'] = $stageRaw;
                    }
                }

                if ($remarksColIdx !== null) {
                    $remarksRaw = trim($row[$remarksColIdx] ?? '');
                    if ($remarksRaw) {
                        $updateData['status_remarks'] = $remarksRaw;
                    }
                }

                if ($rowCcareId) {
                    $updateData['ccare_id'] = $rowCcareId;
                }
                if ($rowNewBizId) {
                    $updateData['new_biz_id'] = $rowNewBizId;
                }

                $pipeline = SalesPipeline::updateOrCreate(
                    [
                        'party_name' => $partyName,
                        'salesperson_id' => $rowSalespersonId,
                    ],
                    $updateData
                );

                // Insert months + accumulate YoY
                $yoyTotals = ['yoy_22_23' => 0.0, 'yoy_23_24' => 0.0, 'yoy_24_25' => 0.0, 'yoy_25_26' => 0.0];

                foreach ($monthMappings as $monthYear => $indices) {
                    // Same month-lock policy the interactive grid enforces via
                    // monthUpdate() — this bulk path wrote Sale/Pending/Forecast
                    // for any month with no check at all, letting any Sales/
                    // CCare/New Biz/Export user silently overwrite a closed
                    // month's figures through the import screen.
                    if (\App\Http\Controllers\tenant\SalesPipelineController::isMonthLockedForUser($user, $monthYear)) {
                        $lockedMonthsSkipped++;
                        continue;
                    }
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

        // Cleanup: the file, and the session's claim on it (one import per upload).
        @unlink($fullPath);
        session()->forget(self::TEMP_KEY);

        $msg = "Successfully processed {$successCount} parties/pipelines.";
        if ($lockedMonthsSkipped > 0) {
            $msg .= " Skipped {$lockedMonthsSkipped} month figure(s) — that month is locked for your role.";
        }
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
