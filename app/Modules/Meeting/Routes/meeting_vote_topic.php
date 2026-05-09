<?php

use App\Modules\Meeting\MeetingVoteTopicController;
use Illuminate\Support\Facades\Route;

Route::delete('/bulk-delete', [MeetingVoteTopicController::class, 'bulkDestroy'])->middleware('permission:meeting-vote-topics.bulkDestroy,web');
Route::patch('/reorder', [MeetingVoteTopicController::class, 'reorder'])->middleware('permission:meeting-vote-topics.update,web');
// Phase control — chỉ chủ trì meeting cha (Policy gate, không qua Spatie permission update).
Route::patch('/{meetingVoteTopic}/open', [MeetingVoteTopicController::class, 'open'])->middleware('can:open,meetingVoteTopic');
Route::patch('/{meetingVoteTopic}/close', [MeetingVoteTopicController::class, 'close'])->middleware('can:close,meetingVoteTopic');
Route::get('/stats', [MeetingVoteTopicController::class, 'stats'])->middleware('permission:meeting-vote-topics.stats,web');
Route::get('/', [MeetingVoteTopicController::class, 'index'])->middleware('permission:meeting-vote-topics.index,web');
Route::get('/{meetingVoteTopic}', [MeetingVoteTopicController::class, 'show'])->middleware('permission:meeting-vote-topics.show,web');
Route::post('/', [MeetingVoteTopicController::class, 'store'])->middleware('permission:meeting-vote-topics.store,web');
Route::put('/{meetingVoteTopic}', [MeetingVoteTopicController::class, 'update'])->middleware('permission:meeting-vote-topics.update,web');
Route::patch('/{meetingVoteTopic}', [MeetingVoteTopicController::class, 'update'])->middleware('permission:meeting-vote-topics.update,web');
Route::delete('/{meetingVoteTopic}', [MeetingVoteTopicController::class, 'destroy'])->middleware('permission:meeting-vote-topics.destroy,web');
