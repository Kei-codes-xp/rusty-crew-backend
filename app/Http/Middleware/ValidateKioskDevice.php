<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateKioskDevice
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = $request->header('X-Kiosk-Device');

        if (empty($deviceId)) {
            return response()->json([
                'message' => 'Missing X-Kiosk-Device header.',
            ], 400);
        }

        // Basic format check: device IDs start with "kiosk_"
        if (!str_starts_with($deviceId, 'kiosk_') && !str_starts_with($deviceId, 'dev_')) {
            return response()->json([
                'message' => 'Invalid kiosk device identifier.',
            ], 403);
        }

        // Attach device ID to request for downstream use
        $request->attributes->set('kiosk_device_id', $deviceId);

        return $next($request);
    }
}
