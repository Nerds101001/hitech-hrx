<?php

namespace App\Http\Requests\Api\Expense;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateExpenseRequest extends FormRequest
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
      'date' => 'required|date',
      'items' => 'required|array|min:1',
      'items.*.expenseTypeId' => 'required|exists:expense_types,id',
      'items.*.amount' => 'required|numeric|min:1',
      'items.*.notes' => 'nullable|string|max:255'
    ];
  }
}
