<?php

use App\Modules\Meeting\MeetingConclusionController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingConclusionController::class, 'bulkDestroy'])->middleware('permission:meeting-conclusions.bulkDestroy,web');
Route::get('/stats', [MeetingConclusionController::class, 'stats'])->middleware('permission:meeting-conclusions.stats,web');
Route::get('/', [MeetingConclusionController::class, 'index'])->middleware('permission:meeting-conclusions.index,web');
Route::get('/{meetingConclusion}', [MeetingConclusionController::class, 'show'])->middleware('permission:meeting-conclusions.show,web');
Route::post('/', [MeetingConclusionController::class, 'store'])->middleware('permission:meeting-conclusions.store,web');
Route::put('/{meetingConclusion}', [MeetingConclusionController::class, 'update'])->middleware('permission:meeting-conclusions.update,web');
Route::patch('/{meetingConclusion}', [MeetingConclusionController::class, 'update'])->middleware('permission:meeting-conclusions.update,web');
Route::delete('/{meetingConclusion}', [MeetingConclusionController::class, 'destroy'])->middleware('permission:meeting-conclusions.destroy,web');
