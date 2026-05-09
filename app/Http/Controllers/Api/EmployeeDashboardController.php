<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeLogResource;
use App\Http\Resources\SwapRequestResource;
use App\Http\Resources\LeaveRequestResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\ShiftResource;
use App\Models\Notification;
use App\Models\ShiftSwap;
use App\Models\TimeLog;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/employee/dashboard
 *
 * Returns all data the employee dashboard page needs in one request:
 *   - todayShift
 *   - isClockedIn + currentLog
 *   - weekShifts (for weekly grid preview)
 *   - pendingSwaps (inbound + outbound)
 *   - pendingLeaves
 *   - unreadCount + recentNotifs
 *
 * All data is scoped to auth()->id() — employees never see other employees' data.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee  = $request->user();
        $today     = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd   = now()->endOfWeek()->toDateString();

        // ── Today's shift ─────────────────────────────────────────────────────
        $todayShift = Shift::where('employee_id', $employee->id)
                           ->where('date', $today)
                           ->first();

        // ── Clock status ──────────────────────────────────────────────────────
        $todayLog = TimeLog::where('employee_id', $employee->id)
                           ->where('date', $today)
                           ->first();

        $isClockedIn = $todayLog && $todayLog->clock_in && !$todayLog->clock_out;

        // ── Week shifts (for mini weekly preview) ──────────────────────────────
        $weekShifts = Shift::where('employee_id', $employee->id)
                           ->whereBetween('date', [$weekStart, $weekEnd])
                           ->get();

        // ── Swaps involving this employee ─────────────────────────────────────
        $swaps = ShiftSwap::where(function ($q) use ($employee) {
                    $q->where('requester_id', $employee->id)
                      ->orWhere('target_id', $employee->id);
                 })
                 ->where('status', 'Pending')
                 ->orderByDesc('created_at')
                 ->get();

        // ── Pending leaves ────────────────────────────────────────────────────
        $leaves = $employee->leaveRequests()
                           ->where('status', 'Pending')
                           ->get();

        // ── Notifications ─────────────────────────────────────────────────────
        $notifs = Notification::where(function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id)
                      ->orWhereNull('employee_id');
                  })
                  ->orderByDesc('created_at')
                  ->take(5)
                  ->get();

        $unreadCount = Notification::where(function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id)
                      ->orWhereNull('employee_id');
                  })
                  ->where('read', false)
                  ->count();

        return response()->json([
            'employee'     => [
                'id'           => $employee->id,
                'firstName'    => $employee->first_name,
                'lastName'     => $employee->last_name,
                'role'         => $employee->role,
                'status'       => $employee->status,
                'leaveBalance' => $employee->leave_balance,
                'avatarColor'  => $employee->avatar_color,
                'hourlyRate'   => (float) $employee->hourly_rate,
                'isSalaried'   => (bool)  $employee->is_salaried,
            ],
            'isClockedIn'    => $isClockedIn,
            'todayLog'       => $todayLog   ? new TimeLogResource($todayLog)     : null,
            'todayShift'     => $todayShift ? new ShiftResource($todayShift)     : null,
            'weekShifts'     => ShiftResource::collection($weekShifts),
            'pendingSwaps'   => SwapRequestResource::collection($swaps),
            'pendingLeaves'  => LeaveRequestResource::collection($leaves),
            'unreadCount'    => $unreadCount,
            'recentNotifs'   => NotificationResource::collection($notifs),
        ]);
    }
}