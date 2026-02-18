<?php

namespace App\Services\Admin;

use App\Models\Admin\AuditLog;
use App\Models\User;

class AuditLogService
{
    public function log(?User $user, string $module, string $action, mixed $subject = null, array $payload = []): AuditLog
    {
        return AuditLog::create([
            'company_id' => $user?->company_id,
            'user_id' => $user?->id,
            'module' => $module,
            'action' => $action,
            'status' => 'success',
            'auditable_type' => is_object($subject) ? $subject::class : null,
            'auditable_id' => is_object($subject) && isset($subject->id) ? $subject->id : null,
            'ip_address' => request()?->ip(),
            'meta' => $payload,
        ]);
    }
}
