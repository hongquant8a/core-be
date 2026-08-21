<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentNoteController;
use Illuminate\Support\Facades\Route;

Route::post('/', [TaskAssignmentNoteController::class, 'store'])->middleware('permission:my-assigned-tasks.note|my-received-tasks.note,web');
