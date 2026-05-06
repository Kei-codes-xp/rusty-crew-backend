<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at;
        $today     = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        if ($createdAt->isToday()) {
            $time = $createdAt->format('h:i A');
        } elseif ($createdAt->isSameDay($yesterday)) {
            $time = 'Yesterday';
        } else {
            $time = $createdAt->format('M d');
        }

        return [
            'id'      => $this->id,
            'type'    => $this->type,
            'message' => $this->message,
            'time'    => $time,
            'read'    => (bool) $this->read,
        ];
    }
}
