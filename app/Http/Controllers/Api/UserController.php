<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\UserAccountStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserStatusModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
  public function searchUsers($query)
  {
    $users = User::where('status', UserAccountStatus::ACTIVE)
      ->whereNot('id', auth()->id())
      ->where(function ($q) use ($query) {
        $q->where('first_name', 'like', "%$query%")
          ->orWhere('email', 'like', "%$query%")
          ->orWhere('last_name', 'like', "%$query%")
          ->orWhere('phone', 'like', "%$query%");
      })
      ->with('designation')
      ->orderBy('first_name', 'asc')
      ->take(10)
      ->get();


    $response = $users->map(function ($user) {
      return [
        'id' => $user->id,
        'firstName' => $user->first_name,
        'lastName' => $user->last_name,
        'avatar' => $user->getProfilePicture(),
        'code' => $user->code,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => $user->status,
        'designation' => $user->designation ? $user->designation->name : 'N/A',
        'role' => $user->roles()->first()->name,
        'dob' => $user->dob,
        'gender' => $user->gender,
        'dateOfJoining' => $user->date_of_joining,
        'createdAt' => $user->created_at,
      ];
    });

    return Success::response($response);
  }

  public function getUsersList(Request $request)
  {
    $skip = (int)$request->input('skip', 0);
    $take = (int)$request->input('take', 10);

    $users = User::where('status', UserAccountStatus::ACTIVE)
      ->whereNot('id', auth()->id())
      ->with('designation')
      ->skip($skip)->take($take)
      ->orderBy('first_name', 'asc')
      ->get();

    $result = $users->map(function ($user) {
      return [
        'id' => $user->id,
        'firstName' => $user->first_name,
        'lastName' => $user->last_name,
        'avatar' => $user->getProfilePicture(),
        'code' => $user->code,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => $user->status,
        'designation' => $user->designation ? $user->designation->name : 'N/A',
        'role' => $user->roles()->first()->name,
        'dob' => $user->dob,
        'gender' => $user->gender,
        'dateOfJoining' => $user->date_of_joining,
        'createdAt' => $user->created_at,
      ];
    });

    $totalCount = User::count();

    return Success::response([
      'users' => $result,
      'totalCount' => $totalCount,
      'skip' => $skip,
      'take' => $take,
    ]);
  }

  // Get User Status
  public function getUserStatus(Request $request)
  {
    $userId = $request->input('userId');

    $userId = $userId && is_numeric($userId) ? $userId : auth()->id();

    $userStatus = UserStatusModel::where('user_id', $userId)->latest()->first();

    if (!$userStatus) {
      return Error::response('Status not found for this user', 404);
    }

    return Success::response([
      'userId' => $userId,
      'status' => $userStatus->status->value,
      'message' => $userStatus->message,
      'expiresAt' => $userStatus->expires_at,
    ]);
  }

  public function getUserInfo($id)
  {
    $user = User::find($id);

    if (!$user) {
      return Error::response('User not found', 404);
    }

    $response = [
      'id' => $user->id,
      'firstName' => $user->first_name,
      'lastName' => $user->last_name,
      'avatar' => $user->getProfilePicture(),
      'code' => $user->code,
      'email' => $user->email,
      'phone' => $user->phone,
      'status' => $user->status,
      'designation' => $user->designation ? $user->designation->name : 'N/A',
      'role' => $user->roles()->first()->name,
      'dob' => $user->dob,
      'gender' => $user->gender,
      'dateOfJoining' => $user->date_of_joining,
      'createdAt' => $user->created_at,
    ];

    return Success::response($response);
  }

  // Update User Status
  public function updateUserStatus(Request $request)
  {
    $request->validate([
      'status' => ['required', Rule::in(UserStatus::cases(), 'value')],
      'message' => 'nullable|string|max:255',
      'expires_at' => 'nullable|date|after:now',
    ]);

    $userId = auth()->id();

    $userStatus = UserStatusModel::create([
      'user_id' => $userId,
      'status' => UserStatus::from($request->input('status')),
      'message' => $request->input('message'),
      'expires_at' => $request->input('expires_at'),
      'created_by_id' => $userId,
    ]);

    return Success::response([
      'userId' => $userId,
      'status' => $userStatus->status,
      'message' => $userStatus->message,
      'expiresAt' => $userStatus->expires_at,
    ]);
  }

}
