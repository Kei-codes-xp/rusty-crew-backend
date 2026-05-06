<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSwap extends Model
{
    use HasFactory;


    protected $fillable = [
        'requester_id',
        'target_id',
        'date',
        'shift_type',
        'status',
        'note',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];
    public function requester()
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    public function target()
    {
        return $this->belongsTo(Employee::class, 'target_id');
    }
}
