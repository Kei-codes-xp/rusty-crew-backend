<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KioskScanResult extends Model
{
    protected $fillable = [
        'token',
        'kiosk_device_id',
        'employee_id',
        'employee_name',
        'action',
        'formatted_time',
        'avatar_color',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}