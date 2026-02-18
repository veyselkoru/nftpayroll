<?php

namespace App\Models;

use App\Models\Admin\WalletValidation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;

class Employee extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (Employee $employee): void {
            if (! Schema::hasTable('wallet_validations')) {
                return;
            }

            $walletAddress = (string) ($employee->wallet_address ?? '');
            $isValid = (bool) preg_match('/^0x[0-9a-fA-F]{40}$/', $walletAddress);

            WalletValidation::create([
                'company_id' => $employee->company_id,
                'user_id' => null,
                'wallet_address' => $walletAddress !== '' ? $walletAddress : 'invalid',
                'network' => 'sepolia',
                'status' => $isValid ? 'valid' : 'invalid',
                'message' => $isValid ? 'Wallet validated on employee save' : 'Invalid wallet on employee save',
                'checked_at' => now(),
            ]);
        });
    }

    protected $fillable = [
        'company_id',
        'employee_code',
        'name',
        'surname',
        'email',
        'national_id',
        'tc_no',
        'position',
        'department',
        'start_date',
        'status',
        'wallet_address',
    ];

    protected $casts = [
        'tc_no' => 'encrypted',
        'start_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function nftMints()
    {
        return $this->hasManyThrough(
            \App\Models\NftMint::class,
            \App\Models\Payroll::class,
            'employee_id', // Payroll tablosundaki foreign key
            'payroll_id',  // NftMint tablosundaki foreign key
            'id',          // Employee tablosundaki local key
            'id'           // Payroll tablosundaki local key
        );
    }

}
