<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\CommonStatus;
use App\Enums\Gender;
use App\Enums\IncentiveType;
use App\Enums\Status;
use App\Enums\TargetType;
use App\Enums\TerminationType;
use App\Enums\UserAccountStatus;
use App\Models\Asset;
use App\Models\BankAccount;
use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\DocumentRequest;
use App\Models\DynamicQrDevice;
use App\Models\ProfileUpdateApproval;

use App\Models\GeofenceGroup;
use App\Models\IpAddressGroup;
use App\Models\LeaveType;
use App\Models\PayrollAdjustment;
use App\Models\QrGroup;
use App\Models\Role;
use App\Models\SalesTarget;
use App\Models\Settings;
use App\Helpers\FileSecurityHelper;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use App\Constants\Constants;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use App\Notifications\Onboarding\OnboardingInvite;
use App\Notifications\Onboarding\OnboardingStatusChanged;
use App\Services\LeaveAccrualService;
use OwenIt\Auditing\Models\Audit;


class EmployeeController extends Controller
{

  public function addOrUpdateLeaveCount(Request $request)
  {
    $validated = $request->validate([
      'userId' => 'required|exists:users,id',
      'leaveTypeId' => 'required|exists:leave_types,id',
      'count' => 'required|numeric|min:0',
    ]);

    $user = User::findOrFail($validated['userId']);

    $balance = $user->leaveBalances()->where('leave_type_id', $validated['leaveTypeId'])->first();

    if ($balance) {
      $balance->balance = $validated['count'];
      $balance->save();
    } else {
      $user->leaveBalances()->create([
        'leave_type_id' => $validated['leaveTypeId'],
        'balance' => $validated['count'],
        'used' => 0,
        'tenant_id' => $user->tenant_id,
      ]);
    }

    return redirect()->back()->with('success', 'Leave count updated successfully.');
  }

  public function addOrUpdatePayrollAdjustment(Request $request)
  {
    $validated = $request->validate([
      'id' => 'nullable|exists:payroll_adjustments,id',
      'adjustmentName' => 'required|string|max:255',
      'adjustmentCode' => 'required|string|max:191',
      'adjustmentType' => 'required|in:benefit,deduction',
      'adjustmentAmount' => 'nullable|numeric|min:0',
      'adjustmentPercentage' => 'nullable|numeric|min:0|max:100',
      'adjustmentNotes' => 'nullable|string|max:1000',
    ]);

    try {
      PayrollAdjustment::updateOrCreate(
        ['id' => $validated['id'] ?? null],
        [
          'user_id' => $request->userId,
          'name' => $validated['adjustmentName'],
          'code' => $validated['adjustmentCode'],
          'type' => $validated['adjustmentType'],
          'applicability' => 'employee',
          'amount' => $validated['adjustmentAmount'] ?? 0,
          'percentage' => $validated['adjustmentPercentage'],
          'notes' => $validated['adjustmentNotes'],
          'updated_by_id' => auth()->id(),
        ]
      );

      return redirect()->back()->with('success', __('Payroll adjustment saved successfully.'));
    } catch (\Exception $e) {
      Log::error('Payroll Adjustment Error: ' . $e->getMessage());
      return redirect()->back()->with('error', __('Failed to save payroll adjustment.'));
    }
  }

  public function getPayrollAdjustmentAjax($id)
  {
    $validated = validator(['id' => $id], ['id' => 'required|exists:payroll_adjustments,id'])->validate();

    $payrollAdjustment = PayrollAdjustment::find($validated['id']);

    return Success::response($payrollAdjustment);
  }

  public function addOrUpdateBankAccount(Request $request)
  {
    $validated = $request->validate([
      'userId' => 'required|exists:users,id',
      'bankName' => 'required|string|max:255',
      'bankCode' => 'required|string|max:255',
      'accountName' => 'required|string|max:255',
      'accountNumber' => 'required|string|max:255',
      'branchName' => 'nullable|string|max:255',
      'branchCode' => 'nullable|string|max:255',
      'bankDocument' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'
    ]);

    // Security check: Only allow users to update their own bank account unless they are admin/hr/manager
    if ($validated['userId'] != auth()->id() && !auth()->user()->hasRole(['admin', 'hr', 'manager'])) {
      return redirect()->back()->with('error', 'Unauthorized action.');
    }

    $isEmployee = auth()->user()->hasRole('employee') && !auth()->user()->hasRole(['admin', 'hr', 'manager']);

    if ($isEmployee) {
      // Check for existing pending request for bank_details
      $existing = ProfileUpdateApproval::where('user_id', auth()->id())
        ->where('type', 'bank_details')
        ->where('status', 'pending')
        ->first();

      if ($existing) {
        return redirect()->back()->with('error', 'You already have a bank update request pending approval.');
      }
    }

    $user = User::find($validated['userId']);
    $path = null;

    if ($request->hasFile('bankDocument')) {
      $file = $request->file('bankDocument');
      $path = \App\Helpers\FileSecurityHelper::encryptAndStore($file, 'bank_documents', 'bank_doc', 'public');
    }

    if ($isEmployee) {
      // Create approval request instead of direct update
      ProfileUpdateApproval::create([
        'user_id' => $user->id,
        'type' => 'bank_details',
        'requested_data' => [
          'bank_name' => $validated['bankName'],
          'bank_code' => $validated['bankCode'],
          'account_name' => $validated['accountName'],
          'account_number' => $validated['accountNumber'],
          'branch_name' => $validated['branchName'] ?? null,
          'branch_code' => $validated['branchCode'] ?? null,
          'passbook_path' => $path
        ],
        'status' => 'pending',
        'tenant_id' => $user->tenant_id
      ]);

      return redirect()->back()->with('info', 'Bank details update request has been submitted to HR for approval.');
    }

    $bank = BankAccount::where('user_id', $user->id)->first();

    if ($bank) {
      $bank->bank_name = $validated['bankName'];
      $bank->bank_code = $validated['bankCode'];
      $bank->account_name = $validated['accountName'];
      $bank->account_number = $validated['accountNumber'];
      $bank->branch_name = $validated['branchName'] ?? $bank->branch_name;
      $bank->branch_code = $validated['branchCode'] ?? $bank->branch_code;
      if ($path) {
        $bank->passbook_path = $path;

        // Synchronize with DocumentRequest for visibility in Documents tab
        try {
          $docType = \App\Models\DocumentType::where('code', 'BANK_PASSBOOK')->first();
          if (!$docType) {
            $docType = \App\Models\DocumentType::create([
              'name' => 'Bank Passbook',
              'code' => 'BANK_PASSBOOK',
              'is_mandatory' => false
            ]);
          }

          \App\Models\DocumentRequest::updateOrCreate(
            [
              'user_id' => $user->id,
              'document_type_id' => $docType->id
            ],
            [
              'generated_file' => $path,
              'status' => 'approved',
              'remarks' => 'Updated via Bank Details form',
              'action_taken_at' => now(),
              'updated_by_id' => auth()->id(),
              'tenant_id' => $user->tenant_id
            ]
          );
        } catch (\Exception $e) {
          \Log::error('Bank document sync error: ' . $e->getMessage());
        }
      }
      $bank->save();
    } else {
      $user->bankAccount()->create([
        'bank_name' => $validated['bankName'],
        'bank_code' => $validated['bankCode'],
        'account_name' => $validated['accountName'],
        'account_number' => $validated['accountNumber'],
        'branch_name' => $validated['branchName'] ?? null,
        'branch_code' => $validated['branchCode'] ?? null,
        'passbook_path' => $path
      ]);
    }

    return redirect()->back()->with('success', 'Bank account added/updated successfully');
  }

  public function create()
  {

    if (User::count() >= Settings::first()->employees_limit) {
      return redirect()->back()->with('error', 'You have reached the maximum limit of employees');
    }

    $shifts = Shift::where('status', Status::ACTIVE)
      ->select('id', 'name', 'code')
      ->get();

    $teams = Team::where('status', Status::ACTIVE)
      ->select('id', 'name', 'code')
      ->get();

    $designations = Designation::where('status', Status::ACTIVE)
      ->select('id', 'name', 'code')
      ->get();

    $users = User::where('status', UserAccountStatus::ACTIVE)
      ->select('id', 'first_name', 'last_name', 'code')
      ->get();

    $roles = Role::get();

    $leavePolicyProfiles = \App\Models\LeavePolicyProfile::all();

    return view('tenant.employees.create', [
      'shifts' => $shifts,
      'teams' => $teams,
      'designations' => $designations,
      'users' => $users,
      'roles' => $roles,
      'leavePolicyProfiles' => $leavePolicyProfiles,
    ]);
  }

  public function deletePayrollAdjustment($id)
  {
    $validated = validator(['id' => $id], ['id' => 'required|exists:payroll_adjustments,id'])->validate();

    $payrollAdjustment = PayrollAdjustment::find($validated['id']);

    if ($payrollAdjustment) {
      $payrollAdjustment->delete();
    }

    return redirect()->back()->with('success', 'Payroll adjustment deleted successfully');
  }

  public function removeDevice(Request $request)
  {
    $validated = $request->validate([
      'userId' => 'required|exists:users,id',
    ]);

    $device = UserDevice::where('user_id', $validated['userId'])
      ->first();

    if ($device) {
      $device->delete();
    }

    return redirect()->back()->with('success', 'Device removed successfully');
  }

  public function allotDevice(Request $request)
  {
    $validated = $request->validate([
      'userId' => 'required|exists:users,id',
      'deviceId' => 'nullable|string|max:255',
      'brand' => 'nullable|string|max:255',
      'deviceType' => 'nullable|string|max:100',
      'assetId' => 'nullable|exists:assets,id',
      'serialNumber' => 'nullable|string|max:255',
      'serviceTag' => 'nullable|string|max:255',
      'modelNumber' => 'nullable|string|max:255',
      'warrantyExpiry' => 'nullable|date',
    ]);

    // Auto-generate Asset Code if not provided
    $deviceTypeMap = [
      'laptop' => 'LAP',
      'desktop' => 'DSK',
      'mobile' => 'MOB',
      'tablet' => 'TAB',
      'peripheral' => 'PER',
      'biometric' => 'BIO',
    ];
    $prefix = $deviceTypeMap[strtolower($validated['deviceType'] ?? '')] ?? 'AST';
    $assetCode = $validated['deviceId'] ?? ($prefix . '-' . strtoupper(substr(uniqid(), -5)));

    // 1. Sync with Asset Management
    if (!empty($validated['assetId'])) {
      $asset = \App\Models\Asset::find($validated['assetId']);
      if ($asset) {
        $assetCode = $asset->asset_code;
        $asset->update([
          'assigned_to' => $validated['userId'],
          'status' => 'assigned',
          'serial_number' => $validated['serialNumber'] ?? $asset->serial_number,
          'model' => $validated['modelNumber'] ?? $asset->model,
          'warranty_expiry' => $validated['warrantyExpiry'] ?? $asset->warranty_expiry,
          'notes' => $validated['serviceTag'] ?? $asset->notes,
        ]);
      }
    } else {
      // Create new asset record for this allotment if not from inventory
      $asset = \App\Models\Asset::create([
        'asset_code' => $assetCode,
        'name' => $validated['brand'] ?? 'Manual Allotment',
        'model' => $validated['modelNumber'] ?? null,
        'serial_number' => $validated['serialNumber'] ?? null,
        'notes' => $validated['serviceTag'] ?? null,
        'warranty_expiry' => $validated['warrantyExpiry'] ?? null,
        'assigned_to' => $validated['userId'],
        'status' => 'assigned',
        'purchase_date' => now(),
        'created_by' => auth()->id(),
      ]);
    }

    // 2. Record Assignment History
    if ($asset && \Illuminate\Support\Facades\Schema::hasTable('asset_assignments')) {
      \App\Models\AssetAssignment::create([
        'asset_id' => $asset->id,
        'user_id' => $validated['userId'],
        'assigned_by' => auth()->id(),
        'assigned_at' => now(),
        'notes' => 'Allotted via Employee Portal',
        'tenant_id' => auth()->user()->tenant_id,
      ]);
    }

    // 3. Update UserDevice tracking record
    $device = UserDevice::updateOrCreate(
      ['user_id' => $validated['userId']],
      [
        'device_id' => $assetCode,
        'brand' => $validated['brand'] ?? 'Company Asset',
        'device_type' => $validated['deviceType'] ?? 'company_device',
        'board' => 'N/A',
        'sdk_version' => 'N/A',
        'model' => $validated['brand'] ?? 'N/A',
        'token' => '',
        'latitude' => 0,
        'longitude' => 0,
        'address' => 'Allotted via Portal',
        'updated_by_id' => auth()->id(),
        'tenant_id' => auth()->user()->tenant_id,
      ]
    );

    return redirect()->back()->with('success', 'Device/Asset allotted successfully');
  }


  public function getReportingToUsersAjax()
  {
    $users = User::where('status', UserAccountStatus::ACTIVE)
      ->select('id', 'first_name', 'last_name', 'code')
      ->get();

    return Success::response($users);
  }

  public function updateWorkInformation(Request $request)
  {

    $validated = $request->validate([
      'id' => 'required|exists:users,id',
      'doj' => 'required|date',
      'departmentId' => 'required|exists:departments,id',
      'designationId' => 'required|exists:designations,id',
      'role' => 'required|exists:roles,name',
      'reportingToId' => 'required|exists:users,id',
      'attendanceType' => 'required|in:open,geofence,ipAddress,staticqr,site,dynamicqr,face',
      'geofenceGroupId' => 'required_if:attendanceType,geofence|exists:geofence_groups,id',
      'ipGroupId' => 'required_if:attendanceType,ipAddress|exists:ip_address_groups,id',
      'qrGroupId' => 'required_if:attendanceType,staticqr|exists:qr_groups,id',
      'siteId' => 'required_if:attendanceType,site|exists:sites,id',
      'dynamicQrId' => 'required_if:attendanceType,dynamicqr|exists:dynamic_qr_devices,id',
      'leavePolicyProfileId' => 'nullable|exists:leave_policy_profiles,id',
      'work_type' => 'nullable|string|max:50',
      'biometric_id' => 'nullable|string|max:255',
    ]);

    $user = User::findOrFail($validated['id']);

    // Update Basic Work Info
    $user->date_of_joining = $validated['doj'];
    $user->department_id = $validated['departmentId'];
    $user->designation_id = $validated['designationId'];
    $user->reporting_to_id = $validated['reportingToId'];

    if ($request->has('leavePolicyProfileId')) {
      $user->leave_policy_profile_id = $validated['leavePolicyProfileId'];
    }

    if ($request->has('work_type')) {
      $user->work_type = $validated['work_type'];
    }

    if ($request->has('biometric_id')) {
      $user->biometric_id = $validated['biometric_id'];
    }

    // Role Sync
    $user->syncRoles([$validated['role']]);

    // Save User
    $user->save();

    // Initialize Leaves (Idempotent)
    $leaveCount = 0;
    if ($user->leave_policy_profile_id) {
      $leaveCount = \App\Services\LeaveAccrualService::initializeForUser($user);
    }

    switch ($validated['attendanceType']) {
      case 'geofence':
        $user->attendance_type = 'geofence';
        $user->geofence_group_id = $validated['geofenceGroupId'];
        break;
      case 'ipAddress':
        $user->attendance_type = 'ip_address';
        $user->ip_address_group_id = $validated['ipGroupId'];
        break;
      case 'staticqr':
        $user->attendance_type = 'qr_code';
        $user->qr_group_id = $validated['qrGroupId'];
        break;
      case 'site':
        $user->attendance_type = 'site';
        $user->site_id = $validated['siteId'];
        break;
      case 'dynamicqr':
        $user->attendance_type = 'dynamic_qr';
        $user->dynamic_qr_device_id = $validated['dynamicQrId'];
        DynamicQrDevice::where('id', $validated['dynamicQrId'])
          ->update(['user_id' => $user->id, 'status' => 'in_use']);
        break;
      case 'face':
        $user->attendance_type = 'face_recognition';
        break;
      default:
        $user->attendance_type = 'open';
        break;
    }


    $user->save();

    // Update user role
    $role = Role::where('name', $validated['role'])->first();
    $user->roles()->sync([$role->id]);

    return redirect()->back()->with('success', 'Work information updated successfully');
  }

  public function updateCompensationInfo(Request $request)
  {
    $validated = $request->validate([
      'id' => 'required|exists:users,id',
      'baseSalary' => 'nullable|numeric|min:0',
      'ctcOffered' => 'nullable|numeric|min:0',
      'availableLeaveCount' => 'nullable|numeric|min:0',
      'leavePolicyProfileId' => 'nullable|exists:leave_policy_profiles,id',
    ]);

    $user = User::find($validated['id']);

    if ($user->base_salary != ($validated['baseSalary'] ?? 0)) {
      $user->base_salary = $validated['baseSalary'];
    }

    if ($user->ctc_offered != ($validated['ctcOffered'] ?? 0)) {
      $user->ctc_offered = $validated['ctcOffered'];
    }

    if ($user->available_leave_count != ($validated['availableLeaveCount'] ?? 0)) {
      $user->available_leave_count = $validated['availableLeaveCount'];
    }

    if ($user->leave_policy_profile_id != ($validated['leavePolicyProfileId'] ?? null)) {
      $user->leave_policy_profile_id = $validated['leavePolicyProfileId'];
    }

    $user->save();

    if ($user->leave_policy_profile_id) {
      \App\Services\LeaveAccrualService::initializeForUser($user->fresh());
    }

    return redirect()->back()->with('success', 'Compensation info updated successfully');
  }

  public function addOrUpdateDocument(Request $request)
  {
    $request->validate([
      'userId' => 'required|exists:users,id',
      'documentName' => 'required|string|max:255',
      'documentNumber' => 'nullable|string|max:255',
      'file' => 'required|file|mimes:pdf,jpg,png,jpeg|max:10240',
    ]);

    // Security check: Only allow users to upload their own document unless they are admin/hr/manager
    if ($request->userId != auth()->id() && !auth()->user()->hasRole(['admin', 'hr', 'manager'])) {
      return redirect()->back()->with('error', 'Unauthorized action.');
    }

    $isEmployee = auth()->user()->hasRole('employee') && !auth()->user()->hasRole(['admin', 'hr', 'manager']);

    try {
      $user = User::findOrFail($request->userId);
      $file = $request->file('file');

      // Find or Create a DocumentType based on the code to avoid unique constraint violations
      // Must bypass TenantTrait global scope globally since the database has a strict unique index on `code`
      $docCode = strtoupper(str_replace(' ', '_', $request->documentName));
      $docType = DocumentType::withoutGlobalScopes()->withTrashed()->where('code', $docCode)->first();

      if ($docType && $docType->trashed()) {
        $docType->restore();
      } elseif (!$docType) {
        $docType = DocumentType::create([
          'name' => $request->documentName,
          'code' => $docCode,
          'status' => CommonStatus::ACTIVE,
          'tenant_id' => $user->tenant_id
        ]);
      }

      $actualDocumentNumber = $request->documentNumber ?? $request->remarks;

      if ($isEmployee) {
        // Check for existing pending request of SAME document type
        $existing = DocumentRequest::where('user_id', $user->id)
          ->where('document_type_id', $docType->id)
          ->where('status', 'pending')
          ->first();

        if ($existing) {
          return redirect()->back()->with('error', 'You already have a pending request for this document.');
        }
      }

      $folder = Constants::BaseFolderOnboardingDocuments . $user->id;
      $extension = $file->getClientOriginalExtension();

      // Ensure Directory exists
      if (!Storage::disk('public')->exists($folder)) {
        Storage::disk('public')->makeDirectory($folder);
      }

      // Automatically map standard document names to core User table columns and file prefixes
      $filePrefix = 'doc';
      $docNameLower = strtolower(trim($request->documentName));
      $userUpdateData = [];

      if (str_contains($docNameLower, 'aadhaar') || str_contains($docNameLower, 'aadhar')) {
        $filePrefix = 'aadhaar_card';
        if ($actualDocumentNumber)
          $userUpdateData['aadhaar_no'] = $actualDocumentNumber;
      } elseif (str_contains($docNameLower, 'pan')) {
        $filePrefix = 'pan_card';
        if ($actualDocumentNumber)
          $userUpdateData['pan_no'] = $actualDocumentNumber;
      } elseif (str_contains($docNameLower, '10th') || str_contains($docNameLower, 'matric')) {
        $filePrefix = 'matric_certificate';
        if ($actualDocumentNumber)
          $userUpdateData['matric_marksheet_no'] = $actualDocumentNumber;
      } elseif (str_contains($docNameLower, '12th') || str_contains($docNameLower, 'inter')) {
        $filePrefix = 'inter_certificate';
        if ($actualDocumentNumber)
          $userUpdateData['inter_marksheet_no'] = $actualDocumentNumber;
      } elseif (str_contains($docNameLower, 'graduation') || str_contains($docNameLower, 'bachelor')) {
        $filePrefix = 'graduation_certificate';
        if ($actualDocumentNumber)
          $userUpdateData['bachelor_marksheet_no'] = $actualDocumentNumber;
      } elseif (str_contains($docNameLower, 'post graduation') || str_contains($docNameLower, 'master')) {
        $filePrefix = 'master_certificate';
        if ($actualDocumentNumber)
          $userUpdateData['master_marksheet_no'] = $actualDocumentNumber;
      } elseif (str_contains($docNameLower, 'experience')) {
        $filePrefix = 'experience_certificate';
        if ($actualDocumentNumber)
          $userUpdateData['experience_certificate_no'] = $actualDocumentNumber;
      } elseif ($docNameLower === 'cancelled cheque') {
        $filePrefix = 'cancelled_cheque';
      }

      // If any core fields need to be updated and user has rights or is pending review but Admin processes it
      if (!$isEmployee && !empty($userUpdateData)) {
        $user->update($userUpdateData);
      }

      $path = \App\Helpers\FileSecurityHelper::encryptAndStore($file, $folder, $filePrefix, 'public');

      $documentRequest = \App\Models\DocumentRequest::updateOrCreate(
        [
          'user_id' => $user->id,
          'document_type_id' => $docType->id,
        ],
        [
          'remarks' => $actualDocumentNumber,
          'generated_file' => $path,
          'status' => $isEmployee ? 'pending' : 'approved',
          'action_taken_at' => !$isEmployee ? now() : null,
          'updated_by_id' => auth()->id(),
          'tenant_id' => $user->tenant_id
        ]
      );

      return redirect()->back()->with('success', $isEmployee ? 'Document update request submitted for approval.' : 'Document uploaded successfully.');
    } catch (\Exception $e) {
      Log::error('Upload Document Error: ' . $e->getMessage());
      return redirect()->back()->with('error', 'Failed to upload document.');
    }
  }

  public function updateBasicInfo(Request $request)
  {
    $validated = $request->validate([
      'id' => 'required|exists:users,id',
      'firstName' => 'required|string|max:255',
      'lastName' => 'required|string',
      'dob' => 'nullable|date',
      'gender' => ['nullable', Rule::in(array_column(Gender::cases(), 'value'))],
      'phone' => 'nullable|string|max:20',
      'official_phone' => 'nullable|string|max:20',
      'altPhone' => 'nullable|string|max:20',
      'email' => 'nullable|email|unique:users,email,' . $request->id,
      'personal_email' => 'nullable|email|max:255',
      'blood_group' => 'nullable|string|max:20',
      'marital_status' => 'nullable|string|max:50',
      'father_name' => 'nullable|string|max:255',
      'mother_name' => 'nullable|string|max:255',
      'spouse_name' => 'nullable|string|max:255',
      'no_of_children' => 'nullable|integer|min:0',
      'birth_country' => 'nullable|string|max:100',
      'citizenship' => 'nullable|string|max:100',
      // Current (Temporary) Address
      'temp_building' => 'nullable|string|max:255',
      'temp_street' => 'nullable|string|max:255',
      'temp_city' => 'nullable|string|max:100',
      'temp_state' => 'nullable|string|max:100',
      'temp_zip' => 'nullable|string|max:20',
      'temp_country' => 'nullable|string|max:100',
      // Permanent Address
      'perm_building' => 'nullable|string|max:255',
      'perm_street' => 'nullable|string|max:255',
      'perm_city' => 'nullable|string|max:100',
      'perm_state' => 'nullable|string|max:100',
      'perm_zip' => 'nullable|string|max:20',
      'perm_country' => 'nullable|string|max:100',
      // Emergency Contact
      'emergency_contact_name' => 'nullable|string|max:255',
      'emergency_contact_relation' => 'nullable|string|max:100',
      'emergency_contact_phone' => 'nullable|string|max:20',
      'biometric_id' => 'nullable|string|max:255',
    ]);

    // Security check: Only allow users to update their own records unless they are admin/hr/manager
    if ($validated['id'] != auth()->id() && !auth()->user()->hasRole(['admin', 'hr', 'manager'])) {
      return redirect()->back()->with('error', 'Unauthorized action.');
    }

    $user = User::find($validated['id']);
    $isEmployee = auth()->user()->hasRole('employee') && !auth()->user()->hasRole(['admin', 'hr', 'manager']);

    // ONLY update fields that are actually in the request AND non-empty to prevent wiping out data
    if ($request->filled('phone'))
      $user->phone = $request->phone;
    if ($request->filled('official_phone'))
      $user->official_phone = $request->official_phone;
    if ($request->filled('email'))
      $user->email = $request->email;
    if ($request->filled('personal_email'))
      $user->personal_email = $request->personal_email;
    if ($request->filled('altPhone'))
      $user->alternate_number = $request->altPhone;

    // Address fields
    foreach ([
      'temp_building',
      'temp_street',
      'temp_city',
      'temp_state',
      'temp_zip',
      'temp_country',
      'perm_building',
      'perm_street',
      'perm_city',
      'perm_state',
      'perm_zip',
      'perm_country'
    ] as $field) {
      if ($request->filled($field))
        $user->$field = $request->$field;
    }

    // Emergency Contact
    if ($request->has('emergency_contact_name'))
      $user->emergency_contact_name = $request->emergency_contact_name;
    if ($request->has('emergency_contact_relation'))
      $user->emergency_contact_relation = $request->emergency_contact_relation;
    if ($request->has('emergency_contact_phone'))
      $user->emergency_contact_phone = $request->emergency_contact_phone;

    // Profile Details
    if ($request->filled('blood_group'))
      $user->blood_group = $request->blood_group;
    if ($request->filled('marital_status'))
      $user->marital_status = $request->marital_status;
    if ($request->filled('father_name'))
      $user->father_name = $request->father_name;
    if ($request->filled('mother_name'))
      $user->mother_name = $request->mother_name;
    if ($request->filled('spouse_name'))
      $user->spouse_name = $request->spouse_name;
    if ($request->filled('no_of_children'))
      $user->no_of_children = $request->no_of_children;
    if ($request->filled('birth_country'))
      $user->birth_country = $request->birth_country;
    if ($request->filled('citizenship'))
      $user->citizenship = $request->citizenship;

    // Restricted fields: Only update if NOT an employee (or if admin/hr/manager)
    // Exception: employees CAN update their own record
    $isSelfEdit = ($validated['id'] == auth()->id());
    if (!$isEmployee || $isSelfEdit) {
      if ($request->filled('firstName'))
        $user->first_name = $request->firstName;
      if ($request->filled('lastName'))
        $user->last_name = $request->lastName;
      if ($request->filled('dob'))
        $user->dob = $request->dob;
      if ($request->filled('gender'))
        $user->gender = $request->gender;
      if ($request->filled('biometric_id') && !$isEmployee)
        $user->biometric_id = $request->biometric_id;
    }

    $user->save();

    return redirect()->back()->with('success', 'Basic info updated successfully');
  }

  /**
   * Initiate the termination process for an employee.
   */
  public function initiateTermination(Request $request, User $user)
  {
    /*// --- Authorization & Pre-condition Check ---
     if (!Auth::user()->can('terminate employees')) { // Example permission
     return Error::response('Permission denied.', 403);
     }*/
    if ($user->status == UserAccountStatus::TERMINATED) { // Use Enum comparison
      return Error::response('Employee is already terminated.', 409);
    }

    // --- Validation ---
    $validator = Validator::make($request->all(), [
      'exitDate' => 'required|date_format:Y-m-d',
      'lastWorkingDay' => 'required|date_format:Y-m-d|after_or_equal:exitDate',
      'terminationType' => ['required', new Enum(TerminationType::class)], // Use Enum validation if created
      // 'terminationType' => 'required|string|in:resignation,terminated_with_cause,...', // Alternative if not using Enum model cast
      'exitReason' => 'required|string|max:1000',
      'isEligibleForRehire' => 'required|boolean',
    ]);

    if ($validator->fails()) {
      return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
    }

    // --- Update User ---
    DB::beginTransaction();
    try {
      $validatedData = $validator->validated();
      $adminUserId = Auth::id();

      $user->update([
        'status' => UserAccountStatus::TERMINATED, // Set status
        'exit_date' => $validatedData['exitDate'],
        'last_working_day' => $validatedData['lastWorkingDay'],
        'termination_type' => $validatedData['terminationType'],
        'exit_reason' => $validatedData['exitReason'],
        'is_eligible_for_rehire' => filter_var($validatedData['isEligibleForRehire'], FILTER_VALIDATE_BOOLEAN),
        'updated_by_id' => $adminUserId,
        // Maybe clear tokens, disable login? Depends on setup.
      ]);

      // TODO: Trigger Offboarding Checklist / Notifications?

      // Log this action (using a generic activity logger or specific audit)
      Log::info("User ID {$user->id} terminated by User ID {$adminUserId}. Reason: {$validatedData['exitReason']}");
      // Or use JobApplicationActivity if termination stems from recruitment flow? Less likely here.

      DB::commit();

      // Return structure consistent with your Success::response wrapper if applicable
      return response()->json([
        'success' => true,
        'message' => 'Employee termination process initiated successfully.'
      ]);
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error("Error terminating employee ID {$user->id}: " . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'An error occurred during termination.'], 500);
    }
  }


  /**
   * Confirm the successful completion of an employee's probation.
   *
   * @param Request $request
   * @param User $user The employee whose probation is being confirmed (Route Model Binding)
   *
   */
  public function confirmProbation(Request $request, User $user)
  {
    /* // --- Authorization Check ---
     // Example: Replace with your actual permission check
     if (!Auth::user()->can('manage_probation')) {
     return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
     }*/

    // --- Pre-condition Check ---
    // Check if user exists and is actually eligible for probation confirmation
    // (e.g., has a probation end date, isn't already confirmed, isn't terminated)
    // Using the accessor assumes it checks for null end_date and null confirmed_at
    // Add more checks if needed based on your exact logic for eligibility
    if ($user->probation_confirmed_at !== null) {
      return response()->json(['success' => false, 'message' => 'Probation has already been confirmed for this employee.'], 409); // 409 Conflict
    }
    if (is_null($user->probation_end_date)) {
      return response()->json(['success' => false, 'message' => 'This employee does not have a probation period defined.'], 400);
    }
    // Optional: Check if probation period actually ended? Or allow early confirmation?
    // if (Carbon::parse($user->probation_end_date)->isFuture()) {
    //    return response()->json(['success' => false, 'message' => 'Probation period has not ended yet.'], 400);
    // }


    // --- Validation ---
    $validator = Validator::make($request->all(), [
      'probationRemarks' => 'nullable|string|max:2000', // Optional remarks
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => $validator->errors()
      ], 422);
    }

    // --- Update User ---
    DB::beginTransaction(); // Optional: Use transaction if other actions occur
    try {
      $adminUser = Auth::user();
      $remarks = $request->input('probationRemarks');
      $confirmationTimestamp = now();

      // Construct remarks entry
      $remarkEntry = "Probation confirmed by {$adminUser->getFullName()} on " . $confirmationTimestamp->format('Y-m-d H:i') . ".";
      if (!empty($remarks)) {
        $remarkEntry .= "\nRemarks: " . $remarks;
      }

      $user->probation_confirmed_at = $confirmationTimestamp;
      // Append remarks or set them - decide on your preferred logic
      $user->probation_remarks = ($user->probation_remarks ? $user->probation_remarks . "\n\n---\n\n" : '') . $remarkEntry;
      // Optional: Update user status if needed, though likely already ACTIVE
      // $user->status = UserAccountStatus::ACTIVE;
      $user->save();

      // TODO: Log this action (e.g., Audit log or specific activity log)
      Log::info("Probation confirmed for User ID {$user->id} by Admin ID {$adminUser->id}.");

      DB::commit(); // Commit transaction if used

      // Return success response consistent with your standard
      return response()->json([
        'success' => true,
        'message' => 'Employee probation confirmed successfully.'
        // Optionally return updated user data or probation status
      ]);
    } catch (\Exception $e) {
      DB::rollBack(); // Rollback transaction on error
      Log::error("Error confirming probation for User ID {$user->id}: " . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'An error occurred while confirming probation.'
      ], 500);
    }
  }

  /**
   * Extend the probation period for an employee.
   *
   * @param Request $request
   * @param User $user The employee whose probation is being extended
   * @return JsonResponse
   */
  public function extendProbation(Request $request, User $user): JsonResponse
  {
    /* // --- Authorization Check ---
     if (!Auth::user()->can('manage_probation')) { // Example permission
     return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
     }*/

    // --- Pre-condition Check ---
    if ($user->probation_confirmed_at !== null) {
      return response()->json(['success' => false, 'message' => 'Probation has already been confirmed.'], 409);
    }
    if (is_null($user->probation_end_date)) {
      return response()->json(['success' => false, 'message' => 'No probation period defined for extension.'], 400);
    }
    if ($user->status !== UserAccountStatus::ACTIVE) {
      return response()->json(['success' => false, 'message' => 'Employee must be active to extend probation.'], 400);
    }

    // --- Validation ---
    $currentEndDate = Carbon::parse($user->probation_end_date);
    $validator = Validator::make($request->all(), [
      // New end date must be after the current probation end date
      'newProbationEndDate' => ['required', 'date_format:Y-m-d', 'after:' . $currentEndDate->toDateString()],
      'probationRemarks' => 'required|string|max:2000', // Reason for extension is required
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => $validator->errors()
      ], 422);
    }

    // --- Update User ---
    DB::beginTransaction();
    try {
      $adminUser = Auth::user();
      $validatedData = $validator->validated();
      $newEndDate = $validatedData['newProbationEndDate'];
      $reason = $validatedData['probationRemarks'];
      $extensionTimestamp = now();

      // Construct remark entry for extension
      $remarkEntry = "Probation extended by {$adminUser->getFullName()} on " . $extensionTimestamp->format('Y-m-d H:i') . " to {$newEndDate}.";
      $remarkEntry .= "\nReason: " . $reason;

      $user->probation_end_date = $newEndDate;
      $user->is_probation_extended = true; // Mark as extended
      $user->probation_remarks = ($user->probation_remarks ? $user->probation_remarks . "\n\n---\n\n" : '') . $remarkEntry;
      // Ensure confirmation date is null if extending
      $user->probation_confirmed_at = null;
      $user->save();

      // TODO: Log this action
      Log::info("Probation extended for User ID {$user->id} to {$newEndDate} by Admin ID {$adminUser->id}.");

      DB::commit();

      return response()->json([
        'success' => true,
        'message' => 'Employee probation extended successfully.'
      ]);
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error("Error extending probation for User ID {$user->id}: " . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'An error occurred while extending probation.'], 500);
    }
  }


  /**
   * Fail the probation period for an employee, initiating termination.
   *
   * @param Request $request
   * @param User $user The employee failing probation
   * @return JsonResponse
   */
  public function failProbation(Request $request, User $user): JsonResponse
  {
    // --- Authorization Check ---
    // Failing probation often leads to termination, might require termination permission
    /* if (!Auth::user()->can('manage_probation') || !Auth::user()->can('terminate_employees')) {
     return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
     }*/

    // --- Pre-condition Check ---
    if ($user->probation_confirmed_at !== null) {
      return response()->json(['success' => false, 'message' => 'Probation has already been confirmed.'], 409);
    }
    if ($user->status !== UserAccountStatus::ACTIVE) { // Must be active to fail probation (not already terminated etc.)
      return response()->json(['success' => false, 'message' => 'Employee is not currently active.'], 400);
    }
    if (is_null($user->probation_end_date)) {
      return response()->json(['success' => false, 'message' => 'No probation period defined to fail.'], 400);
    }


    // --- Validation ---
    $validator = Validator::make($request->all(), [
      'probationRemarks' => 'required|string|max:2000', // Reason for failure is required
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => $validator->errors()
      ], 422);
    }

    // --- Update User (Terminate due to Probation Failure) ---
    DB::beginTransaction();
    try {
      $adminUser = Auth::user();
      $validatedData = $validator->validated();
      $reason = $validatedData['probationRemarks'];
      $terminationTimestamp = now();

      // Construct remark entry for failure
      $remarkEntry = "Probation failed by {$adminUser->getFullName()} on " . $terminationTimestamp->format('Y-m-d H:i') . ".";
      $remarkEntry .= "\nReason: " . $reason;

      // Update user record to reflect termination due to probation failure
      $user->status = UserAccountStatus::TERMINATED; // Or UserAccountStatus::PROBATION_FAILED if using specific status
      $user->exit_date = $terminationTimestamp->toDateString();
      $user->last_working_day = $terminationTimestamp->toDateString(); // Or set differently if needed
      $user->termination_type = TerminationType::PROBATION_FAILED->value; // Use Enum
      $user->exit_reason = "Probation Failed: " . $reason;
      $user->is_eligible_for_rehire = false; // Typically not eligible after probation failure
      $user->probation_remarks = ($user->probation_remarks ? $user->probation_remarks . "\n\n---\n\n" : '') . $remarkEntry;
      // Ensure confirmation date is null
      $user->probation_confirmed_at = null;
      $user->updated_by_id = $adminUser->id;
      $user->save();

      // TODO: Log this action (Termination + Probation Failure)
      Log::info("Probation failed for User ID {$user->id}. Terminated by Admin ID {$adminUser->id}. Reason: {$reason}");

      // TODO: Trigger Offboarding / Notifications?

      DB::commit();

      return response()->json([
        'success' => true,
        'message' => 'Employee probation failed and termination process initiated.'
      ]);
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error("Error failing probation for User ID {$user->id}: " . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'An error occurred while failing probation.'], 500);
    }
  }



  public function index()
  {
    $active = User::where('status', UserAccountStatus::ACTIVE)->count();
    $inactive = User::where('status', UserAccountStatus::INACTIVE)->count();
    $relieved = User::where('status', UserAccountStatus::RELIEVED)->count();
    $onboarding = User::whereIn('status', [
      UserAccountStatus::ONBOARDING,
      UserAccountStatus::ONBOARDING_SUBMITTED,
      UserAccountStatus::ONBOARDING_REQUESTED,
      UserAccountStatus::INVITED
    ])->count();

    $roles = Role::select('id', 'name')
      ->get()
      ->map(function ($role) {
        $role->display_name = $role->display_name ?? $role->name;
        return $role;
      });

    $teams = Team::where('status', Status::ACTIVE)
      ->select('id', 'name', 'code')
      ->get();

    $designations = Designation::where('status', Status::ACTIVE)
      ->select('id', 'name', 'code')
      ->get();

    $departments = \App\Models\Department::where('status', Status::ACTIVE)
      ->select('id', 'name', 'code')
      ->get();

    return view('tenant.employees.index', [
      'totalUser' => $active + $inactive + $relieved + $onboarding,
      'active' => $active,
      'inactive' => $inactive,
      'relieved' => $relieved,
      'onboardingCount' => $onboarding,
      'roles' => $roles,
      'teams' => $teams,
      'departments' => $departments,
      'designations' => $designations,
      'managers' => User::where('status', UserAccountStatus::ACTIVE)->get(),
      'users' => User::whereIn('status', [
        UserAccountStatus::ACTIVE,
        UserAccountStatus::ONBOARDING,
        UserAccountStatus::ONBOARDING_SUBMITTED,
        UserAccountStatus::ONBOARDING_REQUESTED,
        UserAccountStatus::INVITED
      ])->with(['team', 'designation', 'department'])->orderBy('first_name', 'asc')->paginate(12)
    ]);
  }

  public function changeEmployeeProfilePicture(Request $request)
  {
    $rules = [
      'userId' => 'required|exists:users,id',
      'file' => 'required|image|mimes:jpeg,png,jpg|max:5096',
    ];

    $validatedData = $request->validate($rules);

    // Security check: Only allow users to change their own picture unless they are admin/hr/manager
    if ($validatedData['userId'] != auth()->id() && !auth()->user()->hasRole(['admin', 'hr', 'manager'])) {
      return redirect()->back()->with('error', 'Unauthorized action.');
    }

    try {
      $user = User::find($request->input('userId'));

      if (!$user) {
        return Error::response('User not found');
      }

      if ($request->hasFile('file')) {
        $file = $request->file('file');
        $fileName = $user->code . '_' . time() . '.' . $file->getClientOriginalExtension();

        //Delete Old File
        $oldProfilePicture = $user->profile_picture;
        if (!is_null($oldProfilePicture)) {
          $oldProfilePicturePath = Storage::disk('public')->path(Constants::BaseFolderEmployeeProfileWithSlash . $oldProfilePicture);
          if (file_exists($oldProfilePicturePath)) {
            Storage::delete($oldProfilePicturePath);
          }
        }

        //Create Directory if not exists
        if (!Storage::disk('public')->exists(Constants::BaseFolderEmployeeProfile)) {
          Storage::disk('public')->makeDirectory(Constants::BaseFolderEmployeeProfile);
        }

        Storage::disk('public')->putFileAs(Constants::BaseFolderEmployeeProfileWithSlash, $file, $fileName);

        $user->profile_picture = $fileName;
        $user->save();
      }

      return redirect()->back()->with('success', 'Profile picture updated successfully');
    } catch (\Exception $e) {
      Log::error('EmployeeController@changeEmployeeProfilePicture: ' . $e->getMessage());
      return redirect()->back()->with('error', 'Failed to update profile picture');
    }
  }

  public function userListAjax(Request $request)
  {
    try {
      // Map DataTables column indexes to actual DB columns (only real columns).
      $columns = [
        0 => 'first_name', // Column 0 in JS
        1 => 'code',       // Column 1 in JS
        2 => 'team_id',    // Column 2
        3 => 'designation_id', // Column 3
        4 => 'status',      // Column 4
        5 => 'date_of_joining', // Column 5
        6 => 'id',          // Actions (Column 6)
      ];

      $search = [];

      $totalData = User::count();

      $totalFiltered = $totalData;

      $limit = $request->input('length');
      $start = $request->input('start');
      $orderIndex = (int) $request->input('order.0.column', 1);
      $order = $columns[$orderIndex] ?? 'id';
      $dir = strtolower((string) $request->input('order.0.dir', 'desc'));
      if (!in_array($dir, ['asc', 'desc'], true)) {
        $dir = 'desc';
      }

      $query = User::with(['team', 'designation', 'roles']);

      if ($request->has('roleFilter') && !empty($request->input('roleFilter'))) {
        $query->whereHas('roles', function ($q) use ($request) {
          $q->where('name', $request->input('roleFilter'));
        });
      }

      if ($request->has('teamFilter') && !empty($request->input('teamFilter'))) {
        $query->where('team_id', $request->input('teamFilter'));
      }

      if ($request->has('designationFilter') && !empty($request->input('designationFilter'))) {
        $query->where('designation_id', $request->input('designationFilter'));
      }

      if ($request->has('statusFilter') && !empty($request->input('statusFilter'))) {
        $query->where('status', $request->input('statusFilter'));
      }

      if (!empty($request->input('search.value'))) {
        $search = $request->input('search.value');
        $query->where(function ($q) use ($search) {
          $q->where('id', 'LIKE', "%{$search}%")
            ->orWhere('first_name', 'LIKE', "%{$search}%")
            ->orWhere('last_name', 'LIKE', "%{$search}%")
            ->orWhere('phone', 'LIKE', "%{$search}%")
            ->orWhere('email', 'LIKE', "%{$search}%")
            ->orWhere('code', 'LIKE', "%{$search}%");
        });
      }

      $totalFiltered = $query->count();

      $users = $query->offset($start)
        ->limit($limit)
        ->orderBy($order, $dir)
        ->get();

      $data = [];

      if (!empty($users)) {
        $data = $users->map(function ($user) {
          return [
            'id' => $user->id,
            'name' => $user->full_name,
            'attendance_type' => $user->attendance_type,
            'team' => $user->team->name ?? null,
            'designation' => $user->designation->name ?? null,
            'joined' => $user->date_of_joining,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'status' => $user->status->value,
            'code' => $user->code,
            'phone' => $user->phone,
            'role' => $user->role_display_name,
            'profile_picture' => $user->getProfilePicture(),
            'personal_email' => $user->personal_email,
            'official_phone' => $user->official_phone,
          ];
        })->toArray();
      }

      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => intval($totalData),
        'recordsFiltered' => intval($totalFiltered),
        'code' => 200,
        'data' => $data,
      ]);
    } catch (\Exception $e) {
      Log::error('EmployeeController@userListAjax: ' . $e->getMessage());
      return Error::response($e->getMessage());
    }
  }

  public function deleteEmployeeAjax($id)
  {
    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in the demo.');
    }

    try {
      $user = User::find($id);

      if (!$user) {
        return Error::response('User not found');
      }

      if ($user->status == \App\Enums\UserAccountStatus::ACTIVE) {
        $user->status = \App\Enums\UserAccountStatus::INACTIVE;
        $msg = 'User account locked successfully';
      } else {
        $user->status = \App\Enums\UserAccountStatus::ACTIVE;
        $msg = 'User account unlocked successfully';
      }

      $user->save();

      return Success::response($msg);
    } catch (\Exception $e) {
      Log::error('EmployeeController@deleteEmployeeAjax: ' . $e->getMessage());
      return Error::response('Failed to delete user');
    }
  }

  public function resetPasswordAjax(Request $request)
  {
    try {
      $user = User::findOrFail($request->id);
      $user->password = bcrypt('123456');
      $user->save();
      return Success::response('Password reset successfully to default (123456)');
    } catch (\Exception $e) {
      return Error::response($e->getMessage());
    }
  }

  public function show($id)
  {
    validator(['id' => $id], ['id' => 'required|exists:users,id'])->validate();

    // Optimize: Eager load only necessary fields for performance
    $user = User::where('id', $id)
      ->with([
        'userDevice' => fn($q) => $q->select('id', 'user_id', 'device_id', 'brand', 'device_type', 'model'),
        'team:id,name,code',
        'department:id,name,code',
        'leaveBalances.leaveType:id,name,code',
        'shift:id,name,code,start_time,end_time',
        'designation.department:id,name,code',
        'bankAccount:id,user_id,bank_name,bank_code,account_name,account_number,branch_name,branch_code,passbook_path',
        // Only fetch 10 most recent records for these heavy relationships
        'tasks' => fn($q) => $q->latest()->take(10),
        'documentRequests' => fn($q) => $q->with('documentType')->latest(),
        'payslips' => fn($q) => $q->latest()->take(6),
        'payrollAdjustments' => fn($q) => $q->latest()->take(10),
        'salesTargets' => fn($q) => $q->latest()->take(10)
      ])
      ->first();

    $auditLogs = Audit::where('user_id', $id)->latest()->take(5)->get();

    $documentTypes = DocumentType::where('status', CommonStatus::ACTIVE)
      ->select('id', 'name', 'code') // Optimization: Select only needed fields
      ->get();

    $leaveTypes = LeaveType::where('status', Status::ACTIVE)
      ->select('id', 'name', 'code')
      ->get();

    $availableAssets = \App\Models\Asset::where('status', 'available')
      ->select('id', 'name', 'asset_code', 'serial_number')
      ->get();

    $leavePolicyProfiles = \App\Models\LeavePolicyProfile::select('id', 'name')->get();

    // Role of currently viewed user
    $role = $user->getRoleNames()->first() ?? 'Employee';

    // Optimization: These are only needed for the Edit Modals. 
    // In a future step, these will be moved to an AJAX-on-demand endpoint.
    $roles = \Spatie\Permission\Models\Role::select('id', 'name')->get();
    $departments = \App\Models\Department::where('status', \App\Enums\Status::ACTIVE)->select('id', 'name')->get();
    $designations = \App\Models\Designation::where('status', \App\Enums\Status::ACTIVE)->select('id', 'name')->get();
    $allUsers = User::where('status', \App\Enums\UserAccountStatus::ACTIVE)
      ->where('id', '!=', $id)
      ->select('id', 'first_name', 'last_name')
      ->get();

    return view('tenant.employees.view', [
      'user' => $user,
      'documentTypes' => $documentTypes,
      'leaveTypes' => $leaveTypes,
      'availableAssets' => $availableAssets,
      'auditLogs' => $auditLogs,
      'role' => $role,
      'leavePolicyProfiles' => $leavePolicyProfiles,
      'roles' => $roles,
      'departments' => $departments,
      'designations' => $designations,
      'allUsers' => $allUsers
    ]);
  }

  public function store(Request $request)
  {
    $request->validate([
      'firstName' => 'required|string|max:255',
      'lastName' => 'required|string|max:255',
      'gender' => ['required', Rule::in(array_column(Gender::cases(), 'value'))],
      'phone' => 'required|string|max:15|unique:users,phone',
      'altPhone' => 'nullable|string|max:15',
      'email' => 'required|email|unique:users,email',
      'role' => 'required|exists:roles,name',
      'dob' => 'required|date',
      'address' => 'nullable|string|max:255',
      'file' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
      'useDefaultPassword' => 'nullable',
      'password' => 'nullable|min:6',
      'confirmPassword' => 'nullable|min:6|same:password',

      'code' => 'required|string|max:255|unique:users,code',
      'designationId' => 'required|exists:designations,id',
      'doj' => 'required|date',
      'teamId' => 'required|exists:teams,id',
      'shiftId' => 'required|exists:shifts,id',
      'reportingToId' => 'required|exists:users,id',
      'attendanceType' => 'required|in:open,geofence,ipAddress,staticqr,dynamicqr,site,face',
      'geofenceGroupId' => 'required_if:attendanceType,geofence|exists:geofence_groups,id',
      'ipGroupId' => 'required_if:attendanceType,ipAddress|exists:ip_address_groups,id',
      'qrGroupId' => 'required_if:attendanceType,staticqr|exists:qr_groups,id',
      'siteId' => 'required_if:attendanceType,site|exists:sites,id',
      'dynamicQrId' => 'required_if:attendanceType,dynamicqr|exists:dynamic_qr_devices,id',

      'baseSalary' => 'required|numeric',
      'availableLeaveCount' => 'nullable|numeric',
      'leavePolicyProfileId' => 'nullable|exists:leave_policy_profiles,id',
      'work_type' => 'nullable|string|max:50',
    ]);

    try {
      $user = new User();
      $user->first_name = $request->input('firstName');
      $user->last_name = $request->input('lastName');
      $user->gender = Gender::from($request->input('gender'));
      $user->phone = $request->input('phone');
      $user->alternate_number = $request->input('altPhone');
      $user->email = $request->input('email');
      $user->dob = $request->input('dob');
      $user->address = $request->input('address');

      if ($request->has('useDefaultPassword') && $request->input('useDefaultPassword') == 'on') {
        $user->password = bcrypt(Settings::first()->default_password ?? 123456);
      } else {
        $user->password = bcrypt($request->input('password'));
      }

      $user->code = $request->input('code');
      $user->date_of_joining = $request->input('doj');
      $user->team_id = $request->input('teamId');
      $user->shift_id = $request->input('shiftId');
      $user->reporting_to_id = $request->input('reportingToId');
      $user->designation_id = $request->input('designationId');
      $user->base_salary = $request->input('baseSalary');
      $user->leave_policy_profile_id = $request->input('leavePolicyProfileId');
      $user->work_type = $request->input('work_type', 'office');

      //Attendance Type Settings
      switch ($request->input('attendanceType')) {
        case 'geofence':
          $user->attendance_type = 'geofence';
          $user->geofence_group_id = $request->input('geofenceGroupId');
          break;
        case 'ipAddress':
          $user->attendance_type = 'ip_address';
          $user->ip_address_group_id = $request->input('ipGroupId');
          break;
        case 'staticqr':
          $user->attendance_type = 'qr_code';
          $user->qr_group_id = $request->input('qrGroupId');
          break;
        case 'site':
          $user->attendance_type = 'site';
          $user->site_id = $request->input('siteId');
          break;
        case 'dynamicqr':
          $user->attendance_type = 'dynamic_qr';
          $user->dynamic_qr_device_id = $request->input('dynamicQrId');
          DynamicQrDevice::where('id', $request->input('dynamicQrId'))
            ->update(['user_id' => $user->id, 'status' => 'in_use']);
          break;
        case 'face':
          $user->attendance_type = 'face_recognition';
          break;
        default:
          $user->attendance_type = 'open';
          break;
      }

      $user->status = UserAccountStatus::ACTIVE;

      if ($request->hasFile('file')) {

        $file = $request->file('file');
        $fileName = $user->code . '_' . time() . '.' . $file->getClientOriginalExtension();

        //Create Directory if not exists
        if (!Storage::disk('public')->exists(Constants::BaseFolderEmployeeProfile)) {
          Storage::disk('public')->makeDirectory(Constants::BaseFolderEmployeeProfile);
        }

        Storage::disk('public')->putFileAs(Constants::BaseFolderEmployeeProfileWithSlash, $file, $fileName);

        $user->profile_picture = $fileName;
      }

      $user->created_by_id = auth()->id();
      $user->save();

      $user->assignRole($request->input('role'));

      if ($user->leave_policy_profile_id) {
        \App\Services\LeaveAccrualService::initializeForUser($user);
      }


      return redirect()->route('employees.index')->with('success', 'Employee created successfully');
    } catch (\Exception $e) {
      Log::error('EmployeeController@store: ' . $e->getMessage());
      return redirect()->back()->with('error', 'Failed to create employee');
    }
  }

  public function checkEmailValidationAjax(Request $request)
  {
    $email = $request->input('email');

    if (!$email) {
      return response()->json([
        "valid" => false,
      ]);
    }

    //Edit case handling
    if ($request->has('id')) {
      $id = $request->input('id');
      if (User::where('email', $email)->where('id', '!=', $id)->exists()) {
        return response()->json([
          "valid" => false,
        ]);
      } else {
        return response()->json([
          "valid" => true,
        ]);
      }
    }

    if (User::where('email', $email)->exists()) {
      return response()->json([
        "valid" => false,
      ]);
    }

    return response()->json([
      "valid" => true,
    ]);
  }

  public function checkPhoneValidationAjax(Request $request)
  {

    $phone = $request->input('phone');

    if (!$phone) {
      return response()->json([
        "valid" => false,
      ]);
    }

    //Edit Case Handling
    if ($request->has('id')) {
      $id = $request->input('id');
      if (User::where('phone', $phone)->where('id', '!=', $id)->withTrashed()->exists()) {
        return response()->json([
          "valid" => false,
        ]);
      } else {
        return response()->json([
          "valid" => true,
        ]);
      }
    }

    if (User::where('phone', $phone)->withTrashed()->exists()) {
      return response()->json([
        "valid" => false,
      ]);
    }

    return response()->json([
      "valid" => true,
    ]);
  }

  public function checkEmployeeCodeValidationAjax(Request $request)
  {
    $code = $request->input('code');

    if (!$code) {
      return response()->json([
        "valid" => false,
      ]);
    }

    //Edit Case Handling
    if ($request->has('id')) {
      $id = $request->input('id');
      if (User::where('code', $code)->where('id', '!=', $id)->withTrashed()->exists()) {
        return response()->json([
          "valid" => false,
        ]);
      } else {
        return response()->json([
          "valid" => true,
        ]);
      }
    }

    if (User::where('code', $code)->withTrashed()->exists()) {
      return response()->json([
        "valid" => false,
      ]);
    }

    return response()->json([
      "valid" => true,
    ]);
  }

  public function getGeofenceGroups()
  {
    $geofenceGroups = GeofenceGroup::where('status', '=', 'active')
      ->select('id', 'name')
      ->get();

    return response()->json($geofenceGroups);
  }

  public function getIpGroups()
  {
    $ipGroups = IpAddressGroup::where('status', '=', 'active')
      ->select('id', 'name')
      ->get();

    return response()->json($ipGroups);
  }

  public function getQrGroups()
  {
    $qrGroups = QrGroup::where('status', '=', 'active')
      ->select('id', 'name')
      ->get();

    return response()->json($qrGroups);
  }

  public function getDynamicQrDevices()
  {
    $devices = DynamicQrDevice::where('user_id', null)
      ->where('site_id', null)
      ->get();

    return response()->json($devices);
  }

  public function getSites()
  {
    $sites = Site::where('status', '=', 'active')
      ->select('id', 'name')
      ->get();

    return response()->json($sites);
  }

  public function toggleStatus($id)
  {
    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in the demo.');
    }

    if (!auth()->user()->hasRole(['admin', 'hr', 'super_admin'])) {
      return Error::response('Unauthorized action.', 403);
    }

    $user = User::find($id);

    if ($user->status == UserAccountStatus::ACTIVE) {
      $user->status = UserAccountStatus::INACTIVE;
    } else {
      $user->status = UserAccountStatus::ACTIVE;
    }

    $user->save();

    return Success::response('Status updated successfully');
  }

  public function relieveEmployee($id)
  {
    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in the demo.');
    }

    if (!auth()->user()->hasRole(['admin', 'hr', 'super_admin'])) {
      return Error::response('Unauthorized action.', 403);
    }

    $user = User::find($id);

    if ($user) {
      $user->status = UserAccountStatus::RELIEVED;
      $user->relieved_at = now();
      $user->save();
    }

    return Success::response('Employee relieved successfully');
  }

  public function retireEmployee($id)
  {
    if (env('APP_DEMO')) {
      return Error::response('This feature is disabled in the demo.');
    }

    if (!auth()->user()->hasRole(['admin', 'hr', 'super_admin'])) {
      return Error::response('Unauthorized action.', 403);
    }

    $user = User::find($id);

    if ($user) {
      $user->status = UserAccountStatus::RETIRED;
      $user->retired_at = now();
      $user->save();
    }

    return Success::response('Employee retired successfully');
  }

  public function myProfile()
  {
    $user = User::with([
      'userDevice',
      'team',
      'userAvailableLeaves',
      'shift',
      'designation.department',
      'assets.category',
      'salesTargets',
      'bankAccount',
      'tasks',
      'payrollAdjustments',
      'documentRequests',
      'payslips',
      'roles'
    ])->findOrFail(auth()->id());

    $auditLogs = Audit::where('user_id', $user->id)
      ->where('auditable_type', 'App\Models\User')
      ->orderBy('created_at', 'desc')
      ->get();

    $role = $user->roles()->first();

    // Check for pending approvals
    $pendingApprovals = ProfileUpdateApproval::where('user_id', $user->id)
      ->where('status', 'pending')
      ->get();

    $documentTypes = \App\Models\DocumentType::where('status', 'Active')->get();

    return view('account.my-profile', compact('user', 'auditLogs', 'role', 'pendingApprovals', 'documentTypes'));
  }

  /**
   * Display upcoming birthdays and anniversaries for the next 30 days.
   */
  public function celebrations()
  {
    $todayMd = now()->format('md');
    $thirtyDaysLaterMd = now()->addDays(30)->format('md');

    // Show all employees as requested
    $users = User::all()
      ->unique('id');

    $birthdays = $users->filter(function($u) use ($todayMd, $thirtyDaysLaterMd) {
        if (!$u->dob) return false;
        $md = Carbon::parse($u->dob)->format('md');
        if ($todayMd <= $thirtyDaysLaterMd) {
            return $md >= $todayMd && $md <= $thirtyDaysLaterMd;
        }
        // Handles year crossover
        return $md >= $todayMd || $md <= $thirtyDaysLaterMd;
    })->sortBy(function($u) {
        $md = Carbon::parse($u->dob)->format('md');
        // If md is less than today, it's for next year's window (if crossover)
        return $md < now()->format('md') ? '1' . $md : '0' . $md;
    });

    $anniversaries = $users->filter(function($u) use ($todayMd, $thirtyDaysLaterMd) {
        if (!$u->date_of_joining) return false;
        $md = Carbon::parse($u->date_of_joining)->format('md');
        if ($todayMd <= $thirtyDaysLaterMd) {
            return $md >= $todayMd && $md <= $thirtyDaysLaterMd;
        }
        return $md >= $todayMd || $md <= $thirtyDaysLaterMd;
    })->sortBy(function($u) {
        $md = Carbon::parse($u->date_of_joining)->format('md');
        return $md < now()->format('md') ? '1' . $md : '0' . $md;
    });

    return view('tenant.employees.celebrations', compact('birthdays', 'anniversaries'));
  }

  /**
   * Update the authenticated user's profile.
   */
  public function updateMyProfile(Request $request)
  {
    $user = User::findOrFail(auth()->id());
    $isAdmin = $user->hasRole('admin') || $user->hasRole('Admin');
    $isHR = $user->hasRole('hr');

    // 1. Validate Basic and Contact Info (Immediate)
    $basicAndContactData = $request->validate([
      'first_name' => 'nullable|string|max:255',
      'last_name' => 'nullable|string|max:255',
      'phone' => 'nullable|string|max:15',
      'personal_email' => 'nullable|email|max:255',
      'official_phone' => 'nullable|string|max:15',
      'alternate_number' => 'nullable|string|max:15',
      'dob' => 'nullable|date',
      'gender' => 'nullable|string',
      'marital_status' => 'nullable|string',
      'father_name' => 'nullable|string|max:255',
      'emergency_contact_phone' => 'nullable|string|max:15',
      'emergency_contact_name' => 'nullable|string|max:255',
      'emergency_contact_relation' => 'nullable|string|max:255',
      'temp_street' => 'nullable|string',
      'temp_city' => 'nullable|string',
      'temp_state' => 'nullable|string',
      'temp_country' => 'nullable|string',
      'perm_street' => 'nullable|string',
      'perm_city' => 'nullable|string',
      'perm_state' => 'nullable|string',
      'perm_country' => 'nullable|string',
    ]);

    // 2. Validate Bank Info (Potential Approval)
    $bankData = $request->validate([
      'bank_name' => 'nullable|string|max:255',
      'account_number' => 'nullable|string|max:255',
      'bank_code' => 'nullable|string|max:50', // IFSC
      'branch_name' => 'nullable|string|max:255',
      'account_name' => 'nullable|string|max:255',
    ]);

    // 3. Validate Salary (Admin Only)
    if ($request->has('base_salary')) {
      if ($isAdmin) {
        $user->base_salary = $request->base_salary;
      } else {
        return redirect()->back()->with('error', 'Only Admins can update salary details.');
      }
    }

    DB::beginTransaction();
    try {
      // --- Update Basic & Contact Info (Always Immediate) ---
      $user->update(array_filter($basicAndContactData));

      // --- Handle Bank Details ---
      $hasBankChanges = !empty(array_filter($bankData));
      if ($hasBankChanges) {
        if ($isAdmin || $isHR) {
          // Immediate update for Admin/HR
          \App\Models\BankAccount::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($bankData, ['tenant_id' => $user->tenant_id])
          );
        } else {
          // Check for existing pending bank approval
          $existingPending = ProfileUpdateApproval::where('user_id', $user->id)
            ->where('type', 'bank_details')
            ->where('status', 'pending')
            ->exists();

          if ($existingPending) {
            return redirect()->back()->with('error', 'You already have a pending bank detail update request.');
          }

          // Create Approval Request for Employee
          ProfileUpdateApproval::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'type' => 'bank_details',
            'requested_data' => $bankData,
            'status' => 'pending',
          ]);
        }
      }

      DB::commit();
      $msg = 'Profile updated successfully.';
      if ($hasBankChanges && !$isAdmin && !$isHR) {
        $msg .= ' Note: Bank details have been sent for HR approval.';
      }
      return redirect()->back()->with('success', $msg);

    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Profile Update Error: ' . $e->getMessage());
      return redirect()->back()->with('error', 'An error occurred while updating your profile.');
    }
  }

  /**
   * Initiate onboarding for a new hire.
   */
  public function initiateOnboarding(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'firstName' => 'required|string|max:255',
      'lastName' => 'required|string|max:255',
      'email' => 'required|email|unique:users,email',
      'phone' => 'required|string|max:10|unique:users,phone',
      'employeeCode' => 'nullable|string|max:50|unique:users,code',
      'role' => 'required|exists:roles,name',
      'departmentId' => 'required|exists:departments,id',
      'designationId' => 'required|exists:designations,id',
      'reportingToId' => 'required|exists:users,id',
      'siteId' => 'nullable|exists:sites,id',
      'doj' => 'required|date',
      'baseSalary' => 'nullable|numeric',
    ]);

    if ($validator->fails()) {
      if ($request->ajax() || $request->wantsJson()) {
        return response()->json([
          'status' => 'error',
          'message' => 'Validation failed: ' . $validator->errors()->first(),
          'errors' => $validator->errors()
        ], 422);
      }
      return redirect()->back()
        ->withErrors($validator)
        ->withInput()
        ->with('error', 'Validation failed: ' . $validator->errors()->first());
    }

    DB::beginTransaction();
    try {
      $authenticatedUser = Auth::user();

      // Create temporary user with Onboarding status
      $plainPassword = \Illuminate\Support\Str::random(10);
      $employeeCode = $request->employeeCode ? strtoupper(trim($request->employeeCode)) : $this->generateEmployeeCode($authenticatedUser->tenant_id);
      $user = User::create([
        'tenant_id' => $authenticatedUser->tenant_id,
        'first_name' => $request->firstName,
        'last_name' => $request->lastName,
        'name' => $request->firstName . ' ' . $request->lastName,
        'email' => $request->email,
        'phone' => $request->phone,
        'code' => $employeeCode,
        'department_id' => $request->departmentId,
        'designation_id' => $request->designationId,
        'reporting_to_id' => $request->reportingToId,
        'site_id' => $request->siteId,
        'date_of_joining' => $request->doj,
        'base_salary' => $request->baseSalary,
        'status' => UserAccountStatus::ONBOARDING,
        'onboarding_at' => now(),
        'onboarding_deadline' => now()->addDays(3),
        'probation_period_months' => $request->probationPeriodMonths ?? 6,
        'created_by_id' => $authenticatedUser->id,
        'password' => bcrypt($plainPassword),
      ]);

      // Assign Role
      $role = \Spatie\Permission\Models\Role::where('name', $request->role)->first();
      $user->roles()->sync([$role->id]);

      // Send Invitation Notification (with password)
      $user->notify(new OnboardingInvite($user, $plainPassword));

      DB::commit();

      if ($request->ajax() || $request->wantsJson()) {
        return response()->json([
          'status' => 'success',
          'message' => 'Onboarding invitation sent successfully! <br><b>Login:</b> ' . $user->email . '<br><b>Password:</b> ' . $plainPassword,
          'redirect' => route('tenant.dashboard')
        ]);
      }
      return redirect()->route('tenant.dashboard')->with('success', 'Onboarding invitation sent! <br><b>Login:</b> ' . $user->email . '<br><b>Password:</b> ' . $plainPassword);

    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Onboarding Initiation Error: ' . $e->getMessage());
      return redirect()->back()->with('error', 'Failed to initiate onboarding: ' . $e->getMessage());
    }
  }

  /**
   * Stage 1: Validate CSV and return JSON for preview
   */
  public function validateBulkImport(Request $request)
  {
    $request->validate(['file' => 'required|mimes:csv,txt|max:5120']);
    $file = $request->file('file');
    $handle = fopen($file->getRealPath(), 'r');
    $header = fgetcsv($handle);

    $headerMap = [];
    if (is_array($header)) {
      foreach ($header as $idx => $col) {
        $key = strtolower(trim((string) $col));
        if ($key !== '') {
          $headerMap[$key] = $idx;
        }
      }
    }

    $getValue = function (array $data, string $key, int $fallbackIndex = -1) use ($headerMap) {
      if (isset($headerMap[$key])) {
        return trim((string) ($data[$headerMap[$key]] ?? ''));
      }
      if ($fallbackIndex >= 0) {
        return trim((string) ($data[$fallbackIndex] ?? ''));
      }
      return '';
    };

    $rows = [];
    $rowNum = 1;

    while (($data = fgetcsv($handle)) !== FALSE) {
      $rowNum++;
      if (count($data) < 5)
        continue;

      $email = $getValue($data, 'email', 3);
      $phone = $getValue($data, 'phone', 4);
      $code = $getValue($data, 'employee id', 0);

      $errors = [];
      $isValid = true;

      // Email Check
      $existEmail = User::where('email', $email)->first();
      if ($existEmail && $existEmail->status !== UserAccountStatus::TERMINATED) {
        $errors[] = "Email exists";
        $isValid = false;
      }

      // Phone Check
      $existPhone = User::where('phone', $phone)->first();
      if ($existPhone && $existPhone->status !== UserAccountStatus::TERMINATED) {
        $errors[] = "Phone exists";
        $isValid = false;
      }

      // Code Check
      if (!empty($code) && User::where('code', $code)->exists()) {
        $errors[] = "ID exists";
        $isValid = false;
      }

      // Role Check & Normalization
      $roleName = $getValue($data, 'role', 5) ?: 'employee';
      if (strtolower($roleName) === 'employee' || strtolower($roleName) === 'office_employee') {
        $roleName = 'employee';
      }

      $availableRoles = \Spatie\Permission\Models\Role::pluck('name')->toArray();
      if (!in_array($roleName, $availableRoles)) {
        $errors[] = "Invalid Role: $roleName";
        $isValid = false;
      }

      $genderValue = $getValue($data, 'gender');
      $genderEnum = $genderValue ? \App\Enums\Gender::tryFrom(strtolower($genderValue)) : null;
      if ($genderValue && !$genderEnum) {
        $errors[] = "Invalid Gender: $genderValue";
        $isValid = false;
      }

      $rows[] = [
        'row' => $rowNum,
        'code' => $code,
        'first_name' => $getValue($data, 'first name', 1),
        'last_name' => $getValue($data, 'last name', 2),
        'email' => $email,
        'phone' => $phone,
        'role' => $roleName,
        'team_id' => $getValue($data, 'team id', 6),
        'designation_id' => $getValue($data, 'designation id', 7),
        'reporting_to_id' => $getValue($data, 'reporting to id', 8),
        'doj' => $getValue($data, 'date of joining (yyyy-mm-dd)', 9) ?: now()->toDateString(),
        'salary' => $getValue($data, 'annual ctc', 10),
        'shift_id' => $getValue($data, 'shift id', 11),
        'gender' => $genderValue,
        'dob' => $getValue($data, 'date of birth'),
        'blood_group' => $getValue($data, 'blood group'),
        'marital_status' => $getValue($data, 'marital status'),
        'father_name' => $getValue($data, "father's name"),
        'mother_name' => $getValue($data, "mother's name"),
        'spouse_name' => $getValue($data, 'spouse name'),
        'no_of_children' => $getValue($data, 'no of children'),
        'birth_country' => $getValue($data, 'birth country'),
        'citizenship' => $getValue($data, 'citizenship'),
        'temp_building' => $getValue($data, 'temp building'),
        'temp_street' => $getValue($data, 'temp street'),
        'temp_city' => $getValue($data, 'temp city'),
        'temp_state' => $getValue($data, 'temp state'),
        'temp_zip' => $getValue($data, 'temp zip'),
        'temp_country' => $getValue($data, 'temp country'),
        'perm_building' => $getValue($data, 'perm building'),
        'perm_street' => $getValue($data, 'perm street'),
        'perm_city' => $getValue($data, 'perm city'),
        'perm_state' => $getValue($data, 'perm state'),
        'perm_zip' => $getValue($data, 'perm zip'),
        'perm_country' => $getValue($data, 'perm country'),
        'emergency_contact_name' => $getValue($data, 'emergency contact name'),
        'emergency_contact_relation' => $getValue($data, 'emergency contact relation'),
        'emergency_contact_phone' => $getValue($data, 'emergency contact phone'),
        'biometric_id' => $getValue($data, 'biometric id'),
        'aadhaar_no' => $getValue($data, 'aadhaar no'),
        'pan_no' => $getValue($data, 'pan no'),
        'pf_no' => $getValue($data, 'pf no'),
        'esi_no' => $getValue($data, 'esi no'),
        'uan_no' => $getValue($data, 'uan no'),
        'bank_name' => $getValue($data, 'bank name'),
        'bank_code' => $getValue($data, 'bank code'),
        'account_name' => $getValue($data, 'account name'),
        'account_number' => $getValue($data, 'account number'),
        'branch_name' => $getValue($data, 'branch name'),
        'branch_code' => $getValue($data, 'branch code'),
        'tax_no' => $getValue($data, 'tax no'),
        'ctc_offered' => $getValue($data, 'ctc offered'),
        'available_leave_count' => $getValue($data, 'available leave count'),
        'is_valid' => $isValid,
        'errors' => $errors
      ];
    }
    fclose($handle);

    return response()->json([
      'success' => true,
      'data' => $rows,
      'total' => count($rows),
      'valid_count' => collect($rows)->where('is_valid', true)->count()
    ]);
  }

  /**
   * Stage 2: Final processing of confirmed preview data
   */
  public function processBulkImport(Request $request)
  {
    $data = $request->input('candidates', []);
    $count = 0;

    DB::beginTransaction();
    try {
      foreach ($data as $item) {
        if (!($item['is_valid'] ?? false))
          continue;

        $email = $item['email'];
        $phone = $item['phone'];

        // Handle Terminated User cleanup
        $existEmail = User::where('email', $email)->first();
        if ($existEmail && $existEmail->status === UserAccountStatus::TERMINATED) {
          $existEmail->update(['email' => $existEmail->email . '_term_' . time()]);
        }
        $existPhone = User::where('phone', $phone)->first();
        if ($existPhone && $existPhone->status === UserAccountStatus::TERMINATED) {
          $existPhone->update(['phone' => $existPhone->phone . '_term_' . time()]);
        }

        $plainPassword = \Illuminate\Support\Str::random(10);
        $employeeCode = !empty($item['code']) ? strtoupper(trim($item['code'])) : $this->generateEmployeeCode(auth()->user()->tenant_id);
        $user = User::create([
          'tenant_id' => auth()->user()->tenant_id,
          'first_name' => $item['first_name'],
          'last_name' => $item['last_name'],
          'name' => $item['first_name'] . ' ' . $item['last_name'],
          'email' => $email,
          'phone' => $phone,
          'code' => $employeeCode,
          'team_id' => $item['team_id'],
          'designation_id' => $item['designation_id'],
          'reporting_to_id' => $item['reporting_to_id'],
          'date_of_joining' => $item['doj'],
          'base_salary' => $item['salary'],
          'ctc_offered' => $item['ctc_offered'] ?? null,
          'available_leave_count' => $item['available_leave_count'] ?? null,
          'shift_id' => $item['shift_id'] ?? null,
          'gender' => !empty($item['gender']) ? \App\Enums\Gender::tryFrom(strtolower($item['gender'])) : null,
          'dob' => $item['dob'] ?? null,
          'blood_group' => $item['blood_group'] ?? null,
          'marital_status' => $item['marital_status'] ?? null,
          'father_name' => $item['father_name'] ?? null,
          'mother_name' => $item['mother_name'] ?? null,
          'spouse_name' => $item['spouse_name'] ?? null,
          'no_of_children' => $item['no_of_children'] ?? null,
          'birth_country' => $item['birth_country'] ?? null,
          'citizenship' => $item['citizenship'] ?? null,
          'temp_building' => $item['temp_building'] ?? null,
          'temp_street' => $item['temp_street'] ?? null,
          'temp_city' => $item['temp_city'] ?? null,
          'temp_state' => $item['temp_state'] ?? null,
          'temp_zip' => $item['temp_zip'] ?? null,
          'temp_country' => $item['temp_country'] ?? null,
          'perm_building' => $item['perm_building'] ?? null,
          'perm_street' => $item['perm_street'] ?? null,
          'perm_city' => $item['perm_city'] ?? null,
          'perm_state' => $item['perm_state'] ?? null,
          'perm_zip' => $item['perm_zip'] ?? null,
          'perm_country' => $item['perm_country'] ?? null,
          'emergency_contact_name' => $item['emergency_contact_name'] ?? null,
          'emergency_contact_relation' => $item['emergency_contact_relation'] ?? null,
          'emergency_contact_phone' => $item['emergency_contact_phone'] ?? null,
          'biometric_id' => $item['biometric_id'] ?? null,
          'aadhaar_no' => $item['aadhaar_no'] ?? null,
          'pan_no' => $item['pan_no'] ?? null,
          'pf_no' => $item['pf_no'] ?? null,
          'esi_no' => $item['esi_no'] ?? null,
          'uan_no' => $item['uan_no'] ?? null,
          'status' => UserAccountStatus::ONBOARDING,
          'onboarding_at' => now(),
          'onboarding_deadline' => now()->addDays(3),
          'created_by_id' => auth()->id(),
          'password' => bcrypt($plainPassword),
        ]);

        $roleToAssign = $item['role'] ?? 'employee';
        if (!\Spatie\Permission\Models\Role::where('name', $roleToAssign)->exists()) {
          $roleToAssign = 'employee'; // Final fallback
        }
        $user->assignRole($roleToAssign);
        $user->notify(new OnboardingInvite($user, $plainPassword));
        $count++;

        if (!empty($item['bank_name']) || !empty($item['account_number'])) {
          \App\Models\BankAccount::updateOrCreate(
            ['user_id' => $user->id],
            [
              'bank_name' => $item['bank_name'] ?? null,
              'bank_code' => $item['bank_code'] ?? null,
              'account_name' => $item['account_name'] ?? null,
              'account_number' => $item['account_number'] ?? null,
              'branch_name' => $item['branch_name'] ?? null,
              'branch_code' => $item['branch_code'] ?? null,
              'tax_no' => $item['tax_no'] ?? null,
              'tenant_id' => $user->tenant_id,
              'created_by_id' => auth()->id(),
              'updated_by_id' => auth()->id(),
            ]
          );
        }
      }
      DB::commit();
      return response()->json(['success' => true, 'message' => "Successfully invited $count candidates."]);
    } catch (\Exception $e) {
      DB::rollBack();
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

  public function downloadImportTemplate()
  {
    $rows = [
      [
        'Employee ID',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'Role',
        'Team ID',
        'Designation ID',
        'Account Number',
        'Branch Name',
        'Branch Code',
        'Tax No',
        'Ctc Offered',
        'Available Leave Count',
        'Shift Id'
      ],
      [
        'EMP001',
        'John',
        'Doe',
        'john@example.com',
        '9876543210',
        'employee',
        '1',
        '1',
        '1',
        '2026-03-18',
        '500000',
        'male',
        '2000-01-01',
        'O+',
        'single',
        'Robert Doe',
        'Jane Doe',
        '',
        '0',
        'India',
        'India',
        '12A',
        'MG Road',
        'Pune',
        'Maharashtra',
        '411001',
        'India',
        '34B',
        'Park Street',
        'Pune',
        'Maharashtra',
        '411001',
        'India',
        'Alice Doe',
        'Spouse',
        '9876543210',
        'BIO-001',
        '123412341234',
        'ABCDE1234F',
        'ESI12345',
        'UAN12345',
        'HDFC Bank',
        'HDFC0001234',
        'John Doe',
        '1234567890',
        'Main Branch',
        'BR-001',
        'TAX123',
        '500000',
        '12',
        '1'
      ],
    ];

    $csvContent = '';
    foreach ($rows as $row) {
      $csvContent .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
    }

    return response($csvContent, 200, [
      'Content-Type' => 'text/csv',
      'Content-Disposition' => 'attachment; filename="onboarding_template.csv"',
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
      'Pragma' => 'no-cache',
      'Expires' => '0',
    ]);
  }

  public function exportEmployees()
  {
    $employees = User::where('tenant_id', auth()->user()->tenant_id)
      ->with(['team', 'designation'])
      ->get();

    $csvLines = [];
    $csvLines[] = 'Employee ID,Name,Email,Phone,Department,Designation,Status,Joined';

    foreach ($employees as $emp) {
      $status = is_object($emp->status) ? $emp->status->value : ($emp->status ?? '');
      $csvLines[] = implode(',', array_map(
        fn($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"',
        [
          $emp->code,
          $emp->full_name ?? $emp->name,
          $emp->email,
          $emp->phone,
          $emp->team?->name,
          $emp->designation?->name,
          $status,
          $emp->date_of_joining,
        ]
      ));
    }

    $csvContent = implode("\n", $csvLines);

    return response($csvContent, 200, [
      'Content-Type' => 'text/csv',
      'Content-Disposition' => 'attachment; filename="employee_directory.csv"',
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
      'Pragma' => 'no-cache',
      'Expires' => '0',
    ]);
  }

  private function generateEmployeeCode(?int $tenantId = null): string
  {
    if (!$tenantId) {
      $tenantId = auth()->user()->tenant_id ?? null;
    }

    $settings = $tenantId ? Settings::where('tenant_id', $tenantId)->first() : null;
    if (!$settings) {
      $settings = Settings::first();
    }
    if ($settings && !$tenantId) {
      $tenantId = $settings->tenant_id;
    }

    $prefix = strtoupper(trim($settings->employee_code_prefix ?? 'EMP'));
    if ($prefix === '') {
      $prefix = 'EMP';
    }
    if (!str_ends_with($prefix, '-')) {
      $prefix .= '-';
    }

    $lastCodeQuery = User::where('code', 'like', $prefix . '%');
    if ($tenantId) {
      $lastCodeQuery->where('tenant_id', $tenantId);
    }
    $lastCode = $lastCodeQuery
      ->orderBy('id', 'desc')
      ->value('code');

    $nextNumber = 1;
    if ($lastCode && preg_match('/^' . preg_quote($prefix, '/') . '(\\d+)$/', $lastCode, $matches)) {
      $nextNumber = (int) $matches[1] + 1;
    }

    $code = $prefix . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    while (User::where('code', $code)->exists()) {
      $nextNumber++;
      $code = $prefix . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }

    return $code;
  }

  /**
   * Dedicated Onboarding Review Center (Exact Stitch Replication)
   */
  public function reviewCenter(Request $request)
  {
    $onboardingUsers = User::whereIn('status', [UserAccountStatus::ONBOARDING, UserAccountStatus::ONBOARDING_SUBMITTED])
      ->with(['team', 'roles', 'bankAccount'])
      ->orderBy('created_at', 'desc')
      ->get();

    $pendingCount = $onboardingUsers->where('status', UserAccountStatus::ONBOARDING_SUBMITTED)->count();

    $selectedUser = null;
    if ($request->has('user_id')) {
      $selectedUser = User::with(['team', 'roles', 'bankAccount'])->find($request->user_id);
    } elseif ($onboardingUsers->where('status', UserAccountStatus::ONBOARDING_SUBMITTED)->isNotEmpty()) {
      $selectedUser = $onboardingUsers->where('status', UserAccountStatus::ONBOARDING_SUBMITTED)->first();
    }

    $recentlyApproved = User::where('status', UserAccountStatus::ACTIVE)
      ->whereNotNull('onboarding_completed_at')
      ->with('team')
      ->orderBy('onboarding_completed_at', 'desc')
      ->limit(5)
      ->get();

    $roles = Role::select('id', 'name')->get();
    $teams = Team::where('status', Status::ACTIVE)->select('id', 'name')->get();
    $designations = Designation::where('status', Status::ACTIVE)->select('id', 'name')->get();
    $managers = User::where('status', UserAccountStatus::ACTIVE)
      ->select('id', 'first_name', 'last_name')
      ->orderBy('first_name')
      ->get();

    return view('tenant.onboarding.review_center', [
      'onboardingUsers' => $onboardingUsers,
      'pendingCount' => $pendingCount,
      'selectedUser' => $selectedUser,
      'recentlyApproved' => $recentlyApproved,
      'roles' => $roles,
      'teams' => $teams,
      'designations' => $designations,
      'managers' => $managers
    ]);
  }

  /**
   * Review onboarding submission (Traditional View)
   */
  public function reviewOnboarding($id)
  {
    $user = User::findOrFail($id);
    return view('tenant.onboarding.review', [
      'user' => $user,
      'bank' => $user->bankAccount
    ]);
  }

  /**
   * Approve an onboarding submission.
   */
  public function approveOnboarding($id)
  {
    $user = User::findOrFail($id);

    if ($user->status !== UserAccountStatus::ONBOARDING_SUBMITTED) {
      if (request()->ajax()) {
        return response()->json(['success' => false, 'message' => 'User is not in a submittable state.']);
      }
      return redirect()->back()->with('error', 'User is not in a submittable state.');
    }

    DB::beginTransaction();
    try {
      // 1. Update Status to Active
      $user->status = UserAccountStatus::ACTIVE;
      $user->email_verified_at = now(); // Mark as verified since HR approved
      $user->onboarding_resubmission_notes = null; // Clear notes upon approval
      $user->onboarding_completed_at = now();

      // Ensure joining date is never null
      if (empty($user->date_of_joining)) {
        $user->date_of_joining = now()->format('Y-m-d');
      }

      // Calculate Probation End Date
      if ($user->probation_period_months > 0) {
        $user->probation_end_date = \Carbon\Carbon::parse($user->date_of_joining)
          ->addMonths($user->probation_period_months)
          ->format('Y-m-d');
      }

      // Final check for employee code just in case
      if (empty($user->code)) {
        $user->code = $this->generateEmployeeCode($user->tenant_id);
      }

      $user->save();

      // 2. Send Approval Notification
      $user->notify(new OnboardingStatusChanged($user, 'approved'));

      DB::commit();

      if (request()->ajax()) {
        return response()->json(['success' => true, 'message' => 'Onboarding approved! ' . $user->name . ' is now active.']);
      }
      return redirect()->route('employees.index')->with('success', 'Onboarding approved! ' . $user->name . ' is now an active employee.');

    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Onboarding Approval Error: ' . $e->getMessage());
      if (request()->ajax()) {
        return response()->json(['success' => false, 'message' => 'Failed to approve onboarding.']);
      }
      return redirect()->back()->with('error', 'Failed to approve onboarding.');
    }
  }

  /**
   * Request resubmission for an onboarding submission.
   */
  public function requestResubmission(Request $request, $id)
  {
    $request->validate([
      'notes' => 'required|string|max:1000',
      'sections' => 'required|array|min:1'
    ]);

    $user = User::findOrFail($id);

    DB::beginTransaction();
    try {
      // 1. Revert Status to Onboarding and set rejected sections
      $user->status = UserAccountStatus::ONBOARDING;
      $user->onboarding_resubmission_notes = $request->notes;
      $user->onboarding_rejected_sections = $request->sections;
      $user->save();

      // 2. Send Resubmission Notification
      $user->notify(new OnboardingStatusChanged($user, 'resubmission', $request->notes));

      DB::commit();

      if ($request->ajax()) {
        return response()->json(['success' => true, 'message' => 'Resubmission request sent to ' . $user->email]);
      }
      return redirect()->route('employees.index')->with('success', 'Resubmission request sent to ' . $user->email);

    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Onboarding Resubmission Error: ' . $e->getMessage());
      if ($request->ajax()) {
        return response()->json(['success' => false, 'message' => 'Failed to request resubmission.']);
      }
      return redirect()->back()->with('error', 'Failed to request resubmission.');
    }
  }

  // Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
  // KPI / SALES TARGET METHODS
  // Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

  /**
   * Get a single KPI target by ID (AJAX).
   */
  public function getTargetByIdAjax($id)
  {
    $validated = validator(['id' => $id], ['id' => 'required|exists:sales_targets,id'])->validate();

    $salesTarget = SalesTarget::find($validated['id']);

    if ($salesTarget) {
      return response()->json(['success' => true, 'data' => $salesTarget]);
    }

    return response()->json(['success' => false, 'message' => 'KPI target not found.']);
  }

  /**
   * Add or update a KPI / Sales target.
   */
  public function addOrUpdateSalesTarget(Request $request)
  {
    $validated = $request->validate([
      'kpiId' => 'nullable|exists:sales_targets,id',
      'userId' => 'required|exists:users,id',
      'target_type' => 'required|string|max:255',
      'metric_name' => 'required|string|max:255',
      'kpi_type' => 'required|string|max:255',
      'grade_system' => 'required|string|max:255',
      'target_amount' => 'required|numeric',
      'incentive_type' => 'required|in:fixed,percentage,points,count',
      'description' => 'nullable|string|max:1000'
    ]);

    try {
      $formattedDescription = "Type: " . $validated['kpi_type'] . " | Grade: " . $validated['grade_system'] . " | Metric: " . $validated['metric_name'] . "\n\n" . ($validated['description'] ?? '');

      $salesTarget = SalesTarget::updateOrCreate(
        ['id' => $validated['kpiId'] ?? null],
        [
          'user_id' => $validated['userId'],
          'target_type' => $validated['target_type'],
          'target_amount' => $validated['target_amount'],
          'incentive_type' => $validated['incentive_type'],
          'incentive_amount' => $validated['incentive_type'] == 'fixed' ? $validated['target_amount'] : 0,
          'incentive_percentage' => $validated['incentive_type'] == 'percentage' ? $validated['target_amount'] : 0,
          'period' => now()->year,
          'description' => $formattedDescription
        ]
      );

      if ($request->ajax()) {
        return response()->json(['success' => true, 'message' => 'KPI targets deployed successfully!']);
      }
      return redirect()->back()->with('success', 'KPI targets deployed successfully!');
    } catch (\Exception $e) {
      Log::error('EmployeeController@addOrUpdateSalesTarget: ' . $e->getMessage());
      if ($request->ajax()) {
        return response()->json(['success' => false, 'message' => 'Failed to deploy performance target.']);
      }
      return redirect()->back()->with('error', 'Failed to deploy performance target.');
    }
  }

  /**
   * Delete a KPI target.
   */
  public function destroySalesTarget($id)
  {
    try {
      $salesTarget = SalesTarget::findOrFail($id);
      $salesTarget->delete();
      return response()->json(['success' => true, 'message' => 'KPI target deleted successfully.']);
    } catch (\Exception $e) {
      Log::error('EmployeeController@destroySalesTarget: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to delete KPI target.']);
    }
  }

  /**
   * Update KPI Status (Completed, Achieved, etc).
   */
  public function updateKpiStatus(Request $request, $id)
  {
    $validated = $request->validate([
      'status' => 'required|string|max:255'
    ]);

    try {
      $salesTarget = SalesTarget::findOrFail($id);
      $salesTarget->status = $validated['status'];
      $salesTarget->save();

      return response()->json(['success' => true, 'message' => 'KPI status updated successfully!']);
    } catch (\Exception $e) {
      Log::error('EmployeeController@updateKpiStatus: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to update status.']);
    }
  }

  /**
   * Submit KPI Self Assessment.
   */
  public function submitKpiSelfAssessment(Request $request, $id)
  {
    $validated = $request->validate([
      'notes' => 'required|string|max:1000'
    ]);

    try {
      $salesTarget = SalesTarget::findOrFail($id);
      $salesTarget->notes = $validated['notes'];
      $salesTarget->status = 'under_review'; // Automatically move to review
      $salesTarget->save();

      return response()->json(['success' => true, 'message' => 'Self-assessment submitted successfully!']);
    } catch (\Exception $e) {
      Log::error('EmployeeController@submitKpiSelfAssessment: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to submit assessment.']);
    }
  }
}
