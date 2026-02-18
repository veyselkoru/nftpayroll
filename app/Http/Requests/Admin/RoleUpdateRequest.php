<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RoleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'key' => ['sometimes', 'required', 'string', 'max:100'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
            'permissions' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
