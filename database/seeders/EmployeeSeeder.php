<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\ShiftSwap;
use App\Models\TimeLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeSeeder extends Seeder
{
    /**
     * Seeds data matching INITIAL_EMPLOYEES from features/employees/employees.data.ts
     * so the frontend works out of the box without any changes.
     *
     * qrToken matches the frontend's qrToken field (QR001–QR008)
     * avatarColor matches the frontend's avatarColor field ('0'–'7')
     */
    public function run(): void
    {
        $employees = [
            //  firstName  lastName     email                phone              emergency            role      hourly  salaried monthly  qr      avatar  leaveBalance
            ['Vanjoe',   'Santos',       'vanjoe@rustycrew.ph',   '+63 917 111 2222', '+63 917 999 0000', 'Barista', 85,   false, 0,     'QR001', '0', 5],
            ['Ivy',  'Reyes', 'ivy@rustycrew.ph',  '+63 918 333 4444', '+63 918 888 1111', 'Cashier', 80,   false, 0,     'QR002', '1', 5],
            ['Keisuke', 'Karishuku','kei@rustycrew.ph', '+63 919 555 6666', '+63 919 777 2222', 'Manager', 120,  true,  35000, 'QR003', '2', 7],
            ['Bernadeth',  'DeJesus',     'berna@rustycrew.ph',  '+63 920 777 8888', '+63 920 666 3333', 'Barista', 85,   false, 0,     'QR004', '3', 4],
            ['Kyla',  'Santos',    'kyla@rustycrew.ph',  '+63 921 999 0000', '+63 921 555 4444', 'Cashier', 80,   false, 0,     'QR005', '4', 6],
            ['Ben',   'Mercado',   'ben@rustycrew.ph',   '+63 922 111 3333', '+63 922 444 5555', 'Barista', 85,   false, 0,     'QR006', '5', 5],
            ['Luz',   'Bautista',  'luz@rustycrew.ph',   '+63 923 444 5555', '+63 923 333 6666', 'Cashier', 80,   false, 0,     'QR007', '6', 3],
            ['Carlo', 'Tan',       'carlo@rustycrew.ph', '+63 924 666 7777', '+63 924 222 7777', 'Barista', 85,   false, 0,     'QR008', '7', 0],
        ];

        foreach ($employees as $i => [$fn, $ln, $email, $phone, $emergency, $role, $rate, $salaried, $monthly, $qr, $color, $leave]) {
            $status = $fn === 'Carlo' ? 'Resigned' : 'Active';

            Employee::create([
                'first_name'        => $fn,
                'last_name'         => $ln,
                'email'             => $email,
                'phone'             => $phone,
                'emergency_contact' => $emergency,
                'role'              => $role,
                'status'            => $status,
                'hourly_rate'       => $rate,
                'is_salaried'       => $salaried,
                'monthly_salary'    => $monthly,
                'pin'               => Hash::make('1234'),      // default PIN for all
                'password'          => $role === 'Manager' ? Hash::make('password') : null,
                'qr_token'          => Str::random(64),                    
                'leave_balance'     => $leave,
                'avatar_color'      => $color,                  // must match frontend avatarColor
            ]);
        }

        // ── Sample time logs for today ────────────────────────────────────────
        $today = now()->toDateString();

        $logs = [
            [1, '06:02', '14:05', 8.05, 0.05, 'On time', 'QR'],
            [2, '06:08', '14:00', 7.87, 0,    'Late',    'QR'],
            [3, '07:58', null,    0,    0,     'On time', 'Manual'],
            [4, '13:55', null,    0,    0,     'On time', 'QR'],
        ];

        foreach ($logs as [$empId, $in, $out, $hours, $ot, $status, $method]) {
            TimeLog::create([
                'employee_id'  => $empId,
                'date'         => $today,
                'clock_in'     => $in,
                'clock_out'    => $out,
                'hours_worked' => $hours,
                'overtime'     => $ot,
                'status'       => $status,
                'method'       => $method,
            ]);
        }

        // ── Sample pending swap requests ──────────────────────────────────────
        ShiftSwap::create([
            'requester_id' => 5,  // Kyla
            'target_id'    => 4,  // Rico
            'date'         => now()->addDays(2)->toDateString(),
            'shift_type'   => 'Afternoon',
            'status'       => 'Pending',
            'note'         => 'Family event',
        ]);

        ShiftSwap::create([
            'requester_id' => 6,  // Ben
            'target_id'    => 1,  // Ana
            'date'         => now()->addDays(3)->toDateString(),
            'shift_type'   => 'Morning',
            'status'       => 'Pending',
            'note'         => 'Doctor appointment',
        ]);

        // ── Sample notifications ──────────────────────────────────────────────
        Notification::create([
            'employee_id' => null,
            'type'        => 'late',
            'message'     => 'Juan Dela Cruz clocked in 8 mins late',
            'read'        => false,
            'created_at'  => now()->setTime(6, 8),
        ]);

        Notification::create([
            'employee_id' => null,
            'type'        => 'swap',
            'message'     => 'Kyla Santos requested a shift swap',
            'read'        => false,
            'created_at'  => now()->subDay(),
        ]);

        Notification::create([
            'employee_id' => null,
            'type'        => 'leave',
            'message'     => 'Ana Lim filed a sick leave request',
            'read'        => true,
            'created_at'  => now()->subDay(),
        ]);

        Notification::create([
            'employee_id' => null,
            'type'        => 'shift',
            'message'     => 'Shift reminder: Afternoon crew at 2:00 PM',
            'read'        => true,
            'created_at'  => now(),
        ]);
    }
}