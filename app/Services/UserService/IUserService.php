<?php

namespace App\Services\UserService;

use App\Models\User;

interface IUserService
{
  public function checkEmailExists(string $email): bool;

  public function findUserByEmail(string $email): ?User;

  public function registerUser(array $data): ?User;

  public function updateUser(array $data): bool;
}
