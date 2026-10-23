<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory;

    protected $table = 'prescriptions';
    protected $primaryKey = 'prescription_id';

    protected $fillable = [
        'treatment_id',
        'patient_id',
        'dentist_id',
        'medicine',
        'dosage',
        'frequency',
        'duration',
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class, 'treatment_id', 'treatment_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function dentist()
    {
        return $this->belongsTo(Dentist::class, 'dentist_id', 'dentist_id');
    }
}
