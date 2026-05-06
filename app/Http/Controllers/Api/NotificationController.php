<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/notifications
    // Returns notifications for the current user + broadcast (employee_id = null)
    // Maps to frontend Notification[]
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where(function ($q) use ($request) {
                            $q->where('employee_id', $request->user()->id)
                              ->orWhereNull('employee_id');
                         })
                         ->orderByDesc('created_at')
                         ->get();

        return response()->json(NotificationResource::collection($notifications));
    }

    // PATCH /api/notifications/{id}/read
    // Mark single notification as read — matches frontend dismiss button
    public function markRead(Notification $notification): JsonResponse
    {
        $notification->update(['read' => true]);
        return response()->json(new NotificationResource($notification->fresh()));
    }

    // PATCH /api/notifications/read-all
    // Mark all as read — matches frontend "Mark all read" button
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where(function ($q) use ($request) {
            $q->where('employee_id', $request->user()->id)
              ->orWhereNull('employee_id');
        })->update(['read' => true]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    // GET /api/notifications/unread-count
    // Returns unread count for the sidebar badge
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
}