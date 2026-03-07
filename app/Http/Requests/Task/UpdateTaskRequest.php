<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:pending,in_progress,completed'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
