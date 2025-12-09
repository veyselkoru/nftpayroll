<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'type',
        'tax_number',
        'registration_number',
        'country',
        'city',
        'address',
        'contact_phone',
        'contact_email',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
