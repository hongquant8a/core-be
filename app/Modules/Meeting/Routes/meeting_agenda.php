<?php

use App\Modules\Meeting\MeetingAgendaController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingAgendaController::class, 'bulkDestroy'])->middleware('permission:meeting-agendas.bulkDestroy,web');
Route::patch('/reorder', [MeetingAgendaController::class, 'reorder'])->middleware('permission:meeting-agendas.update,web');
Route::get('/', [MeetingAgendaController::class, 'index'])->middleware('permission:meeting-agendas.index,web');
Route::get('/{meetingAgenda}', [MeetingAgendaController::class, 'show'])->middleware('permission:meeting-agendas.show,web');
Route::post('/', [MeetingAgendaController::class, 'store'])->middleware('permission:meeting-agendas.store,web');
Route::put('/{meetingAgenda}', [MeetingAgendaController::class, 'update'])->middleware('permission:meeting-agendas.update,web');
Route::patch('/{meetingAgenda}', [MeetingAgendaController::class, 'update'])->middleware('permission:meeting-agendas.update,web');
Route::delete('/{meetingAgenda}', [MeetingAgendaController::class, 'destroy'])->middleware('permission:meeting-agendas.destroy,web');
