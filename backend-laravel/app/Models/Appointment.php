<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Filters\V1\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Services\V1\WaitlistService;

class Appointment extends Model
{

    protected static function booted(): void
    {
        // Slot freed by a cancel -> offer it to the waitlist
        static::updated(function (Appointment $appointment) {
            if ($appointment->wasChanged('status') && $appointment->status === 'cancelled') {
                app(WaitlistService::class)->offerFor($appointment);
            }
        });

        // Slot freed by a delete -> offer it too
        static::deleted(fn (Appointment $appointment) => app(WaitlistService::class)->offerFor($appointment));
    }
    use HasFactory;

    protected $table = 'appointments';
    protected $primaryKey = 'appointment_id';

    protected $fillable = [
        'patient_id',
        'dentist_id',
        'branch_id',
        'service_id',
        'start_time',
        'end_time',
        'status',
        'risk_flag',
        'booked_via',
        'check_in_time',
        'follow_up_of',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'check_in_time' => 'datetime',
        'risk_flag' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function dentist()
    {
        return $this->belongsTo(Dentist::class, 'dentist_id', 'dentist_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    public function followUpOf()
    {
        return $this->belongsTo(Appointment::class, 'follow_up_of', 'appointment_id');
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class, 'appointment_id');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class, 'appointment_id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'appointment_id');
    }

    public function feedback()
    {
        return $this->hasOne(Feedback::class, 'appointment_id');
    }
        /**
     * Query scope: Appointment::filter($filters) applies the filters sent in the URL.
     */
    public function scopeFilter(Builder $builder, QueryFilter $filters): Builder
    {
        return $filters->apply($builder);
    }
}
