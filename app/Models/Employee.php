<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'employee_code',
        'name',
        'surname',
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

