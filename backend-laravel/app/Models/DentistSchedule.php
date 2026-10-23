<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DentistSchedule extends Model
{
    use HasFactory;

    protected $table = 'dentist_schedules';
    protected $primaryKey = 'schedule_id';

    protected $fillable = [
        'dentist_id',
        'branch_id',
        'day_of_week',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'leave_date',
    ];

    protected $casts = [
        'leave_date' => 'date',
    ];

    public function dentist()
    {
        return $this->belongsTo(Dentist::class, 'dentist_id', 'dentist_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }
}
