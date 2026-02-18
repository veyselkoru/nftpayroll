<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'name', 'key', 'status', 'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];
}
