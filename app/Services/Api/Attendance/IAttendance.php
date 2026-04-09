<?php

namespace App\Services\Api\Attendance;

use App\Http\Requests\Api\Attendance\CheckInOutRequest;
use Illuminate\Http\JsonResponse;

interface IAttendance
{

  public function getStatus(): JsonResponse;

  public function checkInOut(CheckInOutRequest $data): JsonResponse;

  public function isCheckedIn(): bool;

}
