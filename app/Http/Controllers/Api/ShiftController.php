<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShiftResource;
use App\Http\Resources\SwapRequestResource;
use App\Models\Notification;
use App\Models\Shift;
use App\Models\ShiftSwap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShiftController extends Controller
{
    // GET /api/shifts?weekStart=YYYY-MM-DD
    // Returns all shift records for the 7-day window starting on weekStart.
    // Frontend useSchedule sends this query to populate the weekly grid.
    // For days with no record, the frontend falls back to SHIFT_PATTERN (default pattern).
    public function index(Request $request): JsonResponse
    {
        $request->validate(['weekStart' => 'required|date']);

        $start = \Carbon\Carbon::parse($request->weekStart)->startOfDay();
        $end   = $start->copy()->addDays(6)->endOfDay();

        $shifts = Shift::whereBetween('date', [$start, $end])
            ->orderBy('employee_id')
            ->orderBy('date')
            ->get();

        return response()->json(ShiftResource::collection($shifts));
    }

    // POST /api/shifts
    // Upsert — matches frontend setShift(empId, date, type):
    // Body: { employeeId, date, type }
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'employeeId' => 'required|exists:employees,id',
                'date'       => 'required|date',
                'type'       => 'required|in:Morning,Afternoon,Evening,Off',
            ]);

            $shift = Shift::updateOrCreate(
                [
                    'employee_id' => $request->employeeId,
                    'date'        => $request->date,
                ],
                ['type' => $request->type]
            );

            return response()->json(new ShiftResource($shift), 201);
        } catch (\Exception $e) {
            Log::error('Shift store failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Server error',
            ], 500);
        }
    }

    // ── Shift Swaps ────────────────────────────────────────────────────────────

    // GET /api/shifts/swaps
    // Returns all swap requests — frontend SwapRequest[]
    public function swapIndex(): JsonResponse
    {
        $swaps = ShiftSwap::with(['requester', 'target'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(SwapRequestResource::collection($swaps));
    }

    // POST /api/shifts/swaps
    // File a new swap request — body: { requesterId, targetId, date, shiftType, note }
    public function swapStore(Request $request): JsonResponse
    {
        $request->validate([
            'requesterId' => 'required|exists:employees,id',
            'targetId'    => 'required|exists:employees,id|different:requesterId',
            'date'        => 'required|date',
            'shiftType'   => 'required|in:Morning,Afternoon,Evening,Off',
            'note'        => 'nullable|string|max:255',
        ]);

        // Conflict detection: check if target already has a swap pending on same day
        $conflict = ShiftSwap::where('date', $request->date)
            ->where(function ($q) use ($request) {
                $q->where('target_id',    $request->targetId)
                    ->orWhere('requester_id', $request->targetId);
            })
            ->where('status', 'Pending')
            ->exists();

        if ($conflict) {
            return response()->json(['message' => 'Target employee already has a pending swap on this date'], 422);
        }

        $swap = ShiftSwap::create([
            'requester_id' => $request->requesterId,
            'target_id'    => $request->targetId,
            'date'         => $request->date,
            'shift_type'   => $request->shiftType,
            'note'         => $request->note,
            'status'       => 'Pending',
        ]);

        // Notify managers
        Notification::create([
            'employee_id' => null,
            'type'        => 'swap',
            'message'     => "Employee #{$request->requesterId} requested a shift swap for {$request->date}",
        ]);

        return response()->json(new SwapRequestResource($swap), 201);
    }

    // PATCH /api/shifts/swaps/{swap}/approve
    // Manager approves swap → status = 'Approved', shifts get exchanged
    public function swapApprove(ShiftSwap $swap): JsonResponse
    {
        if ($swap->status !== 'Pending') {
            return response()->json(['message' => 'Swap is no longer pending'], 422);
        }

        $swap->update(['status' => 'Approved']);

        // Swap the actual shift records between requester and target
        $requesterShift = Shift::where('employee_id', $swap->requester_id)
            ->where('date', $swap->date)->first();
        $targetShift    = Shift::where('employee_id', $swap->target_id)
            ->where('date', $swap->date)->first();

        if ($requesterShift && $targetShift) {
            [$requesterShift->type, $targetShift->type] = [$targetShift->type, $requesterShift->type];
            $requesterShift->save();
            $targetShift->save();
        }

        Notification::create([
            'employee_id' => $swap->requester_id,
            'type'        => 'swap',
            'message'     => "Your shift swap request for {$swap->date} was approved.",
        ]);

        return response()->json(new SwapRequestResource($swap->fresh()));
    }

    // PATCH /api/shifts/swaps/{swap}/deny
    public function swapDeny(ShiftSwap $swap): JsonResponse
    {
        if ($swap->status !== 'Pending') {
            return response()->json(['message' => 'Swap is no longer pending'], 422);
        }

        $swap->update(['status' => 'Denied']);

        Notification::create([
            'employee_id' => $swap->requester_id,
            'type'        => 'swap',
            'message'     => "Your shift swap request for {$swap->date} was denied.",
        ]);

        return response()->json(new SwapRequestResource($swap->fresh()));
    }
}
