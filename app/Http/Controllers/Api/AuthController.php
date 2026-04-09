<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Models\Designation;
use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\UserService\IUserService;
use Carbon\Carbon;
use Constants;
use Exception;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
  private IUserService $userService;

  function __construct(IUserService $userService)
  {
    $this->userService = $userService;
  }

  public function login(LoginRequest $request)
  {
    $user = $this->userService->findUserByEmail($request['employeeId']);

    if (is_null($user)) {
      return Error::response('User not found', 404);
    }

    $role = $user->roles()->first();

    if (!$role) {
      return Error::response('You do not have permission to access this resource', 403);
    }

    if (!$role->is_mobile_app_access_enabled) {
      return Error::response('You do not have permission to access this resource', 403);
    }

    if($user->status != UserAccountStatus::ACTIVE && $user->status != UserAccountStatus::ONBOARDING){
      return Error::response('User account is not active', 403);
    }

    if (!(new BcryptHasher)->check($request['password'], $user->password)) {
      return Error::response('Email or password is incorrect. Authentication failed.');
    }

    $credentials = ['email' => $user->email, 'password' => $request['password']];

    try {

      $token = $this->generateToken($credentials);
      if ($token == '') {
        return Error::response('Could not generate token, authentication failed');
      }

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
        'isLocationActivityTrackingEnabled' => (bool)$role->is_location_activity_tracking_enabled,
        'designation' => $user->designation ? $user->designation->name : null,
        'is_approver' => $user->designation && ($user->designation->is_leave_approver || $user->designation->is_expense_approver),
        'is_leave_approver' => $user->designation ? $user->designation->is_leave_approver : false,
        'is_expense_approver' => $user->designation ? $user->designation->is_expense_approver : false,
        'createdAt' => $user->created_at->format(Constants::DateTimeFormat),
        'avatar' => $user->profile_picture ? asset(Constants::BaseFolderEmployeeProfileWithSlash . $user->profile_picture) : null,
        'token' => $token,
        'expiresIn' => JWTAuth::factory()->getTTL()
      ];

      return Success::response($response);

    } catch (JWTException $e) {
      Log::error($e->getMessage());
      return Error::response('Could not create token');
    }

  }

  private function generateToken($credentials)
  {
    if (!$token = JWTAuth::attempt($credentials)) {
      return '';
    }

    return $token;
  }

  public function refresh()
  {
    $token = JWTAuth::getToken();
    if (!$token) {
      return Error::response('Token not provided', 401);
    }

    try {

      $newToken = JWTAuth::refresh();

      return Success::response(['token' => $newToken, 'expiresIn' => JWTAuth::factory()->getTTL()]);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return Error::response('Could not refresh token');
    }
  }

  public function logout()
  {
    $token = JWTAuth::getToken();

    if (!$token) {
      return Error::response('Token not provided', 401);
    }

    JWTAuth::setToken($token)->invalidate();

    return Success::response('Successfully logged out');
  }

  public function changePassword(ChangePasswordRequest $request)
  {
    $valReq = $request->validated();

    $user = auth()->user();

    if (!(new BcryptHasher)->check($valReq['currentPassword'], $user->password)) {
      return Error::response('Current password is incorrect');
    }

    $user->password = (new BcryptHasher)->make($valReq['newPassword']);
    $user->save();

    return Success::response('Password changed successfully');
  }

  public function checkEmail(Request $request)
  {
    $userName = $request->all();

    if (!$userName) {
      return Error::response('Invalid request');
    }

    $userName = $userName[0];

    if ($this->userService->checkEmailExists($userName)) {
      return Success::response('Email exists');
    }

    return Error::response('Email does not exist');
  }

  public function loginWithUid(Request $request)
  {
    $input = $request->all();
    if ($input == null || $input == '' || count($input) == 0) {
      return Error::response('Invalid request');
    }

    $uid = $input[0];

    if ($uid == null || $uid == '') {
      return Error::response('uid is required');
    }

    $device = UserDevice::where('device_id', $uid)->first();

    if ($device == null) {
      return Error::response('Device not found');
    }

    $user = User::where('id', $device->user_id)
      ->with('roles')
      ->first();

    if ($user == null) {
      return Error::response('User not found.');
    }

    if ($user->status == 'inactive') {
      return Error::response('User is inactive.');
    }

    $userRole = $user->roles()->first();

    if (!$userRole) {
      return Error::response('You do not have permission to access this resource', 403);
    }

    if (!$userRole->is_mobile_app_access_enabled) {
      return Error::response('You do not have permission to access this resource', 403);
    }

    try {
      if (!$token = JWTAuth::fromUser($user, ['exp' => Carbon::now()->addDays(28)->timestamp])) {
        return Error::response('Unable to create token');
      }
    } catch (JWTException $e) {
      return response()->json(['error' => 'could_not_create_token'], 500);
    }

    $response = [
      'token' => $token,
      'id' => $user->id,
      'firstName' => $user->first_name,
      'lastName' => $user->last_name,
      'employeeCode' => $user->code,
      'dob' => $user->dob != null ? $user->dob->format(Constants::DateFormat) : null,
      'gender' => $user->gender,
      'email' => $user->email,
      'phoneNumber' => $user->phone,
      'status' => $user->status,
      'role' => $userRole->name,
      'isLocationActivityTrackingEnabled' => (bool)$userRole->is_location_activity_tracking_enabled,
      'designation' => $user->designation ? $user->designation->name : null,
      'isApprover' => $user->designation && ($user->designation->is_leave_approver || $user->designation->is_expense_approver),
      'isLeaveApprover' => $user->designation ? $user->designation->is_leave_approver : false,
      'isExpenseApprover' => $user->designation ? $user->designation->is_expense_approver : false,
      'createdAt' => $user->created_at->format(Constants::DateTimeFormat),
      'avatar' => $user->profile_picture ? asset(Constants::BaseFolderEmployeeProfileWithSlash . $user->profile_picture) : null,
      'expiresIn' => JWTAuth::factory()->getTTL()
    ];

    return Success::response($response);
  }

  public function createDemoUser()
  {
    if (!env('APP_DEMO')) {
      return Error::response('Demo mode is not enabled');
    }

    $randomEmail = 'demo' . rand(1, 1000) . '@demo.com';
    while ($this->userService->checkEmailExists($randomEmail)) {
      $randomEmail = 'demo' . rand(1, 1000) . '@demo.com';
    }

    $randomPhoneNumber = rand(100000000, 999999999);
    while (User::where('phone', $randomPhoneNumber)->exists()) {
      $randomPhoneNumber = rand(100000000, 999999999);
    }

    $randomCode = 'DEMO' . rand(1, 1000);
    while (User::where('code', $randomCode)->exists()) {
      $randomCode = 'DEMO' . rand(1, 1000);
    }

    $designation = Designation::first();

    $shift = Shift::first();

    $team = Team::first();


    $user = new User();
    $user->first_name = 'Demo';
    $user->last_name = rand(1, 1000);
    $user->email = $randomEmail;
    $user->password = (new BcryptHasher)->make('123456');
    $user->status = 'active';
    $user->code = $randomCode;
    $user->phone = $randomPhoneNumber;
    $user->base_salary = rand(1000, 5000);
    $user->shift_id = $shift->id;
    $user->designation_id = $designation->id;
    $user->team_id = $team->id;
    $user->dob = now();
    $user->date_of_joining = now();

    $user->save();

    $user->assignRole('employee');

    try {
      if (!$token = JWTAuth::fromUser($user, ['exp' => Carbon::now()->addDays(28)->timestamp])) {
        return Error::response('Unable to create token');
      }
    } catch (JWTException $e) {
      return response()->json(['error' => 'could_not_create_token'], 500);
    }

    $response = [
      'token' => $token,
      'id' => $user->id,
      'firstName' => $user->first_name,
      'lastName' => $user->last_name,
      'employeeCode' => $user->code,
      'dob' => $user->dob ? $user->dob->format(Constants::DateFormat) : null,
      'gender' => $user->gender,
      'email' => $user->email,
      'phoneNumber' => $user->phone,
      'status' => $user->status,
      'role' => 'employee',
      'isLocationActivityTrackingEnabled' => true,
      'designation' => $user->designation ? $user->designation->name : null,
      'createdAt' => $user->created_at->format(Constants::DateTimeFormat),
      'avatar' => $user->profile_picture ? asset(Constants::BaseFolderEmployeeProfileWithSlash . $user->profile_picture) : null,
      'expiresIn' => JWTAuth::factory()->getTTL()
    ];

    return Success::response($response);
  }


}
