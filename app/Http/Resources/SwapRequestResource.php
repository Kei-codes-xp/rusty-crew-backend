<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SwapRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'requesterId' => $this->requester_id,
            'targetId'    => $this->target_id,
            'date'        => $this->date->format('Y-m-d'),
            'shiftType'   => $this->shift_type,
            'status'      => $this->status,
            'note'        => $this->note ?? '',
        ];
    }
}
