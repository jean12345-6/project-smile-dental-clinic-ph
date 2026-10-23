<?php

namespace App\Models;

use App\Http\Filters\V1\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $table = 'patients';
    protected $primaryKey = 'patient_id';

    protected $fillable = [
        'user_id',
        'clinic_id',
        'full_name',
        'birthdate',
        'address',
        'contact_no',
        'email',
        'medical_history',
        'no_show_count',
    ];

    protected $casts = [
        'birthdate' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id', 'clinic_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class, 'patient_id');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'patient_id');
    }

    public function dentalChart()
    {
        return $this->hasMany(DentalChart::class, 'patient_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'patient_id');
    }

    public function chatLogs()
    {
        return $this->hasMany(ChatLog::class, 'patient_id');
    }

    public function ocrBatches()
    {
        return $this->hasMany(OcrBatch::class, 'patient_id');
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class, 'patient_id');
    }

    public function waitlist()
    {
        return $this->hasMany(Waitlist::class, 'patient_id');
    }

    /**
     * Query scope: Patient::filter($filters) applies the filters sent in the URL.
     */
    public function scopeFilter(Builder $builder, QueryFilter $filters): Builder
    {
        return $filters->apply($builder);
    }
}