<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemHealthSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'service', 'status', 'latency_ms', 'error_rate', 'uptime_percent', 'incident_count', 'captured_at', 'meta',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'meta' => 'array',
    ];
}
