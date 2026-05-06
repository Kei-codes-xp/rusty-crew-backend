<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeLogResource extends JsonResource
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
            'employeeId'  => $this->employee_id,
            'date'        => $this->date->format('Y-m-d'),
            'clockIn'     => $this->clock_in,
            'clockOut'    => $this->clock_out,
            'hoursWorked' => (float) $this->hours_worked,
            'overtime'    => (float) $this->overtime,
            'status'      => $this->status,
            'method'      => $this->method,
        ];
    }
}
