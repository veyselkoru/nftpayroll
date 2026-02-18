<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'name', 'provider', 'status', 'config', 'last_test_at', 'last_test_status',
    ];

    protected $casts = [
        'config' => 'array',
        'last_test_at' => 'datetime',
    ];
}
