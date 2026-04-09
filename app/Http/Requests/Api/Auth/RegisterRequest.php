<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
      'firstName' => ['required', 'string', 'max:255'],
      'lastName' => ['required', 'string', 'max:255'],
      'phoneNumber' => ['required', 'string', 'unique:users,phone'],
      'email' => ['required', 'email', 'regex:/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/', 'unique:users,email'],
      'password' => ['required', 'string', 'min:6', 'max:255'],
      'gender' => ['required', Rule::in(array_column(Gender::cases(), 'value'))],
    ];
  }
}
