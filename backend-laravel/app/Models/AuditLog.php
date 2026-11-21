<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\LocalWallClockDates;

class AuditLog extends Model
{
    use LocalWallClockDates;

    use HasFactory;

    protected $table = 'audit_logs';
    protected $primaryKey = 'audit_id';

    protected $fillable = [
        'user_id',
        'action',
        'table_name',
        'record_id',
        'old_value',
        'new_value',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
