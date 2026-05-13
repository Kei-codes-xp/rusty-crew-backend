<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\TimeLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    private const OT_MULTIPLIER          = 1.25;
    private const STANDARD_HOURS_PER_DAY = 8.0;

    public function generate(
        string  $startDate,
        string  $endDate,
        string  $frequency,
        int     $generatedBy,
        ?string $notes = null
    ): PayrollPeriod {
        $start = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);

        return DB::transaction(function () use ($start, $end, $frequency, $generatedBy, $notes) {

            // ── Check for existing period ─────────────────────────────────────
            $existing = PayrollPeriod::where('frequency',  $frequency)
                                     ->where('start_date', $start->toDateString())
                                     ->where('end_date',   $end->toDateString())
                                     ->first();

            if ($existing) {
                if ($existing->isLocked()) {
                    throw new \RuntimeException(
                        "A locked payroll period already exists for this date range."
                    );
                }
                if ($existing->isVoided()) {
                    throw new \RuntimeException(
                        "This date range has a voided period."
                    );
                }
                $existing->entries()->delete();
                $period = $existing;
            } else {
                $period = PayrollPeriod::create([
                    'label'        => $this->buildLabel($start, $end, $frequency),
                    'frequency'    => $frequency,
                    'start_date'   => $start->toDateString(),
                    'end_date'     => $end->toDateString(),
                    'status'       => 'draft',
                    'generated_by' => $generatedBy,
                    'generated_at' => now(),
                    'notes'        => $notes,
                ]);
            }

            // ── FIX 1: Use Employee::query() — NEVER DB::table() ─────────────
            // DB::table('employees') returns stdClass objects.
            // Employee::query()->get() returns Collection<Employee> Eloquent models.
            // buildEntry() type-hints Employee — passing stdClass throws the error seen.
            //
            /** @var Collection<int, Employee> $employees */
            $employees = Employee::query()
                ->where('status', 'Active')
                ->get();

            // Hard assertion — fail loudly if models are wrong type
            $employees->each(function ($emp) {
                if (!$emp instanceof Employee) {
                    throw new \RuntimeException(
                        'Expected Eloquent Employee model, got ' . get_class($emp) . '. ' .
                        'Do not use DB::table() or ->toBase() for payroll queries.'
                    );
                }
            });

            // ── FIX 2: Use TimeLog::query() — NEVER DB::table() ──────────────
            // DB::table('time_logs') returns stdClass with raw string dates.
            // The cast 'date' => 'date:Y-m-d' in TimeLog ONLY runs on Eloquent models.
            // When $log->date is a raw string, calling ->format() throws
            // "Call to unknown method: string::format()".
            //
            /** @var Collection<int, TimeLog> $allLogs */
            $allLogs = TimeLog::query()
                ->whereBetween('date', [
                    $start->toDateString(),
                    $end->toDateString(),
                ])
                ->whereIn('employee_id', $employees->pluck('id'))
                ->whereNotNull('clock_out')
                ->get()                    // Collection<TimeLog> — casts applied here
                ->groupBy('employee_id'); // group for O(1) lookup per employee

            // ── Compute and persist one entry per employee ────────────────────
            $totalGross = 0.0;
            $totalHours = 0.0;
            $totalOT    = 0.0;

            foreach ($employees as $emp) {
                /** @var Collection<int, TimeLog> $logs */
                $logs = $allLogs->get($emp->id, collect());

                $entry = $this->buildEntry($period, $emp, $logs, $start, $end, $frequency);

                $totalGross += (float) $entry->gross_pay;
                $totalHours += (float) $entry->total_hours;
                $totalOT    += (float) $entry->ot_hours;
            }

            // ── Update period aggregates ──────────────────────────────────────
            $period->update([
                'generated_at' => now(),
                'generated_by' => $generatedBy,
                'total_gross'  => round($totalGross, 2),
                'total_hours'  => round($totalHours, 2),
                'total_ot'     => round($totalOT,    2),
                'entry_count'  => $employees->count(),
            ]);

            Log::info('Payroll generated', [
                'period_id'   => $period->id,
                'frequency'   => $frequency,
                'from'        => $start->toDateString(),
                'to'          => $end->toDateString(),
                'employees'   => $employees->count(),
                'total_gross' => $totalGross,
            ]);

            return $period->load('entries');
        });
    }

    public function lock(PayrollPeriod $period, int $lockedBy): PayrollPeriod
    {
        if (!$period->isDraft()) {
            throw new \RuntimeException(
                "Only draft periods can be locked. Current status: {$period->status}"
            );
        }

        $period->lock();

        Log::info('Payroll period locked', [
            'period_id' => $period->id,
            'locked_by' => $lockedBy,
        ]);

        return $period->fresh();
    }

    public function void(PayrollPeriod $period, int $voidedBy): PayrollPeriod
    {
        $period->void();

        Log::info('Payroll period voided', [
            'period_id' => $period->id,
            'voided_by' => $voidedBy,
        ]);

        return $period->fresh();
    }

    public function getEntry(int $periodId, int $employeeId): ?PayrollEntry
    {
        return PayrollEntry::where('payroll_period_id', $periodId)
                           ->where('employee_id', $employeeId)
                           ->first();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * @param  Employee                  $emp   ← Eloquent model — NOT stdClass
     * @param  Collection<int, TimeLog>  $logs  ← Eloquent models — casts applied
     */
    private function buildEntry(
        PayrollPeriod $period,
        Employee      $emp,      // type-hint enforces correct type at call site
        Collection    $logs,
        Carbon        $start,
        Carbon        $end,
        string        $frequency
    ): PayrollEntry {

        $totalHours = round((float) $logs->sum('hours_worked'), 2);
        $otHours    = round((float) $logs->sum('overtime'),     2);

        $basePay = $emp->is_salaried
            ? $this->proratedSalary((float) $emp->monthly_salary, $frequency, $start, $end)
            : $totalHours * (float) $emp->hourly_rate;

        $otPay      = $otHours * (float) $emp->hourly_rate * self::OT_MULTIPLIER;
        $deductions = $this->computeDeductions($emp, $basePay + $otPay);
        $grossPay   = round($basePay + $otPay, 2);
        $netPay     = round($grossPay - $deductions, 2);

        // Daily breakdown — uses resolveDate() to safely handle both
        // Carbon instances (normal Eloquent) and raw strings (defensive fallback)
        $dailyBreakdown = $logs
            ->map(function (TimeLog $log) {
                return [
                    // FIX 2: resolveDate() handles Carbon AND raw strings safely
                    'date'        => $this->resolveDate($log->date),
                    'hoursWorked' => (float) $log->hours_worked,
                    'overtime'    => (float) $log->overtime,
                    'status'      => $log->status,
                    'clockIn'     => $log->clock_in,
                    'clockOut'    => $log->clock_out,
                ];
            })
            ->sortBy('date')
            ->values()
            ->toArray();

        return PayrollEntry::create([
            'payroll_period_id'       => $period->id,
            'employee_id'             => $emp->id,
            'employee_first_name'     => $emp->first_name,
            'employee_last_name'      => $emp->last_name,
            'employee_role'           => $emp->role,
            'is_salaried'             => $emp->is_salaried,
            'hourly_rate_snapshot'    => (float) $emp->hourly_rate,
            'monthly_salary_snapshot' => (float) $emp->monthly_salary,
            'total_hours'             => $totalHours,
            'ot_hours'                => $otHours,
            'base_pay'                => round($basePay,    2),
            'ot_pay'                  => round($otPay,      2),
            'deductions'              => round($deductions, 2),
            'gross_pay'               => $grossPay,
            'net_pay'                 => $netPay,
            'time_log_ids'            => $logs->pluck('id')->toArray(),
            'daily_breakdown'         => $dailyBreakdown,
            'status'                  => 'draft',
        ]);
    }

    /**
     * Safely resolve a date to a Y-m-d string regardless of whether it
     * arrives as a Carbon instance (Eloquent cast) or a raw string (DB::table).
     *
     * This is the defensive fix for "Call to unknown method: date::format()".
     * The real fix is always using Eloquent models, but this guard means the
     * error will never surface even if something upstream changes.
     */
    private function resolveDate(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        if (is_string($date)) {
            return Carbon::parse($date)->format('Y-m-d');
        }

        // Should never reach here with correct Eloquent usage
        return (string) $date;
    }

    private function proratedSalary(
        float  $monthlySalary,
        string $frequency,
        Carbon $start,
        Carbon $end
    ): float {
        return match ($frequency) {
            'weekly'       => round($monthlySalary / 4.33, 2),
            'semi_monthly' => round($monthlySalary / 2,    2),
            'monthly'      => $monthlySalary,
            default        => $monthlySalary,
        };
    }

    private function computeDeductions(Employee $emp, float $grossPay): float
    {
        // Extend here for SSS, PhilHealth, Pag-IBIG
        return 0.00;
    }

    private function buildLabel(Carbon $start, Carbon $end, string $frequency): string
    {
        $fmt  = fn (Carbon $d) => $d->format('M j');
        $year = $end->year;

        return match ($frequency) {
            'weekly'       => "Week of {$fmt($start)} – {$fmt($end)}, {$year}",
            'semi_monthly' => "Semi-monthly {$fmt($start)} – {$fmt($end)}, {$year}",
            'monthly'      => $start->format('F Y'),
            default        => "{$fmt($start)} – {$fmt($end)}, {$year}",
        };
    }
}