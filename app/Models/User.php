<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // Sanctum için HasApiTokens önemli

    public const ROLE_COMPANY_OWNER = 'company_owner';
    public const ROLE_COMPANY_MANAGER = 'company_manager';
    public const ROLE_EMPLOYEE = 'employee';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'company_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function normalizedRole(): ?string
    {
        return match ($this->role) {
            'owner' => self::ROLE_COMPANY_OWNER, // legacy
            'admin' => self::ROLE_COMPANY_MANAGER, // legacy
            default => $this->role,
        };
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
