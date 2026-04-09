<?php

namespace App\Http\Requests\Api\Attendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckInOutRequest extends FormRequest
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
      'latitude' => 'required',
      'longitude' => 'required',
      'altitude' => 'required',
      'speed' => 'required',
      'horizontalAccuracy' => 'nullable',
      'verticalAccuracy' => 'nullable',
      'course' => 'nullable',
      'courseAccuracy' => 'nullable',
      'speedAccuracy' => 'nullable',
      'address' => 'nullable',
    ];
  }
}
