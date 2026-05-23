<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\User;
use App\Models\SalaryPolicy;
use Illuminate\Support\Facades\Response;

class SalaryImportController extends Controller
{
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $data = Excel::toArray(new \stdClass(), $request->file('file'))[0];

        if (empty($data) || count($data) < 2) {
            return redirect()->back()->with('error', 'The uploaded file is empty or does not contain data.');
        }

        // Get headers (first row) and normalize them
        $headers = array_map(function($h) {
            return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace([' ', '-'], '_', trim($h))));
        }, $data[0]);

        // Map column indices
        $mapping = $this->getColumnsMapping($headers);

        $previewData = [];
        $recordsToStore = [];

        // Skip the header row
        $rows = array_slice($data, 1);

        foreach ($rows as $index => $row) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $searchVal = isset($mapping['identifier']) && isset($row[$mapping['identifier']]) ? trim($row[$mapping['identifier']]) : null;
            if (!$searchVal) {
                continue;
            }

            // Find user by biometric_id, code, or email
            $user = User::where('biometric_id', $searchVal)
                ->orWhere('code', $searchVal)
                ->orWhere('email', $searchVal)
                ->first();

            $baseSalary = $this->getMappedValue($row, $mapping, 'base_salary');
            $policyRaw  = $this->getMappedValue($row, $mapping, 'salary_policy_id');
            $customBasic = $this->getMappedValue($row, $mapping, 'custom_basic');
            $customHra = $this->getMappedValue($row, $mapping, 'custom_hra');
            $customCa = $this->getMappedValue($row, $mapping, 'custom_ca');
            $customMedical = $this->getMappedValue($row, $mapping, 'custom_medical');
            $customEdu = $this->getMappedValue($row, $mapping, 'custom_edu');
            $customSpecialAllowance = $this->getMappedValue($row, $mapping, 'custom_special_allowance');
            $customPt = $this->getMappedValue($row, $mapping, 'custom_pt');
            $customEpf = $this->getMappedValue($row, $mapping, 'custom_epf');
            $customEsic = $this->getMappedValue($row, $mapping, 'custom_esic');

            $status = 'Ready';
            $errorMsg = '';

            if (!$user) {
                $status = 'Error';
                $errorMsg = 'Employee not found';
            }

            // Resolve policy — only numeric IDs are attempted; text values are ignored
            $policyId = null;
            $salaryPolicyName = 'Keep Existing';
            if ($policyRaw !== null && is_numeric($policyRaw) && intval($policyRaw) > 0) {
                $policyId = intval($policyRaw);
                $policyObj = SalaryPolicy::find($policyId);
                if ($policyObj) {
                    $salaryPolicyName = $policyObj->name;
                } else {
                    // ID not found — just keep existing, don't block import
                    $policyId = null;
                    $salaryPolicyName = 'Keep Existing (ID not found)';
                }
            }
            // Non-numeric text in policy column (e.g. policy name text) is silently ignored

            $record = [
                'identifier'    => $searchVal,
                'employee_name' => $user ? $user->getFullName() : 'Unknown',
                'user_id'       => $user ? $user->id : null,
                'old_base_salary' => $user ? ($user->base_salary ?? 0) : 0,
                'base_salary'   => $baseSalary !== null ? floatval($baseSalary) : ($user ? ($user->base_salary ?? 0) : 0),
                'salary_policy_id'   => $policyId,
                'salary_policy_name' => $salaryPolicyName,
                'custom_basic'   => $customBasic !== null ? floatval($customBasic) : null,
                'custom_hra'     => $customHra !== null ? floatval($customHra) : null,
                'custom_ca'      => $customCa !== null ? floatval($customCa) : null,
                'custom_medical' => $customMedical !== null ? floatval($customMedical) : null,
                'custom_edu'     => $customEdu !== null ? floatval($customEdu) : null,
                'custom_special_allowance' => $customSpecialAllowance !== null ? floatval($customSpecialAllowance) : null,
                'custom_pt'   => $customPt !== null ? floatval($customPt) : null,
                'custom_epf'  => $customEpf !== null ? floatval($customEpf) : null,
                'custom_esic' => $customEsic !== null ? floatval($customEsic) : null,
                'status'        => $status,
                'error_message' => $errorMsg
            ];

            $previewData[] = $record;
            if ($status === 'Ready') {
                $recordsToStore[] = $record;
            }
        }

        return view('tenant.payroll.salary_import_preview', [
            'previewData' => $previewData,
            'recordsJson' => json_encode($recordsToStore),
            'pageConfigs' => ['contentLayout' => 'wide']
        ]);
    }

    public function store(Request $request)
    {
        $records = json_decode($request->input('records'), true);
        if (empty($records)) {
            return redirect()->route('payroll.index')->with('error', 'No valid records to import.');
        }

        $count = 0;
        foreach ($records as $record) {
            if (empty($record['user_id'])) {
                continue;
            }

            $user = User::find($record['user_id']);
            if ($user) {
                $user->base_salary = $record['base_salary'];
                if ($record['salary_policy_id'] !== null) {
                    $user->salary_policy_id = $record['salary_policy_id'];
                }
                $user->custom_basic = $record['custom_basic'];
                $user->custom_hra = $record['custom_hra'];
                $user->custom_ca = $record['custom_ca'];
                $user->custom_medical = $record['custom_medical'];
                $user->custom_edu = $record['custom_edu'];
                $user->custom_special_allowance = $record['custom_special_allowance'];
                $user->custom_pt = $record['custom_pt'];
                $user->custom_epf = $record['custom_epf'];
                $user->custom_esic = $record['custom_esic'];
                $user->save();
                $count++;
            }
        }

        return redirect()->route('payroll.index')->with('success', "Successfully imported and updated salary breakups for {$count} employees.");
    }

    public function downloadSample()
    {
        // Column names match exactly what the user fills in their sheet:
        // identifier = biometric_id or employee code
        // salary = gross/base monthly salary (CTC)
        // policy = salary_policy_id (optional, leave blank if not needed)
        // basic, hra, ca, medical, edu, special = CREDIT components
        // pt, epf, esic = DEDUCTION components
        $headers = [
            'identifier',   // biometric_id or employee code
            'salary',       // Base Monthly Salary (CTC)
            'basic',        // Basic Salary
            'hra',          // House Rent Allowance
            'ca',           // Conveyance Allowance
            'medical',      // Medical Allowance
            'edu',          // Education Allowance
            'special',      // Special Allowance
            'pt',           // Professional Tax (Deduction)
            'epf',          // Employee PF (Deduction)
            'esic',         // ESIC (Deduction)
        ];

        $callback = function() use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            
            // Sample row 1
            fputcsv($file, [
                '1008',    // identifier (biometric_id or code)
                '33100',   // salary (CTC monthly)
                '21500',   // basic
                '7200',    // hra
                '2400',    // ca
                '2000',    // medical
                '0',       // edu
                '0',       // special
                '200',     // pt (deduction)
                '2580',    // epf (deduction – 12% of basic)
                '0',       // esic (deduction – 0 if gross > 21000)
            ]);
            // Sample row 2
            fputcsv($file, [
                '1009',
                '18000',
                '10800',
                '3600',
                '1600',
                '1000',
                '200',
                '800',
                '0',
                '1296',
                '135',
            ]);
            fclose($file);
        };

        return Response::stream($callback, 200, [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=salary_breakup_import_template.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }

    private function getColumnsMapping(array $headers)
    {
        $mapping = [];

        // Define options for each mapping
        $options = [
            'identifier' => ['biometricid', 'biometricidorcode', 'employeecode', 'code', 'email', 'biometric_id_or_code', 'biometric_id', 'employee_code'],
            'base_salary' => ['basesalary', 'salary', 'base', 'monthlyctc', 'gross', 'base_salary'],
            'salary_policy_id' => ['salarypolicyid', 'policyid', 'policy', 'salary_policy_id'],
            'custom_basic' => ['custombasic', 'basic', 'custom_basic'],
            'custom_hra' => ['customhra', 'hra', 'custom_hra'],
            'custom_ca' => ['customca', 'ca', 'conveyance', 'custom_ca'],
            'custom_medical' => ['custommedical', 'medical', 'custom_medical'],
            'custom_edu' => ['customedu', 'edu', 'education', 'custom_edu'],
            'custom_special_allowance' => ['customspecialallowance', 'specialallowance', 'special', 'splall', 'custom_special_allowance'],
            'custom_pt' => ['custompt', 'pt', 'professionaltax', 'custom_pt'],
            'custom_epf' => ['customepf', 'epf', 'pf', 'providentfund', 'custom_epf'],
            'custom_esic' => ['customesic', 'esic', 'esi', 'custom_esic'],
        ];

        foreach ($options as $key => $synonyms) {
            foreach ($headers as $index => $header) {
                if (in_array(strtolower(trim($header)), $synonyms)) {
                    $mapping[$key] = $index;
                    break;
                }
            }
        }

        // Fallbacks if not found by name
        if (!isset($mapping['identifier'])) {
            $mapping['identifier'] = 0; // First column is usually identifier
        }
        if (!isset($mapping['base_salary'])) {
            $mapping['base_salary'] = 1; // Second column is usually salary
        }

        return $mapping;
    }

    private function getMappedValue($row, $mapping, $key)
    {
        if (isset($mapping[$key]) && isset($row[$mapping[$key]])) {
            $val = trim($row[$mapping[$key]]);
            return $val !== '' ? $val : null;
        }
        return null;
    }
}
