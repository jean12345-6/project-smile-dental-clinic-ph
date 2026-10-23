<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatLog extends Model
{
    use HasFactory;

    protected $table = 'chat_logs';
    protected $primaryKey = 'chat_id';

    protected $fillable = [
        'patient_id',
        'message_in',
        'message_out',
        'intent',
        'escalated',
        'timestamp',
    ];

    protected $casts = [
        'escalated' => 'boolean',
        'timestamp' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }
}
