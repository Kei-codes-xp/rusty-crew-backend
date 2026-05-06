<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\EmployeeResource;
use App\Models\Notification;
use App\Models\TimeLog;
use Illuminate\Http\JsonResponse;


class AuthController extends Controller
{
    public function clockInQR(Request $request): JsonResponse
    {
        $request->validate(['qrToken' => 'required|string']);

        // qr_token is hidden in EmployeeResource; we query directly
        $employee = Employee::where('qr_token', $request->qrToken)
            ->where('status', 'Active')
            ->first();

        if (!$employee) {
            return response()->json(['message' => '❌ Invalid QR code'], 401);
        }

        return $this->handleToggle($employee, 'QR');
    }

    // PIN clock-in
    public function clockInPin(Request $request): JsonResponse
    {
        $request->validate(['pin' => 'required|digits:4']);

        $employee = Employee::where('status', 'Active')
            ->get()
            ->first(fn($e) => Hash::check($request->pin, $e->pin));

        if (!$employee) {
            return response()->json(['message' => '❌ Invalid PIN'], 401);
        }

        return $this->handleToggle($employee, 'Manual');
    }

    public function managerLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $employee = Employee::where('email', $request->email)
            ->whereIn('role', ['Manager', 'Admin'])
            ->first();

        if (!$employee || !Hash::check($request->password, $employee->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $employee->createToken('manager-session', ['manager'])->plainTextToken;

        return response()->json([
            'token'    => $token,
            'employee' => new EmployeeResource($employee),
        ]);
    }
    public function me(Request $request): JsonResponse
    {
        return response()->json(new EmployeeResource($request->user()));
    }
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
    private function handleToggle(Employee $employee, string $method): JsonResponse
    {
        $today    = now()->toDateString();
        $nowTime  = now()->format('H:i');

        $existing = TimeLog::where('employee_id', $employee->id)
            ->where('date', $today)
            ->whereNull('clock_out')
            ->first();

        if ($existing) {
            $inMinutes  = $this->toMinutes($existing->clock_in);
            $outMinutes = $this->toMinutes($nowTime);
            $hours      = round(($outMinutes - $inMinutes) / 60, 2);
            $overtime   = max(0, round($hours - 8, 2));

            $existing->update([
                'clock_out'   => $nowTime,
                'hours_worked' => $hours,
                'overtime'    => $overtime,
            ]);

            return response()->json([
                'action'   => 'clock_out',
                // Frontend clockMsg format: "✅ {firstName} clocked OUT — {hrs}h worked"
                'message'  => "✅ {$employee->first_name} clocked OUT — {$hours}h worked",
                'employee' => new EmployeeResource($employee),
                'log'      => new \App\Http\Resources\TimeLogResource($existing->fresh()),
            ]);
        }

        $shiftStartMinutes = 6 * 60;
        $nowMinutes        = $this->toMinutes($nowTime);
        $status            = $nowMinutes > $shiftStartMinutes ? 'Late' : 'On time';

        $log = TimeLog::create([
            'employee_id' => $employee->id,
            'date'        => $today,
            'clock_in'    => $nowTime,
            'clock_out'   => null,
            'hours_worked' => 0,
            'overtime'    => 0,
            'status'      => $status,
            'method'      => $method,
        ]);

        // Fire late notification
        if ($status === 'Late') {
            Notification::create([
                'employee_id' => null, // broadcast to managers
                'type'        => 'late',
                'message'     => "{$employee->first_name} {$employee->last_name} clocked in late at {$nowTime}",
            ]);
        }

        $token = $employee->createToken('employee-session', ['employee'])->plainTextToken;

        return response()->json([
            'action'   => 'clock_in',
            'message'  => "✅ {$employee->first_name} clocked IN — {$nowTime}",
            'employee' => new EmployeeResource($employee),
            'token'    => $token,
            'log'      => new \App\Http\Resources\TimeLogResource($log),
        ]);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int)$h * 60 + (int)$m;
    }
}
