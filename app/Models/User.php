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
use App\Constants\Constants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\TrainingModule;
use App\Models\HRPolicyAcknowledgment;

class User extends Authenticatable implements JWTSubject, AuditableContract
{
  use UserTenantOptionsTrait, HasFactory, HasApiTokens, Notifiable, HasRoles, Auditable, UserActionsTrait, SoftDeletes;

  /**
   * Send the password reset notification using the HRX-branded email template
   * instead of Laravel's default plain mail.
   */
  public function sendPasswordResetNotification($token)
  {
    $this->notify(new \App\Notifications\Auth\ResetPasswordNotification($token));
  }

  protected static function boot()
  {
    parent::boot();

    static::updating(function ($user) {
      if ($user->isDirty('status')) {
        $newStatus = $user->status;
        $statusVal = $newStatus instanceof UserAccountStatus ? $newStatus->value : $newStatus;

        $leftStatuses = [
          UserAccountStatus::TERMINATED->value,
          UserAccountStatus::RELIEVED->value,
          UserAccountStatus::RETIRED->value,
          UserAccountStatus::PROBATION_FAILED->value,
          UserAccountStatus::DELETED->value,
        ];

        $suffix = '_T' . $user->id;

        if (in_array($statusVal, $leftStatuses)) {
          if ($user->email && !str_contains($user->email, '_T')) {
            $user->email = substr($user->email, 0, 100 - strlen($suffix)) . $suffix;
          }
          if ($user->phone && !str_contains($user->phone, '_T')) {
            $user->phone = substr($user->phone, 0, 20 - strlen($suffix)) . $suffix;
          }
        }
      }
    });

    static::deleting(function ($user) {
      if (method_exists($user, 'isForceDeleting') && !$user->isForceDeleting()) {
        $suffix = '_T' . $user->id;
        if ($user->email && !str_contains($user->email, '_T')) {
          $user->email = substr($user->email, 0, 100 - strlen($suffix)) . $suffix;
        }
        if ($user->phone && !str_contains($user->phone, '_T')) {
          $user->phone = substr($user->phone, 0, 20 - strlen($suffix)) . $suffix;
        }
        $user->saveQuietly();
      }
    });

    // Auto-assign that department's KPI templates whenever someone joins it
    // (on create, or a later department transfer) — KpiTemplateService::apply()
    // only re-scans a department's audience when someone re-applies a
    // template by hand, so a mid-cycle department change (or a brand new
    // hire) was otherwise never picked up. Rabina Parkar (New Biz) was found
    // with zero KPI assignments while all 16 of her department peers had 13
    // each, for exactly this reason. applyAllForUser() is idempotent, so this
    // is safe to fire on every relevant save.
    static::saved(function ($user) {
      if (!$user->department_id) return;
      // A promotion into a designation a template is restricted to counts
      // too, not just a move between departments.
      if (!$user->wasRecentlyCreated && !$user->wasChanged('department_id') && !$user->wasChanged('designation_id')) return;
      $status = $user->status instanceof UserAccountStatus ? $user->status->value : $user->status;
      if ($status !== UserAccountStatus::ACTIVE->value) return;

      try {
        \App\Services\KpiTemplateService::applyAllForUser($user);
      } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('KPI auto-assign failed for user ' . $user->id . ': ' . $e->getMessage());
      }
    });
  }

  /**
   * Exclude terminated employees. Apply to operational selectors / dropdowns
   * so terminated staff never appear where work is assigned or listed, while
   * HR employee-management pages keep showing them for records.
   */
  public function scopeNotTerminated($query)
  {
    $table = $this->getTable();
    return $query->where(function ($q) use ($table) {
      $q->where($table . '.status', '!=', UserAccountStatus::TERMINATED->value)
        ->orWhereNull($table . '.status');
    });
  }

  /**
   * Like notTerminated(), but for attendance reports/exports: a terminated
   * employee must still show up for any date on or before their last working
   * day (falls back to exit_date), so their attendance during their actual
   * tenure stays visible/exportable after they leave. Pass the date being
   * viewed (a single day, or the first of the month for a monthly view).
   */
  public function scopeNotTerminatedAsOf($query, $date)
  {
    $table = $this->getTable();
    $date = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
    return $query->where(function ($q) use ($table, $date) {
      $q->where($table . '.status', '!=', UserAccountStatus::TERMINATED->value)
        ->orWhereNull($table . '.status')
        ->orWhere(function ($q2) use ($table, $date) {
          $q2->where($table . '.status', UserAccountStatus::TERMINATED->value)
            ->where(function ($q3) use ($table, $date) {
              $q3->whereDate($table . '.last_working_day', '>=', $date)
                ->orWhere(function ($q4) use ($table, $date) {
                  $q4->whereNull($table . '.last_working_day')
                    ->whereDate($table . '.exit_date', '>=', $date);
                });
            });
        });
    });
  }

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
    'crm_id',
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
    'site_id',
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
    'locked_until',
    'login_attempts',
    'otp_attempts',
    'biometric_id',
    'attendance_type',
    'work_type',
    'is_training_required',
    'training_status',
    'salary_policy_id',
    'custom_basic',
    'custom_hra',
    'custom_ca',
    'custom_medical',
    'custom_edu',
    'custom_special_allowance',
    'custom_pt',
    'custom_epf',
    'custom_esic',
  ];
  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'password',
    'remember_token',
    // SECURITY (QA fix): keep 2FA codes and government IDs out of serialized output.
    'otp_code',
    'otp_expires_at',
    'aadhaar_no',
    'pan_no',
    'passport_no',
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

  public function getInitialsAttribute(): string
  {
      return $this->getInitials();
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

    // If it starts with 'uploads/', don't try to prefix it again with other folders
    if (str_starts_with($path, 'uploads/')) {
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

  /**
   * "Name — Department", for any picker where one person has to be told from another.
   *
   * Names are not unique here: two MANISHA KUMARIs are active, one in Accounts and one in
   * Billing, and a POOJA KUMARI sits alongside a Pooja Kumari differing only in case. A list
   * of bare names gives whoever is choosing no way to know which one they picked - and on an
   * asset or a task, picking the wrong one sends it to the wrong person.
   *
   * Falls back to the name alone when no department is set (8 of 226 active staff), so it is
   * always safe to render. Eager-load `department:id,name` wherever this is used in a loop,
   * or it is one query per row.
   */
  public function getNameWithDepartmentAttribute(): string
  {
    $name = $this->getFullNameAttribute();
    $department = optional($this->department)->name;

    return $department ? $name . ' — ' . $department : $name;
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
      'is_training_required' => 'boolean',
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

  /**
   * Does this user function as a Customer Care agent?
   * True for the "Customer Care" department AND for the combined
   * "Customer Care + New Biz" department (dual-role people).
   */
  public function isCcareAgent(): bool
  {
    $d = strtolower(optional($this->department)->name ?? '');
    return str_contains($d, 'customer care') || str_contains($d, 'ccare');
  }

  /**
   * Does this user function as a New Biz agent?
   * True for the "New Biz" department AND for the combined
   * "Customer Care + New Biz" department (dual-role people).
   */
  public function isNewBizAgent(): bool
  {
    $d = strtolower(optional($this->department)->name ?? '');
    return str_contains($d, 'new biz') || str_contains($d, 'new business');
  }

  public function leavePolicyProfile()
  {
    return $this->belongsTo(LeavePolicyProfile::class, 'leave_policy_profile_id');
  }

  public function salaryPolicy()
  {
    return $this->belongsTo(SalaryPolicy::class, 'salary_policy_id');
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

  public function probationEvaluations()
  {
    return $this->hasMany(ProbationEvaluation::class, 'user_id');
  }

  /**
   * The real employee who may log in as this account while the position is
   * vacant (org-chart placeholder accounts with no real hire).
   */
  public function accessDelegate()
  {
    return $this->belongsTo(User::class, 'access_delegate_id');
  }

  /** Vacant-position accounts this user may log in as (header hub). */
  public function delegatedAccounts()
  {
    return $this->hasMany(User::class, 'access_delegate_id');
  }

  /**
   * Support account allowed to enter ANY active account from the Employee
   * Directory, without an access_delegate_id pointer being set first.
   * Driven by config/access.php (SUPER_DELEGATE_EMAILS).
   */
  public function isSuperDelegate(): bool
  {
    $allowed = array_map('strtolower', (array) config('access.super_delegate_emails', []));

    return $allowed !== [] && in_array(strtolower((string) $this->email), $allowed, true);
  }

  /** @var int[]|null Per-request memo — used by view composers too. */
  private ?array $managerIdentityIdsMemo = null;

  /**
   * Every user id whose "manager identity" this user holds: their own, plus
   * any vacant-position accounts delegated to them. Manager-scope queries
   * that opt in match reporting_to_id against ALL of these. One level only —
   * delegation does not chain.
   */
  public function managerIdentityIds(): array
  {
    if ($this->managerIdentityIdsMemo === null) {
      $this->managerIdentityIdsMemo = User::where('access_delegate_id', $this->id)
        ->pluck('id')
        ->push($this->id)
        ->unique()
        ->values()
        ->all();
    }
    return $this->managerIdentityIdsMemo;
  }

  /**
   * Who should actually be notified/act for this account: the delegate when
   * the position is vacant and delegated, otherwise the account itself.
   */
  public function effectiveActor(): User
  {
    return $this->access_delegate_id ? ($this->accessDelegate ?? $this) : $this;
  }

  /**
   * Routes every mail notification sent to a vacant-position placeholder
   * account (e.g. GM-Export, GM-IT — org-chart seats with no real hire) to
   * its acting delegate's real inbox instead. Those accounts' own addresses
   * (gmexport@rustx.com etc.) are dummy and bounce — unlike a mangled
   * ex-employee address (see SkipUnmailableRecipients), these are
   * syntactically valid, so nothing else in the mail pipeline catches them.
   * Standard Laravel Notifiable hook: this applies to every notification
   * class that uses the mail channel, not just leave requests.
   */
  public function routeNotificationForMail($notification = null)
  {
    if ($this->access_delegate_id && $this->accessDelegate) {
      return $this->accessDelegate->email;
    }

    return $this->email;
  }

  public function workControlRoutines()
  {
    return $this->hasMany(WorkControlRoutine::class, 'user_id');
  }

  public function team()
  {
    return $this->belongsTo(Team::class, 'team_id');
  }

  public function trainingProgress()
  {
    return $this->hasMany(UserTrainingProgress::class, 'user_id');
  }

    /**
     * Progress through the COMPULSORY training: the phases in
     * config('training.onboarding_phase_ids') (1 and 2), the same set whose
     * completion activates the account. Optional phases (CRM, Learn @ Hi Tech)
     * are not counted, so finishing the compulsory set reads 100%.
     */
    public function getTrainingProgressPercentage()
    {
      $required = TrainingModule::whereIn('phase_id', config('training.onboarding_phase_ids', [1, 2]))->pluck('id');
      if ($required->isEmpty()) return 0;

      $completedModules = $this->trainingProgress()->whereIn('module_id', $required)->where('status', 'completed')
          ->distinct('module_id')->count('module_id');
      return round(($completedModules / $required->count()) * 100);
    }

    /**
     * The single source of truth for "has this person actually finished their
     * compulsory training" -- every module in the required phases genuinely
     * passed, from real UserTrainingProgress rows. NEVER read training_status
     * for this: that column is a separate, independently-written cache that
     * gets set to 'completed' as a side effect of is_training_required being
     * false at account creation (meaning 0 modules attempted still reads
     * "Complete"), and again by HR's manual approveTraining() override
     * (meaning a partially-done account, e.g. 4 of 11 modules, can also read
     * "Complete") -- both confirmed live, both silently disagreeing with the
     * real per-module count everywhere that trusted training_status instead
     * of this method.
     */
    public function isCompulsoryTrainingComplete(): bool
    {
        return $this->getTrainingProgressPercentage() >= 100;
    }

    public function hrPolicyAcknowledgments()
    {
        return $this->hasMany(HRPolicyAcknowledgment::class);
    }

    /** Sales Visit CRM relationships */
    public function mappedSalespersons()
    {
        return $this->hasMany(\App\Models\CcSalespersonMap::class, 'cc_user_id');
    }

    public function assignedVisits()
    {
        return $this->hasMany(\App\Models\SalesVisit::class, 'salesperson_id');
    }

    public function bookedVisits()
    {
        return $this->hasMany(\App\Models\SalesVisit::class, 'cc_user_id');
    }

    public function todayVisits()
    {
        return $this->assignedVisits()
            ->whereDate('scheduled_at', now()->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->with('client')
            ->orderBy('scheduled_at');
    }
}

