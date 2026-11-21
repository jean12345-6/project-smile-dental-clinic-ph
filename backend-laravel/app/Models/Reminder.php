<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\LocalWallClockDates;

class Reminder extends Model
{
    use LocalWallClockDates;

    use HasFactory;

    protected $table = 'reminders';
    protected $primaryKey = 'reminder_id';

    protected $fillable = [
        'appointment_id',
        'type',
        'channel',
        'send_time',
        'send_status',
    ];

    protected $casts = [
        'send_time' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id', 'appointment_id');
    }
}
