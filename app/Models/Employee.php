<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'surname',
        'email',
        'wallet_address',
        'national_id',
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

