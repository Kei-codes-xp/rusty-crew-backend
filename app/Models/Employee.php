<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'emergency_contact',
        'role',
        'status',
        'hourly_rate',
        'is_salaried',
        'monthly_salary',
        'pin',
        'password',
        'qr_token',
        'leave_balance',
        'avatar_color',
    ];


    protected $hidden = ['pin', 'password', 'qr_token', 'deleted_at'];

    protected $casts = [
        'hourly_rate'    => 'decimal:2',
        'monthly_salary' => 'decimal:2',
        'is_salaried'    => 'boolean',
        'leave_balance'  => 'integer',
    ];

    public function timeLogs()
    {
        return $this->hasMany(TimeLog::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function swapRequestsSent()
    {
        return $this->hasMany(ShiftSwap::class, 'requester_id');
    }

    public function swapRequestsReceived()
    {
        return $this->hasMany(ShiftSwap::class, 'target_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }


    // public function getFullNameAttribute(): string
    // {
    //     return "{$this->first_name} {$this->last_name}";
    // }
 
    // public function isManager(): bool
    // {
    //     return in_array($this->role, ['Manager', 'Admin']);
    // }
}
