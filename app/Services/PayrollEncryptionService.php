<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class PayrollEncryptionService
{
    public function encryptPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        return Crypt::encryptString($json);
    }

    public function decryptPayload(string $encrypted): array
    {
        $json = Crypt::decryptString($encrypted);
        return json_decode($json, true) ?? [];
    }
}
