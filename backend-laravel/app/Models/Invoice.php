<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';
    protected $primaryKey = 'invoice_id';

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'total_amount',
        'balance',
        'date_issued',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'date_issued' => 'date',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id', 'appointment_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }
}
