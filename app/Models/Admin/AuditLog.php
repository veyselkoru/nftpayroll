<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'module', 'action', 'status', 'auditable_type', 'auditable_id', 'ip_address', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
