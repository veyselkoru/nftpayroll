<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'name', 'type', 'status', 'file_path', 'filters', 'downloaded_at', 'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'downloaded_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
