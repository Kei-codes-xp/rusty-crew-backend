<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KioskScanResult;
use App\Models\KioskToken;
use App\Models\Notification;
use App\Models\TimeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KioskController extends Controller
{
    private const QR_TTL_SECONDS = 30;

    // ── Avatar color palette (mirrors frontend nameColor()) ──────────────────
    private const AVATAR_COLORS = [
        '#3d5a2b',
        '#2b3d5a',
        '#5a3d2b',
        '#3d2b5a',
        '#5a2b3d',
        '#2b5a3d',
    ];

    // =========================================================================
    // GET /api/kiosk/qr
    // Called by the kiosk frontend every 30 seconds.
    // Returns a signed, time-limited token for the QR image.
    // No auth required — the kiosk is a public device.
    // =========================================================================
    public function generateQR(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Kiosk-Device', 'unknown');

        // Clean up expired tokens for this device (housekeeping)
        KioskToken::where('kiosk_device_id', $deviceId)
            ->where('expires_at', '<', now()->subMinutes(5))
            ->delete();

        // Issue new token
        $token     = Str::random(48);
        $issuedAt  = now();
        $expiresAt = $issuedAt->copy()->addSeconds(self::QR_TTL_SECONDS);

        // HMAC-SHA256 signature: sign(token + '|' + expires_at_unix)
        $signature = hash_hmac(
            'sha256',
            $token . '|' . $expiresAt->timestamp,
            config('app.key')
        );

        KioskToken::create([
            'token'           => $token,
            'signature'       => $signature,
            'kiosk_device_id' => $deviceId,
            'issued_at'       => $issuedAt,
            'expires_at'      => $expiresAt,
        ]);

        return response()->json([
            'token'      => $token,
            'signature'  => $signature,
            'expires_at' => $expiresAt->toISOString(),
            'issued_at'  => $issuedAt->toISOString(),
            'kiosk_id'   => $deviceId,
        ]);
    }

    // =========================================================================
    // POST /api/kiosk/scan
    // Called by the EMPLOYEE's mobile app after scanning the QR.
    // Employee must be authenticated (Sanctum token in Authorization header).
    //
    // Body: { token, signature, exp, kiosk, deviceId }
    //   token     — the UUID from the QR
    //   signature — HMAC from the QR (frontend passes through)
    //   exp       — expiry ISO string from the QR
    //   kiosk     — kiosk device ID from the QR
    //   deviceId  — the employee's mobile device ID (for binding check)
    // =========================================================================
    public function processScan(Request $request): JsonResponse
    {
        $request->validate([
            'token'     => 'required|string',
            'signature' => 'required|string',
            'exp'       => 'required|string',
            'kiosk'     => 'required|string',
            'deviceId'  => 'required|string',
        ]);

        $employee = $request->user();

        // ── 1. Look up the token ──────────────────────────────────────────────
        $kioskToken = KioskToken::where('token', $request->token)
            ->where('kiosk_device_id', $request->kiosk)
            ->first();

        if (!$kioskToken) {
            return $this->scanError('Invalid QR token', 401);
        }

        // ── 2. Check expiry ───────────────────────────────────────────────────
        if ($kioskToken->isExpired()) {
            return $this->scanError('QR code has expired. Please scan the updated code.', 422);
        }

        // ── 3. Check already used (replay attack prevention) ─────────────────
        if ($kioskToken->isUsed()) {
            return $this->scanError('This QR code has already been used.', 422);
        }

        // ── 4. Verify HMAC signature ──────────────────────────────────────────
        $expectedSig = hash_hmac(
            'sha256',
            $request->token . '|' . \Carbon\Carbon::parse($request->exp)->timestamp,
            config('app.key')
        );

        if (!hash_equals($expectedSig, $request->signature)) {
            Log::warning('Kiosk scan: invalid signature', [
                'employee_id' => $employee->id,
                'token'       => $request->token,
            ]);
            return $this->scanError('QR signature verification failed.', 401);
        }

        // ── 5. Employee must be active ────────────────────────────────────────
        if ($employee->status !== 'Active') {
            return $this->scanError('Your account is not active.', 403);
        }

        // ── 6. Clock in / clock out toggle ───────────────────────────────────
        $today    = now()->toDateString();
        $nowTime  = now()->format('H:i');

        $existingLog = TimeLog::where('employee_id', $employee->id)
            ->where('date', $today)
            ->whereNull('clock_out')
            ->first();

        if ($existingLog) {
            // ── Clock OUT ─────────────────────────────────────────────────────
            [$inH, $inM]  = explode(':', $existingLog->clock_in);
            $inMins       = (int)$inH * 60 + (int)$inM;
            $outMins      = (int)now()->format('H') * 60 + (int)now()->format('i');
            $hours        = round(($outMins - $inMins) / 60, 2);
            $overtime     = max(0, round($hours - 8, 2));

            $existingLog->update([
                'clock_out'    => $nowTime,
                'hours_worked' => $hours,
                'overtime'     => $overtime,
                'status'       => $hours < 8 ? 'Undertime' : $existingLog->status,
            ]);

            $action        = 'clock_out';
            $formattedTime = now()->format('g:i A');
        } else {
            // ── Clock IN ──────────────────────────────────────────────────────
            $shiftStartMins = 6 * 60;
            $nowMins        = (int)now()->format('H') * 60 + (int)now()->format('i');
            $isLate         = $nowMins > ($shiftStartMins + 5);

            TimeLog::create([
                'employee_id'  => $employee->id,
                'date'         => $today,
                'clock_in'     => $nowTime,
                'clock_out'    => null,
                'hours_worked' => 0,
                'overtime'     => 0,
                'status'       => $isLate ? 'Late' : 'On time',
                'method'       => 'QR',
            ]);

            if ($isLate) {
                Notification::create([
                    'employee_id' => null,
                    'type'        => 'late',
                    'message'     => "{$employee->first_name} {$employee->last_name} clocked in late at {$nowTime}.",
                ]);
            }

            $action        = 'clock_in';
            $formattedTime = now()->format('g:i A');
        }

        // ── 7. Mark token as consumed ─────────────────────────────────────────
        $kioskToken->update([
            'used_at' => now(),
            'used_by' => $employee->id,
            'action'  => $action,
        ]);

        // ── 8. Write scan result for kiosk feed polling ───────────────────────
        $avatarColor = self::AVATAR_COLORS[array_sum(array_map('ord', str_split($employee->first_name))) % count(self::AVATAR_COLORS)];

        $scanResult = KioskScanResult::create([
            'token'           => $request->token,
            'kiosk_device_id' => $request->kiosk,
            'employee_id'     => $employee->id,
            'employee_name'   => "{$employee->first_name} {$employee->last_name}",
            'action'          => $action,
            'formatted_time'  => $formattedTime,
            'avatar_color'    => $avatarColor,
            'scanned_at'      => now(),
        ]);

        return response()->json([
            'success'      => true,
            'action'       => $action,
            'message'      => $action === 'clock_in'
                ? "✅ {$employee->first_name} clocked IN — {$formattedTime}"
                : "✅ {$employee->first_name} clocked OUT — {$formattedTime}",
            'employeeName' => "{$employee->first_name} {$employee->last_name}",
            'time'         => $formattedTime,
            'scanId'       => $scanResult->id,
        ]);
    }

    // =========================================================================
    // GET /api/kiosk/scans?token={currentToken}
    // Called by the kiosk frontend every 4 seconds to populate the feed panel.
    // Returns recent scan results for the current kiosk device.
    // No auth required — data is display-only (no sensitive info).
    // =========================================================================
    public function recentScans(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Kiosk-Device', 'unknown');

        $scans = KioskScanResult::where('kiosk_device_id', $deviceId)
            ->where('scanned_at', '>=', now()->subHour())
            ->orderByDesc('scanned_at')
            ->limit(8)
            ->get()
            ->map(fn($s) => [
                // Maps to frontend ScanResult interface
                'id'           => (string) $s->id,
                'employeeName' => $s->employee_name,
                'action'       => $s->action,
                'time'         => $s->formatted_time,
                'avatarColor'  => $s->avatar_color,
            ]);

        // Log::info('SCAN RECEIVED', [
        //     'token' => $request->token,
        //     'url' => request()->fullUrl(),
        //     'ip' => request()->ip(),
        //     'user_agent' => request()->userAgent(),
        // ]);

        return response()->json($scans);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function scanError(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
