<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dentist extends Model
{
    use HasFactory;

    protected $table = 'dentists';
    protected $primaryKey = 'dentist_id';

    protected $fillable = [
        'user_id',
        'full_name',
        'specialization',
        'license_no',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function schedules()
    {
        return $this->hasMany(DentistSchedule::class, 'dentist_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'dentist_id');
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class, 'dentist_id');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'dentist_id');
    }
}
