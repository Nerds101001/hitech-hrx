<?php

namespace App\ApiClasses;

use Illuminate\Http\JsonResponse;

class Success
{

  public static function response(
    $data,
    int $code = 200,
    array $headers = [],
    $options = 0
  ): JsonResponse
  {
    /*    if ($code < 200 || $code >= 300) {
          throw new Exception('Status code is invalid');
        }*/

    return response()->json(
      [
        'statusCode' => 200,
        'status' => 'success',
        'data' => $data
      ],
      $code,
      $headers,
      $options
    );
  }
}
