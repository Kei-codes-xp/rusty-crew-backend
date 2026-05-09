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

class ScheduleController extends Controller
{
    // ── Weekly schedule ───────────────────────────────────────────────────────
    // GET /api/employee/schedule?weekStart=YYYY-MM-DD
    public function index(Request $request): JsonResponse
    {
        $request->validate(['weekStart' => 'required|date']);

        $emp   = $request->user();
        $start = \Carbon\Carbon::parse($request->weekStart)->startOfDay();
        $end   = $start->copy()->addDays(6)->endOfDay();

        $shifts = Shift::where('employee_id', $emp->id)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();

        return response()->json(ShiftResource::collection($shifts));
    }

    // ── All swaps involving this employee ─────────────────────────────────────
    // GET /api/employee/schedule/swaps
    public function swapIndex(Request $request): JsonResponse
    {
        $emp = $request->user();

        $swaps = ShiftSwap::where('requester_id', $emp->id)
            ->orWhere('target_id',   $emp->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(SwapRequestResource::collection($swaps));
    }

    // ── Request a swap ────────────────────────────────────────────────────────
    // POST /api/employee/schedule/swaps
    // Body: { targetId, date, shiftType, note }
    public function swapStore(Request $request): JsonResponse
    {
        $request->validate([
            'targetId'  => 'required|exists:employees,id',
            'date'      => 'required|date|after_or_equal:today',
            'shiftType' => 'required|in:Morning,Afternoon,Evening,Off',
            'note'      => 'nullable|string|max:255',
        ]);

        $emp = $request->user();

        // Employees cannot swap with themselves
        if ($emp->id === (int)$request->targetId) {
            return response()->json(['message' => 'Cannot swap with yourself'], 422);
        }

        // Conflict detection: employee already has a pending swap on that date
        $existingConflict = ShiftSwap::where('date', $request->date)
            ->where(function ($q) use ($emp) {
                $q->where('requester_id', $emp->id)
                    ->orWhere('target_id',  $emp->id);
            })
            ->where('status', 'Pending')
            ->exists();

        if ($existingConflict) {
            return response()->json([
                'message' => 'You already have a pending swap request on this date.',
            ], 422);
        }

        $swap = ShiftSwap::create([
            'requester_id' => $emp->id,
            'target_id'    => $request->targetId,
            'date'         => $request->date,
            'shift_type'   => $request->shiftType,
            'note'         => $request->note,
            'status'       => 'Pending',
        ]);

        // Notify target employee
        Notification::create([
            'employee_id' => (int)$request->targetId,
            'type'        => 'swap',
            'message'     => "{$emp->first_name} {$emp->last_name} sent you a shift swap request for {$request->date}.",
        ]);

        // Notify managers
        Notification::create([
            'employee_id' => null,
            'type'        => 'swap',
            'message'     => "{$emp->first_name} requested a shift swap with Employee #{$request->targetId} on {$request->date}.",
        ]);

        return response()->json(new SwapRequestResource($swap->load(['requester', 'target'])), 201);
    }

    // ── Accept a swap (target employee) ──────────────────────────────────────
    // PATCH /api/employee/schedule/swaps/{swap}/accept
    // Only the TARGET employee can accept the swap
    public function swapAccept(Request $request, ShiftSwap $swap): JsonResponse
    {
        $emp = $request->user();

        // Security: only the target can accept
        if ($swap->target_id !== $emp->id) {
            return response()->json(['message' => 'You are not the target of this swap request.'], 403);
        }

        if ($swap->status !== 'Pending') {
            return response()->json(['message' => 'This swap request is no longer pending.'], 422);
        }

        // Employee accepts → sets Approved, then a manager can finalize
        // For simplicity, mark as Approved directly (manager auto-approval flow)
        $swap->update(['status' => 'Approved']);

        // Swap the actual shift records
        $requesterShift = Shift::where('employee_id', $swap->requester_id)
            ->where('date', $swap->date)->first();
        $targetShift    = Shift::where('employee_id', $swap->target_id)
            ->where('date', $swap->date)->first();

        if ($requesterShift && $targetShift) {
            [$requesterShift->type, $targetShift->type] = [$targetShift->type, $requesterShift->type];
            $requesterShift->save();
            $targetShift->save();
        }

        // Notify requester
        Notification::create([
            'employee_id' => $swap->requester_id,
            'type'        => 'swap',
            'message'     => "{$emp->first_name} accepted your shift swap for {$swap->date}.",
        ]);

        return response()->json(new SwapRequestResource($swap->fresh()));
    }

    // ── Deny a swap (target employee) ─────────────────────────────────────────
    // PATCH /api/employee/schedule/swaps/{swap}/deny
    public function swapDeny(Request $request, ShiftSwap $swap): JsonResponse
    {
        $emp = $request->user();

        if ($swap->target_id !== $emp->id) {
            return response()->json(['message' => 'You are not the target of this swap request.'], 403);
        }

        if ($swap->status !== 'Pending') {
            return response()->json(['message' => 'This swap request is no longer pending.'], 422);
        }

        $swap->update(['status' => 'Denied']);

        Notification::create([
            'employee_id' => $swap->requester_id,
            'type'        => 'swap',
            'message'     => "{$emp->first_name} declined your shift swap for {$swap->date}.",
        ]);

        return response()->json(new SwapRequestResource($swap->fresh()));
    }

    public function eligible(Request $request): JsonResponse
    {
        $request->validate(['date' => 'required|date']);

        $emp  = $request->user();
        $date = $request->query('date');

        Log::info('Shift swap eligible request', [
            'employee_id' => $emp->id,
            'date'        => $date,
        ]);

        // FIX 2: Check the requesting employee is actually scheduled that day
        $myShift = Shift::where('employee_id', $emp->id)
            ->where('date', $date)
            ->where('type', '!=', 'Off')
            ->first();

        if (!$myShift) {
            // Return empty — you can't request a swap on your day off
            return response()->json([]);
        }

        // FIX 4: Get IDs of employees already involved in a pending swap on this date
        $busyEmployeeIds = ShiftSwap::where('date', $date)
            ->where('status', 'Pending')
            ->get()
            ->flatMap(fn($s) => [$s->requester_id, $s->target_id])
            ->unique()
            ->values()
            ->toArray();

        // Fetch all colleagues scheduled (non-Off) on this date
        $candidates = Shift::with('employee')
            ->where('date', $date)
            ->where('employee_id', '!=', $emp->id)  // exclude self
            ->where('type', '!=', 'Off')            // exclude days off
            ->whereNotIn('employee_id', $busyEmployeeIds) // exclude busy
            ->whereHas(
                'employee',
                fn($q) =>
                $q->where('status', 'Active')       // only active employees
            )
            ->get();

        Log::info('Eligible swap candidates', [
            'requesting_employee' => $emp->id,
            'date'                => $date,
            'my_shift'            => $myShift->type,
            'candidates_found'    => $candidates->count(),
        ]);

        // FIX 5: Map uses optional chaining on employee relationship
        //        (was crashing silently when employee soft-deleted)
        $mapped = $candidates
            ->filter(fn($row) => $row->employee !== null) // guard against null relation
            ->map(fn($row) => [
                // These keys match the SwapCandidate TypeScript interface exactly
                'employee_id' => $row->employee->id,
                'first_name'  => $row->employee->first_name,
                'last_name'   => $row->employee->last_name,
                'shift_type'  => $row->type,
                'shift_id'    => $row->id,
            ])
            ->values(); // re-index after filter

        return response()->json($mapped);
    }
}
