<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_group_id',
        'company_id',
        'employee_id',
        'period_start',
        'period_end',
        'payment_date',
        'currency',
        'gross_salary',
        'net_salary',
        'bonus',
        'deductions_total',
        'employer_sign_name',
        'employer_sign_title',
        'batch_id',
        'external_batch_ref',
        'external_ref',
        'status',
        'encrypted_payload',
        'ipfs_cid',
    ];

    protected $casts = [
        'period_start'     => 'date',
        'period_end'       => 'date',
        'payment_date'     => 'date',
        'gross_salary'     => 'decimal:2',
        'net_salary'       => 'decimal:2',
        'bonus'            => 'decimal:2',
        'deductions_total' => 'decimal:2',
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