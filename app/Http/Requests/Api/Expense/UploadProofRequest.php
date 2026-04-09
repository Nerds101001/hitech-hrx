<?php

namespace App\Http\Requests\Api\Expense;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadProofRequest extends FormRequest
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
      'file' => 'required|file|mimes:png,jpg,jpeg|max:4096',
      'id' => 'required|exists:expense_request_items,id',
    ];
  }
}
