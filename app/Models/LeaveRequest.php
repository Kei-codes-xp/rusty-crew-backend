<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'from',
        'to',
        'reason',
        'type',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'from'        => 'date:Y-m-d',
        'to'          => 'date:Y-m-d',
        'reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }
}
