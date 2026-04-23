<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentNoteController;
use Illuminate\Support\Facades\Route;

Route::post('/', [TaskAssignmentNoteController::class, 'store'])->middleware('permission:task-assignment-item-notes.store,web');
