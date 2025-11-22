<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'period_start',
        'period_end',
        'gross_salary',
        'net_salary',
        'encrypted_payload',
        'ipfs_cid',
        'status',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    
    public function nftMint()
    {
        return $this->hasOne(\App\Models\NftMint::class, 'payroll_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }


    

}