<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeLogResource;
use App\Http\Resources\SwapRequestResource;
use App\Models\Employee;
use App\Models\ShiftSwap;
use App\Models\TimeLog;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    // GET /api/dashboard
    // Returns all data the DashboardPage needs in one request:
    // metrics, todayShifts, pendingSwaps, recentClockIns, performanceOverview
    public function index(): JsonResponse
    {
        $today    = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd   = now()->endOfWeek()->toDateString();

        // ── Metrics (top 4 cards) ─────────────────────────────────────────────
        $totalStaff    = Employee::count();
        $activeStaff   = Employee::where('status', 'Active')->count();
        $resignedStaff = Employee::where('status', 'Resigned')->count();

        $onShiftToday  = TimeLog::where('date', $today)
                                ->whereNull('clock_out')
                                ->count();

        $weeklyHours   = TimeLog::whereBetween('date', [$weekStart, $weekEnd])
                                ->sum('hours_worked');

        $pendingSwaps  = ShiftSwap::where('status', 'Pending')->count();

        // ── Today's shifts (clocked-in employees) ─────────────────────────────
        $todayLogs = TimeLog::with('employee')
                            ->where('date', $today)
                            ->orderBy('clock_in')
                            ->get();

        // ── Pending swap requests (dashboard card) ────────────────────────────
        $swaps = ShiftSwap::with(['requester', 'target'])
                          ->where('status', 'Pending')
                          ->orderByDesc('created_at')
                          ->take(5)
                          ->get();

        // ── Recent clock-ins (last 3) ─────────────────────────────────────────
        $recentClockIns = TimeLog::with('employee')
                                 ->where('date', $today)
                                 ->orderByDesc('clock_in')
                                 ->take(3)
                                 ->get();

        // ── Performance overview (active employees) ───────────────────────────
        $performance = Employee::where('status', 'Active')
                               ->get()
                               ->map(function (Employee $emp) use ($weekStart, $weekEnd, $today) {
                                   $logs     = $emp->timeLogs()
                                                   ->whereBetween('date', [$weekStart, $weekEnd])
                                                   ->get();
                                   return [
                                       'id'          => $emp->id,
                                       'firstName'   => $emp->first_name,
                                       'lastName'    => $emp->last_name,
                                       'role'        => $emp->role,
                                       'status'      => $emp->status,
                                       'avatarColor' => $emp->avatar_color,
                                       'daysPresent' => $logs->whereNotNull('clock_in')->count(),
                                       'hoursWorked' => round((float) $logs->sum('hours_worked'), 1),
                                       'lateCount'   => $logs->where('status', 'Late')->count(),
                                   ];
                               });

        return response()->json([
            // Metrics — maps to DashboardPage metric cards
            'metrics' => [
                'totalStaff'   => $totalStaff,
                'activeStaff'  => $activeStaff,
                'resignedStaff'=> $resignedStaff,
                'onShiftToday' => $onShiftToday,
                'weeklyHours'  => round((float)$weeklyHours, 0),
                'pendingSwaps' => $pendingSwaps,
            ],
            // Today's shifts card
            'todayLogs'     => TimeLogResource::collection($todayLogs),
            // Pending swaps card
            'pendingSwaps'  => SwapRequestResource::collection($swaps),
            // Recent clock-ins card
            'recentClockIns'=> TimeLogResource::collection($recentClockIns),
            // Performance table
            'performance'   => $performance,
        ]);
    }
}