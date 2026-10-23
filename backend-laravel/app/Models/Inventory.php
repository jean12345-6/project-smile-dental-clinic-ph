<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';
    protected $primaryKey = 'item_id';

    protected $fillable = [
        'branch_id',
        'item_name',
        'quantity',
        'unit',
        'reorder_level',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }
}
