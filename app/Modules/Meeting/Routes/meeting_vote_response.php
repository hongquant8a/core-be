<?php

use App\Modules\Meeting\MeetingVoteResponseController;
use Illuminate\Support\Facades\Route;

// Export — auth-only, không Spatie permission. Gate qua MeetingPolicy::operate (chair/operator).
Route::get('/export', [MeetingVoteResponseController::class, 'export']);
Route::get('/export-summary', [MeetingVoteResponseController::class, 'exportSummary']);

Route::delete('/bulk-delete', [MeetingVoteResponseController::class, 'bulkDestroy'])->middleware('permission:meeting-vote-responses.bulkDestroy,web');
Route::get('/stats', [MeetingVoteResponseController::class, 'stats'])->middleware('permission:meeting-vote-responses.stats,web');
Route::get('/', [MeetingVoteResponseController::class, 'index'])->middleware('permission:meeting-vote-responses.index,web');
Route::get('/{meetingVoteResponse}', [MeetingVoteResponseController::class, 'show'])->middleware('permission:meeting-vote-responses.show,web');
Route::post('/', [MeetingVoteResponseController::class, 'store'])->middleware('permission:meeting-vote-responses.store,web');
Route::put('/{meetingVoteResponse}', [MeetingVoteResponseController::class, 'update'])->middleware('permission:meeting-vote-responses.update,web');
Route::patch('/{meetingVoteResponse}', [MeetingVoteResponseController::class, 'update'])->middleware('permission:meeting-vote-responses.update,web');
Route::delete('/{meetingVoteResponse}', [MeetingVoteResponseController::class, 'destroy'])->middleware('permission:meeting-vote-responses.destroy,web');
