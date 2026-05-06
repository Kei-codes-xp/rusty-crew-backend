<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\TimeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    // GET /api/payroll/weekly?from=YYYY-MM-DD&to=YYYY-MM-DD
    // Returns payroll data per employee.
    // Mirrors frontend computePayroll(emp, totalHours, otHours):
    //   base  = isSalaried ? monthlySalary : totalHours * hourlyRate
    //   otPay = otHours * hourlyRate * 1.25
    //   gross = base + otPay
    public function weekly(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $employees = Employee::where('status', 'Active')
                             ->orderBy('first_name')
                             ->get();

        $result = $employees->map(function (Employee $emp) use ($request) {
            $logs = TimeLog::where('employee_id', $emp->id)
                           ->whereBetween('date', [$request->from, $request->to])
                           ->get();

            $totalHours = (float) $logs->sum('hours_worked');
            $otHours    = (float) $logs->sum('overtime');

            // Mirrors computePayroll() in utils/employee.ts
            $base  = $emp->is_salaried ? (float)$emp->monthly_salary : $totalHours * (float)$emp->hourly_rate;
            $otPay = $otHours * (float)$emp->hourly_rate * 1.25;
            $gross = $base + $otPay;

            return [
                'id'           => $emp->id,
                'firstName'    => $emp->first_name,
                'lastName'     => $emp->last_name,
                'role'         => $emp->role,
                'hourlyRate'   => (float) $emp->hourly_rate,
                'isSalaried'   => (bool)  $emp->is_salaried,
                'monthlySalary'=> (float) $emp->monthly_salary,
                'totalHours'   => round($totalHours, 2),
                'otHours'      => round($otHours,    2),
                'base'         => round($base,   2),
                'otPay'        => round($otPay,  2),
                'gross'        => round($gross,  2),
                // Per-day breakdown so frontend can render daily columns
                'logs'         => $logs->map(fn ($l) => [
                    'date'        => $l->date->format('Y-m-d'),
                    'hoursWorked' => (float) $l->hours_worked,
                    'overtime'    => (float) $l->overtime,
                    'status'      => $l->status,
                ]),
            ];
        });

        return response()->json([
            'from'        => $request->from,
            'to'          => $request->to,
            'employees'   => $result,
            'totalGross'  => round($result->sum('gross'),      2),
            'totalHours'  => round($result->sum('totalHours'), 2),
            'totalOT'     => round($result->sum('otHours'),    2),
        ]);
    }

    // GET /api/payroll/payslip/{id}?from=YYYY-MM-DD&to=YYYY-MM-DD
    // Returns payslip data for a single employee (the "View Payslip" modal)
    public function payslip(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $logs = TimeLog::where('employee_id', $employee->id)
                       ->whereBetween('date', [$request->from, $request->to])
                       ->get();

        $totalHours = (float) $logs->sum('hours_worked');
        $otHours    = (float) $logs->sum('overtime');
        $base       = $employee->is_salaried ? (float)$employee->monthly_salary : $totalHours * (float)$employee->hourly_rate;
        $otPay      = $otHours * (float)$employee->hourly_rate * 1.25;

        return response()->json([
            'employee'    => new EmployeeResource($employee),
            'period'      => ['from' => $request->from, 'to' => $request->to],
            'totalHours'  => round($totalHours, 2),
            'otHours'     => round($otHours,    2),
            'base'        => round($base,   2),
            'otPay'       => round($otPay,  2),
            'gross'       => round($base + $otPay, 2),
        ]);
    }
}