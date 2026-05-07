<?php

use App\Modules\Meeting\MeetingController;
use Illuminate\Support\Facades\Route;

Route::delete('/bulk-delete', [MeetingController::class, 'bulkDestroy'])->middleware('permission:meetings.bulkDestroy,web');
Route::patch('/bulk-status', [MeetingController::class, 'bulkUpdateStatus'])->middleware('permission:meetings.bulkUpdateStatus,web');
Route::get('/export', [MeetingController::class, 'export'])->middleware('permission:meetings.export,web');
Route::get('/stats', [MeetingController::class, 'stats'])->middleware('permission:meetings.stats,web');
Route::get('/', [MeetingController::class, 'index'])->middleware('permission:meetings.index,web');
Route::get('/{meeting}', [MeetingController::class, 'show'])->middleware(['permission:meetings.show,web', 'count.meeting.view']);
Route::post('/', [MeetingController::class, 'store'])->middleware('permission:meetings.store,web');
Route::put('/{meeting}', [MeetingController::class, 'update'])->middleware('permission:meetings.update,web');
Route::patch('/{meeting}', [MeetingController::class, 'update'])->middleware('permission:meetings.update,web');
Route::delete('/{meeting}', [MeetingController::class, 'destroy'])->middleware('permission:meetings.destroy,web');
Route::patch('/{meeting}/status', [MeetingController::class, 'changeStatus'])->middleware('permission:meetings.changeStatus,web');
// THAO TÁC NHANH (Tab 7 Điều hành) — operator khoá / mở khoá danh sách điểm danh.
Route::patch('/{meeting}/lock-attendance', [MeetingController::class, 'lockAttendance'])->middleware('permission:meetings.lockAttendance,web');
Route::patch('/{meeting}/unlock-attendance', [MeetingController::class, 'unlockAttendance'])->middleware('permission:meetings.unlockAttendance,web');
// Runtime state operator điều khiển — start cũng dùng cho resume sau pause.
Route::patch('/{meeting}/start', [MeetingController::class, 'start'])->middleware('permission:meetings.start,web');
Route::patch('/{meeting}/pause', [MeetingController::class, 'pause'])->middleware('permission:meetings.pause,web');
Route::patch('/{meeting}/end', [MeetingController::class, 'end'])->middleware('permission:meetings.end,web');
// Highlight pointers cho Tab 8 màn chiếu — operator chỉ định chương trình + đăng ký đang chiếu.
Route::patch('/{meeting}/highlight-agenda', [MeetingController::class, 'highlightAgenda'])->middleware('permission:meetings.highlightAgenda,web');
Route::patch('/{meeting}/highlight-discussion', [MeetingController::class, 'highlightDiscussion'])->middleware('permission:meetings.highlightDiscussion,web');
