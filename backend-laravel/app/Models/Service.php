<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $table = 'services';
    protected $primaryKey = 'service_id';

    protected $fillable = [
        'clinic_id',
        'service_name',
        'duration_min',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id', 'clinic_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }
}
