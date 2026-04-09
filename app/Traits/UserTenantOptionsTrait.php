<?php

namespace App\Traits;

use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\BankAccount;
use App\Models\Designation;
use App\Models\DigitalIdCard;
use App\Models\ExpenseRequest;
use App\Models\LeaveRequest;
use App\Models\LoanRequest;
use App\Models\PaymentCollection;
use App\Models\PayrollAdjustment;
use App\Models\SalesTarget;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\UserAvailableLeave;
use App\Models\UserDevice;
use App\Models\UserSettings;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Asset;
use App\Models\AssetActivity;
use App\Models\AssetAssignment;
use App\Models\AssetMaintenance;
use App\Models\Event;
use App\Models\CourseEnrollment;
use App\Models\LessonCompletion;
use App\Models\Note;
use App\Models\Tag;

trait UserTenantOptionsTrait
{

  public function userDevice()
  {
    return $this->hasOne(UserDevice::class);
  }

  public function team()
  {
    return $this->belongsTo(Team::class);
  }


  public function shift()
  {
    return $this->belongsTo(Shift::class);
  }

  public function userAvailableLeaves()
  {
    return $this->hasMany(UserAvailableLeave::class);
  }


  public function reportingTo()
  {
    return $this->belongsTo(User::class, 'reporting_to_id');
  }


  public function isOnLeave(): bool
  {
    return LeaveRequest::where('user_id', $this->id)
      ->where('status', LeaveRequestStatus::APPROVED)
      ->where('from_date', '<=', now()->toDateString())
      ->where('to_date', '>=', now()->toDateString())
      ->exists();
  }

  public function designation()
  {
    return $this->belongsTo(Designation::class);
  }

  public function getReportingToUserName()
  {
    $user = User::find($this->reporting_to_id);
    return $user ? $user->getFullName() : '';
  }


  public function userSettings()
  {
    return $this->hasOne(UserSettings::class);
  }

  public function digitalIdCard()
  {
    return $this->hasOne(DigitalIdCard::class);
  }

  public function leaveRequests()
  {
    return $this->hasMany(LeaveRequest::class);
  }

  public function expenseRequests()
  {
    return $this->hasMany(ExpenseRequest::class);
  }

  public function loanRequests()
  {
    return $this->hasMany(LoanRequest::class);
  }

  public function paymentCollections()
  {
    return $this->hasMany(PaymentCollection::class);
  }

  public function site()
  {
    return $this->belongsTo(Site::class);
  }

  public function salesTargets()
  {
    return $this->hasMany(SalesTarget::class);
  }

  public function getTodayAttendance()
  {
    return $this->attendances()
      ->whereDate('check_in_time', now()->toDateString())
      ->first();
  }

  public function attendances()
  {
    return $this->hasMany(Attendance::class);
  }


  public function bankAccount()
  {
    return $this->hasOne(BankAccount::class);
  }


  public function payrollAdjustments()
  {
    return $this->hasMany(PayrollAdjustment::class)->where('user_id', $this->id);
  }

  /**
   * The events this user is attending.
   */
  public function eventsAttending()
  {
    return $this->belongsToMany(Event::class, 'event_user', 'user_id', 'event_id')
      ->withTimestamps(); // Match the Event model's relationship
  }

  /**
   * The events created by this user.
   */
  public function eventsCreated()
  {
    return $this->hasMany(Event::class, 'created_by_id');
  }

  public function notes()
  {
    return $this->hasMany(Note::class);
  }

  /**
   * Get the tags created by this user.
   */
  public function tags()
  {
    return $this->hasMany(Tag::class);
  }


  /**
   * Get all asset assignment records for the user (history).
   */
  public function assetAssignments(): HasMany
  {
    return $this->hasMany(AssetAssignment::class)->orderBy('assigned_at', 'desc');
  }

  /**
   * Get the assets currently assigned to the user.
   */
  public function currentAssets() // Not a standard relationship, but a useful query
  {
    // Get assets through assignments where returned_at is null
    return \App\Models\Asset::whereHas('assignments', function ($query) {
      $query->where('user_id', $this->id)->whereNull('returned_at');
    })->get();

    // Alternative using HasManyThrough requires more setup or a dedicated "CurrentAssignment" model/view
    // return $this->hasManyThrough(\App\Models\Asset::class, AssetAssignment::class, 'user_id', 'id', 'id', 'asset_id')
    //            ->whereNull('asset_assignments.returned_at');
  }

  /**
   * Get maintenance records completed/logged by this user.
   */
  public function loggedMaintenances(): HasMany
  {
    return $this->hasMany(AssetMaintenance::class, 'completed_by_id');
  }

  /**
   * Get assets created by this user.
   */
  public function createdAssets(): HasMany
  {
    return $this->hasMany(\App\Models\Asset::class, 'created_by_id');
  }

  /**
   * Get asset activity logs performed BY this user.
   */
  public function performedAssetActivities(): HasMany
  {
    return $this->hasMany(AssetActivity::class, 'user_id')->orderBy('created_at', 'desc');
  }

  /**
   * Get asset activity logs where this user was INVOLVED (e.g., assigned asset).
   */
  public function involvedAssetActivities(): HasMany
  {
    return $this->hasMany(AssetActivity::class, 'related_user_id')->orderBy('created_at', 'desc');
  }


  /**
   * Get the course enrollments for the user.
   */
  public function courseEnrollments(): HasMany
  {
    return $this->hasMany(CourseEnrollment::class);
  }

  /**
   * Get all lesson completions for the user across all courses.
   */
  public function lessonCompletions(): HasMany
  {
    return $this->hasMany(LessonCompletion::class);
  }

  public function tasks()
  {
    return $this->hasMany(Task::class);
  }

}
