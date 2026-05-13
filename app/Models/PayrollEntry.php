<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'employee_first_name',
        'employee_last_name',
        'employee_role',
        'is_salaried',
        'hourly_rate_snapshot',
        'monthly_salary_snapshot',
        'total_hours',
        'ot_hours',
        'base_pay',
        'ot_pay',
        'deductions',
        'gross_pay',
        'net_pay',
        'time_log_ids',
        'daily_breakdown',
        'status',
        'remarks',
    ];

    protected $casts = [
        'is_salaried'             => 'boolean',
        'hourly_rate_snapshot'    => 'decimal:2',
        'monthly_salary_snapshot' => 'decimal:2',
        'total_hours'             => 'decimal:2',
        'ot_hours'                => 'decimal:2',
        'base_pay'                => 'decimal:2',
        'ot_pay'                  => 'decimal:2',
        'deductions'              => 'decimal:2',
        'gross_pay'               => 'decimal:2',
        'net_pay'                 => 'decimal:2',
        'time_log_ids'            => 'array',
        'daily_breakdown'         => 'array',
    ];


    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }


        // ── Guards ────────────────────────────────────────────────────────────────

    /**
     * Prevent any modification to a locked entry.
     * Called in PayrollService before any update.
     */
    public function assertMutable(): void
    {
        if ($this->status === 'locked') {
            throw new \RuntimeException(
                "PayrollEntry #{$this->id} is locked and cannot be modified."
            );
        }
    }
}
