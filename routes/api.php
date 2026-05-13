<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    AttendanceController,
    DashboardController,
    EmployeeController,
    EmployeeNotificationController,
    EmployeePayrollController,
    KioskController,
    LeaveController,
    NotificationController,
    PayrollController,
    ProfileController,
    ShiftController,
    ScheduleController,
};


use Illuminate\Http\Request;



// PIN clock-in — body: { pin }     matches clockIn via PIN tab
Route::post('/auth/pin',     [AuthController::class, 'clockInPin']);

// Manager login — body: { email, password }

Route::post('/auth/manager', [AuthController::class, 'managerLogin']);
Route::post('/auth/crew', [AuthController::class, 'crewLogin']);


Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me',           [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('employees/{employee}/qr',       [EmployeeController::class, 'qrCode']);
    Route::get('employees/{employee}/qr-token', [EmployeeController::class, 'qrToken']);
    Route::apiResource('employees', EmployeeController::class);


    Route::get('/attendance',          [AttendanceController::class, 'index']);
    Route::get('/attendance/range',    [AttendanceController::class, 'range']);
    Route::get('/attendance/summary',  [AttendanceController::class, 'summary']);
    Route::post('/attendance/manual',  [AttendanceController::class, 'manual']);
    Route::post('/clock-in', [AttendanceController::class, 'scan']);
    Route::post('/clock-out', [AttendanceController::class, 'clockOut']);
    // QR clock-in — body: { qrToken }  matches clockInByQR(qrToken)
    Route::get('/shift-swaps/eligible', [ScheduleController::class, 'eligible']);




    Route::get('/shifts',                          [ShiftController::class, 'index']);
    Route::post('/shifts',                         [ShiftController::class, 'store']);
    Route::get('/shifts/swaps',                    [ShiftController::class, 'swapIndex']);
    Route::post('/shifts/swaps',                   [ShiftController::class, 'swapStore']);
    Route::patch('/shifts/swaps/{swap}/approve',   [ShiftController::class, 'swapApprove']);
    Route::patch('/shifts/swaps/{swap}/deny',      [ShiftController::class, 'swapDeny']);


    Route::get('/leaves',                        [LeaveController::class, 'index']);
    Route::post('/leaves',                       [LeaveController::class, 'store']);
    Route::patch('/leaves/{leave}/approve',      [LeaveController::class, 'approve']);
    Route::patch('/leaves/{leave}/deny',         [LeaveController::class, 'deny']);

    Route::get('/notifications',               [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',  [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/read-all',    [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // Route::get('/auth/qr/generate', [KioskController::class, 'generate']);

    // Route::prefix('swaps')->group(function () {
    //     Route::get('/',                        [ScheduleController::class, 'swapIndex']);
    //     Route::post('/',                       [ScheduleController::class, 'swapStore']);
    //     Route::patch('/{swap}/accept',         [ScheduleController::class, 'swapAccept']);
    //     Route::patch('/{swap}/deny',           [ScheduleController::class, 'swapDeny']);
    // });

    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut']);

    // Route::get('/leaves',  [LeaveController::class, 'index']);
    // Route::post('/leaves', [LeaveController::class, 'store']);


    // Route::prefix('payroll')->group(function () {
    //     Route::get('/',              [PayrollController::class, 'index']);
    //     Route::get('/payslip',       [PayrollController::class, 'payslip']);
    //     Route::get('/payslip/pdf',   [PayrollController::class, 'payslipPdf']);
    // });


    Route::prefix('notifications')->group(function () {
        Route::get('/crew',                      [EmployeeNotificationController::class, 'index']);
        Route::get('/crew/unread-count',          [EmployeeNotificationController::class, 'unreadCount']);
        Route::patch('/crew/read-all',            [EmployeeNotificationController::class, 'markAllRead']);
        Route::patch('/crew/{notification}/read', [EmployeeNotificationController::class, 'markRead']);
    });

    Route::get('/profile',          [ProfileController::class, 'show']);
    Route::patch('/profile',          [ProfileController::class, 'update']);
    Route::patch('/profile/password', [ProfileController::class, 'changePassword']);
    Route::post('/profile/avatar',   [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar',   [ProfileController::class, 'removeAvatar']);
});
Route::middleware(['kiosk.device'])
     ->prefix('kiosk')
     ->group(function () {
 
    // ── Public kiosk endpoints (no user auth) ──────────────────────────────
 
    // GET /api/kiosk/qr
    // Kiosk frontend polls this every 30 s to get a fresh signed token.
    // Returns: { token, signature, expires_at, issued_at, kiosk_id }
    // Maps to: useKioskQR.fetchQR()
    Route::get('/qr', [KioskController::class, 'generateQR']);
 
    // GET /api/kiosk/scans?token={currentToken}
    // Kiosk polls this every 4 s to populate the live feed panel.
    // Returns: ScanResult[] (last 8 scans from the past hour)
    // Maps to: useKioskQR.pollScans()
    Route::get('/scans', [KioskController::class, 'recentScans']);
 
    // ── Authenticated scan endpoint (employee mobile app) ──────────────────
 
    // POST /api/kiosk/scan
    // Called by the employee's authenticated mobile app after QR scan.
    // Requires: Authorization: Bearer {employee_sanctum_token}
    // Body: { token, signature, exp, kiosk, deviceId }
    // Returns: { success, action, message, employeeName, time, scanId }
    Route::middleware('auth:sanctum')
         ->post('/scan', [KioskController::class, 'processScan']);
});


Route::middleware(['auth:sanctum'])
     ->prefix('payroll')
     ->group(function () {
 
    // POST /api/payroll/generate
    // Generate (or regenerate) a payroll period.
    // Body: { startDate, endDate, frequency, notes? }
    // Returns: { message, period, entries[] }
    Route::post('/generate', [PayrollController::class, 'generate']);
 
    // GET /api/payroll/periods
    // List all payroll periods, paginated.
    // Query: ?frequency=weekly&status=draft
    Route::get('/periods', [PayrollController::class, 'periods']);
 
    // GET /api/payroll/periods/{period}
    // Single period detail (no entries).
    Route::get('/periods/{period}', [PayrollController::class, 'showPeriod']);
 
    // PATCH /api/payroll/periods/{period}/lock
    // Lock a draft period — makes it immutable.
    Route::patch('/periods/{period}/lock', [PayrollController::class, 'lock']);
 
    // PATCH /api/payroll/periods/{period}/void
    // Void a draft period — soft-cancel.
    Route::patch('/periods/{period}/void', [PayrollController::class, 'void']);
 
    // GET /api/payroll/{period}/entries
    // All PayrollEntry rows for a period (full breakdown per employee).
    Route::get('/{period}/entries', [PayrollController::class, 'entries']);
 
    // GET /api/payroll/payslip/{employee}/{period}
    // Frozen payslip for one employee in one period.
    // IMPORTANT: declare before /{period}/entries to avoid route conflict
    Route::get('/payslip/{employee}/{period}',      [PayrollController::class, 'payslip']); 
    Route::get('/payslip/{employee}/{period}/pdf',  [PayrollController::class, 'payslipPdf']);

});


Route::middleware(['auth:sanctum'])
    ->prefix('employee/payroll')
    ->group(function () {

        // GET /api/employee/payroll/periods
        Route::get('/periods', [EmployeePayrollController::class, 'periods']);

        // GET /api/employee/payroll/{period}
        Route::get('/{period}', [EmployeePayrollController::class, 'show']);

        // GET /api/employee/payroll/{period}/payslip
        Route::get('/{period}/payslip', [EmployeePayrollController::class, 'payslip']);

        // GET /api/employee/payroll/{period}/pdf
        Route::get('/{period}/pdf', [EmployeePayrollController::class, 'pdf']);
    });