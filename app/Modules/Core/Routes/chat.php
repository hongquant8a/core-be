<?php

use App\Modules\Core\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/conversations', [ChatController::class, 'directConversations']);
Route::get('/conversations/{user}/messages', [ChatController::class, 'directMessages']);
Route::post('/conversations/{user}/messages', [ChatController::class, 'sendDirectMessage']);

Route::middleware('ensure.route.org')->group(function () {
    Route::get('/meetings/{meeting}/messages', [ChatController::class, 'meetingMessages'])->middleware('can:participate,meeting');
    Route::post('/meetings/{meeting}/messages', [ChatController::class, 'sendMeetingMessage'])->middleware('can:participate,meeting');
});
