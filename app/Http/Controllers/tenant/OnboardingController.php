<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Models\Team;
use App\Models\Designation;
use App\Models\BankAccount;
use App\Notifications\Onboarding\OnboardingStatusChanged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FileSecurityHelper;
use Constants;

class OnboardingController extends Controller
{
    /**
     * Show the onboarding form to the user.
     */
    public function index()
    {
        $user = Auth::user();
        
        $userStatus = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;

        // If already submitted, we still show the form but it will be read-only in the view
        // (Handled by $isSubmitted logic in Blade)
        if ($userStatus === UserAccountStatus::ONBOARDING_SUBMITTED->value) {
            return redirect()->route('user.dashboard.index');
        }

        if ($userStatus !== UserAccountStatus::ONBOARDING->value && 
            $userStatus !== UserAccountStatus::ONBOARDING_REQUESTED->value) {
            return redirect()->route('tenant.dashboard');
        }

        return view('tenant.onboarding.form', [
            'user' => $user,
            'teams' => Team::select('id', 'name')->get(),
            'designations' => Designation::select('id', 'name')->get(),
            'pageConfigs' => ['myLayout' => 'blank'],
        ]);
    }

    /**
     * Store the onboarding submission.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        $userStatus = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;
        if ($userStatus === UserAccountStatus::ONBOARDING_SUBMITTED->value) {
            return redirect()->back()->with('error', 'Your application is already under review and cannot be modified.');
        }

        $isResubmission = !empty($user->onboarding_resubmission_notes);
        $rejectedSections = (array) ($user->onboarding_rejected_sections ?? []);
        $existingFiles = $this->getOnboardingFileIndex($user);

        $rules = [];

        if (!$isResubmission || in_array('personal', $rejectedSections)) {
            $rules = array_merge($rules, [
                'first_name' => 'required|string|regex:/^[a-zA-Z\s]+$/|max:255',
                'last_name' => 'required|string|regex:/^[a-zA-Z\s]+$/|max:255',
                'dob' => 'required|date|before:18 years ago',
                'father_name' => 'required|string|regex:/^[a-zA-Z\s]+$/|max:255',
                'mother_name' => 'required|string|regex:/^[a-zA-Z\s]+$/|max:255',
                'marital_status' => 'required|string',
                'blood_group' => 'required|string',
                'highest_qualification' => 'required|string',
            ]);
        }

        if (!$isResubmission || in_array('contact', $rejectedSections)) {
             $rules = array_merge($rules, [
                'phone' => 'required|string|digits:10',
                'personal_email' => 'required|email|max:255',
                'official_phone' => 'required|string|digits:10',
                'perm_street' => 'required|string',
                'perm_city' => 'required|string',
                'perm_state' => 'required|string',
                'perm_zip' => 'required|string|digits:6',
             ]);
        }

        if (!$isResubmission || in_array('banking', $rejectedSections)) {
            $hasCheque = $this->hasOnboardingFile($existingFiles, 'cancelled_cheque');
            $rules = array_merge($rules, [
                'bank_name' => 'required|string|max:255',
                'account_number' => 'required|string|max:255',
                'confirm_account_number' => 'required|same:account_number',
                'ifsc_code' => ['required', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
                'cheque_file' => ($isResubmission || $hasCheque ? 'nullable' : 'required') . '|file|mimes:pdf,jpg,png|max:500',
            ]);
        }

        if (!$isResubmission || in_array('documents', $rejectedSections)) {
            $hasPhoto = $this->hasOnboardingFile($existingFiles, 'profile_photo');
            $hasAadhaar = $this->hasOnboardingFile($existingFiles, 'aadhaar_card');
            $hasPan = $this->hasOnboardingFile($existingFiles, 'pan_card');
            $hasMatric = $this->hasOnboardingFile($existingFiles, 'matric_certificate');
            $hasInter = $this->hasOnboardingFile($existingFiles, 'inter_certificate');
            $hasBachelor = $this->hasOnboardingFile($existingFiles, 'graduation_certificate');
            $rules = array_merge($rules, [
                'aadhaar_no' => 'required|string|digits:12',
                'pan_no' => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
                'photo' => ($isResubmission || $hasPhoto ? 'nullable' : 'required') . '|image|mimes:jpg,png|max:100',
                'aadhaar_file' => ($isResubmission || $hasAadhaar ? 'nullable' : 'required') . '|file|mimes:pdf,jpg,png|max:300',
                'pan_file' => ($isResubmission || $hasPan ? 'nullable' : 'required') . '|file|mimes:pdf,jpg,png|max:300',
                'matric_university' => 'nullable|string',
                'matric_marksheet_no' => 'nullable|string',
                'matric_file' => ($isResubmission || $hasMatric ? 'nullable' : 'required') . '|file|mimes:pdf,jpg,png|max:300',
                'inter_university' => 'nullable|string',
                'inter_marksheet_no' => 'nullable|string',
                'inter_file' => ($isResubmission || $hasInter ? 'nullable' : 'required') . '|file|mimes:pdf,jpg,png|max:300',
                'bachelor_university' => 'nullable|string',
                'bachelor_marksheet_no' => 'nullable|string',
                'graduation_file' => ($isResubmission || $hasBachelor ? 'nullable' : 'required') . '|file|mimes:pdf,jpg,png|max:300',
                'master_university' => 'nullable|string',
                'master_marksheet_no' => 'nullable|string',
                'master_file' => 'nullable|file|mimes:pdf,jpg,png|max:300',
                'experience_certificate_no' => 'nullable|string',
                'experience_file' => 'nullable|file|mimes:pdf,jpg,png|max:300',
            ]);
        }

        $request->validate($rules);

        DB::beginTransaction();
        try {
            $userData = $request->only([
                'first_name', 'last_name', 'dob', 'gender', 'blood_group',
                'father_name', 'mother_name', 'marital_status', 'spouse_name', 
                'no_of_children', 'citizenship', 'birth_country',
                'phone', 'alternate_number', 'home_phone', 'personal_email', 'official_phone',
                'perm_street', 'perm_building', 'perm_zip', 'perm_city', 'perm_state', 'perm_country',
                'temp_street', 'temp_building', 'temp_zip', 'temp_city', 'temp_state', 'temp_country',
                'aadhaar_no', 'pan_no', 'passport_no', 'passport_issue_date', 'passport_expiry_date',
                'visa_type', 'visa_issue_date', 'visa_expiry_date',
                'frro_registration', 'frro_issue_date', 'frro_expiry_date',
                'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
                'highest_qualification',
                'matric_marksheet_no', 'matric_university',
                'inter_marksheet_no', 'inter_university',
                'bachelor_marksheet_no', 'bachelor_university',
                'master_marksheet_no', 'master_university',
                'experience_certificate_no',
            ]);

            if ($request->has('same_as_permanent')) {
                $userData['temp_street'] = $userData['perm_street'] ?? null;
                $userData['temp_building'] = $userData['perm_building'] ?? null;
                $userData['temp_city'] = $userData['perm_city'] ?? null;
                $userData['temp_state'] = $userData['perm_state'] ?? null;
                $userData['temp_zip'] = $userData['perm_zip'] ?? null;
                $userData['temp_country'] = $userData['perm_country'] ?? null;
            }

            if (isset($userData['first_name']) && isset($userData['last_name'])) {
                $userData['name'] = $userData['first_name'] . ' ' . $userData['last_name'];
            }

            if ($request->has('consent_accepted')) {
                $userData['consent_accepted_at'] = now();
            }

            // Update User Profile
            $user->update($userData);

            // Update/Create Bank Account
            BankAccount::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'bank_name' => $request->bank_name,
                    'account_number' => $request->account_number,
                    'account_name' => $request->account_name ?? $user->name,
                    'bank_code' => $request->ifsc_code, // Assuming bank_code is IFSC
                    'branch_name' => $request->branch_name ?? 'N/A',
                    'branch_code' => $request->ifsc_code,
                ]
            );

            // Handle File Uploads (Photo, Cheque, Documents)
            $this->handleFileUploads($request, $user);

            // Set Status to Submitted
            $user->status = UserAccountStatus::ONBOARDING_SUBMITTED;
            $user->onboarding_completed_at = now();
            $user->consent_accepted_at = now();
            $user->onboarding_resubmission_notes = null;
            $user->onboarding_rejected_sections = null;
            $user->save();

            DB::commit();

            return redirect()->route('onboarding.status')->with('success', 'Your onboarding details have been submitted for review.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Onboarding Submission Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Something went wrong. Please try again or contact HR.');
        }
    }

    /**
     * Partial save for onboarding steps.
     */
    public function autoSave(Request $request)
    {
        $user = Auth::user();
        
        try {
            // Get only fields that are fillable on the User model
            $userData = $request->only($user->getFillable());
            
            // Basic normalization
            if (isset($userData['first_name']) && isset($userData['last_name'])) {
                $userData['name'] = $userData['first_name'] . ' ' . $userData['last_name'];
            }
            
            // Validate incoming data
            $rules = [
                'personal_email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:15',
                'official_phone' => 'nullable|string|max:15',
                'perm_zip' => 'nullable|string|max:10',
                'temp_zip' => 'nullable|string|max:10',
                'aadhaar_no' => 'nullable|string|max:20',
                'pan_no' => 'nullable|string|max:15',
                'dob' => 'nullable|date',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
            ];
            
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
            }

            if ($request->has('same_as_permanent')) {
                $userData['temp_street'] = $request->perm_street ?? null;
                $userData['temp_building'] = $request->perm_building ?? null;
                $userData['temp_zip'] = $request->perm_zip ?? null;
                $userData['temp_city'] = $request->perm_city ?? null;
                $userData['temp_state'] = $request->perm_state ?? null;
                $userData['temp_country'] = $request->perm_country ?? null;
            }

            // Update User Profile
            $user->update($userData);

            // If banking data is present, update bank account
            if ($request->filled('bank_name') && $request->filled('account_number')) {
                BankAccount::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'bank_name' => $request->bank_name,
                        'account_number' => $request->account_number,
                        'account_name' => $request->account_name ?? $user->name,
                        'bank_code' => $request->ifsc_code,
                        'branch_name' => $request->branch_name ?? 'N/A',
                        'branch_code' => $request->ifsc_code,
                    ]
                );
            }


            Log::info('Onboarding Auto-Save Success for User: ' . $user->id);
            return response()->json(['success' => true, 'message' => 'Progress saved.']);

        } catch (\Exception $e) {
            Log::error('Onboarding Auto-Save Error User ' . ($user->id ?? 'unknown') . ': ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save progress: ' . $e->getMessage()], 500);
        }
    }

    /**
     * AJAX File Upload.
     */
    public function uploadFile(Request $request)
    {
        $user = Auth::user();
        
        if (!$request->hasFile('file')) {
            return response()->json(['success' => false, 'message' => 'No file uploaded.'], 400);
        }

        $inputName = $request->input('field');
        $file = $request->file('file');
        
        // Map input field to file prefix
        $filePrefixes = [
            'photo' => 'profile_photo',
            'aadhaar_file' => 'aadhaar_card',
            'pan_file' => 'pan_card',
            'passport_file' => 'passport_document',
            'visa_file' => 'visa_document',
            'frro_file' => 'frro_document',
            'cheque_file' => 'cancelled_cheque',
            'matric_file' => 'matric_certificate',
            'inter_file' => 'inter_certificate',
            'graduation_file' => 'graduation_certificate',
            'master_file' => 'master_certificate',
            'experience_file' => 'experience_certificate',
        ];

        $prefix = $filePrefixes[$inputName] ?? 'document';
        $folder = Constants::BaseFolderOnboardingDocuments . $user->id;

        try {
            $path = \App\Helpers\FileSecurityHelper::encryptAndStore($file, $folder, $prefix, 'public');
            
            if (!$path) {
                throw new \Exception('Encryption or storage failed.');
            }

            if ($inputName === 'photo') {
                $user->profile_picture = $path;
                $user->save();
            } else {
                // Create/Update Document Request for visibility in standard system
                $docMapping = [
                    'aadhaar_card' => ['name' => 'Aadhaar Card', 'code' => 'AADHAAR_CARD'],
                    'pan_card' => ['name' => 'PAN Card', 'code' => 'PAN_CARD'],
                    'matric_certificate' => ['name' => '10th Marksheet (Matric)', 'code' => 'MATRIC_CERTIFICATE'],
                    'inter_certificate' => ['name' => '12th Marksheet (Intermediate)', 'code' => 'INTER_CERTIFICATE'],
                    'graduation_certificate' => ['name' => 'Graduation Marksheet', 'code' => 'GRADUATION_CERTIFICATE'],
                    'master_certificate' => ['name' => 'Post Graduation Marksheet', 'code' => 'MASTER_CERTIFICATE'],
                    'experience_certificate' => ['name' => 'Experience Certificate', 'code' => 'EXPERIENCE_CERTIFICATE'],
                    'cancelled_cheque' => ['name' => 'Cancelled Cheque', 'code' => 'CANCELLED_CHEQUE'],
                ];

                $mapping = $docMapping[$prefix] ?? [
                    'name' => ucwords(str_replace('_', ' ', $prefix)),
                    'code' => strtoupper($prefix)
                ];
                
                // Find or Create Document Type
                $docType = \App\Models\DocumentType::withoutGlobalScopes()->where('code', $mapping['code'])->first();
                if (!$docType) {
                    $docType = \App\Models\DocumentType::create([
                        'name' => $mapping['name'],
                        'code' => $mapping['code'],
                        'status' => \App\Enums\CommonStatus::ACTIVE,
                        'tenant_id' => $user->tenant_id
                    ]);
                }

                \App\Models\DocumentRequest::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'document_type_id' => $docType->id,
                    ],
                    [
                        'generated_file' => $path,
                        'status' => 'pending',
                        'remarks' => 'Uploaded during onboarding (AJAX)',
                        'tenant_id' => $user->tenant_id
                    ]
                );
            }

            return response()->json([
                'success' => true, 
                'message' => 'File uploaded successfully.',
                'path' => \App\Helpers\FileSecurityHelper::generateSecureUrl($path),
                'field' => $inputName
            ]);
        } catch (\Exception $e) {
            Log::error('Onboarding AJAX Upload Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to upload and secure file.'], 500);
        }
    }

    /**
     * Show submission status.
     */
    public function status()
    {
        $user = Auth::user();
        return view('tenant.onboarding.status', ['user' => $user]);
    }

    /**
     * Handle onboarding file uploads.
     */
    private function handleFileUploads(Request $request, User $user)
    {
        $folder = Constants::BaseFolderOnboardingDocuments . $user->id;

        $files = [
            'photo' => 'profile_photo',
            'aadhaar_file' => 'aadhaar_card',
            'pan_file' => 'pan_card',
            'passport_file' => 'passport_document',
            'visa_file' => 'visa_document',
            'frro_file' => 'frro_document',
            'cheque_file' => 'cancelled_cheque',
            'matric_file' => 'matric_certificate',
            'inter_file' => 'inter_certificate',
            'graduation_file' => 'graduation_certificate',
            'master_file' => 'master_certificate',
            'experience_file' => 'experience_certificate',
        ];

        foreach ($files as $inputName => $fileNamePrefix) {
            if ($request->hasFile($inputName)) {
                $file = $request->file($inputName);
                $path = \App\Helpers\FileSecurityHelper::encryptAndStore($file, $folder, $fileNamePrefix, 'public');
                
                if ($path) {
                    if ($inputName === 'photo') {
                        $user->profile_picture = $path;
                        $user->save();
                    } else {
                        // Create/Update Document Request for visibility in standard system
                        $docMapping = [
                            'aadhaar_card' => ['name' => 'Aadhaar Card', 'code' => 'AADHAAR_CARD'],
                            'pan_card' => ['name' => 'PAN Card', 'code' => 'PAN_CARD'],
                            'matric_certificate' => ['name' => '10th Marksheet (Matric)', 'code' => 'MATRIC_CERTIFICATE'],
                            'inter_certificate' => ['name' => '12th Marksheet (Intermediate)', 'code' => 'INTER_CERTIFICATE'],
                            'graduation_certificate' => ['name' => 'Graduation Marksheet', 'code' => 'GRADUATION_CERTIFICATE'],
                            'master_certificate' => ['name' => 'Post Graduation Marksheet', 'code' => 'MASTER_CERTIFICATE'],
                            'experience_certificate' => ['name' => 'Experience Certificate', 'code' => 'EXPERIENCE_CERTIFICATE'],
                            'cancelled_cheque' => ['name' => 'Cancelled Cheque', 'code' => 'CANCELLED_CHEQUE'],
                        ];

                        $mapping = $docMapping[$fileNamePrefix] ?? [
                            'name' => ucwords(str_replace('_', ' ', $fileNamePrefix)),
                            'code' => strtoupper($fileNamePrefix)
                        ];
                        
                        // Find or Create Document Type
                        $docType = \App\Models\DocumentType::withoutGlobalScopes()->where('code', $mapping['code'])->first();
                        if (!$docType) {
                            $docType = \App\Models\DocumentType::create([
                                'name' => $mapping['name'],
                                'code' => $mapping['code'],
                                'status' => \App\Enums\CommonStatus::ACTIVE,
                                'tenant_id' => $user->tenant_id
                            ]);
                        }

                        \App\Models\DocumentRequest::updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'document_type_id' => $docType->id,
                            ],
                            [
                                'generated_file' => $path,
                                'status' => 'pending',
                                'remarks' => 'Uploaded during onboarding',
                                'tenant_id' => $user->tenant_id
                            ]
                        );
                    }
                }
            }
        }
    }

    private function getOnboardingFileIndex(User $user): array
    {
        $folder = Constants::BaseFolderOnboardingDocuments . $user->id;
        $files = Storage::disk('public')->files($folder);
        $index = [];

        $knownPrefixes = [
            'profile_photo', 'cancelled_cheque', 'aadhaar_card', 'pan_card', 
            'matric_certificate', 'inter_certificate', 'graduation_certificate', 'master_certificate'
        ];

        foreach ($files as $file) {
            $name = basename($file);
            foreach ($knownPrefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $index[$prefix] = true;
                }
            }
        }

        return $index;
    }

    private function hasOnboardingFile(array $index, string $prefix): bool
    {
        return isset($index[$prefix]);
    }
}
