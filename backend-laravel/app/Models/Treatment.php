<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    use HasFactory;

    protected $table = 'treatments';
    protected $primaryKey = 'treatment_id';

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'dentist_id',
        'tooth_no',
        'procedure_name',
        'notes',
        'treatment_date',
        'cost',
    ];

    protected $casts = [
        'treatment_date' => 'date',
        'cost' => 'decimal:2',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id', 'appointment_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function dentist()
    {
        return $this->belongsTo(Dentist::class, 'dentist_id', 'dentist_id');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'treatment_id');
    }
}
