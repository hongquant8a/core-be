<?php

use App\Modules\Core\MeetingChatConversationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MeetingChatConversationController::class, 'index'])->middleware('permission:meeting-chat-conversations.index,web');
Route::get('/{meetingChatConversation}', [MeetingChatConversationController::class, 'show'])->middleware('permission:meeting-chat-conversations.show,web');
Route::delete('/{meetingChatConversation}', [MeetingChatConversationController::class, 'destroy'])->middleware('permission:meeting-chat-conversations.destroy,web');
