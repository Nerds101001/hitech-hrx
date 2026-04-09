<?php

namespace App\Http\Requests\Api\Auth;

use App\ApiClasses\Error;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
      'employeeId' => ['required', 'string', 'exists:users,email',],
      'password' => ['required', 'string', 'min:6', 'max:255'],
      'isManager' => ['nullable', 'boolean'],
    ];
  }

  /**
   * Handle a failed validation attempt.
   *
   * @param Validator $validator
   * @return void
   *
   * @throws ValidationException
   */
  protected function failedValidation($validator)
  {
    throw new HttpResponseException(Error::response($validator->errors()->first()));
  }
}
