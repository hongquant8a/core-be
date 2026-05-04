<?php

use App\Modules\Meeting\MeetingVoteTopicController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingVoteTopicController::class, 'bulkDestroy'])->middleware('permission:meeting-vote-topics.bulkDestroy,web');
Route::patch('/reorder', [MeetingVoteTopicController::class, 'reorder'])->middleware('permission:meeting-vote-topics.update,web');
Route::patch('/{meetingVoteTopic}/open', [MeetingVoteTopicController::class, 'open'])->middleware('permission:meeting-vote-topics.update,web');
Route::patch('/{meetingVoteTopic}/close', [MeetingVoteTopicController::class, 'close'])->middleware('permission:meeting-vote-topics.update,web');
Route::get('/stats', [MeetingVoteTopicController::class, 'stats'])->middleware('permission:meeting-vote-topics.stats,web');
Route::get('/', [MeetingVoteTopicController::class, 'index'])->middleware('permission:meeting-vote-topics.index,web');
Route::get('/{meetingVoteTopic}', [MeetingVoteTopicController::class, 'show'])->middleware('permission:meeting-vote-topics.show,web');
Route::post('/', [MeetingVoteTopicController::class, 'store'])->middleware('permission:meeting-vote-topics.store,web');
Route::put('/{meetingVoteTopic}', [MeetingVoteTopicController::class, 'update'])->middleware('permission:meeting-vote-topics.update,web');
Route::patch('/{meetingVoteTopic}', [MeetingVoteTopicController::class, 'update'])->middleware('permission:meeting-vote-topics.update,web');
Route::delete('/{meetingVoteTopic}', [MeetingVoteTopicController::class, 'destroy'])->middleware('permission:meeting-vote-topics.destroy,web');
