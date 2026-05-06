<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
         return [
            'id'         => $this->id,
            'employeeId' => $this->employee_id,
            'from'       => $this->from->format('Y-m-d'),
            'to'         => $this->to->format('Y-m-d'),
            'reason'     => $this->reason,
            'type'       => $this->type,
            'status'     => $this->status,
        ];
    }
}
