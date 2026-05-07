<?php

use App\Modules\Meeting\MeetingAttendanceController;
use Illuminate\Support\Facades\Route;

Route::delete('/bulk-delete', [MeetingAttendanceController::class, 'bulkDestroy'])->middleware('permission:meeting-attendances.bulkDestroy,web');
Route::post('/checkin', [MeetingAttendanceController::class, 'checkin'])->middleware('permission:meeting-attendances.checkin,web');
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
