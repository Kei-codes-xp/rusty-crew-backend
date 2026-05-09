<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class EmployeeLeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $leaves = LeaveRequest::where('employee_id', $request->user()->id)
                              ->orderByDesc('created_at')
                              ->get();
 
        return response()->json(LeaveRequestResource::collection($leaves));
    }
 
    // ── File a leave request ──────────────────────────────────────────────────
    // POST /api/employee/leaves
    // Body matches frontend LeaveForm: { from, to, reason, type }
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'from'   => 'required|date|after_or_equal:today',
            'to'     => 'required|date|after_or_equal:from',
            'reason' => 'required|string|max:500',
            'type'   => 'required|in:Sick,Vacation,Emergency',
        ]);
 
        $emp  = $request->user();
 
        // Calculate working days requested
        $days = \Carbon\Carbon::parse($request->from)
                              ->diffInWeekdays(\Carbon\Carbon::parse($request->to)) + 1;
 
        // Balance check
        if ($emp->leave_balance < $days) {
            return response()->json([
                'message' => "Insufficient leave balance. You have {$emp->leave_balance} day(s) remaining, but requested {$days}.",
            ], 422);
        }
 
        // Check for overlapping approved/pending leaves
        $overlap = LeaveRequest::where('employee_id', $emp->id)
            ->whereIn('status', ['Pending', 'Approved'])
            ->where(function ($q) use ($request) {
                $q->whereBetween('from', [$request->from, $request->to])
                  ->orWhereBetween('to',   [$request->from, $request->to])
                  ->orWhere(function ($q2) use ($request) {
                      $q2->where('from', '<=', $request->from)
                         ->where('to',   '>=', $request->to);
                  });
            })
            ->exists();
 
        if ($overlap) {
            return response()->json([
                'message' => 'You already have a leave request that overlaps with these dates.',
            ], 422);
        }
 
        $leave = LeaveRequest::create([
            'employee_id' => $emp->id,
            'from'        => $request->from,
            'to'          => $request->to,
            'reason'      => $request->reason,
            'type'        => $request->type,
            'status'      => 'Pending',
        ]);
 
        // Notify all managers
        Notification::create([
            'employee_id' => null,
            'type'        => 'leave',
            'message'     => "{$emp->first_name} {$emp->last_name} filed a {$request->type} leave request ({$request->from} – {$request->to}).",
        ]);
 
        return response()->json(new LeaveRequestResource($leave), 201);
    }
}
