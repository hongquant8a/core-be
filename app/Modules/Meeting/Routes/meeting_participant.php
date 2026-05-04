<?php

use App\Modules\Meeting\MeetingParticipantController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingParticipantController::class, 'bulkDestroy'])->middleware('permission:meeting-participants.bulkDestroy,web');
Route::get('/stats', [MeetingParticipantController::class, 'stats'])->middleware('permission:meeting-participants.stats,web');
Route::get('/', [MeetingParticipantController::class, 'index'])->middleware('permission:meeting-participants.index,web');
Route::get('/{meetingParticipant}', [MeetingParticipantController::class, 'show'])->middleware('permission:meeting-participants.show,web');
Route::post('/', [MeetingParticipantController::class, 'store'])->middleware('permission:meeting-participants.store,web');
Route::put('/{meetingParticipant}', [MeetingParticipantController::class, 'update'])->middleware('permission:meeting-participants.update,web');
Route::patch('/{meetingParticipant}', [MeetingParticipantController::class, 'update'])->middleware('permission:meeting-participants.update,web');
Route::delete('/{meetingParticipant}', [MeetingParticipantController::class, 'destroy'])->middleware('permission:meeting-participants.destroy,web');
