<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WalletValidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wallet_address' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{40}$/'],
            'network' => ['nullable', 'string', 'max:50'],
        ];
    }
}
