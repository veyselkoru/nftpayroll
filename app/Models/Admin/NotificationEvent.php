<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'title', 'body', 'channel', 'status', 'is_read', 'read_at', 'payload',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'payload' => 'array',
    ];
}
