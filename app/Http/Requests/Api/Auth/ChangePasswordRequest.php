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
      'newPassword' => ['required', 'string', 'max:255', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
    ];
  }

  /**
   * Trim before validating so a stray copy-paste space can't get baked into
   * the stored password or read as a spurious length/complexity failure.
   */
  protected function prepareForValidation(): void
  {
    $this->merge([
      'newPassword' => trim((string) $this->input('newPassword')),
    ]);
  }
}
