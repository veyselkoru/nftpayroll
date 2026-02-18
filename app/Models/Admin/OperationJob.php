<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'triggered_by_user_id', 'employee_id', 'payroll_id', 'nft_mint_id',
        'name', 'type', 'status', 'attempts', 'max_attempts', 'retry_count', 'payload',
        'error_message', 'tx_hash', 'token_id', 'duration_ms', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
