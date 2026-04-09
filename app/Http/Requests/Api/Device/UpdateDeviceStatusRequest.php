<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceStatusRequest extends FormRequest
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
      'batteryPercentage' => 'required|numeric',
      'isGPSOn' => 'required|boolean',
      'isWifiOn' => 'required|boolean',
      'isMock' => 'required|boolean',
      'isCharging' => 'boolean',
      'latitude' => 'required',
      'longitude' => 'required',
      'altitude' => 'required',
      'speed' => 'required',
      'horizontalAccuracy' => 'required',
      'verticalAccuracy' => 'required',
      'course' => 'required',
      'courseAccuracy' => 'required',
      'speedAccuracy' => 'required',
    ];
  }
}
