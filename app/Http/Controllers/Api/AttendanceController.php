<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeLogResource;
use App\Models\Employee;
use App\Models\TimeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    // GET /api/attendance?date=YYYY-MM-DD
    // Returns all time logs for a given date (defaults to today)
    // Maps to frontend todayLogs / timeLogs used in DashboardPage + AttendancePage
    public function index(Request $request): JsonResponse
    {
        $date = $request->get('date', now()->toDateString());

        $logs = TimeLog::with('employee')
                       ->where('date', $date)
                       ->orderBy('clock_in')
                       ->get();

        return response()->json(TimeLogResource::collection($logs));
    }

    // GET /api/attendance/range?from=YYYY-MM-DD&to=YYYY-MM-DD
    // Returns logs for a date range — used by payroll weekly computation
    public function range(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $logs = TimeLog::whereBetween('date', [$request->from, $request->to])
                       ->orderBy('date')
                       ->orderBy('employee_id')
                       ->get();

        return response()->json(TimeLogResource::collection($logs));
    }

    // POST /api/attendance/manual
    // Manual time entry — matches frontend manualRecord(employeeId, time, type)
    // Body: { employeeId, time, type: 'in'|'out' }
    public function manual(Request $request): JsonResponse
    {
        $request->validate([
            'employeeId' => 'required|exists:employees,id',
            'time'       => 'required|date_format:H:i',
            'type'       => 'required|in:in,out',
        ]);

        $today    = now()->toDateString();
        $employee = Employee::findOrFail($request->employeeId);

        if ($request->type === 'in') {
            // Prevent duplicate open log
            $exists = TimeLog::where('employee_id', $employee->id)
                             ->where('date', $today)
                             ->whereNull('clock_out')
                             ->exists();

            if ($exists) {
                return response()->json(['message' => 'Employee already has an open clock-in today'], 422);
            }

            $log = TimeLog::create([
                'employee_id' => $employee->id,
                'date'        => $today,
                'clock_in'    => $request->time,
                'clock_out'   => null,
                'hours_worked'=> 0,
                'overtime'    => 0,
                'status'      => 'On time',  // manual entries assumed on-time
                'method'      => 'Manual',
            ]);

            return response()->json(new TimeLogResource($log), 201);
        }

        // type === 'out'
        $log = TimeLog::where('employee_id', $employee->id)
                      ->where('date', $today)
                      ->whereNull('clock_out')
                      ->first();

        if (!$log) {
            return response()->json(['message' => 'No open clock-in found for today'], 404);
        }

        $inMinutes  = $this->toMinutes($log->clock_in);
        $outMinutes = $this->toMinutes($request->time);
        $hours      = round(($outMinutes - $inMinutes) / 60, 2);
        $overtime   = max(0, round($hours - 8, 2));

        $log->update([
            'clock_out'    => $request->time,
            'hours_worked' => $hours,
            'overtime'     => $overtime,
            'status'       => $hours < 8 ? 'Undertime' : $log->status,
        ]);

        return response()->json(new TimeLogResource($log->fresh()));
    }

    // GET /api/attendance/summary?date=YYYY-MM-DD
    // Returns today's summary stats used in AttendancePage summary cards
    public function summary(Request $request): JsonResponse
    {
        $date        = $request->get('date', now()->toDateString());
        $logs        = TimeLog::where('date', $date)->get();
        $activeCount = Employee::where('status', 'Active')->count();

        return response()->json([
            'date'         => $date,
            'clockedIn'    => $logs->whereNotNull('clock_in')->count(),
            'onTime'       => $logs->where('status', 'On time')->count(),
            'late'         => $logs->where('status', 'Late')->count(),
            'notClockedIn' => max(0, $activeCount - $logs->count()),
        ]);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int)$h * 60 + (int)$m;
    }
}