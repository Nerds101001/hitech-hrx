<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\Language;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Services\UserService\IUserService;
use Constants;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{

  private IUserService $userService;

  function __construct(IUserService $userService)
  {

    $this->userService = $userService;
  }

  public function me()
  {
    $user = auth()->user();

    $role = $user->roles()->first();

    $response = [
      'id' => $user->id,
      'firstName' => $user->first_name,
      'lastName' => $user->last_name,
      'employeeCode' => $user->code,
      'dob' => $user->dob != null ? $user->dob->format(Constants::DateFormat) : null,
      'gender' => $user->gender,
      'email' => $user->email,
      'phoneNumber' => $user->phone,
      'status' => $user->status,
      'role' => $role->name,
      'locationActivityTrackingEnabled' => (boolean)$role->is_location_activity_tracking_enabled,
      'designation' => $user->designation ? $user->designation->name : null,
      'createdAt' => $user->created_at->format(Constants::DateTimeFormat),
      'avatar' => $user->profile_picture ? asset(Constants::BaseFolderEmployeeProfileWithSlash . $user->profile_picture) : null,
      'isLocationActivityTrackingEnabled' => (bool)$role->is_location_activity_tracking_enabled,
      'isApprover' => $user->designation && ($user->designation->is_leave_approver || $user->designation->is_expense_approver),
      'isLeaveApprover' => $user->designation ? $user->designation->is_leave_approver : false,
      'isExpenseApprover' => $user->designation ? $user->designation->is_expense_approver : false,
    ];

    return Success::response($response);
  }

  public function getProfilePicture()
  {
    $user = auth()->user();

    if (is_null($user->profile_picture)) {
      return Error::response('Profile picture not found');
    }

    return Success::response(asset(Constants::BaseFolderEmployeeProfileWithSlash . $user->profile_picture));
  }

  public function updateProfilePicture(Request $request)
  {
    $request->validate([
      'file' => 'required|file|mimes:png,jpg,jpeg|max:2048'
    ]);

    $file = $request->file('file');

    $fileType = $file->getClientOriginalExtension();

    try {
      //Delete old file
      $user = auth()->user();
      $oldProfilePicture = $user->profile_picture;
      if (!is_null($oldProfilePicture)) {
        $oldProfilePicturePath = Storage::disk('public')->path(Constants::BaseFolderEmployeeProfileWithSlash . $oldProfilePicture);
        if (file_exists($oldProfilePicturePath)) {
          Storage::delete($oldProfilePicturePath);
        }
      }

      $fileName = time() . '.' . $fileType;

      //Save new file
      Storage::disk('public')->putFileAs(Constants::BaseFolderEmployeeProfileWithSlash . $file, $fileName);

      $user->profile_picture = $fileName;
      $user->save();

      return Success::response('Profile picture updated successfully');
    } catch (Exception $e) {
      Log::error('AccountController@updateProfilePicture: ' . $e->getMessage());
      return Error::response('Could not update profile picture');
    }
  }

  public function getAccountStatus()
  {
    return Success::response(auth()->user()->status);
  }

  public function getLanguage()
  {
    return Success::response(auth()->user()->language);
  }

  public function updateLanguage(Request $request)
  {
    $request->validate([
      'language' => ['required', 'min:2', Rule::in(array_column(Language::cases(), 'value'))]
    ]);

    $language = $request->language;

    $user = auth()->user();
    $user->language = Language::from($language);
    $user->save();

    return Success::response('Language updated successfully');
  }

  public function updateProfile(UpdateProfileRequest $request)
  {

    $valReq = $request->validated();

    $result = $this->userService->updateUser($valReq);

    if ($result) {
      return Success::response('Profile updated successfully');
    }

    return Error::response('Could not update profile');
  }

  public function deleteAccountRequest(Request $reason)
  {
    $deleteReason = $reason->reason;

    $user = auth()->user();
    $user->status = UserAccountStatus::DELETED;
    $user->delete_request_reason = $deleteReason;
    $user->delete_request_at = now();
    $user->save();

    return Success::response('Account deletion request sent successfully');
  }
}
