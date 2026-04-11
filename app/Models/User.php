<?php

namespace App\Models;

use App\Enums\OfflineRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserAccountStatus;
// use App\Models\SuperAdmin\Plan;
// use App\Models\SuperAdmin\Subscription;
// use App\Models\SuperAdmin\OfflineRequest;
use App\Traits\UserActionsTrait;
use App\Traits\UserTenantOptionsTrait;
use Carbon\Carbon;
use Constants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, AuditableContract
{
  use UserTenantOptionsTrait, HasFactory, HasApiTokens, Notifiable, HasRoles, Auditable, UserActionsTrait, SoftDeletes;

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'first_name',
    'last_name',
    'phone',
    'status',
    'dob',
    'gender',
    'profile_picture',
    'alternate_number',
    'cover_picture',
    'email',
    'personal_email',
    'phone',
    'official_phone',
    'email_verified_at',
    'phone_verified_at',
    'password',
    'remember_token',
    'language',
    'delete_request_at',
    'designation_id',
    'shift_id',
    'delete_request_reason',
    'team_id',
    'department_id',
    'code',
    'date_of_joining',
    'base_salary',
    'anniversary_date',
    'available_leave_count',
    'relieved_at',
    'relieved_reason',
    'retired_at',
    'retired_reason',
    'is_customer',
    'exit_date',
    'exit_reason',
    'termination_type',
    'last_working_day',
    'is_eligible_for_rehire',
    'notice_period_days',
    'probation_period_months',
    'probation_end_date',
    'probation_confirmed_at',
    'is_probation_extended',
    'probation_remarks',

    // Onboarding & Extended Profile
    'onboarding_at',
    'onboarding_deadline',
    'onboarding_completed_at',
    'onboarding_resubmission_notes',
    'consent_accepted_at',
    'ctc_offered',
    'leave_policy_profile_id',
    'designation_offered',
    'home_phone',
    'birth_country',
    'father_name',
    'mother_name',
    'marital_status',
    'spouse_name',
    'no_of_children',
    'children_details',
    'citizenship',
    'blood_group',
    'perm_street',
    'perm_building',
    'perm_zip',
    'perm_city',
    'perm_state',
    'perm_country',
    'temp_street',
    'temp_building',
    'temp_zip',
    'temp_city',
    'temp_state',
    'temp_country',
    'passport_no',
    'passport_issue_date',
    'passport_expiry_date',
    'visa_type',
    'visa_issue_date',
    'visa_expiry_date',
    'frro_registration',
    'frro_issue_date',
    'frro_expiry_date',
    'aadhaar_no',
    'pan_no',
    'pf_no',
    'esi_no',
    'uan_no',
    'emergency_contact_name',
    'emergency_contact_relation',
    'emergency_contact_phone',
    'onboarding_rejected_sections',
    'highest_qualification',
    'matric_marksheet_no',
    'matric_university',
    'inter_marksheet_no',
    'inter_university',
    'bachelor_marksheet_no',
    'bachelor_university',
    'master_marksheet_no',
    'master_university',
    'experience_certificate_no',
    'otp_code',
    'otp_expires_at',
    'locked_until',
    'login_attempts',
    'otp_attempts',
    'biometric_id',
    'attendance_type',
    'work_type',
  ];
  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'password',
    'remember_token',
  ];

  public function getUserForProfile()
  {
    return [
      'name' => $this->getFullName(),
      'code' => $this->code,
      'initials' => $this->getInitials(),
      'profile_picture' => $this->getProfilePicture(),
    ];
  }

  /**
   * Check if the user is currently under probation.
   */
  public function isUnderProbation(): bool
  {
    return $this->status === UserAccountStatus::ACTIVE && // Must be active
      !is_null($this->probation_end_date) && // Must have an end date
      is_null($this->probation_confirmed_at) && // Must not be confirmed yet
      Carbon::parse($this->probation_end_date)->isFuture(); // End date must be in the future
  }

  /**
   * Get a display string for the user's probation status.
   */
  public function getProbationStatusDisplayAttribute(): string
  {
    if ($this->status !== UserAccountStatus::ACTIVE || is_null($this->probation_end_date)) {
      return 'Not Applicable';
    }
    if (!is_null($this->probation_confirmed_at)) {
      return 'Completed on ' . Carbon::parse($this->probation_confirmed_at)->format('M d, Y');
    }
    if ($this->isUnderProbation()) {
      $statusText = 'Active until ' . Carbon::parse($this->probation_end_date)->format('M d, Y');
      if ($this->is_probation_extended) {
        $statusText .= ' (Extended)';
      }
      return $statusText;
    }
    if (Carbon::parse($this->probation_end_date)->isPast()) {
      // Past due date but not confirmed - needs action
      return 'Pending Confirmation (Ended ' . Carbon::parse($this->probation_end_date)->format('M d, Y') . ')';
    }
    return 'Unknown'; // Should not happen often
  }
  // --- End Probation Accessors ---


  public function getFullName()
  {
    return $this->first_name . ' ' . $this->last_name;
  }

  public function getInitials(): string
  {
    $first = substr($this->first_name ?? $this->name ?? 'U', 0, 1);
    $last = substr($this->last_name ?? '', 0, 1);
    return strtoupper($first . $last);
  }

  public function getProfilePicture()
  {
    if (!$this->profile_picture) {
      return null;
    }

    $path = $this->profile_picture;

    // 1. Check if the path exists as-is (already fully prefixed)
    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
      return \App\Helpers\FileSecurityHelper::generateSecureUrl($path);
    }

    // 2. Try prefixing with employee profile folder (Standard/Legacy)
    $profilePath = Constants::BaseFolderEmployeeProfileWithSlash . $path;
    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($profilePath)) {
      return \App\Helpers\FileSecurityHelper::generateSecureUrl($profilePath);
    }

    // 3. Try prefixing with onboarding folder (if only filename was saved)
    $onboardingPath = Constants::BaseFolderOnboardingDocuments . $this->id . '/' . $path;
    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($onboardingPath)) {
      return \App\Helpers\FileSecurityHelper::generateSecureUrl($onboardingPath);
    }

    return null;
  }

  /**
   * Get URL for Aadhaar Card from onboarding documents.
   */
  public function getAadhaarUrl()
  {
    $folder = Constants::BaseFolderOnboardingDocuments . $this->id;
    $files = \Illuminate\Support\Facades\Storage::disk('public')->files($folder);

    $file = collect($files)->filter(function ($f) {
      $name = strtolower(basename($f));
      return str_starts_with($name, 'aadhaar_card') || str_contains($name, 'aadhaar');
    })->last();

    return $file ? \App\Helpers\FileSecurityHelper::generateSecureUrl($file) : null;
  }

  /**
   * Get URL for PAN Card from onboarding documents.
   */
  public function getPanUrl()
  {
    $folder = Constants::BaseFolderOnboardingDocuments . $this->id;
    $files = \Illuminate\Support\Facades\Storage::disk('public')->files($folder);

    $file = collect($files)->filter(function ($f) {
      $name = strtolower(basename($f));
      return str_starts_with($name, 'pan_card') || str_contains($name, 'pan_card') || str_contains($name, 'pan');
    })->last();

    return $file ? \App\Helpers\FileSecurityHelper::generateSecureUrl($file) : null;
  }

  /**
   * Get URL for Bank Cheque from onboarding documents.
   */
  public function getChequeUrl()
  {
    $folder = Constants::BaseFolderOnboardingDocuments . $this->id;
    $files = \Illuminate\Support\Facades\Storage::disk('public')->files($folder);
    $file = collect($files)->filter(function ($f) {
      $name = strtolower(basename($f));
      return str_starts_with($name, 'cancelled_cheque') || str_contains($name, 'cheque') || str_contains($name, 'bank');
    })->last();
    return $file ? \App\Helpers\FileSecurityHelper::generateSecureUrl($file) : null;
  }

  /**
   * Get URL for Matric Certificate from onboarding documents.
   */
  public function getMatricUrl()
  {
    $folder = Constants::BaseFolderOnboardingDocuments . $this->id;
    $files = \Illuminate\Support\Facades\Storage::disk('public')->files($folder);
    $file = collect($files)->filter(function ($f) {
      $name = strtolower(basename($f));
      return str_starts_with($name, 'matric_certificate') || str_contains($name, 'matric') || str_contains($name, '10th');
    })->last();
    return $file ? \App\Helpers\FileSecurityHelper::generateSecureUrl($file) : null;
  }

  /**
   * Get URL for Inter Certificate from onboarding documents.
   */
  public function getInterUrl()
  {
    $folder = Constants::BaseFolderOnboardingDocuments . $this->id;
    $files = \Illuminate\Support\Facades\Storage::disk('public')->files($folder);
    $file = collect($files)->filter(function ($f) {
      $name = strtolower(basename($f));
      return str_starts_with($name, 'inter_certificate') || str_contains($name, 'inter') || str_contains($name, '12th');
    })->last();
    return $file ? \App\Helpers\FileSecurityHelper::generateSecureUrl($file) : null;
  }

  /**
   * Get URL for Bachelor Certificate from onboarding documents.
   */
  public function getBachelorUrl()
  {
    $folder = Constants::BaseFolderOnboardingDocuments . $this->id;
    $files = \Illuminate\Support\Facades\Storage::disk('public')->files($folder);
    $file = collect($files)->filter(function ($f) {
      $name = strtolower(basename($f));
      return str_starts_with($name, 'graduation_certificate') || str_contains($name, 'graduation') || str_contains($name, 'bachelor');
    })->last();
    return $file ? \App\Helpers\FileSecurityHelper::generateSecureUrl($file) : null;
  }

  /**
   * Get URL for Master Certificate from onboarding documents.
   */
  public function getMasterUrl()
  {
    $folder = Constants::BaseFolderOnboardingDocuments . $this->id;
    $files = \Illuminate\Support\Facades\Storage::disk('public')->files($folder);
    $file = collect($files)->filter(function ($f) {
      $name = strtolower(basename($f));
      return str_starts_with($name, 'master_certificate') || str_contains($name, 'master') || str_contains($name, 'post');
    })->last();
    return $file ? \App\Helpers\FileSecurityHelper::generateSecureUrl($file) : null;
  }

  /**
   * Get URL for Experience Certificate from onboarding documents.
   */
  public function getExperienceUrl()
  {
    $folder = Constants::BaseFolderOnboardingDocuments . $this->id;
    $files = \Illuminate\Support\Facades\Storage::disk('public')->files($folder);
    $file = collect($files)->filter(function ($f) {
      $name = strtolower(basename($f));
      return str_starts_with($name, 'experience_certificate') || str_contains($name, 'experience');
    })->last();
    return $file ? \App\Helpers\FileSecurityHelper::generateSecureUrl($file) : null;
  }

  /*
    public function activePlan()
    {
      return $this->belongsTo(Plan::class, 'plan_id');
    }
  */

  public function getJWTIdentifier()
  {
    return $this->getKey();
  }

  public function getJWTCustomClaims()
  {
    return [];
  }

  /**
   * Specifies the user's FCM tokens
   *
   * @return string|array
   */
  public function fcmToken()
  {
    return $this->getDeviceToken();
  }

  public function getDeviceToken()
  {
    $userDevice = UserDevice::where('user_id', $this->id)->first();
    return $userDevice?->token;
  }

  public function hasActivePlan(): bool
  {
    return $this->plan_id != null && $this->plan_expired_date >= now()->toDateString();
  }

  /*
    public function hasPendingOfflineRequest(): bool
    {
      return OfflineRequest::where('user_id', $this->id)
        ->where('status', OfflineRequestStatus::PENDING)
        ->exists();
    }
  */

  /*
    public function activeSubscription()
    {
      return $this->subscriptions()
        ->where('status', SubscriptionStatus::ACTIVE)
        ->where('end_date', '>=', now()->toDateString())
        ->first();
    }
  */

  /*
    public function subscriptions()
    {
      return $this->hasMany(Subscription::class);
    }
  */

  public function getFullNameAttribute()
  {
    return trim("{$this->first_name} {$this->last_name}");
  }

  public function getNameAttribute()
  {
    return $this->getFullNameAttribute();
  }


  //Tenant Specific

  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password' => 'hashed',
      'phone_verified_at' => 'datetime',
      'dob' => 'date',
      'probation_end_date' => 'date',
      'onboarding_at' => 'datetime',
      'onboarding_deadline' => 'datetime',
      'onboarding_completed_at' => 'datetime',
      'otp_expires_at' => 'datetime',
      'locked_until' => 'datetime',
      'consent_accepted_at' => 'datetime',
      'passport_issue_date' => 'date',
      'passport_expiry_date' => 'date',
      'visa_issue_date' => 'date',
      'visa_expiry_date' => 'date',
      'frro_issue_date' => 'date',
      'frro_expiry_date' => 'date',
      'children_details' => 'array',
      'onboarding_rejected_sections' => 'array',
      'status' => UserAccountStatus::class,
      'date_of_joining' => 'date',
      'anniversary_date' => 'date',
    ];
  }

  public function creatorId()
  {
    if ($this->hasRole('admin')) {
      return $this->id;
    }
    return $this->created_by_id ?? 1; // Fallback to first user
  }

  public function dateFormat($date)
  {
    return \Carbon\Carbon::parse($date)->format('M d, Y');
  }
  public function documentRequests()
  {
    return $this->hasMany(DocumentRequest::class);
  }

  public function site()
  {
    return $this->belongsTo(Site::class, 'site_id');
  }

  /**
   * Check if multiple check-in/out is enabled for the user.
   * Priorities: Unit Override > Role Setting
   */
  public function isMultiCheckInOutEnabled(): bool
  {
    if ($this->site_id) {
      $site = $this->site;
      if ($site && !is_null($site->is_multiple_check_in_enabled)) {
        return (bool) $site->is_multiple_check_in_enabled;
      }
    }

    $role = $this->roles->first();
    return $role ? (bool) $role->is_multiple_check_in_enabled : false;
  }

  /**
   * Check if auto check-out is enabled for the user.
   * Priorities: Unit Override > Global Setting
   */
  public function isAutoCheckOutEnabled(): bool
  {
    if ($this->site_id) {
      $site = $this->site;
      if ($site && !is_null($site->is_auto_check_out_enabled)) {
        return (bool) $site->is_auto_check_out_enabled;
      }
    }

    $settings = Settings::first();
    return $settings ? (bool) $settings->is_auto_check_out_enabled : false;
  }

  /**
   * Check if biometric verification is enabled for the user.
   * Priorities: Unit Override > Global Setting
   */
  public function isBiometricVerificationEnabled(): bool
  {
    if ($this->site_id) {
      $site = $this->site;
      if ($site && !is_null($site->is_biometric_verification_enabled)) {
        return (bool) $site->is_biometric_verification_enabled;
      }
    }

    $settings = Settings::first();
    return $settings ? (bool) $settings->is_biometric_verification_enabled : false;
  }

  public function designation()
  {
    return $this->belongsTo(Designation::class, 'designation_id');
  }

  public function assets()
  {
    return $this->hasMany(Asset::class, 'assigned_to');
  }

  public function currentAssets()
  {
    return $this->assets()->where('status', 'assigned');
  }

  /**
   * Get the display name of the user's primary role.
   */
  public function getRoleDisplayNameAttribute(): string
  {
    $role = $this->roles->first();
    return $role && !empty($role->display_name) ? $role->display_name : 'No Role';
  }

  public function payslips()
  {
    return $this->hasMany(Payslip::class, 'user_id');
  }

  public function bankAccount()
  {
    return $this->hasOne(BankAccount::class, 'user_id');
  }

  public function department()
  {
    return $this->belongsTo(Department::class, 'department_id');
  }

  public function leavePolicyProfile()
  {
    return $this->belongsTo(LeavePolicyProfile::class, 'leave_policy_profile_id');
  }

  public function leaveBalances()
  {
    return $this->hasMany(LeaveBalance::class, 'user_id');
  }

  public function attendance()
  {
    return $this->hasMany(Attendance::class);
  }

  public function attendances()
  {
    return $this->hasMany(Attendance::class);
  }

  public function leaveRequests()
  {
    return $this->hasMany(LeaveRequest::class);
  }
}
