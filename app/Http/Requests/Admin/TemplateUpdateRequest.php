<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TemplateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'max:100'],
            'body' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:draft,published,archived'],
        ];
    }
}
