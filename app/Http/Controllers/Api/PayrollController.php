<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollEntryResource;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Admin/Manager payroll controller.
 *
 * All computation is delegated to PayrollService.
 * This controller only handles HTTP concerns: validation, auth, response.
 *
 * Endpoints:
 *   POST   /api/payroll/generate               → generate a new payroll period
 *   GET    /api/payroll/periods                → list all periods (paginated)
 *   GET    /api/payroll/periods/{period}       → single period detail
 *   PATCH  /api/payroll/periods/{period}/lock  → lock a draft period
 *   PATCH  /api/payroll/periods/{period}/void  → void a draft period
 *   GET    /api/payroll/{period}/entries       → all entries for a period
 *   GET    /api/payroll/payslip/{employee}/{period} → single employee payslip
 *   GET    /api/payroll/payslip/{employee}/{period}/pdf → download PDF
 */
class PayrollController extends Controller
{
    public function __construct(private PayrollService $service) {}

    // =========================================================================
    // POST /api/payroll/generate
    // Body: { startDate, endDate, frequency, notes? }
    // =========================================================================
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'startDate' => 'required|date',
            'endDate'   => 'required|date|after_or_equal:startDate',
            'frequency' => 'required|in:weekly,semi_monthly,monthly',
            'notes'     => 'nullable|string|max:500',
        ]);

        try {
            $period = $this->service->generate(
                startDate:   $data['startDate'],
                endDate:     $data['endDate'],
                frequency:   $data['frequency'],
                generatedBy: $request->user()->id,
                notes:       $data['notes'] ?? null,
            );

            return response()->json([
                'message' => 'Payroll generated successfully.',
                'period'  => new PayrollPeriodResource($period),
                'entries' => PayrollEntryResource::collection($period->entries),
            ], 201);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // GET /api/payroll/periods
    // Query: ?frequency=weekly&status=draft&page=1
    // =========================================================================
    public function periods(Request $request): JsonResponse
    {
        $query = PayrollPeriod::orderByDesc('start_date');

        if ($request->filled('frequency')) {
            $query->where('frequency', $request->frequency);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $periods = $query->paginate(15);

        return response()->json([
            'data' => PayrollPeriodResource::collection($periods->items()),
            'meta' => [
                'total'        => $periods->total(),
                'per_page'     => $periods->perPage(),
                'current_page' => $periods->currentPage(),
                'last_page'    => $periods->lastPage(),
            ],
        ]);
    }

    // =========================================================================
    // GET /api/payroll/periods/{period}
    // =========================================================================
    public function showPeriod(PayrollPeriod $period): JsonResponse
    {
        Log::info(''. $period);
        return response()->json(new PayrollPeriodResource($period));
    }

    // =========================================================================
    // PATCH /api/payroll/periods/{period}/lock
    // =========================================================================
    public function lock(Request $request, PayrollPeriod $period): JsonResponse
    {
        try {
            $locked = $this->service->lock($period, $request->user()->id);
            return response()->json([
                'message' => 'Payroll period locked successfully.',
                'period'  => new PayrollPeriodResource($locked),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // PATCH /api/payroll/periods/{period}/void
    // =========================================================================
    public function void(Request $request, PayrollPeriod $period): JsonResponse
    {
        try {
            $voided = $this->service->void($period, $request->user()->id);
            return response()->json([
                'message' => 'Payroll period voided.',
                'period'  => new PayrollPeriodResource($voided),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // GET /api/payroll/{period}/entries
    // Returns all PayrollEntry rows for the given period.
    // =========================================================================
    public function entries(PayrollPeriod $period): JsonResponse
    {
        $entries = $period->entries()
                          ->orderBy('employee_first_name')
                          ->get();

        return response()->json([
            'period'  => new PayrollPeriodResource($period),
            'entries' => PayrollEntryResource::collection($entries),
        ]);
    }

    // =========================================================================
    // GET /api/payroll/payslip/{employee}/{period}
    // Returns the frozen payslip for one employee in one period.
    // =========================================================================
    public function payslip(Employee $employee, PayrollPeriod $period): JsonResponse
    {
        $entry = $this->service->getEntry($period->id, $employee->id);

        if (!$entry) {
            return response()->json([
                'message' => "No payroll entry found for this employee in period #{$period->id}.",
            ], 404);
        }

        return response()->json([
            'period' => new PayrollPeriodResource($period),
            'entry'  => new PayrollEntryResource($entry),
        ]);
    }

    // =========================================================================
    // GET /api/payroll/payslip/{employee}/{period}/pdf
    // Returns a downloadable payslip.
    // Install: composer require barryvdh/laravel-dompdf
    // =========================================================================
    public function payslipPdf(Employee $employee, PayrollPeriod $period)
    {
        $entry = $this->service->getEntry($period->id, $employee->id);

        if (!$entry) {
            return response()->json(['message' => 'Payslip not found.'], 404);
        }

        $html = view('payslip', [
            'period' => $period,
            'entry'  => $entry,
        ])->render();

        $filename = "payslip_{$employee->id}_{$period->start_date}_{$period->end_date}.html";

        // Swap this block for DomPDF in production:
        // return \PDF::loadHTML($html)->download($filename . '.pdf');

        return response($html, 200, [
            'Content-Type'        => 'text/html',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }


    
}