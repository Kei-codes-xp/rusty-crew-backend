<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'date',
        'type',
    ];
    protected $casts = [
        'date' => 'date:Y-m-d',
    ];
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
