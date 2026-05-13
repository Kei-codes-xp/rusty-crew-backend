<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'label',
        'frequency',
        'start_date',
        'end_date',
        'status',
        'generated_by',
        'generated_at',
        'locked_at',
        'total_gross',
        'total_hours',
        'total_ot',
        'entry_count',
        'notes',
    ];

    protected $casts = [
        'start_date'    => 'date:Y-m-d',
        'end_date'      => 'date:Y-m-d',
        'generated_at'  => 'datetime',
        'locked_at'     => 'datetime',
        'total_gross'   => 'decimal:2',
        'total_hours'   => 'decimal:2',
        'total_ot'      => 'decimal:2',
        'entry_count'   => 'integer',
    ];


    public function entries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(Employee::class, 'generated_by');
    }


    // ── State helpers ─────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }


    public function lock(): void
    {
        if (!$this->isDraft()) {
            throw new \RuntimeException("Only draft periods can be locked.");
        }

        // Recompute aggregates from entries before locking
        $entries = $this->entries;

        $this->update([
            'status'      => 'locked',
            'locked_at'   => now(),
            'total_gross' => $entries->sum('gross_pay'),
            'total_hours' => $entries->sum('total_hours'),
            'total_ot'    => $entries->sum('ot_hours'),
            'entry_count' => $entries->count(),
        ]);

        // Lock all child entries
        $this->entries()->update(['status' => 'locked']);
    }


    public function void(): void
    {
        if ($this->isLocked()) {
            throw new \RuntimeException("Locked periods cannot be voided. Contact admin.");
        }

        $this->update(['status' => 'voided']);
        $this->entries()->update(['status' => 'voided']);
    }
}
