<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BulkOperationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'name', 'type', 'status', 'total_items', 'processed_items', 'failed_items', 'payload', 'results', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'results' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
