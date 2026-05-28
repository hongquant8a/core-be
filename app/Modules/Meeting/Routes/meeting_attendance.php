<?php

use App\Modules\Meeting\Controllers\MeetingAttendanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Flat admin routes — Spatie permission (chỉ CRUD/báo cáo cấp admin)
|--------------------------------------------------------------------------
| Các action gắn meeting cụ thể (checkin, mark-absent, manual-checkin,
| approve, reject) đã DROP — chỉ tồn tại ở nested route
| `/api/meetings/{meeting}/attendances/*` với Gate Policy.
| Xem changelog: 2026-05-15-audit-flat-routes-cleanup-fe.txt
*/

// Export — auth-only, không Spatie permission. Gate qua MeetingPolicy::operate (chair/operator).
Route::get('/export', [MeetingAttendanceController::class, 'export']);

Route::delete('/bulk-delete', [MeetingAttendanceController::class, 'bulkDestroy'])->middleware('permission:meeting-attendances.bulkDestroy,web');
Route::get('/stats', [MeetingAttendanceController::class, 'stats'])->middleware('permission:meeting-attendances.stats,web');
Route::get('/', [MeetingAttendanceController::class, 'index'])->middleware('permission:meeting-attendances.index,web');
Route::get('/{meetingAttendance}', [MeetingAttendanceController::class, 'show'])->middleware('permission:meeting-attendances.show,web');
Route::post('/', [MeetingAttendanceController::class, 'store'])->middleware('permission:meeting-attendances.store,web');
Route::put('/{meetingAttendance}', [MeetingAttendanceController::class, 'update'])->middleware('permission:meeting-attendances.update,web');
Route::patch('/{meetingAttendance}', [MeetingAttendanceController::class, 'update'])->middleware('permission:meeting-attendances.update,web');
Route::delete('/{meetingAttendance}', [MeetingAttendanceController::class, 'destroy'])->middleware('permission:meeting-attendances.destroy,web');
