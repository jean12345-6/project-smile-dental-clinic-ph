<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    use HasFactory;

    protected $table = 'clinics';
    protected $primaryKey = 'clinic_id';

    protected $fillable = [
        'clinic_name',
        'logo_path',
        'contact_no',
        'email',
        'status',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class, 'clinic_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'clinic_id');
    }

    public function faqEntries()
    {
        return $this->hasMany(FaqEntry::class, 'clinic_id');
    }

    public function patients()
    {
        return $this->hasMany(Patient::class, 'clinic_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'clinic_id');
    }
}
