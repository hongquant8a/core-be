<?php

use App\Modules\Meeting\MeetingAttendanceController;
use Illuminate\Support\Facades\Route;

// Export — auth-only, không Spatie permission. Gate qua MeetingPolicy::operate (chair/operator).
Route::get('/export', [MeetingAttendanceController::class, 'export']);

Route::delete('/bulk-delete', [MeetingAttendanceController::class, 'bulkDestroy'])->middleware('permission:meeting-attendances.bulkDestroy,web');
Route::post('/checkin', [MeetingAttendanceController::class, 'checkin'])->middleware('permission:meeting-attendances.checkin,web');
// Điểm danh qua QR — auth required, dùng chung permission `checkin` với endpoint button.
Route::post('/checkin-by-token', [MeetingAttendanceController::class, 'checkinByToken'])->middleware('permission:meeting-attendances.checkin,web');
// Thư ký điểm danh hộ đại biểu — permission `store` (privileged role).
Route::post('/manual-checkin', [MeetingAttendanceController::class, 'manualCheckin'])->middleware('permission:meeting-attendances.store,web');
Route::post('/mark-absent', [MeetingAttendanceController::class, 'markAbsent'])->middleware('permission:meeting-attendances.markAbsent,web');
// Operator action duyệt/từ chối điểm danh đại biểu (Tab 7 DUYỆT ĐIỂM DANH).
Route::patch('/{meetingAttendance}/approve', [MeetingAttendanceController::class, 'approve'])->middleware('permission:meeting-attendances.approve,web');
Route::patch('/{meetingAttendance}/reject', [MeetingAttendanceController::class, 'reject'])->middleware('permission:meeting-attendances.reject,web');
Route::get('/stats', [MeetingAttendanceController::class, 'stats'])->middleware('permission:meeting-attendances.stats,web');
Route::get('/', [MeetingAttendanceController::class, 'index'])->middleware('permission:meeting-attendances.index,web');
Route::get('/{meetingAttendance}', [MeetingAttendanceController::class, 'show'])->middleware('permission:meeting-attendances.show,web');
Route::post('/', [MeetingAttendanceController::class, 'store'])->middleware('permission:meeting-attendances.store,web');
Route::put('/{meetingAttendance}', [MeetingAttendanceController::class, 'update'])->middleware('permission:meeting-attendances.update,web');
Route::patch('/{meetingAttendance}', [MeetingAttendanceController::class, 'update'])->middleware('permission:meeting-attendances.update,web');
Route::delete('/{meetingAttendance}', [MeetingAttendanceController::class, 'destroy'])->middleware('permission:meeting-attendances.destroy,web');
