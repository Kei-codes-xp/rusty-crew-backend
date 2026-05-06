<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    // GET /api/leaves
    // Returns all leave requests — maps to frontend LeaveRequest[]
    public function index(): JsonResponse
    {
        $leaves = LeaveRequest::with('employee')
                              ->orderByDesc('created_at')
                              ->get();

        return response()->json(LeaveRequestResource::collection($leaves));
    }

    // POST /api/leaves
    // File a new request — body matches frontend LeaveForm:
    // { employeeId, from, to, reason, type }
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'employeeId' => 'required|exists:employees,id',
            'from'       => 'required|date',
            'to'         => 'required|date|after_or_equal:from',
            'reason'     => 'required|string|max:500',
            'type'       => 'required|in:Sick,Vacation,Emergency',
        ]);

        // Check leave balance
        $employee = Employee::findOrFail($request->employeeId);
        $days     = \Carbon\Carbon::parse($request->from)
                                  ->diffInWeekdays(\Carbon\Carbon::parse($request->to)) + 1;

        if ($employee->leave_balance < $days) {
            return response()->json(['message' => "Insufficient leave balance ({$employee->leave_balance} days left)"], 422);
        }

        $leave = LeaveRequest::create([
            'employee_id' => $request->employeeId,
            'from'        => $request->from,
            'to'          => $request->to,
            'reason'      => $request->reason,
            'type'        => $request->type,
            'status'      => 'Pending',
        ]);

        // Notify managers
        Notification::create([
            'employee_id' => null,
            'type'        => 'leave',
            'message'     => "{$employee->first_name} {$employee->last_name} filed a {$request->type} leave request.",
        ]);

        return response()->json(new LeaveRequestResource($leave), 201);
    }

    // PATCH /api/leaves/{leave}/approve
    // Manager approves — deducts leave_balance, notifies employee
    public function approve(Request $request, LeaveRequest $leave): JsonResponse
    {
        if ($leave->status !== 'Pending') {
            return response()->json(['message' => 'Leave request is no longer pending'], 422);
        }

        $days = \Carbon\Carbon::parse($leave->from)
                              ->diffInWeekdays(\Carbon\Carbon::parse($leave->to)) + 1;

        $leave->update([
            'status'      => 'Approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Deduct leave balance
        $leave->employee->decrement('leave_balance', $days);

        Notification::create([
            'employee_id' => $leave->employee_id,
            'type'        => 'leave',
            'message'     => "Your {$leave->type} leave ({$leave->from} – {$leave->to}) was approved.",
        ]);

        return response()->json(new LeaveRequestResource($leave->fresh()));
    }

    // PATCH /api/leaves/{leave}/deny
    public function deny(Request $request, LeaveRequest $leave): JsonResponse
    {
        if ($leave->status !== 'Pending') {
            return response()->json(['message' => 'Leave request is no longer pending'], 422);
        }

        $leave->update([
            'status'      => 'Denied',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        Notification::create([
            'employee_id' => $leave->employee_id,
            'type'        => 'leave',
            'message'     => "Your {$leave->type} leave ({$leave->from} – {$leave->to}) was denied.",
        ]);

        return response()->json(new LeaveRequestResource($leave->fresh()));
    }
}