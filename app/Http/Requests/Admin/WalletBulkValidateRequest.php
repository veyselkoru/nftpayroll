<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WalletBulkValidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.wallet_address' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{40}$/'],
            'items.*.network' => ['nullable', 'string', 'max:50'],
        ];
    }
}
