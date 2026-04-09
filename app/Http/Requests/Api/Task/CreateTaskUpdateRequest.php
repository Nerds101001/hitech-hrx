<?php

namespace App\Http\Requests\Api\Task;

use App\Enums\TaskUpdateType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTaskUpdateRequest extends FormRequest
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
      'taskId' => 'required|exists:tasks,id',
      'type' => ['required', Rule::in(array_column(TaskUpdateType::cases(), 'values'))],
      'message' => 'required|string|min:10|max:300',
      'file' => 'nullable|file|mimes:png,jpg,jpeg|max:10024',
    ];
  }
}
