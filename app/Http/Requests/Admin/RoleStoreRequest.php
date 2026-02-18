<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RoleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'permissions' => ['nullable', 'array'],
        ];
    }
}
