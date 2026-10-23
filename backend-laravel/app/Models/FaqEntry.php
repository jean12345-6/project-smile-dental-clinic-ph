<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqEntry extends Model
{
    use HasFactory;

    protected $table = 'faq_entries';
    protected $primaryKey = 'faq_id';

    protected $fillable = [
        'clinic_id',
        'question',
        'answer',
        'category',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id', 'clinic_id');
    }
}
