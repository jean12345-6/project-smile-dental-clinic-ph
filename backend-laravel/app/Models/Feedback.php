<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';
    protected $primaryKey = 'feedback_id';

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'rating',
        'comment',
        'date_submitted',
    ];

    protected $casts = [
        'date_submitted' => 'date',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id', 'appointment_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }
}
