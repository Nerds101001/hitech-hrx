<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'firstName' => ['required', 'string'],
      'lastName' => ['required', 'string'],
      'dob' => ['required', 'date', 'date_format:d-m-Y'],
      'gender' => ['required', Rule::in(array_column(Gender::cases(), 'value'))]
    ];
  }
}
