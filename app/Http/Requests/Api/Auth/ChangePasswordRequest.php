<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
      'currentPassword' => ['required', 'string', 'min:6', 'max:255'],
      'newPassword' => ['required', 'string', 'min:6', 'max:255']
    ];
  }
}
