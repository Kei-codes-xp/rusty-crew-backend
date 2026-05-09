<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\KioskController;

Route::middleware(['kiosk.device'])
     ->prefix('kiosk')
     ->group(function () {

    // GET /api/kiosk/qr
    // Kiosk frontend polls every 30s → useKioskQR.fetchQR()
    // Returns: { token, signature, expires_at, issued_at, kiosk_id }
    Route::get('/qr', [KioskController::class, 'generateQR']);

    // GET /api/kiosk/scans
    // Kiosk polls every 4s → useKioskQR.pollScans()
    // Returns: ScanResult[] (last 8 scans from past hour)
    Route::get('/scans', [KioskController::class, 'recentScans']);

    // POST /api/kiosk/scan
    // Called by employee's authenticated mobile app after scanning QR
    // Requires: Authorization: Bearer {sanctum_token}
    // Body: { token, signature, exp, kiosk, deviceId }
    Route::middleware('auth:sanctum')
         ->post('/scan', [KioskController::class, 'processScan']);
});