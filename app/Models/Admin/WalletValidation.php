<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'wallet_address', 'network', 'status', 'message', 'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];
}
