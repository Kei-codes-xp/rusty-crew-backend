<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
         return [
            'id'            => $this->id,
            'firstName'     => $this->first_name,
            'lastName'      => $this->last_name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'emergency'     => $this->emergency_contact,
            'role'          => $this->role,
            'status'        => $this->status,
            'hourlyRate'    => (float) $this->hourly_rate,
            'isSalaried'    => (bool)  $this->is_salaried,
            'monthlySalary' => (float) $this->monthly_salary,
            'leaveBalance'  => (int)   $this->leave_balance,
            'avatarColor'   => $this->avatar_color,
        ];
    }
}
