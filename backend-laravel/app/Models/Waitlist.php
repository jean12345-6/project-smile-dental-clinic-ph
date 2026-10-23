<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Waitlist extends Model
{
    use HasFactory;

    protected $table = 'waitlist';
    protected $primaryKey = 'waitlist_id';

    protected $fillable = [
        'patient_id',
        'service_id',
        'branch_id',
        'dentist_id',
        'preferred_date',
        'status',
        'offered_at',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'offered_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function dentist()
    {
        return $this->belongsTo(Dentist::class, 'dentist_id', 'dentist_id');
    }
}
