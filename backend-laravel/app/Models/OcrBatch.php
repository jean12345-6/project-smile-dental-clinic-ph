<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OcrBatch extends Model
{
    use HasFactory;

    protected $table = 'ocr_batches';
    protected $primaryKey = 'batch_id';

    protected $fillable = [
        'patient_id',
        'image_path',
        'extracted_text',
        'verified_by',
        'verify_status',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by', 'id');
    }
}
