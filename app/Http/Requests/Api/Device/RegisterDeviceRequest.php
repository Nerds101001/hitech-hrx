<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'uid' => 'required|string|min:12|max:16',
      'deviceType' => 'required|string',
      'sdkVersion' => 'required|string',
      'deviceName' => 'required|string',
      'deviceModel' => 'required|string',
      'osVersion' => 'required|string',
      'appVersion' => 'required|string',
      'board' => 'nullable|string',
      'address' => 'nullable|string',
      'ipAddress' => 'nullable|string',
    ];
  }
}
