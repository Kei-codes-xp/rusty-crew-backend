<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Maps PayrollEntry model → frontend PayrollEntry interface:
 * {
 *   id, periodId, employeeId,
 *   firstName, lastName, role,
 *   isSalaried, hourlyRate, monthlySalary,
 *   totalHours, otHours,
 *   base, otPay, deductions, gross, net,
 *   status, logs[], timeLogIds
 * }
 *
 * IMPORTANT: This resource uses SNAPSHOT values (hourly_rate_snapshot,
 * monthly_salary_snapshot) — NOT the live employee record.
 * This ensures payslips remain correct even after employee rate changes.
 */
class PayrollEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ──────────────────────────────────────────────────────
            'id'       => $this->id,
            'periodId' => $this->payroll_period_id,

            // ── Employee (snapshot — not live) ────────────────────────────────
            'employeeId'   => $this->employee_id,
            'firstName'    => $this->employee_first_name,
            'lastName'     => $this->employee_last_name,
            'role'         => $this->employee_role,

            // ── Pay settings snapshot ─────────────────────────────────────────
            'isSalaried'    => (bool)  $this->is_salaried,
            'hourlyRate'    => (float) $this->hourly_rate_snapshot,
            'monthlySalary' => (float) $this->monthly_salary_snapshot,

            // ── Time totals ───────────────────────────────────────────────────
            'totalHours' => (float) $this->total_hours,
            'otHours'    => (float) $this->ot_hours,

            // ── Pay breakdown ─────────────────────────────────────────────────
            'base'       => (float) $this->base_pay,
            'otPay'      => (float) $this->ot_pay,
            'deductions' => (float) $this->deductions,
            'gross'      => (float) $this->gross_pay,
            'net'        => (float) $this->net_pay,

            // ── Status ────────────────────────────────────────────────────────
            'status'  => $this->status,
            'remarks' => $this->remarks,

            // ── Daily breakdown (maps to PayrollDayLog[]) ─────────────────────
            'logs' => $this->daily_breakdown ?? [],

            // ── Audit trail ───────────────────────────────────────────────────
            'timeLogIds' => $this->time_log_ids ?? [],
        ];
    }
}