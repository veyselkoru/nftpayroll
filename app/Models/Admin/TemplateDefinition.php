<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'name', 'type', 'version', 'status', 'body', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];
}
