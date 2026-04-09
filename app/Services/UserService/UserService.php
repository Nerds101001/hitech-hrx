<?php

namespace App\Services\UserService;

use App\Enums\Gender;
use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Hashing\BcryptHasher;

class UserService implements IUserService
{

  public function findUserByEmail(string $email): ?User
  {
    return User::where('email', $email)->first();
  }

  public function registerUser(array $data): ?User
  {
    $user = new User();
    $user->first_name = $data['firstName'];
    $user->last_name = $data['lastName'];
    $user->email = $data['email'];
    $user->password = (new BcryptHasher)->make($data['password']);
    $user->phone = $data['phoneNumber'];
    $user->gender = Gender::from($data['gender']);
    $user->status = UserAccountStatus::ACTIVE;
    $user->save();

    $user->assignRole('user');

    return $user;
  }

  public function checkEmailExists(string $email): bool
  {
    return User::where('email', $email)->exists();
  }

  public function updateUser(array $data): bool
  {
    $user = auth()->user();
    $user->first_name = $data['firstName'];
    $user->last_name = $data['lastName'];
    $user->gender = Gender::from($data['gender']);
    $user->dob = date_create_from_format('d-m-Y', $data['dob']);
    $user->save();

    return true;
  }
}
