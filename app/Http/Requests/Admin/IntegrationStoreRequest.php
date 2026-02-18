<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IntegrationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,error'],
            'config' => ['nullable', 'array'],
        ];
    }
}
