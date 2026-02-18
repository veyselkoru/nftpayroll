<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'employee_id', 'payroll_id', 'user_id', 'title', 'type', 'policy_key',
        'status', 'payload', 'approved_by', 'approved_at', 'rejected_at', 'rejection_reason',
    ];

    protected $casts = [
        'payload' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];
}
