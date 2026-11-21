<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\LocalWallClockDates;

class Payment extends Model
{
    use LocalWallClockDates;

    use HasFactory;

    protected $table = 'payments';
    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'invoice_id',
        'amount',
        'method',
        'paid_at',
        'received_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by', 'id');
    }
}
