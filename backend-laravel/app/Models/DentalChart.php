<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DentalChart extends Model
{
    use HasFactory;

    protected $table = 'dental_chart';
    protected $primaryKey = 'chart_id';

    protected $fillable = [
        'patient_id',
        'tooth_no',
        'condition',
        'date_updated',
    ];

    protected $casts = [
        'date_updated' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }
}
