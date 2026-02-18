<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IntegrationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'provider' => ['sometimes', 'required', 'string', 'max:100'],
            'status' => ['sometimes', 'required', 'in:active,inactive,error'],
            'config' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
