<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class EmployeeNotificationController extends Controller
{
      // ── All notifications for this employee ───────────────────────────────────
    // GET /api/employee/notifications
    public function index(Request $request): JsonResponse
    {
        $notifs = Notification::where(function ($q) use ($request) {
                    $q->where('employee_id', $request->user()->id)
                      ->orWhereNull('employee_id');
                  })
                  ->orderByDesc('created_at')
                  ->get();
 
        return response()->json(NotificationResource::collection($notifs));
    }
 
    // ── Unread count (for sidebar/tab badge) ──────────────────────────────────
    // GET /api/employee/notifications/unread-count
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where(function ($q) use ($request) {
                    $q->where('employee_id', $request->user()->id)
                      ->orWhereNull('employee_id');
                 })
                 ->where('read', false)
                 ->count();
 
        return response()->json(['unread' => $count]);
    }
 
    // ── Mark all as read ──────────────────────────────────────────────────────
    // PATCH /api/employee/notifications/read-all
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where(function ($q) use ($request) {
            $q->where('employee_id', $request->user()->id)
              ->orWhereNull('employee_id');
        })->update(['read' => true]);
 
        return response()->json(['message' => 'All notifications marked as read']);
    }
 
    // ── Dismiss single notification ───────────────────────────────────────────
    // PATCH /api/employee/notifications/{id}/read
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        // Ensure employee can only mark their own (or broadcast) notifications
        if (
            $notification->employee_id !== null &&
            $notification->employee_id !== $request->user()->id
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
 
        $notification->update(['read' => true]);
        return response()->json(new NotificationResource($notification->fresh()));
    }
}
