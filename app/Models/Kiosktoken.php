<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KioskToken extends Model
{
    protected $fillable = [
        'token',
        'signature',
        'kiosk_device_id',
        'issued_at',
        'expires_at',
        'used_at',
        'used_by',
        'action',
    ];

    protected $casts = [
        'issued_at'  => 'datetime',
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function usedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'used_by');
    }
}