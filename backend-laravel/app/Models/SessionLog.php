<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\LocalWallClockDates;

class SessionLog extends Model
{
    use LocalWallClockDates;

    use HasFactory;

    protected $table = 'session_logs';
    protected $primaryKey = 'session_id';

    protected $fillable = [
        'user_id',
        'login_time',
        'logout_time',
        'ip_address',
        'device',
        'end_reason',
    ];

    protected $casts = [
        'login_time' => 'datetime',
        'logout_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
