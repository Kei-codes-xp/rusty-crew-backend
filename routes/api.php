<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    AttendanceController,
    DashboardController,
    EmployeeController,
    LeaveController,
    NotificationController,
    PayrollController,
    ShiftController,
};
use Illuminate\Http\Request;
 
// QR clock-in — body: { qrToken }  matches clockInByQR(qrToken)
Route::post('/auth/qr',      [AuthController::class, 'clockInQR']);
 
// PIN clock-in — body: { pin }     matches clockIn via PIN tab
Route::post('/auth/pin',     [AuthController::class, 'clockInPin']);
 
// Manager login — body: { email, password }
Route::post('/auth/manager', [AuthController::class, 'managerLogin']);
 

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
 

    Route::get('/shifts',                          [ShiftController::class, 'index']);
    Route::post('/shifts',                         [ShiftController::class, 'store']);
    Route::get('/shifts/swaps',                    [ShiftController::class, 'swapIndex']);
    Route::post('/shifts/swaps',                   [ShiftController::class, 'swapStore']);
    Route::patch('/shifts/swaps/{swap}/approve',   [ShiftController::class, 'swapApprove']);
    Route::patch('/shifts/swaps/{swap}/deny',      [ShiftController::class, 'swapDeny']);

    Route::get('/payroll/weekly',               [PayrollController::class, 'weekly']);
    Route::get('/payroll/payslip/{employee}',   [PayrollController::class, 'payslip']);
 
    Route::get('/leaves',                        [LeaveController::class, 'index']);
    Route::post('/leaves',                       [LeaveController::class, 'store']);
    Route::patch('/leaves/{leave}/approve',      [LeaveController::class, 'approve']);
    Route::patch('/leaves/{leave}/deny',         [LeaveController::class, 'deny']);
 
    Route::get('/notifications',               [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',  [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/read-all',    [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});