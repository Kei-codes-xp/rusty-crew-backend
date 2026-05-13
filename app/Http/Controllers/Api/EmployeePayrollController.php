<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollEntryResource;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Employee-scoped payroll controller (read-only).
 *
 * Employees can only see their OWN entries.
 * All values come from the persisted PayrollEntry snapshot —
 * no computation happens here.
 *
 * Endpoints (all under /api/employee):
 *   GET /api/employee/payroll/periods          → list periods that include me
 *   GET /api/employee/payroll/{period}         → my entry for one period
 *   GET /api/employee/payroll/{period}/payslip → payslip data for one period
 *   GET /api/employee/payroll/{period}/pdf     → download payslip
 */
class EmployeePayrollController extends Controller
{
    public function __construct(private PayrollService $service) {}

    // ── List periods that have an entry for this employee ─────────────────────
    // GET /api/employee/payroll/periods
    public function periods(Request $request): JsonResponse
    {
        $emp = $request->user();

        $periods = PayrollPeriod::whereHas('entries', fn ($q) => $q->where('employee_id', $emp->id))
                                ->where('status', '!=', 'voided')
                                ->orderByDesc('start_date')
                                ->paginate(12);

        return response()->json([
            'data' => PayrollPeriodResource::collection($periods->items()),
            'meta' => [
                'total'        => $periods->total(),
                'current_page' => $periods->currentPage(),
                'last_page'    => $periods->lastPage(),
            ],
        ]);
    }

    // ── My entry for a single period ──────────────────────────────────────────
    // GET /api/employee/payroll/{period}
    public function show(Request $request, PayrollPeriod $period): JsonResponse
    {
        $emp   = $request->user();
        $entry = $this->service->getEntry($period->id, $emp->id);

        if (!$entry) {
            return response()->json([
                'message' => 'No payroll record found for this period.',
            ], 404);
        }

        return response()->json([
            'period' => new PayrollPeriodResource($period),
            'entry'  => new PayrollEntryResource($entry),
        ]);
    }

    // ── Payslip alias (same as show, named for clarity) ───────────────────────
    // GET /api/employee/payroll/{period}/payslip
    public function payslip(Request $request, PayrollPeriod $period): JsonResponse
    {
        return $this->show($request, $period);
    }

    // ── PDF download ──────────────────────────────────────────────────────────
    // GET /api/employee/payroll/{period}/pdf
    public function pdf(Request $request, PayrollPeriod $period)
    {
        $emp   = $request->user();
        $entry = $this->service->getEntry($period->id, $emp->id);

        if (!$entry) {
            return response()->json(['message' => 'Payslip not found.'], 404);
        }

        $html = view('payslip', [
            'period' => $period,
            'entry'  => $entry,
        ])->render();

        $filename = "payslip_{$emp->id}_{$period->start_date}_{$period->end_date}.html";

        return response($html, 200, [
            'Content-Type'        => 'text/html',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}