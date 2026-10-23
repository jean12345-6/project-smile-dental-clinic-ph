<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $table = 'branches';
    protected $primaryKey = 'branch_id';

    protected $fillable = [
        'clinic_id',
        'branch_name',
        'address',
        'contact_no',
        'open_time',
        'close_time',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id', 'clinic_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'branch_id');
    }

    public function dentistSchedules()
    {
        return $this->hasMany(DentistSchedule::class, 'branch_id');
    }

    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'branch_id');
    }
}
